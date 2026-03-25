<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserReconciliation extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_reconciliations';

    protected $fillable = [
        'cuenta_id',
        'usuario_id',
        'rfc_owner',
        'metadata_item_id',
        'cfdi_id',
        'uuid',
        'status',
        'differences_json',
        'meta',
    ];

    protected $casts = [
        'differences_json' => 'array',
        'meta'             => 'array',
    ];
}