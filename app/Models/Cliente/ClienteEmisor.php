<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class ClienteEmisor extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table      = 'cliente_emisores';

    protected $fillable = [
        'cuenta_id',
        'rfc',
        'razon_social',
        'nombre_comercial',
        'regimen_fiscal',
        'cp',
        'activo',
    ];

    public function cuenta()
    {
        return $this->belongsTo(\App\Models\Cuenta::class, 'cuenta_id');
    }
}
