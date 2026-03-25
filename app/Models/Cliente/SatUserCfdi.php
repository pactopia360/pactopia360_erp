<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserCfdi extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_cfdis';

    protected $fillable = [
        'xml_upload_id',
        'cuenta_id',
        'usuario_id',
        'rfc_owner',
        'uuid',
        'version_cfdi',
        'rfc_emisor',
        'nombre_emisor',
        'rfc_receptor',
        'nombre_receptor',
        'fecha_emision',
        'subtotal',
        'descuento',
        'iva',
        'total',
        'tipo_comprobante',
        'moneda',
        'metodo_pago',
        'forma_pago',
        'direction',
        'xml_path',
        'xml_hash',
        'meta',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'subtotal'      => 'decimal:2',
        'descuento'     => 'decimal:2',
        'iva'           => 'decimal:2',
        'total'         => 'decimal:2',
        'meta'          => 'array',
    ];
}