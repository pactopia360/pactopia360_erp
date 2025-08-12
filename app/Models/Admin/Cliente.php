<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $fillable = ['razon_social','nombre_comercial','rfc','plan_id','plan','activo'];
    protected $casts = ['activo'=>'boolean'];

    public function plan(){ return $this->belongsTo(Plan::class); }
    public function pagos(){ return $this->hasMany(Pago::class); }
    public function cfdis(){ return $this->hasMany(Cfdi::class); }
}
