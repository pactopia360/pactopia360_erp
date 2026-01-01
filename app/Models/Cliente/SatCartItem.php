<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SatCartItem extends Model
{
    use HasUuids;

    protected $connection = 'mysql_clientes';
    protected $table = 'sat_cart_items';

    protected $fillable = [
        'cuenta_id',
        'sat_download_id',
        'qty',
        'unit_price',
        'total',
        'currency',
        'status',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'float',
        'total'      => 'float',
    ];

    public function download()
    {
        return $this->belongsTo(\App\Models\Cliente\SatDownload::class, 'sat_download_id');
    }
}
