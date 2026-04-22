<?php

namespace App\Models\Cliente;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UsuarioCuenta extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $connection = 'mysql_clientes';
    protected $table = 'usuarios_cuenta';
    protected $primaryKey = 'id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cuenta_id',
        'nombre',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}