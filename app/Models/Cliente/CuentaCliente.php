<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CuentaCliente extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'cuentas_cliente';

    /** Clave primaria UUID */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'codigo_cliente',
        'customer_no',
        'rfc_padre',
        'razon_social',
        'plan_actual',
        'modo_cobro',
        'estado_cuenta',
        'espacio_asignado_mb',
        'hits_asignados',
    ];

    protected $casts = [
        'espacio_asignado_mb' => 'integer',
        'hits_asignados'      => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /** Relación: una cuenta → muchos usuarios */
    public function usuarios(): HasMany
    {
        return $this->hasMany(UsuarioCuenta::class, 'cuenta_id', 'id');
    }
}
