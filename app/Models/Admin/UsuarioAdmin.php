<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

final class UsuarioAdmin extends Model
{
    protected $connection = 'mysql_admin';
    protected $table      = 'usuarios_admin';

    public $incrementing  = false; // tu auth usa uuid normalmente; si tu id es int, cambia a true
    protected $keyType    = 'string';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'rol',
        'permisos',
        'activo',
        'es_superadmin',
        'force_password_change',
        'last_login_at',
        'last_login_ip',
        'remember_token',
    ];

    protected $casts = [
        'activo'               => 'boolean',
        'es_superadmin'        => 'boolean',
        'force_password_change'=> 'boolean',
        'last_login_at'        => 'datetime',
    ];
}
