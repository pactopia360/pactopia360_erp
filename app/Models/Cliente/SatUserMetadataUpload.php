<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserMetadataUpload extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_metadata_uploads';

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
        'rows_count',
        'direction_detected',
        'status',
        'meta',
    ];

    protected $casts = [
        'bytes'      => 'integer',
        'rows_count' => 'integer',
        'meta'       => 'array',
    ];
}