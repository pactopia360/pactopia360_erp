<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class AlertasController extends Controller
{
    /**
     * Listado de notificaciones/alertas del cliente.
     * GET /cliente/alertas?status=unread|read|all&per_page=15
     */
    public function index(Request $request): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $status   = $this->sanitizeStatus($request->query('status', 'unread')); // default: unread
        $perPage  = max(5, min(100, (int) $request->integer('per_page', 15)));
        $page     = max(1, (int) $request->integer('page', 1));

        $hasTable = $this->hasTable('notificaciones', 'mysql_clientes');

        if ($hasTable) {
            $q = DB::connection('mysql_clientes')->table('notificaciones');

            // Scope por cuenta o usuario
            if ($cuenta && $this->hasColumn('notificaciones', 'cuenta_id', 'mysql_clientes')) {
                $q->where('cuenta_id', $cuenta->id);
            } elseif ($this->hasColumn('notificaciones', 'user_id', 'mysql_clientes')) {
                $q->where('user_id', $user?->id ?? -1);
            }

            // Filtro leídas/no leídas
            $colReadBool = $this->hasColumn('notificaciones', 'leida', 'mysql_clientes');
            $colReadAt   = $this->hasColumn('notificaciones', 'read_at', 'mysql_clientes');

            if ($status !== 'all') {
                if ($colReadBool) {
                    $status === 'unread'
                        ? $q->where(function ($w) { $w->whereNull('leida')->orWhere('leida', false); })
                        : $q->where('leida', true);
                } elseif ($colReadAt) {
                    $status === 'unread'
                        ? $q->whereNull('read_at')
                        : $q->whereNotNull('read_at');
                }
            }

            // Orden por fecha si existe; si no, por id desc
            if ($this->hasColumn('notificaciones', 'created_at', 'mysql_clientes')) {
                $q->orderByDesc('created_at');
            } elseif ($this->hasColumn('notificaciones', 'fecha', 'mysql_clientes')) {
                $q->orderByDesc('fecha');
            } else {
                $q->orderByDesc('id');
            }

            // Paginación
            $total  = (clone $q)->count();
            $rows   = $q->forPage($page, $perPage)->get();
            $notifs = new LengthAwarePaginator($rows, $total, $perPage, $page, [
                'path'     => $request->url(),
                'pageName' => 'page',
            ]);

            // Contador de no leídas para badge del header
            $unreadCount = (function () use ($q, $colReadBool, $colReadAt) {
                $b = $q->cloneWithout(['orders', 'columns'])->clone();
                if ($colReadBool) {
                    return (int) $b->where(function ($w) { $w->whereNull('leida')->orWhere('leida', false); })->count();
                }
                if ($colReadAt) {
                    return (int) $b->whereNull('read_at')->count();
                }
                return 0;
            })();

            return view('cliente.alertas', [
                'status'      => $status,
                'notifs'      => $notifs,
                'items'       => $notifs->items(),
                'notifCount'  => $unreadCount,
                'chatCount'   => 0,
                'cartCount'   => 0,
            ]);
        }

        // Sin tabla: estructura segura vacía
        $notifs = new LengthAwarePaginator(collect(), 0, $perPage, $page, [
            'path'     => $request->url(),
            'pageName' => 'page',
        ]);

        return view('cliente.alertas', [
            'status'      => $status,
            'notifs'      => $notifs,
            'items'       => [],
            'notifCount'  => 0,
            'chatCount'   => 0,
            'cartCount'   => 0,
        ]);
    }

    /**
     * PATCH /cliente/alertas/{id}/read
     * Marca como leída (soporta columna leida:bool o read_at:timestamp).
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        if (!$this->hasTable('notificaciones', 'mysql_clientes')) {
            return response()->json(['ok' => false, 'msg' => 'Tabla notificaciones no existe'], 404);
        }

        $q = DB::connection('mysql_clientes')->table('notificaciones')->where('id', $id);

        // Scope por cuenta o usuario
        if ($cuenta && $this->hasColumn('notificaciones', 'cuenta_id', 'mysql_clientes')) {
            $q->where('cuenta_id', $cuenta->id);
        } elseif ($this->hasColumn('notificaciones', 'user_id', 'mysql_clientes')) {
            $q->where('user_id', $user?->id ?? -1);
        }

        $exists = (clone $q)->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'msg' => 'Notificación no encontrada'], 404);
        }

        $data = [];
        if ($this->hasColumn('notificaciones', 'leida', 'mysql_clientes')) {
            $data['leida'] = true;
        }
        if ($this->hasColumn('notificaciones', 'read_at', 'mysql_clientes')) {
            $data['read_at'] = now();
        }
        if (empty($data)) {
            // No hay columnas de lectura; no hacemos nada destructivo.
            return response()->json(['ok' => false, 'msg' => 'No existe columna leida/read_at'], 400);
        }

        $q->update($data);

        return response()->json(['ok' => true, 'id' => (int) $id]);
    }

    /**
     * DELETE /cliente/alertas/{id}
     * Elimina la notificación (soft o hard, según esquema; aquí hard delete básico).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        if (!$this->hasTable('notificaciones', 'mysql_clientes')) {
            return response()->json(['ok' => false, 'msg' => 'Tabla notificaciones no existe'], 404);
        }

        $q = DB::connection('mysql_clientes')->table('notificaciones')->where('id', $id);

        // Scope por cuenta o usuario
        if ($cuenta && $this->hasColumn('notificaciones', 'cuenta_id', 'mysql_clientes')) {
            $q->where('cuenta_id', $cuenta->id);
        } elseif ($this->hasColumn('notificaciones', 'user_id', 'mysql_clientes')) {
            $q->where('user_id', $user?->id ?? -1);
        }

        $deleted = (int) $q->delete();

        if ($deleted < 1) {
            return response()->json(['ok' => false, 'msg' => 'No se eliminó (no encontrada o sin permisos)'], 404);
        }

        return response()->json(['ok' => true, 'deleted' => $deleted, 'id' => (int) $id]);
    }

    /* ============================
     * Helpers internos
     * ============================ */

    private function sanitizeStatus(?string $raw): string
    {
        $raw = strtolower(trim((string) $raw));
        return in_array($raw, ['unread', 'read', 'all'], true) ? $raw : 'unread';
    }

    private function hasTable(string $table, ?string $conn = null): bool
    {
        try {
            return Schema::connection($conn ?: config('database.default'))->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasColumn(string $table, string $col, ?string $conn = null): bool
    {
        try {
            return Schema::connection($conn ?: config('database.default'))->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
