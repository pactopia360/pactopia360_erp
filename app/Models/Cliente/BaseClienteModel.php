<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

abstract class BaseClienteModel extends Model
{
    protected $connection = 'mysql_clientes';
}
