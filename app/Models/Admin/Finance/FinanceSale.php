<?php

declare(strict_types=1);

namespace App\Models\Admin\Finance;

use Illuminate\Database\Eloquent\Model;

final class FinanceSale extends Model
{
    protected $table = 'finance_sales';

    protected $fillable = [
        'sale_code','account_id','vendor_id',
        'origin','periodicity',
        'f_cta','f_mov','invoice_date','paid_date','sale_date',
        'receiver_rfc','pay_method','cfdi_use',
        'subtotal','iva','total',
        'statement_status','statement_sent_at','statement_paid_at',
        'invoice_status','invoice_uuid',
        'payment_id','invoice_request_id',
        'include_in_statement','target_period',
        'notes',
    ];

    protected $casts = [
        'include_in_statement' => 'bool',
        'subtotal' => 'float',
        'iva' => 'float',
        'total' => 'float',
        'f_cta' => 'date',
        'f_mov' => 'date',
        'invoice_date' => 'date',
        'paid_date' => 'date',
        'sale_date' => 'date',
        'statement_sent_at' => 'date',
        'statement_paid_at' => 'date',
    ];
}
