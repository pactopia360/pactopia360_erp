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

class ChatController extends Controller
{
    /**
     * Conversación con soporte (simple).
     * Tabla opcional: mysql_clientes.soporte_mensajes
     * Campos sugeridos: id, cuenta_id, user_id, role(sender) ['cliente','soporte'], body, created_at
     */
    public function index(Request $request): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $rows = [];
        $has  = $this->hasTable('soporte_mensajes', 'mysql_clientes');

        if ($has) {
            $q = DB::connection('mysql_clientes')->table('soporte_mensajes');

            if ($cuenta && $this->hasColumn('soporte_mensajes', 'cuenta_id', 'mysql_clientes')) {
                $q->where('cuenta_id', $cuenta->id);
            } elseif ($this->hasColumn('soporte_mensajes', 'user_id', 'mysql_clientes')) {
                $q->where('user_id', $user?->id ?? -1);
            }

            $rows = $q->orderByDesc('created_at')->limit(50)->get();
        }

        return view('cliente.chat', [
            'items'      => $rows,
            'has_table'  => $has,
            // para el header (si los usas después)
            'notifCount' => view()->shared('notifCount') ?? 0,
            'chatCount'  => 0,
            'cartCount'  => 0,
        ]);
    }

    /**
     * Enviar mensaje a soporte.
     * Si no existe la tabla, responde 501 pero no truena.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        if (!$this->hasTable('soporte_mensajes', 'mysql_clientes')) {
            return response()->json(['ok' => false, 'msg' => 'Feature no disponible (falta tabla).'], 501);
        }

        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $payload = [
            'body'       => $data['body'],
            'role'       => 'cliente',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->hasColumn('soporte_mensajes', 'cuenta_id', 'mysql_clientes')) {
            $payload['cuenta_id'] = $cuenta?->id;
        }
        if ($this->hasColumn('soporte_mensajes', 'user_id', 'mysql_clientes')) {
            $payload['user_id'] = $user?->id;
        }

        $id = DB::connection('mysql_clientes')->table('soporte_mensajes')->insertGetId($payload);

        return response()->json([
            'ok'   => true,
            'id'   => $id,
            'item' => $payload,
        ]);
    }

    /* helpers */
    private function hasTable(string $table, ?string $conn = null): bool
    {
        try { return Schema::connection($conn ?: config('database.default'))->hasTable($table); }
        catch (\Throwable $e) { return false; }
    }
    private function hasColumn(string $table, string $col, ?string $conn = null): bool
    {
        try { return Schema::connection($conn ?: config('database.default'))->hasColumn($table, $col); }
        catch (\Throwable $e) { return false; }
    }
}
