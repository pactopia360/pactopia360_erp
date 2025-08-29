<?php

namespace App\Models\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Conexión y tabla del módulo ADMIN
     */
    protected $connection = 'mysql_admin';
    protected $table = 'account_users';

    /**
     * PK UUID (string, no autoincrement)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Asignación masiva
     * Ajusta estos campos a los que realmente tenga tu tabla account_users.
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        // 'role',
        // 'active',
        // 'last_login_at',
        // 'last_login_ip',
        // etc...
    ];

    /**
     * Ocultos en arrays/JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts comunes (habilita si tu tabla tiene estas columnas)
     */
    protected $casts = [
        // 'email_verified_at' => 'datetime',
        // 'last_login_at'     => 'datetime',
        // 'active'            => 'boolean',
    ];
}
