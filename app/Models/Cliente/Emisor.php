<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Emisor extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql_clientes';
    protected $table = 'emisores';

    protected $fillable = [
        'cuenta_id','rfc','razon_social','nombre_comercial',
        'email','regimen_fiscal','grupo','direccion','certificados','series',
        'status','csd_serie','csd_vigencia_hasta','ext_id',
    ];

    protected $casts = [
        'direccion'          => 'array',
        'certificados'       => 'array',
        'series'             => 'array',
        'csd_vigencia_hasta' => 'datetime',
    ];
}
