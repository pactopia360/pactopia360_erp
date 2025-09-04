<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $get = fn($k) => method_exists($user,'getAttribute') ? $user->getAttribute($k) : ($user->$k ?? null);

            // Flag directo en modelo
            $sa  = (bool)($get('es_superadmin') ?? $get('is_superadmin') ?? $get('superadmin') ?? false);
            if ($sa) return true;

            // Rol por texto
            $rol = strtolower((string)($get('rol') ?? $get('role') ?? ''));
            if ($rol === 'superadmin') return true;

            // Lista desde .env (config('app.superadmins'))
            $list = config('app.superadmins', []);
            $email = Str::lower((string) ($get('email') ?? ''));
            foreach ((array) $list as $allowed) {
                if ($email !== '' && Str::lower(trim($allowed)) === $email) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * BEFORE global:
         *  - local/dev/testing → permite TODO en /admin/*
         *  - superadmin por flag/rol o por lista APP_SUPERADMINS → TODO permitido
         */
        Gate::before(function ($user = null, ?string $ability = null, ?array $arguments = []) {
            $u = $this->currentUser($user);

            // 🔧 Forzar reconocimiento de superadmin
            if ($u && ($u->es_superadmin === true || strtolower((string) $u->rol) === 'superadmin')) {
                return true;
            }

            // Entornos dev siempre todo permitido
            if (app()->environment(['local','development','testing'])) {
                if (request()->is('admin/*')) return true;
            }

            if ($this->isSuper($u)) return true;

            return null;
        });


        /**
         * Gate genérico "perm" (punto único de verdad).
         */
        Gate::define('perm', function ($user, string $perm) {
            $u   = $this->currentUser($user);
            $key = strtolower(trim($perm));

            // Método en modelo tiene prioridad
            try {
                if ($u && method_exists($u, 'hasPerm')) {
                    $res = $u->hasPerm($key);
                    if ($res !== null) return (bool)$res;
                }
            } catch (\Throwable $e) {}

            // Sin infraestructura → no bloquees navegación base
            try {
                if (!Schema::hasTable('permisos')) return true;
            } catch (\Throwable $e) { return true; }

            try {
                $permId = DB::table('permisos')->where('clave', $key)->value('id');
                if (!$permId) {
                    // Claves comunes permitidas por defecto
                    $allow = [
                        'usuarios_admin.ver','usuarios_admin.crear','usuarios_admin.editar','usuarios_admin.eliminar',
                        'usuarios_admin.impersonar',
                        'perfiles.ver','perfiles.crear','perfiles.editar','perfiles.eliminar',
                        'clientes.ver','clientes.crear','clientes.editar','clientes.eliminar',
                        'planes.ver','planes.crear','planes.editar','planes.eliminar',
                        'pagos.ver','pagos.crear','pagos.editar','pagos.eliminar',
                        'facturacion.ver','facturacion.crear','facturacion.editar','facturacion.eliminar',
                        'auditoria.ver','reportes.ver',
                        'configuracion.ver',
                    ];

                    return in_array($key, $allow, true);
                }

                // Por perfil (si existe columna)
                $perfilId = null;
                if (Schema::hasColumn('usuario_administrativos', 'perfil_id')) {
                    $perfilId = (int) DB::table('usuario_administrativos')
                        ->where('id', $u->id)->value('perfil_id');
                }
                if ($perfilId) {
                    return DB::table('perfil_permiso')
                        ->where('perfil_id', $perfilId)
                        ->where('permiso_id', $permId)
                        ->exists();
                }

                // Pivot usuario_permiso (fallback)
                if (Schema::hasTable('usuario_permiso')) {
                    return DB::table('usuario_permiso')
                        ->where('usuario_id', $u->id)
                        ->where('permiso_id', $permId)
                        ->exists();
                }

                // Hay infraestructura pero sin match → deniega
                return false;
            } catch (\Throwable $e) {
                // En error, no bloquees
                return true;
            }
        });

        /**
         * ALIAS automáticos a "perm".
         */
        $abilities = [
            'usuarios_admin.ver','usuarios_admin.crear','usuarios_admin.editar','usuarios_admin.eliminar',
            'usuarios_admin.impersonar',
            'perfiles.ver','perfiles.crear','perfiles.editar','perfiles.eliminar',
            'clientes.ver','clientes.crear','clientes.editar','clientes.eliminar',
            'planes.ver','planes.crear','planes.editar','planes.eliminar',
            'pagos.ver','pagos.crear','pagos.editar','pagos.eliminar',
            'facturacion.ver','facturacion.crear','facturacion.editar','facturacion.eliminar',
            'auditoria.ver','reportes.ver','configuracion.ver',
        ];


        foreach ($abilities as $ab) {
            if (!Gate::has($ab)) {
                Gate::define($ab, function ($user) use ($ab) {
                    return Gate::forUser($user)->allows('perm', $ab);
                });
            }
        }

        /**
         * AFTER (debug opcional).
         */
        Gate::after(function ($user = null, string $ability, bool $result, array $arguments = []) {
            if ($result === false && request()->is('admin/*')) {
                try {
                    Log::warning('[Gate deny]', [
                        'ability' => $ability,
                        'user_id' => $user?->id,
                        'email'   => $user?->email,
                        'args'    => $arguments,
                        'path'    => request()->path(),
                        'env'     => app()->environment(),
                    ]);
                } catch (\Throwable $e) {}
            }
        });
    }
}
