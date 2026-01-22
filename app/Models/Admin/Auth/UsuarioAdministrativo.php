<?php

namespace App\Models\Admin\Auth;

use App\Models\Admin\BaseAdminModel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\Auth\WithoutRememberToken;

/**
 * Usuario administrativo (Admin)
 *
 * SOT en LOCAL/PROD con tabla configurable:
 * - env P360_ADMIN_USERS_TABLE puede forzar: usuario_administrativos | usuarios_admin
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

    /** Default PROD */
    protected $table = 'usuarios_admin';

    /** PK default PROD */
    public $incrementing  = true;
    protected $keyType    = 'int';
    protected $primaryKey = 'id';

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

    protected $hidden = ['password'];

    protected $casts = [
        'activo'        => 'integer',
        'es_superadmin' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $conn = $this->getConnectionName();

        try {
            $schema = Schema::connection($conn);

            // 1) Si env fuerza tabla y existe, úsala
            $forced = trim((string) env('P360_ADMIN_USERS_TABLE', ''));
            if ($forced !== '' && $schema->hasTable($forced)) {
                $this->applyTableSchema($forced);
                return;
            }

            // 2) Auto: preferir LOCAL si existe
            if ($schema->hasTable('usuario_administrativos')) {
                $this->applyTableSchema('usuario_administrativos');
                return;
            }

            // 3) Fallback PROD
            if ($schema->hasTable('usuarios_admin')) {
                $this->applyTableSchema('usuarios_admin');
                return;
            }

            // 4) Si no existe ninguna, deja default
        } catch (\Throwable $e) {
            // no romper
        }
    }

    private function applyTableSchema(string $table): void
    {
        $this->setTable($table);

        // Si es tabla local UUID
        if ($table === 'usuario_administrativos') {
            $this->incrementing = false;
            $this->keyType      = 'string';
            $this->primaryKey   = 'id';
            return;
        }

        // PROD / usuarios_admin
        $this->incrementing = true;
        $this->keyType      = 'int';
        $this->primaryKey   = 'id';
    }

    /** Fallback si no existe mysql_admin */
    public function getConnectionName()
    {
        return config('database.connections.mysql_admin')
            ? 'mysql_admin'
            : (config('database.default') ?? 'mysql');
    }

    public function getGuardName(): string
    {
        return 'admin';
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = is_string($value) ? mb_strtolower(trim($value)) : $value;
    }

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

    public function isActive(): bool
    {
        $schema = Schema::connection($this->getConnectionName());
        if (!$schema->hasColumn($this->getTable(), 'activo')) return true;
        return (int) $this->activo === 1;
    }

    public function markLastLogin(?string $ip = null): void
    {
        // opcional
    }

    public function isSuperAdmin(): bool
    {
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
