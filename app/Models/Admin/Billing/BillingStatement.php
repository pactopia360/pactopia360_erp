<?php

namespace App\Models\Admin\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingStatement extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'billing_statements';

    protected $fillable = [
        'account_id','period',
        'total_cargo','total_abono','saldo',
        'status','due_date','sent_at','paid_at',
        'snapshot','meta','is_locked'
    ];

    protected $casts = [
        'total_cargo' => 'decimal:2',
        'total_abono' => 'decimal:2',
        'saldo'       => 'decimal:2',
        'is_locked'   => 'boolean',
        'sent_at'     => 'datetime',
        'paid_at'     => 'datetime',
        'due_date'    => 'date',
        'snapshot'    => 'array',
        'meta'        => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BillingStatementItem::class, 'statement_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(BillingStatementEmail::class, 'statement_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BillingStatementEvent::class, 'statement_id');
    }
}
