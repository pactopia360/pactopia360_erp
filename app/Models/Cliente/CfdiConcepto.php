<?php

namespace App\Models\Cliente;

class CfdiConcepto extends BaseClienteModel
{
    protected $table = 'cfdi_conceptos';

    protected $fillable = [
        'cfdi_id','producto_id','descripcion',
        'cantidad','precio_unitario','subtotal','iva','total',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'precio_unitario' => 'float',
        'subtotal' => 'float',
        'iva' => 'float',
        'total' => 'float',
    ];

    public function cfdi()     { return $this->belongsTo(Cfdi::class); }
    public function producto() { return $this->belongsTo(Producto::class); }
}
