<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserVault extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_vaults';

    protected $fillable = [
        'cuenta_id',
        'usuario_id',
        'rfc',
        'alias',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta'      => 'array',
    ];
}