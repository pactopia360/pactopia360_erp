<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AccountContract extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'account_contracts';

    protected $fillable = [
        'account_id',
        'code',
        'title',
        'version',
        'status',
        'signed_at',
        'signed_by_user_id',
        'signed_name',
        'signed_email',
        'signature_png_base64',
        'signature_hash',
        'signed_pdf_path',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function getIsSignedAttribute(): bool
    {
        return $this->status === 'signed' && !empty($this->signed_at);
    }
}
