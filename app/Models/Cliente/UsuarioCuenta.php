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

    protected $fillable = [
        'nombre',
        'email',
        'password',
        // 🔴 NO elimines tus campos actuales, agrega aquí los que ya tienes
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}