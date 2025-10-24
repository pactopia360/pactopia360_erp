<?php

namespace App\Models\Admin\Auth;

use App\Models\Admin\BaseAdminModel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable; // Métodos core de Auth
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\Auth\WithoutRememberToken;


/**
 * Modelo de usuario administrativo (backoffice).
 * - Conexión: mysql_admin (con fallback a default si no existe)
 * - SIN remember_token (se anula con el trait WithoutRememberToken con precedencia)
 * - SIN cast 'hashed' en password para evitar doble-hash accidental.
 */
class UsuarioAdministrativo extends BaseAdminModel implements AuthenticatableContract
{
    use HasFactory, Notifiable;

    // Resolvemos la colisión: damos prioridad a *WithoutRememberToken*
    use Authenticatable, WithoutRememberToken {
        WithoutRememberToken::getRememberToken insteadof Authenticatable;
        WithoutRememberToken::setRememberToken insteadof Authenticatable;
        WithoutRememberToken::getRememberTokenName insteadof Authenticatable;

        // (opcional) Si quieres seguir accediendo a los originales del trait de Laravel,
        // puedes exponer alias protegidos así:
        Authenticatable::getRememberToken as protected __authGetRememberToken;
        Authenticatable::setRememberToken as protected __authSetRememberToken;
        Authenticatable::getRememberTokenName as protected __authGetRememberTokenName;
    }

    /** Conexión recomendada */
    protected $connection = 'mysql_admin';
    protected $table = 'usuario_administrativos';

    /** Clave primaria (UUID) */
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $primaryKey = 'id';

    /** Atributos asignables (sin remember_token) */
    protected $fillable = [
        'id',
        'nombre',
        'email',
        'password',  // guardar hash manualmente con Hash::make()
        'rol',
        'activo',
        'es_superadmin',
        'force_password_change',
        'last_login_at',
        'last_login_ip',
        // opcionales si existen en BD:
        'codigo_usuario',
        'estatus',
        'is_blocked',
        'ultimo_login_at',
        'ip_ultimo_login',
    ];

    /** Ocultos en arrays/JSON */
    protected $hidden = [
        'password',
    ];

    /** Casts seguros */
    protected $casts = [
        'activo'                => 'boolean',
        'es_superadmin'         => 'boolean',
        'force_password_change' => 'boolean',
        'last_login_at'         => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    /** Fallback si no existe conexión mysql_admin en config/database */
    public function getConnectionName()
    {
        return config('database.connections.mysql_admin')
            ? 'mysql_admin'
            : (config('database.default') ?? 'mysql');
    }

    /** Guard para Policies/Gates */
    public function getGuardName(): string
    {
        return 'admin';
    }

    /** UUID al crear si no viene */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /* =========================
     | Mutators / Accessors
     * ========================= */

    /** Normaliza email a minúsculas */
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
     | Helpers de estado
     * ========================= */

    public function mustChangePassword(): bool
    {
        return (bool) ($this->force_password_change ?? false);
    }

    public function isActive(): bool
    {
        $schema = Schema::connection($this->getConnectionName());
        if (!$schema->hasColumn($this->getTable(), 'activo')) return true;
        return (bool) $this->activo;
    }

    public function markLastLogin(?string $ip = null): void
    {
        $schema = Schema::connection($this->getConnectionName());
        $data   = [];

        if ($schema->hasColumn($this->getTable(), 'last_login_at')) {
            $data['last_login_at'] = now();
        }
        if ($ip && $schema->hasColumn($this->getTable(), 'last_login_ip')) {
            $data['last_login_ip'] = $ip;
        }

        if ($data) {
            $this->forceFill($data)->saveQuietly();
        }
    }

    /* =========================
     | Superadmin / Permisos (opcionales)
     * ========================= */

    protected array $permCache = [];

    public function isSuperAdmin(): bool
    {
        if (!empty($this->es_superadmin)) return true;

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

        if (array_key_exists($key, $this->permCache)) {
            return $this->permCache[$key];
        }

        $conn   = $this->getConnectionName();
        $schema = Schema::connection($conn);

        if (
            !$schema->hasTable('usuario_perfil') ||
            !$schema->hasTable('perfil_permiso') ||
            !$schema->hasTable('permisos')
        ) {
            return $this->permCache[$key] = false;
        }

        $hasClave  = $schema->hasColumn('permisos', 'clave');
        $hasNombre = $schema->hasColumn('permisos', 'nombre');

        if (!$hasClave && !$hasNombre) {
            return $this->permCache[$key] = false;
        }

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

        return $this->permCache[$key] = $q->exists();
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
