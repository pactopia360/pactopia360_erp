<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class StripePrice extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'stripe_price_list';

    // Si tu tabla no tiene 'name', NO lo declares en fillable.
    protected $fillable = [
        'price_key',
        'plan',
        'billing_cycle',
        'stripe_price_id',
        'currency',
        'display_amount',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_amount' => 'decimal:2',
        'meta' => 'array',
    ];
}
