<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';
    protected $connection = 'mysql_admin';
    protected $fillable = [
        'titulo','tipo','valor','plan_id','fecha_inicio','fecha_fin',
        'codigo_cupon','uso_maximo','usos_actuales','activa'
    ];
}
