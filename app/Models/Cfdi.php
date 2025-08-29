<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cfdi extends Model
{
    protected $table = 'cfdis';
    protected $fillable = ['cliente_id','serie','folio','total','fecha','estatus','uuid'];
    protected $casts = ['fecha'=>'datetime','total'=>'float'];
    public function cliente(){ return $this->belongsTo(Cliente::class); }
}
