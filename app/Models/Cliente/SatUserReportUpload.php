<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SatUserReportUpload extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'sat_user_report_uploads';

    protected $fillable = [
        'cuenta_id',
        'usuario_id',
        'rfc_owner',
        'report_type',
        'linked_metadata_upload_id',
        'linked_xml_upload_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime',
        'bytes',
        'rows_count',
        'status',
        'meta',
    ];

    protected $casts = [
        'linked_metadata_upload_id' => 'integer',
        'linked_xml_upload_id'      => 'integer',
        'bytes'                     => 'integer',
        'rows_count'                => 'integer',
        'meta'                      => 'array',
    ];
}