<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class Receptor extends Model
{
    /** Usamos la conexión principal por defecto. */
    protected $connection = 'mysql';

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
