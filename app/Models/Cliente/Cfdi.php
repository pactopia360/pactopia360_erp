<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo CFDI (módulo Cliente)
 *
 * Representa los comprobantes fiscales emitidos por una cuenta cliente.
 * Está vinculado a la base de datos `mysql_clientes`.
 */
class Cfdi extends Model
{
    /**
     * Conexión a la BD de clientes.
     *
     * @var string
     */
    protected $connection = 'mysql_clientes';

    /**
     * Tabla asociada.
     *
     * @var string
     */
    protected $table = 'cfdis';

    /**
     * Campos asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cliente_id',
        'receptor_id',
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

    /**
     * Casts de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha'    => 'datetime',
        'subtotal' => 'float',
        'iva'      => 'float',
        'total'    => 'float',
    ];

    /* ========================================
     | Relaciones
     * ======================================== */

    /**
     * Emisor / Cliente dueño del CFDI.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
    }

    /**
     * Receptor del CFDI.
     */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Receptor::class, 'receptor_id', 'id');
    }

    /**
     * Conceptos del CFDI.
     */
    public function conceptos(): HasMany
    {
        return $this->hasMany(CfdiConcepto::class, 'cfdi_id', 'id');
    }
}
