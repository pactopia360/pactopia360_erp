<?php

namespace App\Models\Admin\Auth;

use App\Models\Admin\BaseAdminModel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\Auth\WithoutRememberToken;

/**
 * Usuario administrativo (Admin)
 *
 * Compatibilidad:
 * - LOCAL:  usuario_administrativos (UUID string, no AI)
 * - PROD:   usuarios_admin (bigint AI)
 *
 * Conexión: mysql_admin
 * SIN remember_token (se anula con WithoutRememberToken)
 */
class UsuarioAdministrativo extends BaseAdminModel implements AuthenticatableContract
{
    use HasFactory, Notifiable;

    // Prioridad a WithoutRememberToken
    use Authenticatable, WithoutRememberToken {
        WithoutRememberToken::getRememberToken insteadof Authenticatable;
        WithoutRememberToken::setRememberToken insteadof Authenticatable;
        WithoutRememberToken::getRememberTokenName insteadof Authenticatable;

        Authenticatable::getRememberToken as protected __authGetRememberToken;
        Authenticatable::setRememberToken as protected __authSetRememberToken;
        Authenticatable::getRememberTokenName as protected __authGetRememberTokenName;
    }

    /** Conexión ADMIN */
    protected $connection = 'mysql_admin';

    /**
     * Tabla default (PROD). En constructor se ajusta si existe la tabla LOCAL.
     */
    protected $table = 'usuarios_admin';

    /** PK default (PROD) */
    public $incrementing  = true;
    protected $keyType    = 'int';
    protected $primaryKey = 'id';

    /** Atributos asignables (conjunto amplio para ambos esquemas) */
    protected $fillable = [
        'id',
        'nombre',
        'email',
        'password',
        'rol',
        'perfil_id',
        'activo',
        'es_superadmin',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    /** Ocultos */
    protected $hidden = [
        'password',
    ];

    /** Casts */
    protected $casts = [
        'activo'        => 'integer',
        'es_superadmin' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Elegir tabla según exista en el esquema real de la conexión admin
        $conn = $this->getConnectionName();

        try {
            $schema = Schema::connection($conn);

            // Preferir tabla LOCAL si existe
            if ($schema->hasTable('usuario_administrativos')) {
                $this->setTable('usuario_administrativos');

                // En tu local es UUID (string)
                $this->incrementing = false;
                $this->keyType = 'string';
                $this->primaryKey = 'id';
            } else {
                // Fallback PROD
                $this->setTable('usuarios_admin');

                // En prod es bigint AI
                $this->incrementing = true;
                $this->keyType = 'int';
                $this->primaryKey = 'id';
            }
        } catch (\Throwable $e) {
            // Si falla Schema por cualquier razón, no rompas el modelo: se queda en default PROD
        }
    }

    /** Fallback si no existe conexión mysql_admin */
    public function getConnectionName()
    {
        return config('database.connections.mysql_admin')
            ? 'mysql_admin'
            : (config('database.default') ?? 'mysql');
    }

    /** Guard */
    public function getGuardName(): string
    {
        return 'admin';
    }

    /** Normaliza email */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = is_string($value) ? mb_strtolower(trim($value)) : $value;
    }

    /* =========================
     | Scopes
     * ========================= */
    public function scopeActive($q)
    {
        $schema = Schema::connection($this->getConnectionName());
        return $schema->hasColumn($this->getTable(), 'activo')
            ? $q->where('activo', 1)
            : $q;
    }

    public function scopeEmail($q, string $email)
    {
        return $q->where('email', mb_strtolower(trim($email)));
    }

    /* =========================
     | Helpers
     * ========================= */
    public function isActive(): bool
    {
        $schema = Schema::connection($this->getConnectionName());
        if (!$schema->hasColumn($this->getTable(), 'activo')) return true;
        return (int) $this->activo === 1;
    }

    public function markLastLogin(?string $ip = null): void
    {
        // En ambos esquemas actuales no dependemos de columnas last_login_*.
    }

    public function isSuperAdmin(): bool
    {
        // Flag primero (local tiene es_superadmin)
        if ((int)($this->es_superadmin ?? 0) === 1) return true;

        $rol = strtolower((string)($this->rol ?? ''));
        if ($rol === 'superadmin') return true;

        $envList = config('app.superadmins', []);
        if (empty($envList)) {
            $envList = array_filter(array_map('trim', explode(',', (string) env('APP_SUPERADMINS', ''))));
        }
        if (empty($envList)) return false;

        $email = mb_strtolower((string) $this->email);
        return in_array($email, array_map('mb_strtolower', (array) $envList), true);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        $mine  = mb_strtolower((string) ($this->rol ?? ''));
        foreach ($roles as $r) {
            if ($mine === mb_strtolower(trim((string) $r))) return true;
        }
        return false;
    }

    public function hasPerm(string $clave): bool
    {
        if ($this->isSuperAdmin()) return true;

        $key = mb_strtolower(trim($clave));
        if ($key === '') return false;

        $conn   = $this->getConnectionName();
        $schema = Schema::connection($conn);

        if (
            !$schema->hasTable('usuario_perfil') ||
            !$schema->hasTable('perfil_permiso') ||
            !$schema->hasTable('permisos')
        ) {
            return false;
        }

        $hasClave  = $schema->hasColumn('permisos', 'clave');
        $hasNombre = $schema->hasColumn('permisos', 'nombre');
        if (!$hasClave && !$hasNombre) return false;

        $q = DB::connection($conn)
            ->table('usuario_perfil')
            ->join('perfil_permiso', 'usuario_perfil.perfil_id', '=', 'perfil_permiso.perfil_id')
            ->join('permisos', 'perfil_permiso.permiso_id', '=', 'permisos.id')
            ->where('usuario_perfil.usuario_id', $this->getKey());

        if ($hasClave && $hasNombre) {
            $q->where(function ($w) use ($key) {
                $w->whereRaw('LOWER(permisos.clave) = ?', [$key])
                  ->orWhereRaw('LOWER(permisos.nombre) = ?', [$key]);
            });
        } elseif ($hasClave) {
            $q->whereRaw('LOWER(permisos.clave) = ?', [$key]);
        } else {
            $q->whereRaw('LOWER(permisos.nombre) = ?', [$key]);
        }

        return $q->exists();
    }

    public function canDo(string $perm): bool
    {
        return $this->hasPerm($perm);
    }

    public function canAccessEverything(): bool
    {
        return $this->isSuperAdmin();
    }
}
