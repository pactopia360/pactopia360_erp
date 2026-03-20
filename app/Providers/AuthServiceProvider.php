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
        'clientes.impersonate',
        'planes.ver','planes.crear','planes.editar','planes.eliminar',
        'pagos.ver','pagos.crear','pagos.editar','pagos.eliminar',
        'facturacion.ver','facturacion.crear','facturacion.editar','facturacion.eliminar',
        'auditoria.ver','reportes.ver','configuracion.ver',

        // módulos por prefijo (para sidebar)
        'billing.*','sat.*','soporte.*','empresas.*',
        'crm.*','cxp.*','cxc.*','conta.*','nomina.*','facturacion.*','docs.*','pv.*','bancos.*',
    ];

    /** -------- Helpers internos -------- */
    private function currentUser($passedUser)
    {
        if (is_object($passedUser)) {
            return $passedUser;
        }

        return auth('admin')->user() ?: auth()->user();
    }

    private function isSuper($user): bool
    {
        try {
            if (!$user) {
                return false;
            }

            $get = fn ($k) => method_exists($user, 'getAttribute') ? $user->getAttribute($k) : ($user->$k ?? null);

            $sa = (bool) ($get('es_superadmin') ?? $get('is_superadmin') ?? $get('superadmin') ?? false);
            if ($sa) {
                return true;
            }

            $rol = strtolower((string) ($get('rol') ?? $get('role') ?? ''));
            if ($rol === 'superadmin') {
                return true;
            }

            $list = config('app.superadmins', []);
            if (empty($list)) {
                $envList = array_filter(array_map('trim', explode(',', (string) env('APP_SUPERADMINS', ''))));
                $list = array_map('strtolower', $envList);
            }

            $email = Str::lower((string) ($get('email') ?? ''));
            foreach ((array) $list as $allowed) {
                if ($email !== '' && Str::lower(trim((string) $allowed)) === $email) {
                    return true;
                }
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
                    $path = ltrim((string) $req->path(), '/');
                    return Str::startsWith($path, 'admin/');
                }
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    private function extractUserPerms($user): array
    {
        try {
            if (!$user) {
                return [];
            }

            $raw = null;
            if (method_exists($user, 'getAttribute')) {
                $raw = $user->getAttribute('permisos');
            } else {
                $raw = $user->permisos ?? null;
            }

            if (is_array($raw)) {
                return array_values(array_unique(array_map(
                    fn ($x) => strtolower(trim((string) $x)),
                    array_filter($raw)
                )));
            }

            if (is_string($raw) && trim($raw) !== '') {
                $j = json_decode($raw, true);

                if (is_array($j)) {
                    return array_values(array_unique(array_map(
                        fn ($x) => strtolower(trim((string) $x)),
                        array_filter($j)
                    )));
                }

                $parts = preg_split('/[\n,]+/', $raw) ?: [];
                $out = [];

                foreach ($parts as $p) {
                    $p = strtolower(trim((string) $p));
                    if ($p !== '') {
                        $out[] = $p;
                    }
                }

                return array_values(array_unique($out));
            }

            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function permMatches(string $need, array $granted): bool
    {
        $need = strtolower(trim($need));
        if ($need === '') {
            return false;
        }

        if (!$granted) {
            return false;
        }

        if (in_array('*', $granted, true)) {
            return true;
        }

        if (in_array($need, $granted, true)) {
            return true;
        }

        $parts = explode('.', $need);
        while (count($parts) > 1) {
            array_pop($parts);
            $prefix = implode('.', $parts) . '.*';
            if (in_array($prefix, $granted, true)) {
                return true;
            }
        }

        foreach ($granted as $g) {
            $g = strtolower(trim((string) $g));
            if ($g === '*' || $g === '') {
                continue;
            }

            if (Str::endsWith($g, '.*')) {
                $pref = substr($g, 0, -2);
                if ($pref !== '' && Str::startsWith($need, $pref . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolvePermissionByKey($user, ?string $perm, bool $isProd, bool $strictProd): bool
    {
        $u = $this->currentUser($user);

        if (!$u) {
            return false;
        }

        if (!is_string($perm)) {
            return false;
        }

        $key = strtolower(trim($perm));
        if ($key === '') {
            return false;
        }

        try {
            $list = $this->extractUserPerms($u);
            if (!empty($list)) {
                return $this->permMatches($key, $list);
            }
        } catch (Throwable $e) {
        }

        try {
            if (method_exists($u, 'hasPerm')) {
                $res = $u->hasPerm($key);
                if ($res !== null) {
                    return (bool) $res;
                }
            }
        } catch (Throwable $e) {
        }

        try {
            $hasPermTable = Schema::hasTable('permisos');
        } catch (Throwable $e) {
            $hasPermTable = false;
        }

        if (!$hasPermTable) {
            return ($isProd && $strictProd)
                ? false
                : in_array($key, self::COMMON_ABILITIES, true);
        }

        $ttl = (int) env('PERM_CACHE_TTL', 30);

        try {
            $permId = Cache::remember("perm:id:{$key}", $ttl, function () use ($key) {
                return DB::table('permisos')->where('clave', $key)->value('id');
            });

            if (!$permId) {
                if ($isProd && $strictProd) {
                    return false;
                }

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

                if ($has) {
                    return true;
                }
            }

            if (Schema::hasTable('usuario_permiso')) {
                $has = Cache::remember("perm:user:{$u->id}:{$permId}", $ttl, function () use ($u, $permId) {
                    return DB::table('usuario_permiso')
                        ->where('usuario_id', $u->id)
                        ->where('permiso_id', $permId)
                        ->exists();
                });

                if ($has) {
                    return true;
                }
            }

            return false;
        } catch (Throwable $e) {
            return ($isProd && $strictProd) ? false : true;
        }
    }

    public function boot(): void
    {
        $this->registerPolicies();

        $isProd         = app()->environment('production');
        $strictProd     = filter_var(env('PERM_STRICT_PROD', true), FILTER_VALIDATE_BOOL);
        $auditGates     = filter_var(env('AUDIT_GATES', true), FILTER_VALIDATE_BOOL);
        $bypassDevLocal = filter_var(env('ADMIN_BYPASS_DEV', true), FILTER_VALIDATE_BOOL);

        Gate::before(function ($user = null, ?string $ability = null, ?array $arguments = []) use ($bypassDevLocal, $isProd, $strictProd) {
            $u = $this->currentUser($user);

            if ($this->isSuper($u)) {
                return true;
            }

            if (app()->environment(['local', 'development', 'testing']) && $bypassDevLocal) {
                if ($this->isAdminRequest()) {
                    return true;
                }
            }

            // ✅ Soporte directo para middleware tipo can:clientes.ver
            if (is_string($ability) && $ability !== '' && str_contains($ability, '.')) {
                return $this->resolvePermissionByKey($u, $ability, $isProd, $strictProd);
            }

            return null;
        });

        // ✅ Mantener compatibilidad para Gate::authorize('perm', 'clientes.ver')
        Gate::define('perm', function ($user = null, $perm = null) use ($isProd, $strictProd) {
            return $this->resolvePermissionByKey($user, is_string($perm) ? $perm : null, $isProd, $strictProd);
        });

        foreach (self::COMMON_ABILITIES as $ab) {
            if (!Gate::has($ab)) {
                Gate::define($ab, function ($user) use ($ab, $isProd, $strictProd) {
                    return $this->resolvePermissionByKey($user, $ab, $isProd, $strictProd);
                });
            }
        }

        Gate::after(function ($user = null, $ability = null, ?bool $result = null, array $arguments = []) use ($auditGates) {
            if (!$auditGates) {
                return;
            }

            $isAdminReq = $this->isAdminRequest();

            if ($result === false && $isAdminReq) {
                try {
                    Log::warning('[Gate deny]', [
                        'ability' => is_string($ability) ? $ability : null,
                        'user_id' => $user?->id,
                        'email'   => $user?->email,
                        'args'    => $arguments,
                        'path'    => (!app()->runningInConsole() && function_exists('request') && request()) ? request()->path() : 'cli/job',
                        'env'     => app()->environment(),
                    ]);
                } catch (Throwable $e) {
                }
            }
        });
    }
}