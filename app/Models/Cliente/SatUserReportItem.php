<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserReportItem extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_report_items';

    protected $fillable = [
        'cuenta_id',
        'usuario_id',
        'rfc_owner',
        'report_upload_id',
        'report_type',
        'direction',
        'line_no',
        'uuid',
        'fecha_emision',
        'periodo_ym',
        'emisor_rfc',
        'emisor_nombre',
        'receptor_rfc',
        'receptor_nombre',
        'tipo_comprobante',
        'moneda',
        'subtotal',
        'descuento',
        'traslados',
        'retenidos',
        'total',
        'raw_row',
        'meta',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'subtotal'      => 'decimal:2',
        'descuento'     => 'decimal:2',
        'traslados'     => 'decimal:2',
        'retenidos'     => 'decimal:2',
        'total'         => 'decimal:2',
        'raw_row'       => 'array',
        'meta'          => 'array',
    ];
}