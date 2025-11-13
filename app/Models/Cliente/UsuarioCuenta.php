<?php

namespace App\Models\Cliente;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UsuarioCuenta extends Authenticatable
{
    use Notifiable;

    /** Conexión y tabla. */
    protected $connection = 'mysql_clientes';
    protected $table      = 'usuarios_cuenta';

    /** Primary key es UUID string, no autoincrement. */
    protected $primaryKey  = 'id';
    public $incrementing   = false;
    protected $keyType     = 'string';

    /** Campos asignables. */
    protected $fillable = [
        'id',
        'cuenta_id',
        'tipo',
        'rol',
        'nombre',
        'email',
        'password',
        'password_temp',
        'must_change_password',
        'activo',
        'created_at',
        'updated_at',
    ];

    /** Ocultos. */
    protected $hidden = [
        'password',
        'password_temp',
        'remember_token',
    ];

    /** Casts. */
    protected $casts = [
        'activo'               => 'boolean',
        'must_change_password' => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    /** Nombre de remember me cookie (si existe esa columna). */
    protected $rememberTokenName = 'remember_token';

    /** ========= UUID AUTO ========== */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    /* ========================= RELACIONES ========================= */

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaCliente::class, 'cuenta_id', 'id');
    }

    /* =================== ACCESSORS / HELPERS ====================== */

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->nombre ?: $this->attributes['nombre'] ?? 'Usuario');
    }

    public function isOwner(): bool
    {
        $rol  = strtolower((string) $this->rol);
        $tipo = strtolower((string) $this->tipo);
        return $rol === 'owner' || $tipo === 'owner';
    }

    public function mustChangePassword(): bool
    {
        return (bool) ($this->must_change_password ?? false);
    }

    public function isActive(): bool
    {
        return (bool) ($this->activo ?? false);
    }

    public function planActual(): ?string
    {
        if (!$this->relationLoaded('cuenta')) {
            $this->loadMissing('cuenta');
        }
        return $this->cuenta?->plan_actual;
    }

    public function estadoCuenta(): ?string
    {
        if (!$this->relationLoaded('cuenta')) {
            $this->loadMissing('cuenta');
        }
        return $this->cuenta?->estado_cuenta;
    }

    public function cuentaActiva(): bool
    {
        $estado = strtolower((string) $this->estadoCuenta());
        return $this->isActive() && in_array($estado, ['activa','activa_ok','ok','active'], true);
    }

    public function emailOrNull(): ?string
    {
        return $this->email ?: null;
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    /** Siempre almacenar password ya hasheado (evita doble-hash si ya viene bcrypt). */
    protected function password(): Attribute
    {
        return Attribute::set(function ($value) {
            if (is_string($value) && preg_match('/^\$2y\$/', $value)) {
                return $value;
            }
            return !empty($value) ? bcrypt($value) : $value;
        });
    }
}
