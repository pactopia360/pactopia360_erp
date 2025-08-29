<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';
    protected $fillable = ['clave','nombre','precio_mensual','activo'];
    protected $casts = ['precio_mensual'=>'float','activo'=>'boolean'];
}
