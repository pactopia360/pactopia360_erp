<?php

namespace App\Models\Cliente;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Base para modelos autenticables del módulo Cliente.
 * Fija la conexión mysql_clientes para no repetirla.
 */
abstract class BaseClienteAuthenticatable extends Authenticatable
{
    /**
     * Conexión de BD del módulo de clientes.
     *
     * @var string
     */
    protected $connection = 'mysql_clientes';
}

