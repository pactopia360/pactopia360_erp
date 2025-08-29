<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permisos';
    protected $fillable = ['clave','grupo','label','descripcion','activo'];
}
