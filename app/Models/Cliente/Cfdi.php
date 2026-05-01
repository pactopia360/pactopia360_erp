<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cfdi extends Model
{
    protected $connection = 'mysql_clientes';

    protected $table = 'cfdis';

    protected $fillable = [
        'cliente_id',
        'cuenta_id',

        'receptor_id',
        'empleado_nomina_id',
        'emisor_credential_id',

        'uuid',
        'serie',
        'folio',
        'fecha',
        'fecha_cfdi',
        'fecha_timbrado',

        'version_cfdi',
        'tipo',
        'tipo_documento',
        'tipo_comprobante',

        'estatus',
        'status',

        'rfc_emisor',
        'rfc_receptor',
        'razon_emisor',
        'razon_receptor',
        'emisor_rfc',
        'emisor_razon_social',

        'moneda',
        'tipo_cambio',
        'forma_pago',
        'metodo_pago',
        'uso_cfdi',
        'regimen_receptor',
        'cp_receptor',
        'condiciones_pago',

        'subtotal',
        'descuento',
        'iva',
        'total',

        'saldo_original',
        'saldo_pagado',
        'saldo_pendiente',
        'estado_pago',

        'tipo_relacion',
        'uuid_relacionado',

        'rep_json',
        'nomina_json',
        'carta_porte_json',
        'receptor_nomina_json',
        'ia_fill_json',
        'ia_fiscal_snapshot',
        'ia_fiscal_score',
        'ia_fiscal_nivel',

        'adenda_tipo',
        'adenda_json',
        'adenda_xml',

        'observaciones',

        'pac_env',
        'pac_status',
        'pac_uuid',
        'pac_response',
        'json_enviado',
        'xml_base64',
        'xml_timbrado',
        'pdf_base64',
        'sello_cfd',
        'sello_sat',
        'no_certificado_sat',
        'no_certificado_cfd',
        'qr_url',
        'cadena_original',
        'timbrado_por',
        'es_timbrado_real',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_cfdi' => 'datetime',
        'fecha_timbrado' => 'datetime',

        'subtotal' => 'float',
        'descuento' => 'float',
        'iva' => 'float',
        'total' => 'float',
        'tipo_cambio' => 'float',

        'saldo_original' => 'float',
        'saldo_pagado' => 'float',
        'saldo_pendiente' => 'float',

        'ia_fiscal_score' => 'float',

        'rep_json' => 'array',
        'nomina_json' => 'array',
        'carta_porte_json' => 'array',
        'receptor_nomina_json' => 'array',
        'ia_fill_json' => 'array',
        'ia_fiscal_snapshot' => 'array',
        'adenda_json' => 'array',

        'es_timbrado_real' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Receptor::class, 'receptor_id', 'id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Receptor::class, 'receptor_id', 'id');
    }

    public function empleadoNomina(): BelongsTo
    {
        return $this->belongsTo(EmpleadoNomina::class, 'empleado_nomina_id', 'id');
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(CfdiConcepto::class, 'cfdi_id', 'id');
    }
}