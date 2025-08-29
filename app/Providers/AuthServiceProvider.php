<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthServiceProvider extends ServiceProvider
{
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

            $sa  = (bool)($get('es_superadmin') ?? $get('is_superadmin') ?? $get('superadmin') ?? false);
            if ($sa) return true;

            $rol = strtolower((string)($get('rol') ?? $get('role') ?? ''));
            return $rol === 'superadmin';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * BEFORE global:
         *  - Env local/dev/testing → deja pasar todo (flujo de desarrollo)
         *  - Dueño (tu correo) → todo permitido
         *  - Superadmin flag/rol → todo permitido
         * Aplica a cualquier ability (incluido 'perm').
         */
        Gate::before(function ($user = null, ?string $ability = null, ?array $arguments = []) {
            $u = $this->currentUser($user);

            // 1) Desbloqueo en entornos de desarrollo
            if (app()->environment(['local','development','testing'])) {
                // Solo por higiene, si no estás en /admin no fuerces permitir:
                if (request()->is('admin/*')) return true;
            }

            // 2) Dueño (por correo)
            try {
                if ($u && strcasecmp((string)$u->email, 'marco.padilla@pactopia.com') === 0) {
                    return true;
                }
            } catch (\Throwable $e) {}

            // 3) Superadmin
            if ($this->isSuper($u)) return true;

            return null; // deja que lo resuelvan las definiciones siguientes
        });

        /**
         * Gate genérico "perm" (punto único de verdad).
         */
        Gate::define('perm', function ($user, string $perm) {
            $u   = $this->currentUser($user);
            $key = strtolower(trim($perm));

            // hasPerm() de tu modelo tiene prioridad si existe
            try {
                if ($u && method_exists($u, 'hasPerm')) {
                    $res = $u->hasPerm($key);
                    if ($res !== null) return (bool)$res;
                }
            } catch (\Throwable $e) {}

            // Si no hay infraestructura de permisos, no bloquees navegación base
            try {
                if (!Schema::hasTable('permisos')) return true;
            } catch (\Throwable $e) { return true; }

            try {
                $permId = DB::table('permisos')->where('clave', $key)->value('id');
                if (!$permId) {
                    // Claves comunes permitidas por defecto (puedes ajustar)
                    $allow = [
                        'usuarios_admin.ver','usuarios_admin.crear','usuarios_admin.editar','usuarios_admin.eliminar',
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

                // Infra presente pero sin match -> deniega
                return false;
            } catch (\Throwable $e) {
                // Si algo truena, no bloquees
                return true;
            }
        });

        /**
         * ALIAS automáticos:
         * Si alguna ruta usa `can:usuarios_admin.ver` (u otra),
         * la delegamos al gate `perm` con la misma clave.
         */
        $abilities = [
            'usuarios_admin.ver','usuarios_admin.crear','usuarios_admin.editar','usuarios_admin.eliminar',
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
         * AFTER (opcional, útil para debug):
         * Loguea la decisión de Gate (solo cuando deniega y estés en /admin).
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
