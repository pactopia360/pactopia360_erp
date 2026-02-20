<?php

declare(strict_types=1);

namespace App\Models\Admin\Finance;

use Illuminate\Database\Eloquent\Model;

final class FinanceVendor extends Model
{
    protected $table = 'finance_vendors';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'default_commission_pct',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_commission_pct' => 'decimal:3',
        'meta' => 'array',
    ];
}