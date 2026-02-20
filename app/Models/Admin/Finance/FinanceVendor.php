<?php

declare(strict_types=1);

namespace App\Models\Admin\Finance;

use Illuminate\Database\Eloquent\Model;

final class FinanceVendor extends Model
{
    protected $connection = 'mysql_admin';
    protected $table      = 'finance_vendors';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'is_active',
        'commission_rate',
        'meta',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'commission_rate' => 'decimal:3',
        'meta'            => 'array',
    ];
}
