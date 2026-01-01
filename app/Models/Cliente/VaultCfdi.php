<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class VaultCfdi extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table      = 'sat_vault_cfdis';

    protected $fillable = [
        'cuenta_id',
        'uuid',

        // fecha (según tu tabla)
        'fecha_emision',
        'fecha',

        // clasificación
        'tipo', // emitidos/recibidos
        'tipo_comprobante',

        // RFCs
        'rfc_emisor',
        'rfc_receptor',
        'rfc',

        // razones sociales (como tu controller las usa)
        'razon_emisor',
        'razon_receptor',

        // importes
        'subtotal',
        'iva',
        'total',

        // linkage
        'vault_file_id',
        'xml_path',

        // opcionales
        'meta',
        'source',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha'         => 'datetime',
        'subtotal'      => 'float',
        'iva'           => 'float',
        'total'         => 'float',
        'meta'          => 'array',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
}
