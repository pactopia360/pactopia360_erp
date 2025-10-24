<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UsuarioCuenta extends BaseClienteAuthenticatable
{
    use HasFactory, Notifiable;

    /** Conexión y tabla (BD clientes) */
    protected $connection = 'mysql_clientes';
    protected $table      = 'usuarios_cuenta';

    /** PK como UUID string */
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $primaryKey = 'id';

    /** Asignables */
    protected $fillable = [
        'id',
        'cuenta_id',
        'tipo',                 // owner | admin | user (histórico)
        'rol',                  // owner | admin | user (preferido)
        'nombre',
        'email',
        'phone',
        'password',             // IMPORTANTE: asignable pero sin cast 'hashed'
        'activo',
        'ultimo_login_at',
        'ip_ultimo_login',
        'sync_version',
        // 'must_change_password', // si existe en tu schema y quieres fillable
        'remember_token',       // si la columna existe
    ];

    /** Ocultos en arrays/JSON */
    protected $hidden = [
        'password',
        'password_temp',   // compat opcional si existiera
        'password_plain',  // compat opcional si existiera
        'remember_token',
    ];

    /** Casts (sin 'hashed' para evitar re-hash accidental) */
    protected $casts = [
        'activo'               => 'boolean',
        'ultimo_login_at'      => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'sync_version'         => 'integer',
        'must_change_password' => 'boolean', // si no existe la columna, queda null
        'email_verified_at'    => 'datetime',
    ];

    /** Genera UUID al crear (si no viene) */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /* ==========================
     | Relaciones
     * ========================== */

    /** usuario → cuenta */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaCliente::class, 'cuenta_id', 'id');
    }

    /* ==========================
     | Scopes / Helpers
     * ========================== */

    public function scopeActivos($q)
    {
        return $q->where('activo', 1);
    }

    public function scopeOfCuenta($q, string $cuentaId)
    {
        return $q->where('cuenta_id', $cuentaId);
    }

    /** Guard útil en Policies */
    public function getGuardName(): string
    {
        return 'web';
    }

    /** ¿Es propietario? (acepta rol o tipo = owner) */
    public function isOwner(): bool
    {
        return ($this->rol === 'owner') || ($this->tipo === 'owner');
    }

    /** ¿Puede iniciar sesión? */
    public function canLogin(): bool
    {
        return (bool) $this->activo;
    }

    /** Marca último login (fecha + IP) guardando en silencio */
    public function markLastLogin(?string $ip = null): void
    {
        $this->forceFill([
            'ultimo_login_at' => now(),
            'ip_ultimo_login' => $ip,
        ])->saveQuietly();
    }

    /* ==========================
     | Mutators
     * ========================== */

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? Str::lower(trim((string) $value)) : null;
    }

    public function setNombreAttribute($value): void
    {
        $this->attributes['nombre'] = $value ? trim((string) $value) : null;
    }

    /**
     * Si tu columna de contraseña se llama distinto a 'password',
     * define getAuthPassword(). En este caso no hace falta.
     */
    // public function getAuthPassword()
    // {
    //     return (string) ($this->password ?? '');
    // }
}
