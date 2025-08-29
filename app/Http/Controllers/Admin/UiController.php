<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class UiController extends Controller
{
    /**
     * Beacon de logs del frontend (UI/Debug).
     * Acepta JSON (sendBeacon/fetch) o querystring (fallback GET).
     * Se registra en el canal "home" -> storage/logs/home.log
     */
    public function log(Request $request): JsonResponse
    {
        // Acepta tanto JSON crudo como form/query
        $payload = $request->all();
        if (empty($payload)) {
            $raw = $request->getContent();
            if (is_string($raw) && $raw !== '') {
                $json = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    $payload = $json;
                }
            }
        }

        Log::channel('home')->info('[UI BEACON]', [
            'actor'   => optional(auth('admin')->user())->only(['id', 'email', 'name']), // puede ser null
            'ip'      => $request->ip(),
            'agent'   => substr((string) $request->userAgent(), 0, 200),
            'payload' => $payload,
            'at'      => now()->toIso8601String(),
        ]);

        return response()->json(['ok' => true], 200);
    }

    /**
     * Diagnóstico rápido para la UI. (Requiere auth por rutas)
     */
    public function diag(Request $request): JsonResponse
    {
        $routes = collect([
            'home'         => 'admin.home',
            'dashboard'    => 'admin.dashboard',
            'usuarios'     => 'admin.usuarios.index',
            'perfiles'     => 'admin.perfiles.index',
            'clientes'     => 'admin.clientes.index',
            'planes'       => 'admin.planes.index',
            'pagos'        => 'admin.pagos.index',
            'facturacion'  => 'admin.facturacion.index',
            'auditoria'    => 'admin.auditoria.index',
            'reportes'     => 'admin.reportes.index',
        ])->map(fn ($name) => Route::has($name) ? route($name) : null);

        return response()->json([
            'ok'        => true,
            'time'      => now()->toIso8601String(),
            'env'       => app()->environment(),
            'php'       => PHP_VERSION,
            'laravel'   => app()->version(),
            'user'      => optional(auth('admin')->user())->only(['id', 'email', 'name']),
            'routes'    => $routes,
            'server_ip' => request()->server('SERVER_ADDR'),
        ], 200);
    }

    /**
     * Healthcheck liviano. (Público)
     */
    public function heartbeat(): JsonResponse
    {
        return response()
            ->json(['ok' => true, 'ts' => microtime(true)], 200)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Backend de NovaBot (demo). (Requiere auth por rutas)
     * También registramos en canal "home".
     */
    public function botAsk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'       => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        $q       = trim($data['q']);
        $history = $data['history'] ?? [];

        Log::channel('home')->info('[NovaBot]', [
            'actor'   => optional(auth('admin')->user())->only(['id', 'email']),
            'q'       => $q,
            'history' => is_array($history) ? count($history) : 0,
            'ip'      => $request->ip(),
        ]);

        if (Str::startsWith($q, '/')) {
            return response()->json(['answer' => $this->handleSlashCommand($q)], 200);
        }

        return response()->json(['answer' => $this->answerFor($q)], 200);
    }

    // ---------- Helpers ----------

    protected function urlIf(string $routeName, string $fallback = '(no disponible)'): string
    {
        return Route::has($routeName) ? route($routeName) : $fallback;
    }

    protected function answerFor(string $q): string
    {
        $ql = Str::of($q)->lower();

        if ($ql->contains('reporte')) {
            return "Puedes abrir Reportes aquí: " . $this->urlIf('admin.reportes.index');
        }
        if ($ql->contains('pago')) {
            return "Módulo de Pagos: " . $this->urlIf('admin.pagos.index') . "\nNuevo pago: " . (Route::has('admin.pagos.create') ? route('admin.pagos.create') : '(no disponible)');
        }
        if ($ql->contains('cliente')) {
            return "Clientes: " . $this->urlIf('admin.clientes.index') . "\nCrear cliente: " . (Route::has('admin.clientes.create') ? route('admin.clientes.create') : '(no disponible)');
        }
        if ($ql->contains('plan')) {
            return "Planes: " . $this->urlIf('admin.planes.index');
        }
        if ($ql->contains('factur')) {
            return "Facturación: " . $this->urlIf('admin.facturacion.index');
        }
        if ($ql->contains('auditor')) {
            return "Auditoría: " . $this->urlIf('admin.auditoria.index');
        }
        if ($ql->contains('usuario')) {
            return "Usuarios admin: " . $this->urlIf('admin.usuarios.index');
        }
        if ($ql->contains('perfil') || $ql->contains('permiso')) {
            return "Perfiles & Permisos: " . $this->urlIf('admin.perfiles.index');
        }
        if ($ql->contains('home') || $ql->contains('inicio') || $ql->contains('dashboard')) {
            return "Ir al Home: " . $this->urlIf('admin.home');
        }

        return "Recibí: “{$q}”.\nEstoy en modo demostración. Prueba: reportes, pagos, clientes, planes, facturación, auditoría, usuarios, perfiles.\nTambién soportamos /help y /ping.";
    }

    protected function handleSlashCommand(string $cmd): string
    {
        $name = Str::of($cmd)->after('/')->before(' ')->lower()->value();

        return match ($name) {
            'help' => "Comandos disponibles:\n• /help — esta ayuda\n• /clear — limpia historial (cliente)\n• /ping — prueba de latido\n• /theme — alterna tema (cliente)\n• /log on|off — logs verbosos (cliente)",
            'ping' => 'PONG ✔ (backend)',
            default => "No reconozco “/{$name}”. Usa /help.",
        };
    }
}
