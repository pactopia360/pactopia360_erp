<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario CLIENTE (Portal Cliente)
 * - conexión: mysql_clientes
 * - tabla: usuarios_cuenta
 *
 * Nota: si tu tabla real se llama distinto, ajusta $table.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** @var string */
    protected $connection = 'mysql_clientes';

    /** @var string */
    protected $table = 'usuarios_cuenta';

    /**
     * PK
     * Si tu PK es uuid/string, cambia $keyType y $incrementing.
     */
    protected $primaryKey = 'id';
    public $incrementing = false; // cambia a true si es int autoincrement
    protected $keyType = 'string'; // cambia a 'int' si aplica

    /**
     * Campos típicos de tu usuario cliente.
     * Ajusta según tu tabla real.
     */
    protected $fillable = [
        'cuenta_id',
        'name',
        'nombre',
        'email',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relación esperada por:
     * - EnsureAccountIsActive (usa $user->cuenta opcional)
     * - AccountBillingController::resolveAdminAccountId() (usa $user->cuenta->admin_account_id, rfc_padre)
     */
    public function cuenta()
    {
        return $this->belongsTo(\App\Models\CuentaCliente::class, 'cuenta_id', 'id');
    }
}
