<?php

namespace App\Http\Controllers\Cliente;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class EstadoCuentaController extends Controller
{
    public function index()
    {
        $user = Auth::guard('web')->user();
        abort_if(!$user, 403);

        $cliConn = 'mysql_clientes';
        $admConn = 'mysql_admin';

        // ===== Cuenta espejo (mysql_clientes.cuentas_cliente)
        $cuentaId = (string) ($user->cuenta_id ?? '');
        $cuenta   = null;
        $rfc      = null;
        $adminAccountId = null;

        if ($cuentaId !== '' && Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
            $select = ['id', 'rfc_padre'];
            foreach (['estado_cuenta','is_blocked','admin_account_id','plan_actual','modo_cobro','razon_social'] as $c) {
                if (Schema::connection($cliConn)->hasColumn('cuentas_cliente', $c)) $select[] = $c;
            }

            $cuenta = DB::connection($cliConn)->table('cuentas_cliente')
                ->where('id', $cuentaId)->select($select)->first();

            if ($cuenta) {
                $rfc = $cuenta->rfc_padre ?? null;
                if (isset($cuenta->admin_account_id)) {
                    $adminAccountId = (int) $cuenta->admin_account_id;
                }
            }
        }

        // ===== Cuenta admin (mysql_admin.accounts)
        $account = null;
        if (Schema::connection($admConn)->hasTable('accounts')) {
            $emailCol = $this->colAdminEmail();
            $accSelect = ['id', DB::raw("$emailCol as email")];
            foreach (['plan','billing_cycle','next_invoice_date','email_verified_at','phone_verified_at','is_blocked','estado_cuenta','razon_social'] as $c) {
                if (Schema::connection($admConn)->hasColumn('accounts', $c)) $accSelect[] = $c;
            }

            if ($adminAccountId) {
                $account = DB::connection($admConn)->table('accounts')
                    ->where('id', $adminAccountId)->select($accSelect)->first();
            }

            if (!$account && $rfc) {
                // Buscar por RFC en distintas columnas
                foreach (['rfc','rfc_padre','tax_id'] as $rc) {
                    if (Schema::connection($admConn)->hasColumn('accounts', $rc)) {
                        $acc = DB::connection($admConn)->table('accounts')->select($accSelect)->where($rc, $rfc)->first();
                        if ($acc) { $account = $acc; $adminAccountId = (int) $acc->id; break; }
                    }
                }
                // Fallback: algunos esquemas usan el RFC como id
                if (!$account) {
                    $acc = DB::connection($admConn)->table('accounts')->select($accSelect)->where('id', $rfc)->first();
                    if ($acc) { $account = $acc; $adminAccountId = (int) $acc->id; }
                }
            }
        }

        // ===== Movimientos (mysql_admin.estados_cuenta)
        $movs = collect();
        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $cols = $this->existingColumns($admConn, 'estados_cuenta', [
                'id','periodo','concepto','detalle','cargo','abono','saldo','moneda','created_at','updated_at',
                'account_id','cuenta_id','rfc'
            ]);

            $orderCol = $this->firstExisting($admConn, 'estados_cuenta', ['periodo','created_at','id']);

            $q = DB::connection($admConn)->table('estados_cuenta')->select($cols)->orderByDesc($orderCol)->limit(120);

            // Vinculación flexible
            if ($adminAccountId && Schema::connection($admConn)->hasColumn('estados_cuenta', 'account_id')) {
                $q->where('account_id', $adminAccountId);
            } elseif ($adminAccountId && Schema::connection($admConn)->hasColumn('estados_cuenta', 'cuenta_id')) {
                $q->where('cuenta_id', $adminAccountId);
            } elseif ($rfc && Schema::connection($admConn)->hasColumn('estados_cuenta', 'rfc')) {
                $q->where('rfc', $rfc);
            } else {
                // Sin forma clara de enlazar: evita traer datos ajenos
                $q->whereRaw('1=0');
            }

            $movs = $q->get();
        }

        // ===== Balance calculado
        $balance = null;
        if ($movs->count()) {
            if (isset($movs[0]->saldo)) {
                $balance = (float) $movs[0]->saldo;
            } else {
                $cargo = $movs->sum(fn($r) => (float) ($r->cargo ?? 0));
                $abono = $movs->sum(fn($r) => (float) ($r->abono ?? 0));
                $balance = $cargo - $abono;
            }
        }

        // ===== Resumen de cuenta para la vista
        $estadoBloqueado = false;
        $estadoTexto     = null;

        if ($cuenta && isset($cuenta->estado_cuenta)) {
            $estadoTexto = (string) $cuenta->estado_cuenta;
            $estadoBloqueado = in_array(Str::lower($estadoTexto), ['bloqueada','bloqueada_pago','suspendida','inactiva','pendiente_pago'], true);
        } elseif ($account && isset($account->estado_cuenta)) {
            $estadoTexto = (string) $account->estado_cuenta;
        }

        if (!$estadoBloqueado && $cuenta && isset($cuenta->is_blocked)) {
            $estadoBloqueado = (int) $cuenta->is_blocked === 1;
        }
        if (!$estadoBloqueado && $account && isset($account->is_blocked)) {
            $estadoBloqueado = (int) $account->is_blocked === 1;
        }

        $accountInfo = [
            'email'           => $account->email ?? null,
            'email_verified'  => isset($account->email_verified_at) && !empty($account->email_verified_at),
            'phone_verified'  => isset($account->phone_verified_at) && !empty($account->phone_verified_at),
            'plan'            => $account->plan         ?? ($cuenta->plan_actual ?? null),
            'billing_cycle'   => $account->billing_cycle?? ($cuenta->modo_cobro ?? null),
            'next_invoice_at' => $account->next_invoice_date ?? null,
            'estado_cuenta'   => $estadoTexto,
            'is_blocked'      => $estadoBloqueado,
            'admin_account_id'=> $adminAccountId,
            'rfc'             => $rfc,
            'razon_social'    => $account->razon_social ?? ($cuenta->razon_social ?? null),
        ];

        // === NUEVO: resumen tipo Home/Perfil para que el layout pinte PRO correctamente ===
        $summary = $this->buildAccountSummary();

        return view('cliente.estado_cuenta', [
            'movs'     => $movs,
            'balance'  => $balance,
            'account'  => $accountInfo,
            'cuenta'   => $cuenta,
            'summary'  => $summary,
        ]);
    }

    // ===== Helpers =====

    private function colAdminEmail(): string
    {
        try {
            if (Schema::connection('mysql_admin')->hasColumn('accounts','correo_contacto')) return 'correo_contacto';
            if (Schema::connection('mysql_admin')->hasColumn('accounts','email')) return 'email';
        } catch (Throwable $e) {}
        return 'email';
    }

    private function existingColumns(string $conn, string $table, array $wanted): array
    {
        $out = [];
        foreach ($wanted as $c) {
            try {
                if (Schema::connection($conn)->hasColumn($table, $c)) $out[] = $c;
            } catch (Throwable $e) {}
        }
        return $out ?: ['id'];
    }

    private function firstExisting(string $conn, string $table, array $cands): string
    {
        foreach ($cands as $c) {
            try {
                if (Schema::connection($conn)->hasColumn($table, $c)) return $c;
            } catch (Throwable $e) {}
        }
        return 'id';
    }

    /**
     * Resumen de cuenta igualado a la lógica de Home/Perfil
     * para que el header sepa si la cuenta es PRO.
     */
    private function buildAccountSummary(): array
    {
        $u      = Auth::guard('web')->user();
        $cuenta = $u?->cuenta;

        if (!$cuenta) {
            return [
                'razon'        => (string) ($u->nombre ?? $u->email ?? '—'),
                'plan'         => 'free',
                'is_pro'       => false,
                'cycle'        => 'mensual',
                'next_invoice' => null,
                'estado'       => null,
                'blocked'      => false,
                'balance'      => 0.0,
                'space_total'  => 512.0,
                'space_used'   => 0.0,
                'space_pct'    => 0.0,
                'timbres'      => 0,
                'admin_id'     => null,
            ];
        }

        $admConn = 'mysql_admin';

        $planKey = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($planKey === 'FREE' ? 10 : 0));
        $saldoMx = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ($u->nombre ?? $u->email ?? '—');

        $adminId = $cuenta->admin_account_id ?? null;
        $rfc     = $cuenta->rfc_padre ?? null;

        if (!$adminId && $rfc && Schema::connection($admConn)->hasTable('accounts')) {
            $acc = DB::connection($admConn)->table('accounts')->select('id')->where('rfc', strtoupper($rfc))->first();
            if ($acc) $adminId = (int) $acc->id;
        }

        $acc = null;
        if ($adminId && Schema::connection($admConn)->hasTable('accounts')) {
            $cols = ['id'];
            foreach ([
                'plan',
                'billing_cycle',
                'next_invoice_date',
                'estado_cuenta',
                'is_blocked',
                'razon_social',
                'email',
                'email_verified_at',
                'phone_verified_at',
            ] as $c) {
                if (Schema::connection($admConn)->hasColumn('accounts', $c)) {
                    $cols[] = $c;
                }
            }
            $acc = DB::connection($admConn)->table('accounts')->select($cols)->where('id', $adminId)->first();
        }

        $balance = $saldoMx;
        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $linkCol = null;
            $linkVal = null;

            if (Schema::connection($admConn)->hasColumn('estados_cuenta', 'account_id') && $adminId) {
                $linkCol = 'account_id';
                $linkVal = $adminId;
            } elseif (Schema::connection($admConn)->hasColumn('estados_cuenta', 'cuenta_id') && $adminId) {
                $linkCol = 'cuenta_id';
                $linkVal = $adminId;
            } elseif (Schema::connection($admConn)->hasColumn('estados_cuenta', 'rfc') && $rfc) {
                $linkCol = 'rfc';
                $linkVal = strtoupper($rfc);
            }

            if ($linkCol !== null) {
                $orderCol = Schema::connection($admConn)->hasColumn('estados_cuenta', 'periodo')
                    ? 'periodo'
                    : (Schema::connection($admConn)->hasColumn('estados_cuenta', 'created_at') ? 'created_at' : 'id');

                $last = DB::connection($admConn)->table('estados_cuenta')
                    ->where($linkCol, $linkVal)
                    ->orderByDesc($orderCol)
                    ->first();

                if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                    $balance = (float) $last->saldo;
                } else {
                    $hasCargo = Schema::connection($admConn)->hasColumn('estados_cuenta', 'cargo');
                    $hasAbono = Schema::connection($admConn)->hasColumn('estados_cuenta', 'abono');

                    if ($hasCargo || $hasAbono) {
                        $cargo = $hasCargo
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol, $linkVal)->sum('cargo')
                            : 0.0;
                        $abono = $hasAbono
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol, $linkVal)->sum('abono')
                            : 0.0;
                        $balance = $cargo - $abono;
                    }
                }
            }
        }

        $spaceTotal = (float) ($cuenta->espacio_total_mb ?? 512);
        $spaceUsed  = (float) ($cuenta->espacio_usado_mb ?? 0);
        $spacePct   = $spaceTotal > 0 ? min(100, round(($spaceUsed / $spaceTotal) * 100, 1)) : 0;

        $plan   = strtolower((string) ($acc->plan ?? $planKey));
        $cycle  = $acc->billing_cycle ?? ($cuenta->modo_cobro ?? 'mensual');
        $estado = $acc->estado_cuenta ?? ($cuenta->estado_cuenta ?? null);
        $blocked = (bool) (($acc->is_blocked ?? 0) || ($cuenta->is_blocked ?? 0));

        return [
            'razon'        => (string) ($acc->razon_social ?? $razon),
            'plan'         => $plan,
            'is_pro'       => in_array($plan, ['pro','premium','empresa','business'], true) || Str::startsWith($plan, 'pro'),
            'cycle'        => $cycle,
            'next_invoice' => $acc->next_invoice_date ?? null,
            'estado'       => $estado,
            'blocked'      => $blocked,
            'balance'      => $balance,
            'space_total'  => $spaceTotal,
            'space_used'   => $spaceUsed,
            'space_pct'    => $spacePct,
            'timbres'      => $timbres,
            'admin_id'     => $adminId,
        ];
    }
}
