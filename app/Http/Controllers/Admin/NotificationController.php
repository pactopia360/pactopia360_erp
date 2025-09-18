<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class NotificationController extends Controller
{
    /**
     * Vista principal (listado extendido).
     */
    public function index(): View
    {
        [$items] = $this->fetchList($this->adminId());
        return view('admin.notificaciones.index', ['items' => $items]);
    }

    /**
     * Badge de conteo (rápido y tolerante a falta de sesión).
     * Siempre entrega JSON 200 para no romper fetchers en login/landing.
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $uid = $this->adminId(); // explícito por guard

            // ===== Tabla estándar de Laravel =====
            if (Schema::hasTable('notifications')) {
                $q = DB::table('notifications')->whereNull('read_at');

                // Filtra por usuario si el esquema lo soporta
                if ($uid && Schema::hasColumn('notifications', 'notifiable_id')) {
                    $q->where('notifiable_id', $uid);
                }
                return response()->json(['count' => (int) $q->count()], 200);
            }

            // ===== Tabla personalizada =====
            if (Schema::hasTable('notificaciones')) {
                $readCol = $this->firstExistingColumn('notificaciones', ['leida','read_at']);
                $q = DB::table('notificaciones');

                if ($readCol === 'leida') {
                    $q->where('leida', 0);
                } elseif ($readCol === 'read_at') {
                    $q->whereNull('read_at');
                }

                // Filtra por usuario si existe user_id/admin_id
                if ($uid) {
                    if (Schema::hasColumn('notificaciones', 'user_id'))  $q->where('user_id',  $uid);
                    if (Schema::hasColumn('notificaciones', 'admin_id')) $q->where('admin_id', $uid);
                }

                return response()->json(['count' => (int) $q->count()], 200);
            }

            // ===== Fallback de demo / sin tablas =====
            return response()->json(['count' => (int) session('admin_unread_notifications', 0)], 200);

        } catch (Throwable $e) {
            // Nunca dejes caer el header por un error de conteo
            return response()->json(['count' => 0], 200);
        }
    }

    /**
     * Listado corto para dropdown (20 máx).
     */
    public function list(Request $request): JsonResponse
    {
        try {
            [$items, $moreUrl] = $this->fetchList($this->adminId());
            return response()->json(['items' => $items, 'more_url' => $moreUrl], 200);
        } catch (Throwable $e) {
            return response()->json(['items' => [], 'more_url' => $this->moreUrl()], 200);
        }
    }

    /**
     * Marcar todas como leídas (solo afecta al usuario autenticado cuando hay columnas).
     */
    public function readAll(Request $request): JsonResponse
    {
        $uid = $this->adminId();
        $updated = 0;

        try {
            if (Schema::hasTable('notifications')) {
                $q = DB::table('notifications')->whereNull('read_at');
                if ($uid && Schema::hasColumn('notifications', 'notifiable_id')) {
                    $q->where('notifiable_id', $uid);
                }
                $updated = $q->update(['read_at' => now()]);

            } elseif (Schema::hasTable('notificaciones')) {
                $readCol = $this->firstExistingColumn('notificaciones', ['leida','read_at']);
                if ($readCol === 'leida') {
                    $q = DB::table('notificaciones')->where('leida', 0);
                    if ($uid) {
                        if (Schema::hasColumn('notificaciones','user_id'))  $q->where('user_id',  $uid);
                        if (Schema::hasColumn('notificaciones','admin_id')) $q->where('admin_id', $uid);
                    }
                    $updated = $q->update(['leida' => 1]);

                } elseif ($readCol === 'read_at') {
                    $q = DB::table('notificaciones')->whereNull('read_at');
                    if ($uid) {
                        if (Schema::hasColumn('notificaciones','user_id'))  $q->where('user_id',  $uid);
                        if (Schema::hasColumn('notificaciones','admin_id')) $q->where('admin_id', $uid);
                    }
                    $updated = $q->update(['read_at' => now()]);
                }
            } else {
                session(['admin_unread_notifications' => 0]);
            }
        } catch (Throwable $e) {
            // swallow -> devolvemos ok=false si quieres distinguir
            return response()->json(['ok' => false, 'updated' => 0], 200);
        }

        return response()->json(['ok' => true, 'updated' => (int) $updated], 200);
    }

    /* ==========================================================
       Helpers privados
       ========================================================== */

    /**
     * Id del admin autenticado (guard explícito).
     */
    private function adminId(): ?int
    {
        $u = auth('admin')->user();
        if ($u) return (int) $u->id;

        // Fallback: si el proyecto a veces usa web guard para admin UI
        $w = auth('web')->user();
        return $w ? (int) $w->id : null;
    }

    /**
     * Devuelve el primer nombre de columna existente en una tabla.
     */
    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    /**
     * Construye los 20 más recientes en formato compacto para dropdown.
     */
    private function fetchList(?int $uid = null): array
    {
        $items = [];

        // ===== Esquema estándar
        if (Schema::hasTable('notifications')) {
            $q = DB::table('notifications')->orderByDesc('created_at')->limit(20);
            if ($uid && Schema::hasColumn('notifications', 'notifiable_id')) {
                $q->where('notifiable_id', $uid);
            }
            foreach ($q->get() as $r) {
                $items[] = [
                    'title' => $this->cleanStr($r->type ?? 'Notificación'),
                    'text'  => $this->extractText($r->data ?? null),
                    'date'  => (string) ($r->created_at ?? ''),
                ];
            }
            return [$items, $this->moreUrl()];
        }

        // ===== Esquema personalizado
        if (Schema::hasTable('notificaciones')) {
            $title = $this->firstExistingColumn('notificaciones', ['titulo','title']);
            $body  = $this->firstExistingColumn('notificaciones', ['contenido','body']);
            $date  = $this->firstExistingColumn('notificaciones', ['fecha','created_at']);

            $q = DB::table('notificaciones')->orderByDesc($date ?? 'id')->limit(20);
            if ($uid) {
                if (Schema::hasColumn('notificaciones','user_id'))  $q->where('user_id',  $uid);
                if (Schema::hasColumn('notificaciones','admin_id')) $q->where('admin_id', $uid);
            }

            foreach ($q->get() as $r) {
                $items[] = [
                    'title' => $title ? $this->cleanStr((string) $r->{$title}) : 'Notificación',
                    'text'  => $body  ? $this->cleanStr((string) $r->{$body})  : '',
                    'date'  => $date  ? (string) $r->{$date}                   : '',
                ];
            }

            return [$items, $this->moreUrl()];
        }

        // ===== Sin tablas -> vacío
        return [[], $this->moreUrl()];
    }

    private function moreUrl(): string
    {
        return Route::has('admin.notificaciones')
            ? route('admin.notificaciones')
            : url('/admin/notificaciones');
    }

    private function cleanStr(?string $s): string
    {
        $s = (string) ($s ?? '');
        $s = trim($s);
        // recorta notificaciones muy largas para dropdown
        return Str::limit($s, 160);
    }

    /**
     * Soporta data en texto plano o JSON y extrae un resumen.
     */
    private function extractText($data): string
    {
        if (is_string($data)) {
            // ¿parece JSON?
            $t = trim($data);
            if (($t[0] ?? '') === '{' || ($t[0] ?? '') === '[') {
                try {
                    $obj = json_decode($t, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($obj)) {
                        $text = $obj['message'] ?? $obj['text'] ?? $obj['title'] ?? null;
                        if (is_string($text)) return $this->cleanStr($text);
                        return $this->cleanStr(Str::limit(json_encode($obj, JSON_UNESCAPED_UNICODE), 160));
                    }
                } catch (Throwable $e) { /* ignora */ }
            }
            return $this->cleanStr($data);
        }
        if (is_array($data)) {
            $text = $data['message'] ?? $data['text'] ?? $data['title'] ?? null;
            return $this->cleanStr(is_string($text) ? $text : Str::limit(json_encode($data, JSON_UNESCAPED_UNICODE), 160));
        }
        return '';
    }
}
