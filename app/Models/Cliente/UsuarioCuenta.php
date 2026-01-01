<?php

namespace App\Models\Cliente;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Usuario del portal Cliente
 * DB: mysql_clientes.usuarios_cuenta
 *
 * PK UUID (string)
 * Autenticable por guard "web"
 */
class UsuarioCuenta extends Authenticatable
{
    use Notifiable;

    /* ============================================================
     |  Conexión / Tabla
     * ============================================================ */

    protected $connection = 'mysql_clientes';
    protected $table      = 'usuarios_cuenta';

    /* ============================================================
     |  Primary Key (UUID)
     * ============================================================ */

    protected $primaryKey  = 'id';
    protected $keyType     = 'string';
    public    $incrementing = false;

    /* ============================================================
     |  Asignación masiva
     * ============================================================ */

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

    /* ============================================================
     |  Ocultos
     * ============================================================ */

    protected $hidden = [
        'password',
        'password_temp',
        'remember_token',
    ];

    /* ============================================================
     |  Casts
     * ============================================================ */

    protected $casts = [
        'activo'               => 'boolean',
        'must_change_password' => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    /* ============================================================
     |  Remember token
     * ============================================================ */

    protected $rememberTokenName = 'remember_token';

    /* ============================================================
     |  Boot: UUID automático
     * ============================================================ */

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /* ============================================================
     |  Relaciones
     * ============================================================ */

    /**
     * usuarios_cuenta.cuenta_id -> cuentas_cliente.id
     */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaCliente::class, 'cuenta_id', 'id');
    }

    /* ============================================================
     |  Accessors / Helpers
     * ============================================================ */

    /**
     * Nombre visible del usuario.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(
            fn () => $this->nombre ?: ($this->attributes['nombre'] ?? 'Usuario')
        );
    }

    /**
     * ¿Es dueño/owner?
     */
    public function isOwner(): bool
    {
        $rol  = strtolower((string) $this->rol);
        $tipo = strtolower((string) $this->tipo);

        return in_array($rol, ['owner', 'dueño', 'propietario'], true)
            || in_array($tipo, ['owner', 'dueño', 'propietario'], true);
    }

    /**
     * Forzar cambio de contraseña.
     * Usado por EnsureAccountIsActive.
     */
    public function mustChangePassword(): bool
    {
        return (bool) ($this->must_change_password ?? false);
    }

    /**
     * Usuario activo.
     */
    public function isActive(): bool
    {
        return (bool) ($this->activo ?? false);
    }

    /**
     * Plan actual desde la cuenta (lazy safe).
     */
    public function planActual(): ?string
    {
        $this->loadMissing('cuenta');
        return $this->cuenta?->plan_actual;
    }

    /**
     * Estado de cuenta desde la cuenta (lazy safe).
     */
    public function estadoCuenta(): ?string
    {
        $this->loadMissing('cuenta');
        return $this->cuenta?->estado_cuenta;
    }

    /**
     * Validación rápida de cuenta operativa.
     */
    public function cuentaActiva(): bool
    {
        $estado = strtolower((string) $this->estadoCuenta());

        return $this->isActive()
            && in_array($estado, ['activa', 'activa_ok', 'ok', 'active'], true);
    }

    /**
     * Email seguro.
     */
    public function emailOrNull(): ?string
    {
        return $this->email ?: null;
    }

    /**
     * Password usado por Auth.
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Setter de password:
     * - Evita doble hash si ya viene bcrypt
     */
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
