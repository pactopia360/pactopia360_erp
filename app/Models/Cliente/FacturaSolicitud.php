<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class FacturaSolicitud extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'facturas_solicitudes';

    protected $fillable = [
        'account_id',
        'period',
        'status',
        'notes',
        'requested_by_user_id',
        'zip_path',
        'zip_name',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
