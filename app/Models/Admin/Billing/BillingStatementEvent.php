<?php

namespace App\Models\Admin\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingStatementEvent extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql_admin';
    protected $table = 'billing_statement_events';

    protected $fillable = ['statement_id','event','actor','notes','meta','created_at'];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BillingStatement::class, 'statement_id');
    }
}
