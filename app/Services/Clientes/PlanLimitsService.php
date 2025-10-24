<?php

namespace App\Services\Clientes;

use App\Models\Cliente\CuentaCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PlanLimitsService
{
    public const PLAN_FREE = 'FREE';
    public const PLAN_PRO  = 'PRO';

    /** Obtiene la cuenta (con bloqueo opcional). */
    public function getCuenta(string $cuentaId, bool $forUpdate = false): ?CuentaCliente
    {
        $q = CuentaCliente::on('mysql_clientes')->where('id', $cuentaId);
        return $forUpdate ? $q->lockForUpdate()->first() : $q->first();
    }

    /** ¿Puede emitir N timbres adicionales? (FREE: 5 total; PRO: sin límite de timbres por este control) */
    public function canUseTimbres(string $cuentaId, int $toUse = 1): bool
    {
        $c = $this->getCuenta($cuentaId);
        if (!$c) return false;

        if ($c->plan_actual === self::PLAN_FREE) {
            return ($c->hits_usados + $toUse) <= $c->hits_asignados; // p.ej. 0+1 <= 5
        }
        // PRO: este método no limita; los límites diarios de masivos se controlan aparte.
        return true;
    }

    /** Registra consumo de timbres. */
    public function addTimbresUsed(string $cuentaId, int $used = 1): void
    {
        DB::connection('mysql_clientes')->transaction(function () use ($cuentaId, $used) {
            $c = $this->getCuenta($cuentaId, true);
            if (!$c) return;
            $c->hits_usados = (int)$c->hits_usados + $used;
            $c->save();
        });
    }

    /** ¿Puede generar facturas masivas hoy? (solo PRO) */
    public function canRunMassInvoicesToday(string $cuentaId, int $toRun = 1): bool
    {
        $c = $this->getCuenta($cuentaId);
        if (!$c) return false;

        if ($c->plan_actual !== self::PLAN_PRO) return false;

        // Reset diario si ya pasó la hora
        $this->ensureDailyReset($c);

        $limit = (int)$c->max_mass_invoices_per_day; // PRO: 100
        if ($limit <= 0) return false;

        return ($c->mass_invoices_used_today + $toRun) <= $limit;
    }

    /** Registra consumo de facturas masivas del día. */
    public function addMassInvoicesUsed(string $cuentaId, int $used = 1): void
    {
        DB::connection('mysql_clientes')->transaction(function () use ($cuentaId, $used) {
            $c = $this->getCuenta($cuentaId, true);
            if (!$c) return;

            $this->ensureDailyReset($c);

            $c->mass_invoices_used_today = (int)$c->mass_invoices_used_today + $used;
            $c->save();
        });
    }

    /** Reset diario si corresponde. */
    public function ensureDailyReset(CuentaCliente $c): void
    {
        $now = Carbon::now();
        $resetAt = $c->mass_invoices_reset_at ? Carbon::parse($c->mass_invoices_reset_at) : null;

        if (!$resetAt || $now->gte($resetAt)) {
            $c->mass_invoices_used_today = 0;
            $c->mass_invoices_reset_at = $now->copy()->startOfDay()->addDay(); // mañana 00:00
        }
    }

    /** ¿Puede crear otro usuario? FREE=1, PRO=ilimitado (usa null o número alto). */
    public function canAddUser(string $cuentaId, int $currentUsers): bool
    {
        $c = $this->getCuenta($cuentaId);
        if (!$c) return false;

        if ($c->plan_actual === self::PLAN_FREE) {
            return $currentUsers < (int)$c->max_usuarios; // default 1
        }
        // PRO: si max_usuarios == 0 o null -> ilimitado; si tiene valor, respétalo
        if (empty($c->max_usuarios)) return true;
        return $currentUsers < (int)$c->max_usuarios;
    }

    /** ¿Puede agregar otra empresa? En FREE dejamos multiempresa sin límite (9999). */
    public function canAddEmpresa(string $cuentaId, int $currentEmpresas): bool
    {
        $c = $this->getCuenta($cuentaId);
        if (!$c) return false;

        $limit = (int)$c->max_empresas;
        return $currentEmpresas < $limit;
    }

    /** ¿Tiene espacio para subir N MB? */
    public function hasStorageSpace(string $cuentaId, int $incomingMB): bool
    {
        $c = $this->getCuenta($cuentaId);
        if (!$c) return false;

        return ((int)$c->espacio_usado_mb + $incomingMB) <= (int)$c->espacio_asignado_mb;
    }

    /** Registra consumo de almacenamiento. */
    public function addStorageUsed(string $cuentaId, int $mb): void
    {
        DB::connection('mysql_clientes')->transaction(function () use ($cuentaId, $mb) {
            $c = $this->getCuenta($cuentaId, true);
            if (!$c) return;

            $c->espacio_usado_mb = (int)$c->espacio_usado_mb + max(0, $mb);
            $c->save();
        });
    }

    /** Upgrade Free → Pro: ajusta límites y sincroniza plan/stripe si se manda modo_cobro. */
    public function upgradeToPro(string $cuentaId, ?string $modoCobro = 'mensual'): void
    {
        DB::connection('mysql_clientes')->transaction(function () use ($cuentaId, $modoCobro) {
            /** @var CuentaCliente|null $c */
            $c = $this->getCuenta($cuentaId, true);
            if (!$c) return;

            $c->plan_actual = self::PLAN_PRO;
            $c->modo_cobro = $modoCobro; // mensual|anual
            $c->espacio_asignado_mb = 15 * 1024; // 15GB
            $c->max_mass_invoices_per_day = 100;
            $c->max_usuarios = 0; // 0 => ilimitado
            // max_empresas lo dejamos 9999 para ambos
            $c->estado_cuenta = 'activa';
            if (!$c->mass_invoices_reset_at) {
                $c->mass_invoices_reset_at = now()->startOfDay()->addDay();
            }
            $c->save();
        });

        Log::info("Cuenta {$cuentaId} actualizada a PRO ({$modoCobro}).");
    }

    /** Downgrade Pro → Free (por falta de pago, cancelación, etc.). */
    public function downgradeToFree(string $cuentaId): void
    {
        DB::connection('mysql_clientes')->transaction(function () use ($cuentaId) {
            $c = $this->getCuenta($cuentaId, true);
            if (!$c) return;

            $c->plan_actual = self::PLAN_FREE;
            $c->modo_cobro = null;
            $c->espacio_asignado_mb = 1024; // 1GB
            $c->max_mass_invoices_per_day = 0; // sin masivos
            $c->max_usuarios = 1;
            $c->estado_cuenta = 'activa'; // o "bloqueada" si procede
            $c->save();
        });

        Log::info("Cuenta {$cuentaId} degradada a FREE.");
    }
}
