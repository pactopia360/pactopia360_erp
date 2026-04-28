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
        'emisor_credential_id',

        'serie',
        'folio',
        'subtotal',
        'iva',
        'total',
        'fecha',
        'fecha_cfdi',
        'estatus',
        'uuid',

        'rfc_emisor',
        'rfc_receptor',
        'razon_emisor',
        'razon_receptor',

        'moneda',
        'forma_pago',
        'metodo_pago',
        'tipo',
        'tipo_comprobante',

        'pac_env',
        'pac_status',
        'pac_uuid',
        'pac_response',
        'json_enviado',
        'xml_base64',
        'xml_timbrado',
        'pdf_base64',
        'fecha_timbrado',
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
        'iva' => 'float',
        'total' => 'float',
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

    public function conceptos(): HasMany
    {
        return $this->hasMany(CfdiConcepto::class, 'cfdi_id', 'id');
    }
}