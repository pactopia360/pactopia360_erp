<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo CFDI (módulo Cliente)
 */
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
        'estatus',
        'uuid',
        'moneda',
        'forma_pago',
        'metodo_pago',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'float',
        'iva' => 'float',
        'total' => 'float',
    ];

    /**
     * Compatibilidad con la vista actual:
     * realmente el CFDI se relaciona con receptores, no con App\Models\Cliente\Cliente.
     */
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