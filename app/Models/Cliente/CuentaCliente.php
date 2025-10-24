<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Cuenta de cliente (DB: mysql_clientes.cuentas_cliente)
 *
 * Columnas presentes (según tu esquema):
 * - id (char36, PK) | codigo_cliente | customer_no (bigint unsigned)
 * - rfc_padre | razon_social | plan_actual (p.ej. BASIC/PRO)
 * - modo_cobro (p.ej. mensual/anual) | estado_cuenta (activa/bloqueada/…)
 * - espacio_asignado_mb (uint) | hits_asignados (uint)
 * - created_at | updated_at
 */
class CuentaCliente extends BaseClienteModel
{
    /** Conexión y tabla */
    protected $connection = 'mysql_clientes';
    protected $table      = 'cuentas_cliente';

    /** PK uuid (char36) */
    protected $primaryKey   = 'id';
    protected $keyType      = 'string';
    public    $incrementing = false;

    /** Constantes de plan/estado */
    public const PLAN_BASIC = 'BASIC'; // equivalente a FREE
    public const PLAN_FREE  = 'FREE';
    public const PLAN_PRO   = 'PRO';

    public const STATUS_ACTIVA         = 'activa';
    public const STATUS_BLOQUEADA      = 'bloqueada';
    public const STATUS_SUSPENDIDA     = 'suspendida';
    public const STATUS_PAGO_PENDIENTE = 'pago_pendiente';
    public const STATUS_BLOQUEADA_PAGO = 'bloqueada_pago';

    /** Asignables (solo columnas reales) */
    protected $fillable = [
        'id',
        'codigo_cliente',
        'customer_no',
        'rfc_padre',
        'razon_social',
        'plan_actual',          // BASIC|FREE|PRO (normalizamos a BASIC/PRO)
        'modo_cobro',           // mensual|anual|…
        'estado_cuenta',        // activa|bloqueada|suspendida|…
        'espacio_asignado_mb',
        'hits_asignados',
    ];

    /** Casts */
    protected $casts = [
        'customer_no'         => 'integer',
        'espacio_asignado_mb' => 'integer',
        'hits_asignados'      => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    /** Genera UUID al crear */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    /* ==========================
     | Relaciones
     * ========================== */

    /** 1 cuenta → N usuarios */
    public function usuarios(): HasMany
    {
        return $this->hasMany(UsuarioCuenta::class, 'cuenta_id', 'id');
    }

    /**
     * Usuario owner (acepta sinónimos en rol/tipo):
     * - owner | admin_owner | dueño | propietario | padre (histórico)
     */
    public function owner(): HasOne
    {
        return $this->hasOne(UsuarioCuenta::class, 'cuenta_id', 'id')
            ->where(function ($q) {
                $q->whereIn('rol',   ['owner', 'admin_owner', 'dueño', 'propietario'])
                  ->orWhereIn('tipo', ['owner', 'admin_owner', 'dueño', 'propietario', 'padre']);
            })
            ->where('activo', 1)
            ->orderBy('created_at');
    }

    /* ==========================
     | Scopes / Helpers de plan/estado
     * ========================== */

    public function scopeActiva($q) { return $q->where('estado_cuenta', self::STATUS_ACTIVA); }
    public function scopePro($q)    { return $q->whereIn('plan_actual', [self::PLAN_PRO]); }
    public function scopeFree($q)   { return $q->whereIn('plan_actual', [self::PLAN_FREE, self::PLAN_BASIC]); }

    /** Plan helpers (BASIC se trata como FREE) */
    public function isFree(): bool
    {
        $p = strtoupper((string) $this->plan_actual);
        return in_array($p, [self::PLAN_FREE, self::PLAN_BASIC], true);
    }

    public function isPro(): bool
    {
        return strtoupper((string) $this->plan_actual) === self::PLAN_PRO;
    }

    public function isActiva(): bool
    {
        return (string) $this->estado_cuenta === self::STATUS_ACTIVA;
    }

    public function isPagoPendiente(): bool
    {
        return in_array((string) $this->estado_cuenta, [self::STATUS_PAGO_PENDIENTE, self::STATUS_BLOQUEADA_PAGO], true);
    }

    /** Label de plan amigable */
    public function getPlanLabelAttribute(): string
    {
        return $this->isPro() ? 'PRO' : 'FREE';
    }

    /* ==========================
     | Normalizadores
     * ========================== */

    public function setRfcPadreAttribute(?string $v): void
    {
        $this->attributes['rfc_padre'] = $v ? Str::upper(trim($v)) : null;
    }

    public function setRazonSocialAttribute(?string $v): void
    {
        $this->attributes['razon_social'] = $v ? trim($v) : null;
    }

    /**
     * Normaliza plan:
     *  - 'free', 'basic', 'basico' → BASIC (equivalente FREE)
     *  - 'pro'                     → PRO
     */
    public function setPlanActualAttribute(?string $v): void
    {
        $v = $v ? Str::upper(trim($v)) : null;
        if ($v && in_array($v, ['FREE', 'BASIC', 'BASICO'], true)) {
            $v = self::PLAN_BASIC;
        }
        if ($v === 'PRO') {
            $v = self::PLAN_PRO;
        }
        $this->attributes['plan_actual'] = $v;
    }

    public function setModoCobroAttribute(?string $v): void
    {
        $this->attributes['modo_cobro'] = $v ? Str::lower(trim($v)) : null;
    }

    /* ==========================
     | Reglas simples
     * ========================== */

    /** ¿Tiene asignado al menos N MB? (no llevas espacio_usado_mb en esta tabla) */
    public function hasStorageSpaceAssigned(int $mb): bool
    {
        return (int) ($this->espacio_asignado_mb ?? 0) >= max(0, $mb);
    }

    /** FREE: valida contra hits asignados; PRO: sin límite por esta tabla */
    public function canUseTimbres(int $toUse = 1): bool
    {
        if ($this->isFree()) {
            $asig = (int) ($this->hits_asignados ?? 0);
            // Sin columna de "usados" aquí; se valida que la bolsa asignada alcance.
            return $toUse <= $asig && $asig > 0;
        }
        return true;
    }
}
