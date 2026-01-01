<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

final class AccountRecipient extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'account_recipients';

    protected $fillable = [
        'account_id',
        'email',
        'name',
        'type',
        'is_enabled',
    ];

    protected $casts = [
        'account_id'  => 'integer',
        'is_enabled'  => 'boolean',
    ];
}
