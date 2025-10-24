<?php

namespace App\Models\Cliente;

class Producto extends BaseClienteModel
{
    protected $table = 'productos';

    protected $fillable = [
        'cuenta_id','sku','descripcion','precio_unitario',
        'iva_tasa','clave_prodserv','clave_unidad','activo',
    ];

    protected $casts = [
        'precio_unitario' => 'float',
        'iva_tasa' => 'float',
        'activo' => 'bool',
    ];
}
