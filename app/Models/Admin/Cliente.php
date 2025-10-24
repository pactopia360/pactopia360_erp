<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Cliente extends BaseAdminModel
{
    protected $table = 'clientes';
    protected $guarded = [];
    public $timestamps = true;

    // Ejemplos de columnas comunes (opcional):
    // protected $casts = ['activo' => 'boolean'];
}
