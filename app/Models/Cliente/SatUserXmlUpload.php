<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserXmlUpload extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_xml_uploads';

    protected $fillable = [
        'cuenta_id',
        'usuario_id',
        'rfc_owner',
        'source_type',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime',
        'bytes',
        'files_count',
        'direction_detected',
        'status',
        'meta',
    ];

    protected $casts = [
        'bytes'      => 'integer',
        'files_count'=> 'integer',
        'meta'       => 'array',
    ];
}