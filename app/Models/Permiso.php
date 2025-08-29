<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = [
        'clave', 'grupo', 'label', 'activo', 'meta',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'meta'   => 'array',
    ];
}
