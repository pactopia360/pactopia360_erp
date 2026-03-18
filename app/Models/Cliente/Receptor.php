<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class Receptor extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'receptores';

    protected $fillable = [
        'cuenta_id',
        'rfc',
        'razon_social',
        'nombre_comercial',
        'uso_cfdi',
        'email',
    ];

    protected $casts = [
        'cuenta_id' => 'integer',
    ];
}