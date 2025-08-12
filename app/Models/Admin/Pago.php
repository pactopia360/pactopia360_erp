<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';
    protected $fillable = ['cliente_id','monto','fecha','estado','metodo_pago','referencia'];
    protected $casts = ['fecha'=>'datetime','monto'=>'decimal:2'];

    public function cliente(){ return $this->belongsTo(Cliente::class); }
}
