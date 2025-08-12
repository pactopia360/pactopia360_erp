<?php

namespace App\Models\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'admin_users';
    protected $connection = 'mysql'; // BD admin
    protected $fillable = ['nombre','email','rfc','password','rol','activo'];
    protected $hidden = ['password','remember_token'];
}

