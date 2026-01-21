<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de Policies (respeta tus clases).
     */
    protected $policies = [
        \App\Models\Admin\Auth\UsuarioAdministrativo::class => \App\Policies\UsuarioAdministrativoPolicy::class,
        \App\Models\Cliente::class => \App\Policies\ClientePolicy::class,
        \App\Models\Plan::class    => \App\Policies\PlanPolicy::class,
        \App\Models\Pago::class    => \App\Policies\PagoPolicy::class,
        \App\Models\Cfdi::class    => \App\Policies\CfdiPolicy::class,
    ];

    /** Abilities “comunes” (alias a perm) */
    private const COMMON_ABILITIES = [
        'usuarios_admin.ver','usuarios_admin.crear','usuarios_admin.editar','usuarios_admin.eliminar',
        'usuarios_admin.impersonar',
        'perfiles.ver','perfiles.crear','perfiles.editar','perfiles.eliminar',
        'clientes.ver','clientes.crear','clientes.editar','clientes.eliminar',
        'planes.ver','planes.crear','planes.editar','planes.eliminar',
        'pagos.ver','pagos.crear','pagos.editar','pagos.eliminar',
        'facturacion.ver','facturacion.crear','facturacion.editar','facturacion.eliminar',
        'auditoria.ver','reportes.ver','configuracion.ver',

        // ✅ módulos por prefijo (para sidebar)
        'billing.*','sat.*','soporte.*','empresas.*',
        'crm.*','cxp.*','cxc.*','conta.*','nomina.*','facturacion.*','docs.*','pv.*','bancos.*',
    ];

    /** -------- Helpers internos -------- */
    private function currentUser($passedUser)
    {
        if (is_object($passedUser)) return $passedUser;
        return auth('admin')->user() ?: auth()->user();
    }

    private function isSuper($user): bool
    {
        try {
            if (!$user) return false;

            $get = fn($k) => method_exists($user, 'getAttribute') ? $user->getAttribute($k) : ($user->$k ?? null);

            // Flag directo en modelo
            $sa  = (bool)($get('es_superadmin') ?? $get('is_superadmin') ?? $get('superadmin') ?? false);
            if ($sa) return true;

            // Rol por texto
            $rol = strtolower((string)($get('rol') ?? $get('role') ?? ''));
            if ($rol === 'superadmin') return true;

            // Lista desde config('app.superadmins') o APP_SUPERADMINS coma-separado
            $list = config('app.superadmins', []);
            if (empty($list)) {
                $envList = array_filter(array_map('trim', explode(',', (string) env('APP_SUPERADMINS', ''))));
                $list = array_map('strtolower', $envList);
            }
            $email = Str::lower((string) ($get('email') ?? ''));
            foreach ((array)$list as $allowed) {
                if ($email !== '' && Str::lower(trim($allowed)) === $email) return true;
            }

            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** request() seguro en CLI/jobs */
    private function isAdminRequest(): bool
    {
        try {
            if (!app()->runningInConsole() && function_exists('request')) {
                $req = request();
                if ($req) {
                    $path = ltrim((string)$req->path(), '/');
                    return Str::startsWith($path, 'admin/');
                }
            }
        } catch (Throwable $e) {}
        return false;
    }

    /**
     * ✅ Extrae permisos desde usuarios_admin.permisos (JSON)
     * Soporta:
     * - array
     * - string JSON
     * - null
     *
     * @return array<int,string>
     */
    private function extractUserPerms($user): array
    {
        try {
            if (!$user) return [];

            $raw = null;
            if (method_exists($user, 'getAttribute')) {
                $raw = $user->getAttribute('permisos');
            } else {
                $raw = $user->permisos ?? null;
            }

            if (is_array($raw)) {
                return array_values(array_unique(array_map(fn($x)=>strtolower(trim((string)$x)), array_filter($raw))));
            }

            if (is_string($raw) && trim($raw) !== '') {
                $j = json_decode($raw, true);
                if (is_array($j)) {
                    return array_values(array_unique(array_map(fn($x)=>strtolower(trim((string)$x)), array_filter($j))));
                }
                // si guardaste "a,b,c" por error, también lo toleramos
                $parts = preg_split('/[\n,]+/', $raw) ?: [];
                $out = [];
                foreach ($parts as $p) {
                    $p = strtolower(trim((string)$p));
                    if ($p !== '') $out[] = $p;
                }
                return array_values(array_unique($out));
            }

            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * ✅ Match de permisos con wildcards:
     * - '*' => todo
     * - 'billing.*' => prefijo
     * - exact match
     */
    private function permMatches(string $need, array $granted): bool
    {
        $need = strtolower(trim($need));
        if ($need === '') return false;
        if (!$granted) return false;

        if (in_array('*', $granted, true)) return true;
        if (in_array($need, $granted, true)) return true;

        // si necesito "billing.ver", acepta "billing.*"
        $parts = explode('.', $need);
        while (count($parts) > 1) {
            array_pop($parts);
            $prefix = implode('.', $parts) . '.*';
            if (in_array($prefix, $granted, true)) return true;
        }

        // si me dieron un wildcard tipo "admin.*" (o cualquiera), también permite por prefijo
        foreach ($granted as $g) {
            $g = strtolower(trim((string)$g));
            if ($g === '*' || $g === '') continue;
            if (Str::endsWith($g, '.*')) {
                $pref = substr($g, 0, -2);
                if ($pref !== '' && Str::startsWith($need, $pref . '.')) return true;
            }
        }

        return false;
    }

    public function boot(): void
    {
        $this->registerPolicies();

        $isProd         = app()->environment('production');
        $strictProd     = filter_var(env('PERM_STRICT_PROD', true), FILTER_VALIDATE_BOOL);
        $auditGates     = filter_var(env('AUDIT_GATES', true), FILTER_VALIDATE_BOOL);
        $bypassDevLocal = filter_var(env('ADMIN_BYPASS_DEV', true), FILTER_VALIDATE_BOOL);

        /**
         * BEFORE global:
         * - Si es superadmin → acceso total.
         * - En local/testing, puedes permitir admin en rutas /admin/* con ADMIN_BYPASS_DEV=true.
         *   (EN PRODUCCIÓN, NUNCA HAY BYPASS.)
         */
        Gate::before(function ($user = null, ?string $ability = null, ?array $arguments = []) use ($bypassDevLocal) {
            $u = $this->currentUser($user);

            // Superadmin siempre pasa
            if ($this->isSuper($u)) return true;

            // Bypass opcional SOLO en local/testing (nunca en producción)
            if (app()->environment(['local', 'development', 'testing']) && $bypassDevLocal) {
                if ($this->isAdminRequest()) return true;
            }

            return null;
        });

        /**
         * Gate genérico "perm" (punto único de verdad).
         * ✅ PRIORIDAD #1: usuarios_admin.permisos (JSON) con wildcards.
         * ✅ PRIORIDAD #2: método hasPerm() en el modelo (si existe).
         * ✅ PRIORIDAD #3: infra legacy de tablas (si existe).
         */
        Gate::define('perm', function ($user, string $perm) use ($isProd, $strictProd) {
            $u = $this->currentUser($user);
            if (!$u) return false;

            $key = strtolower(trim($perm));
            if ($key === '') return false;

            // ✅ 1) JSON en usuarios_admin.permisos
            try {
                $list = $this->extractUserPerms($u);
                if (!empty($list)) {
                    return $this->permMatches($key, $list);
                }
            } catch (Throwable $e) {
                // sigue
            }

            // ✅ 2) Método en modelo tiene prioridad (si lo usas en el futuro)
            try {
                if (method_exists($u, 'hasPerm')) {
                    $res = $u->hasPerm($key);
                    if ($res !== null) return (bool)$res;
                }
            } catch (Throwable $e) {
                // continúa al flujo por tablas
            }

            // ✅ 3) Infra legacy por tablas (si existe)
            try {
                $hasPermTable = Schema::hasTable('permisos');
            } catch (Throwable $e) {
                $hasPermTable = false;
            }

            if (!$hasPermTable) {
                // Producción estricta -> deniega; en otros entornos -> permite solo abilities comunes
                return ($isProd && $strictProd) ? false : in_array($key, self::COMMON_ABILITIES, true);
            }

            $ttl = (int) env('PERM_CACHE_TTL', 30);

            try {
                $permId = Cache::remember("perm:id:{$key}", $ttl, function () use ($key) {
                    return DB::table('permisos')->where('clave', $key)->value('id');
                });

                if (!$permId) {
                    if ($isProd && $strictProd) return false;
                    return in_array($key, self::COMMON_ABILITIES, true);
                }

                $perfilId = Cache::remember("user:perfil:{$u->id}", $ttl, function () use ($u) {
                    if (Schema::hasColumn('usuario_administrativos', 'perfil_id')) {
                        return (int) DB::table('usuario_administrativos')->where('id', $u->id)->value('perfil_id');
                    }
                    return 0;
                });

                if ($perfilId) {
                    $has = Cache::remember("perm:perfil:{$perfilId}:{$permId}", $ttl, function () use ($perfilId, $permId) {
                        return DB::table('perfil_permiso')
                            ->where('perfil_id', $perfilId)
                            ->where('permiso_id', $permId)
                            ->exists();
                    });
                    if ($has) return true;
                }

                if (Schema::hasTable('usuario_permiso')) {
                    $has = Cache::remember("perm:user:{$u->id}:{$permId}", $ttl, function () use ($u, $permId) {
                        return DB::table('usuario_permiso')
                            ->where('usuario_id', $u->id)
                            ->where('permiso_id', $permId)
                            ->exists();
                    });
                    if ($has) return true;
                }

                return false;
            } catch (Throwable $e) {
                return ($isProd && $strictProd) ? false : true;
            }
        });

        /**
         * ALIAS automáticos a "perm".
         */
        foreach (self::COMMON_ABILITIES as $ab) {
            if (!Gate::has($ab)) {
                Gate::define($ab, function ($user) use ($ab) {
                    return Gate::forUser($user)->allows('perm', $ab);
                });
            }
        }

        /**
         * AFTER (log de denegación en /admin/* si AUDIT_GATES=true).
         */
        Gate::after(function ($user, string $ability, bool $result, array $arguments = []) use ($auditGates) {
            if (!$auditGates) return;

            $isAdminReq = $this->isAdminRequest();

            if ($result === false && $isAdminReq) {
                try {
                    Log::warning('[Gate deny]', [
                        'ability' => $ability,
                        'user_id' => $user?->id,
                        'email'   => $user?->email,
                        'args'    => $arguments,
                        'path'    => (!app()->runningInConsole() && function_exists('request') && request()) ? request()->path() : 'cli/job',
                        'env'     => app()->environment(),
                    ]);
                } catch (Throwable $e) {}
            }
        });
    }
}
