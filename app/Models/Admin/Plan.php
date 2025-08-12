<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';
    protected $fillable = ['clave','nombre','descripcion','precio_mensual','activo'];
    protected $casts = ['activo'=>'boolean','precio_mensual'=>'decimal:2'];

    public function clientes(){ return $this->hasMany(Cliente::class, 'plan_id'); }
}
