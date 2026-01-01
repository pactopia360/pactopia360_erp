<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaultFile extends Model
{
    use HasFactory;

    protected $connection = 'mysql_clientes';
    protected $table      = 'sat_vault_files';

    protected $primaryKey = 'id';
    public $incrementing  = true;
    protected $keyType    = 'int';

    protected $fillable = [
        'cuenta_id',
        'source',
        'source_id',
        'rfc',
        'filename',
        'path',
        'disk',
        'bytes',
        'mime',
        'created_by',
        'size_bytes',

        // opcionales legacy/compat (si existen)
        'uuid',
        'fecha_emision',
        'tipo',
        'rfc_emisor',
        'razon_social',
        'subtotal',
        'iva',
        'total',
    ];

    protected $casts = [
        'bytes'       => 'integer',
        'size_bytes'  => 'integer',
        'fecha_emision' => 'date',
        'subtotal'    => 'float',
        'iva'         => 'float',
        'total'       => 'float',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    protected $guarded = [];
}
