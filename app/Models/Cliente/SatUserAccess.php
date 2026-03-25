<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserAccess extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_access';

    protected $fillable = [
        'cuenta_id',
        'usuario_id',
        'can_access_vault',
        'can_upload_metadata',
        'can_upload_xml',
        'can_export',
        'meta',
    ];

    protected $casts = [
        'can_access_vault'   => 'boolean',
        'can_upload_metadata'=> 'boolean',
        'can_upload_xml'     => 'boolean',
        'can_export'         => 'boolean',
        'meta'               => 'array',
    ];
}