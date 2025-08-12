<?php

namespace App\Models\Admin\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UsuarioAdministrativo extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuario_administrativos'; // tabla existente en p360v1_admin

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'activo',
        'es_superadmin',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activo'        => 'boolean',
        'es_superadmin' => 'boolean',
        'last_login_at' => 'datetime',
    ];
}
