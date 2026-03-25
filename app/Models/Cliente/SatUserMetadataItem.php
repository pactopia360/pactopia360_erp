<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserMetadataItem extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_metadata_items';

    protected $fillable = [
        'metadata_upload_id',
        'cuenta_id',
        'usuario_id',
        'rfc_owner',
        'uuid',
        'rfc_emisor',
        'nombre_emisor',
        'rfc_receptor',
        'nombre_receptor',
        'fecha_emision',
        'fecha_certificacion_sat',
        'monto',
        'efecto_comprobante',
        'estatus',
        'fecha_cancelacion',
        'direction',
        'raw_line',
        'meta',
    ];

    protected $casts = [
        'fecha_emision'            => 'datetime',
        'fecha_certificacion_sat'  => 'datetime',
        'fecha_cancelacion'        => 'datetime',
        'monto'                    => 'decimal:2',
        'meta'                     => 'array',
    ];
}