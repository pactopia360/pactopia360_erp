<?php

namespace App\Models\Admin\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingStatementEmail extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'billing_statement_emails';

    protected $fillable = ['statement_id','email','is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BillingStatement::class, 'statement_id');
    }
}
