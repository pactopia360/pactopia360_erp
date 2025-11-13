<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Audit extends Model
{
    use HasFactory;

    protected $connection = 'mysql_admin';
    protected $table = 'audits';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id', 'account_id', 'usuario_id', 'event',
        'rfc', 'razon_social', 'correo', 'plan',
        'ip', 'user_agent', 'meta'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'meta' => 'array',
    ];
}
