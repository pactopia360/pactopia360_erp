<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;


/**
 * Cuenta de cliente (DB: mysql_clientes.cuentas_cliente)
 *
 * Tabla espejo `cuentas_cliente` (uuid PK):
 * - id (char(36) / uuid, PK)
 * - rfc_padre
 * - admin_account_id (nullable)
 * - razon_social, nombre_comercial, email, telefono
 * - plan, plan_actual, modo_cobro, estado_cuenta
 * - activo, is_blocked
 * - espacio_asignado_mb, hits_asignados, max_usuarios, max_empresas
 * - codigo_cliente, customer_no
 * - next_invoice_date, billing_cycle
 * - created_at, updated_at
 *
 * Nota:
 * - La tabla legacy `clientes` puede seguir existiendo, pero ESTE modelo ya no la usa.
 */
class CuentaCliente extends BaseClienteModel
{
    protected $connection = 'mysql_clientes';
    protected $table      = 'cuentas_cliente';

    // PK UUID
    protected $primaryKey   = 'id';
    protected $keyType      = 'string';
    public    $incrementing = false;

    public const PLAN_FREE  = 'FREE';
    public const PLAN_BASIC = 'BASIC';
    public const PLAN_PRO   = 'PRO';

    public const STATUS_PENDIENTE      = 'pendiente';
    public const STATUS_ACTIVA         = 'activa';
    public const STATUS_BLOQUEADA      = 'bloqueada';
    public const STATUS_SUSPENDIDA     = 'suspendida';
    public const STATUS_PAGO_PENDIENTE = 'pago_pendiente';
    public const STATUS_BLOQUEADA_PAGO = 'bloqueada_pago';

    /**
     * En espejo hay variaciones por entorno; usamos guarded vacío para no bloquear inserts
     * (ya que tu RegisterController hace set dinámico con cliHas()).
     */
    protected $guarded = [];

    protected $casts = [
        'activo'           => 'boolean',
        'is_blocked'       => 'boolean',
        'admin_account_id' => 'integer',
        'espacio_asignado_mb' => 'integer',
        'hits_asignados'      => 'integer',
        'max_usuarios'        => 'integer',
        'max_empresas'        => 'integer',
        'customer_no'         => 'integer',
        'next_invoice_date'   => 'date',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    /* =========================
     | Relaciones
     * ========================= */

    public function usuarios(): HasMany
    {
        // usuarios_cuenta.cuenta_id (uuid char36) -> cuentas_cliente.id (uuid)
        return $this->hasMany(UsuarioCuenta::class, 'cuenta_id', 'id');
    }

    public function owner(): HasOne
    {
        return $this->hasOne(UsuarioCuenta::class, 'cuenta_id', 'id')
            ->where(function ($q) {
                $q->whereIn('rol', ['owner', 'admin_owner', 'dueño', 'propietario'])
                  ->orWhereIn('tipo', ['owner', 'admin_owner', 'dueño', 'propietario', 'padre']);
            })
            ->orderByDesc('created_at');
    }

    /* =========================
     | Normalización / compat mínima
     * ========================= */

    public function getRfcPadreAttribute(): ?string
    {
        $val = $this->attributes['rfc_padre'] ?? null;
        return $val ? Str::upper(trim((string) $val)) : null;
    }

    public function setRfcPadreAttribute(?string $v): void
    {
        $this->attributes['rfc_padre'] = $v ? Str::upper(trim((string) $v)) : null;
    }

    public function getPlanActualAttribute(): ?string
    {
        $v = $this->attributes['plan_actual'] ?? ($this->attributes['plan'] ?? null);
        return $v ? strtoupper((string) $v) : null;
    }

    public function getEstadoCuentaAttribute(): ?string
    {
        $v = $this->attributes['estado_cuenta'] ?? null;
        return $v ? strtolower((string) $v) : null;
    }

    /* =========================
     | Helpers
     * ========================= */

    public function isFree(): bool
    {
        $p = strtoupper((string) ($this->plan_actual ?? $this->attributes['plan_actual'] ?? $this->attributes['plan'] ?? ''));
        return in_array($p, [self::PLAN_FREE, self::PLAN_BASIC, 'BASICO'], true);
    }

    public function isPro(): bool
    {
        $p = strtoupper((string) ($this->plan_actual ?? $this->attributes['plan_actual'] ?? $this->attributes['plan'] ?? ''));
        return $p === self::PLAN_PRO;
    }

    public function getPlanLabelAttribute(): string
    {
        return $this->isPro() ? 'PRO' : 'FREE';
    }

    public function satCredenciales(): HasMany
    {
        return $this->hasMany(SatCredential::class, 'cuenta_id', 'id');
    }

}
