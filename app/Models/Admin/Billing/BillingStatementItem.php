<?php

namespace App\Models\Admin\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingStatementItem extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'billing_statement_items';

    protected $fillable = [
        'statement_id','type','code','description',
        'qty','unit_price','amount','ref','meta'
    ];

    protected $casts = [
        'qty'        => 'decimal:4',
        'unit_price' => 'decimal:2',
        'amount'     => 'decimal:2',
        'meta'       => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BillingStatement::class, 'statement_id');
    }
}
