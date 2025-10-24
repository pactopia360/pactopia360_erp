<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cuenta extends BaseAdminModel
{
    use SoftDeletes;

    protected $table = 'accounts';
    //protected $table = 'cuentas';
    protected $connection = 'mysql_admin';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id','rfc_padre','razon_social','codigo_cliente','email_principal',
        'email_verified_at','plan_id','estado','timbres','espacio_mb',
        'licencia','ciclo','proximo_corte',
        'bloqueado','bloqueado_desde','bloqueo_motivo',
        'hits_asignados'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'proximo_corte'     => 'date',
        'bloqueado'         => 'boolean',
        'bloqueado_desde'   => 'datetime',
    ];

    public function plan(){ return $this->belongsTo(Plan::class); }
}
