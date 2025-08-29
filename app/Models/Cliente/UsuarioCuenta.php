<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UsuarioCuenta extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'usuarios_cuenta';

    /** Clave primaria UUID */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'cuenta_id',
        'tipo',            // padre | hijo
        'nombre',
        'email',
        'password',
        'rol',             // owner | admin | user
        'activo',
        'ultimo_login_at',
        'ip_ultimo_login',
        'sync_version',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'activo'          => 'boolean',
        'ultimo_login_at' => 'datetime',
        'sync_version'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /** Relación: usuario → cuenta */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaCliente::class, 'cuenta_id', 'id');
    }
}
