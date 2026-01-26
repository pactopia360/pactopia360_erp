<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use Carbon\Carbon;

class PerfilController extends Controller
{
    /* =========================================================
     |                  Utilidades de conexión/ esquema
     |=========================================================*/

    protected function tableExists(string $table, string $conn): bool
    {
        try { return Schema::connection($conn)->hasTable($table); }
        catch (\Throwable $e) { return false; }
    }

    protected function hasCol(string $table, string $col, string $conn): bool
    {
        try { return Schema::connection($conn)->hasColumn($table, $col); }
        catch (\Throwable $e) { return false; }
    }

    /**
     * Elige la conexión donde exista la tabla (prioriza $preferred).
     */
    protected function pickConn(string $table, string $preferred = 'mysql'): string
    {
        $alts = ($preferred === 'mysql') ? ['mysql', 'mysql_clientes'] : ['mysql_clientes', 'mysql'];
        foreach ($alts as $c) {
            if ($this->tableExists($table, $c)) return $c;
        }
        return $preferred;
    }

    /* =========================================================
     |                       Pantalla Perfil
     |=========================================================*/

    /**
     * IMPORTANTE:
     * Tu ruta apunta a PerfilController@index, así que index() debe existir.
     * Aquí lo hacemos alias de show() para no romper nada.
     */
    public function index(): View
    {
        return $this->show();
    }

    public function show(): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        // ======================================================
        // RFC efectivo para Perfil:
        // - primero desde cuenta (rfc_padre / rfc)
        // - si no existe, desde SAT credentials (cuenta_id o email)
        // ======================================================
        $rfcCuenta = null;
        try {
            $rfcCuenta = strtoupper(trim((string)($cuenta->rfc_padre ?? $cuenta->rfc ?? '')));
            if ($rfcCuenta === '') $rfcCuenta = null;
        } catch (\Throwable $e) {
            $rfcCuenta = null;
        }

        $rfcSat = null;
        if (!$rfcCuenta) {
            $cuentaIdInt = null;
            try { $cuentaIdInt = $cuenta?->id ? (int)$cuenta->id : null; } catch (\Throwable $e) { $cuentaIdInt = null; }
            $email = null;
            try { $email = $user?->email ? (string)$user->email : null; } catch (\Throwable $e) { $email = null; }

            $rfcSat = $this->resolveRfcFromSatCredentials($cuentaIdInt, $email);
        }

        $rfcPerfil = $rfcCuenta ?: $rfcSat; // RFC final a mostrar


        // Plan base desde cuenta clientes
        $planFromCuenta = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
        $planLower      = strtolower($planFromCuenta);
        $isProFromCuenta = in_array($planLower, ['pro','premium','empresa','business'], true)
                        || Str::startsWith($planLower, 'pro');

        $plan  = $planFromCuenta;
        $isPro = $isProFromCuenta;

        // === Resumen de cuenta desde admin (igual que en HomeController) ===
        $summary = $this->buildAccountSummaryPerfil();

        // Si admin dice que es PRO, sobreescribimos
        if (!empty($summary)) {
            $planSum = strtolower((string) ($summary['plan'] ?? ''));
            if ($planSum !== '') {
                $plan  = strtoupper($planSum);
                $isPro = (bool) ($summary['is_pro'] ?? $isPro);
            }
        }

        // ====== Conexiones probables ======
        $cliConn   = $this->pickConn('emisores', 'mysql_clientes'); // empresas/emisores
        $factConn  = $this->pickConn('cfdis', 'mysql_clientes');    // facturas
        $acctConn  = $this->guessAccountConn();                     // pagos/estado de cuenta
        $shopConn  = $this->guessShopConn();                        // compras/órdenes

        // ====== Empresas / Emisores ======
        $emisores = collect();
        if ($this->tableExists('emisores', $cliConn)) {
            $q = DB::connection($cliConn)->table('emisores')
                ->when($cuenta && $this->hasCol('emisores','cuenta_id',$cliConn),
                    fn($qq)=>$qq->where('cuenta_id', $cuenta->id))
                ->orderBy(DB::raw("COALESCE(nombre_comercial, razon_social, rfc)"));

            $cols = ['id','rfc','razon_social'];
            foreach (['nombre_comercial','email','regimen_fiscal','grupo','logo_path','direccion_json','certificados_json','series_json'] as $c) {
                if ($this->hasCol('emisores',$c,$cliConn)) $cols[] = $c;
            }
            $emisores = $q->get($cols)->map(function ($e) {
                $e->logo_url = null;
                if (property_exists($e, 'logo_path') && $e->logo_path) {
                    $e->logo_url = Storage::disk('public')->exists($e->logo_path)
                        ? Storage::url($e->logo_path)
                        : null;
                }
                return $e;
            });
        }

        // ====== Facturas / CFDIs (resumen + recientes) ======
        $facturasResumen = [
            'totales_mes'   => 0.0,
            'emitidos_mes'  => 0.0,
            'cancelados_mes'=> 0.0,
            'count_mes'     => 0,
        ];
        $facturasRecientes = collect();

        if ($this->tableExists('cfdis', $factConn)) {
            $from = now()->startOfMonth()->toDateTimeString();
            $to   = now()->endOfMonth()->toDateTimeString();

            $cfdiQ = DB::connection($factConn)->table('cfdis');

            if ($this->hasCol('cfdis','cliente_id',$factConn) && $emisores->count()) {
                $ids = $emisores->pluck('id')->all();
                $cfdiQ->whereIn('cliente_id', $ids);
            } elseif ($cuenta && $this->hasCol('cfdis','cuenta_id',$factConn)) {
                $cfdiQ->where('cuenta_id', $cuenta->id);
            }

            $baseMes = (clone $cfdiQ)->whereBetween('fecha', [$from, $to]);
            $facturasResumen['totales_mes']    = (float) ($baseMes->clone()->sum('total') ?? 0);
            $facturasResumen['emitidos_mes']   = (float) ($baseMes->clone()->where('estatus','emitido')->sum('total') ?? 0);
            $facturasResumen['cancelados_mes'] = (float) ($baseMes->clone()->where('estatus','cancelado')->sum('total') ?? 0);
            $facturasResumen['count_mes']      = (int)   ($baseMes->clone()->count() ?? 0);

            $cols = ['id','uuid','serie','folio','total','fecha','estatus'];
            foreach (['cliente_id','receptor_id','moneda'] as $c) {
                if ($this->hasCol('cfdis',$c,$factConn)) $cols[] = $c;
            }
            $facturasRecientes = $cfdiQ->orderByDesc('fecha')->limit(10)->get($cols);
        }

        // ====== Pagos / Estado de cuenta ======
        $estadoCuenta = [
            'saldo'     => 0.0,
            'movimientos_recientes' => collect(),
        ];

        if ($acctConn) {
            $movTable = $this->firstExisting($acctConn, [
                'account_movements','cuenta_movimientos','movimientos_cuenta'
            ]);
            if ($movTable) {
                $q = DB::connection($acctConn)->table($movTable)
                    ->when($cuenta && $this->hasCol($movTable,'cuenta_id',$acctConn),
                        fn($qq)=>$qq->where('cuenta_id',$cuenta->id))
                    ->orderByDesc('fecha');

                $cols = ['id'];
                foreach (['fecha','concepto','tipo','monto','saldo'] as $c) {
                    if ($this->hasCol($movTable,$c,$acctConn)) $cols[] = $c;
                }
                $estadoCuenta['movimientos_recientes'] = $q->limit(10)->get($cols);

                if ($this->hasCol($movTable,'saldo',$acctConn)) {
                    $ultimo = (clone $q)->first($cols);
                    $estadoCuenta['saldo'] = (float) ($ultimo->saldo ?? 0);
                } elseif ($this->hasCol($movTable,'monto',$acctConn) && $this->hasCol($movTable,'tipo',$acctConn)) {
                    $sum = DB::connection($acctConn)->table($movTable)
                        ->when($cuenta && $this->hasCol($movTable,'cuenta_id',$acctConn),
                            fn($qq)=>$qq->where('cuenta_id',$cuenta->id))
                        ->selectRaw("
                            SUM(CASE WHEN tipo IN ('cargo','charge')  THEN monto ELSE 0 END)
                          - SUM(CASE WHEN tipo IN ('abono','credit') THEN monto ELSE 0 END) AS saldo
                        ")->value('saldo');
                    $estadoCuenta['saldo'] = round((float) $sum, 2);
                }
            }
        }

        // ====== Compras (órdenes) ======
        $compras = collect();
        if ($shopConn) {
            $ordersTable = $this->firstExisting($shopConn, [
                'orders', 'billing_orders', 'compras', 'ordenes'
            ]);
            if ($ordersTable) {
                $q = DB::connection($shopConn)->table($ordersTable)
                    ->when($cuenta && $this->hasCol($ordersTable,'cuenta_id',$shopConn),
                        fn($qq)=>$qq->where('cuenta_id',$cuenta->id))
                    ->orderByDesc('created_at');

                $cols = ['id'];
                foreach (['created_at','status','total','moneda','descripcion','folio'] as $c) {
                    if ($this->hasCol($ordersTable,$c,$shopConn)) $cols[] = $c;
                }
                $compras = $q->limit(10)->get($cols);
            }
        }

        // ====== KPIs rápidos para header Perfil ======
        $kpis = [
            'plan'                 => $plan,
            'timbres_disponibles'  => (int) ($cuenta->timbres_disponibles ?? 0),
            'emisores'             => $emisores->count(),
            'facturas_mes'         => $facturasResumen['count_mes'],
            'monto_mes'            => round($facturasResumen['totales_mes'], 2),
            'saldo'                => round($estadoCuenta['saldo'], 2),
        ];

        return view('cliente.perfil', [
            'user'               => $user,
            'cuenta'             => $cuenta,
            'plan'               => $plan,
            'isPro'              => $isPro,

            // Empresas
            'emisores'           => $emisores,

            // Facturas
            'facturasResumen'    => $facturasResumen,
            'facturasRecientes'  => $facturasRecientes,

            // Estado de cuenta / pagos
            'estadoCuenta'       => $estadoCuenta,

            // Compras
            'compras'            => $compras,

            // KPIs
            'kpis'               => $kpis,

            // Resumen (para header / layout, igual que en Home)
            'summary'            => $summary,

            // RFC a mostrar en Perfil (cuenta SOT + fallback SAT)
            'rfcPerfil'          => $rfcPerfil,
            'rfcPerfilSource'    => $rfcCuenta ? 'cuenta' : ($rfcSat ? 'sat_credentials' : null),
        ]);

    }

    /* =========================================================
     |                       Helpers varios
     |=========================================================*/

    protected function firstExisting(string $conn, array $tables): ?string
    {
        foreach ($tables as $t) if ($this->tableExists($t, $conn)) return $t;
        return null;
    }

    protected function guessAccountConn(): ?string
    {
        foreach (['mysql', 'mysql_clientes'] as $conn) {
            if ($this->tableExists('account_movements', $conn)
             || $this->tableExists('cuenta_movimientos', $conn)
             || $this->tableExists('movimientos_cuenta', $conn)) {
                return $conn;
            }
        }
        return null;
    }

    protected function guessShopConn(): ?string
    {
        foreach (['mysql', 'mysql_clientes'] as $conn) {
            if ($this->tableExists('orders', $conn)
             || $this->tableExists('billing_orders', $conn)
             || $this->tableExists('compras', $conn)
             || $this->tableExists('ordenes', $conn)) {
                return $conn;
            }
        }
        return null;
    }

        /**
     * RFC efectivo desde SAT credentials (fallback cuando cuenta no tiene rfc).
     * Prioridad:
     *  - por cuenta_id (si existe)
     *  - por email (si existe)
     */
    protected function resolveRfcFromSatCredentials(?int $cuentaId, ?string $email): ?string
    {
        try {
            $modelClass = \App\Models\Cliente\SatCredential::class;
            if (!class_exists($modelClass)) return null;

            $m = new $modelClass();
            $table = method_exists($m, 'getTable') ? $m->getTable() : 'sat_credentials';
            $conn  = method_exists($m, 'getConnectionName') && $m->getConnectionName()
                ? $m->getConnectionName()
                : 'mysql_clientes';

            // Si por alguna razón el modelo usa otra conexión y no existe, fallback
            if (!$this->tableExists($table, $conn)) {
                $conn = $this->pickConn($table, 'mysql_clientes');
                if (!$this->tableExists($table, $conn)) return null;
            }

            $q = DB::connection($conn)->table($table);

            // Columnas esperadas (con tolerancia)
            $hasCuenta = $this->hasCol($table, 'cuenta_id', $conn);
            $hasRfc    = $this->hasCol($table, 'rfc', $conn);

            // meta puede existir o no; aquí no lo necesitamos
            if (!$hasRfc) return null;

            // 1) por cuenta_id
            if ($cuentaId && $hasCuenta) {
                $row = (clone $q)
                    ->where('cuenta_id', $cuentaId)
                    ->orderByDesc('id')
                    ->first(['rfc']);

                $rfc = strtoupper(trim((string)($row->rfc ?? '')));
                if ($rfc !== '') return $rfc;
            }

            // 2) fallback por email dentro de meta JSON (si existe) NO lo asumimos
            //    Como no sabemos si existe columna email, hacemos fallback SOLO si existe columna meta y es JSON string.
            $hasMeta = $this->hasCol($table, 'meta', $conn);
            if ($email && $hasMeta) {
                // Búsqueda conservadora: LIKE del email en meta (evita JSON_EXTRACT incompatible)
                $row = (clone $q)
                    ->where('meta', 'like', '%' . $email . '%')
                    ->orderByDesc('id')
                    ->first(['rfc']);

                $rfc = strtoupper(trim((string)($row->rfc ?? '')));
                if ($rfc !== '') return $rfc;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * Igual que buildAccountSummary() de HomeController
     * pero local a PerfilController.
     */
    protected function buildAccountSummaryPerfil(): array
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

        if (!$adminId && $rfc && $this->tableExists('accounts', $admConn)) {
            $acc = DB::connection($admConn)->table('accounts')->select('id')->where('rfc', strtoupper($rfc))->first();
            if ($acc) $adminId = (int) $acc->id;
        }

        $acc = null;
        if ($adminId && $this->tableExists('accounts', $admConn)) {
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
                if ($this->hasCol('accounts', $c, $admConn)) {
                    $cols[] = $c;
                }
            }
            $acc = DB::connection($admConn)->table('accounts')->select($cols)->where('id', $adminId)->first();
        }

        $balance = $saldoMx;
        if ($this->tableExists('estados_cuenta', $admConn)) {
            $linkCol = null;
            $linkVal = null;

            if ($this->hasCol('estados_cuenta', 'account_id', $admConn) && $adminId) {
                $linkCol = 'account_id';
                $linkVal = $adminId;
            } elseif ($this->hasCol('estados_cuenta', 'cuenta_id', $admConn) && $adminId) {
                $linkCol = 'cuenta_id';
                $linkVal = $adminId;
            } elseif ($this->hasCol('estados_cuenta', 'rfc', $admConn) && $rfc) {
                $linkCol = 'rfc';
                $linkVal = strtoupper($rfc);
            }

            if ($linkCol !== null) {
                $orderCol = $this->hasCol('estados_cuenta', 'periodo', $admConn)
                    ? 'periodo'
                    : ($this->hasCol('estados_cuenta', 'created_at', $admConn) ? 'created_at' : 'id');

                $last = DB::connection($admConn)->table('estados_cuenta')
                    ->where($linkCol, $linkVal)
                    ->orderByDesc($orderCol)
                    ->first();

                if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                    $balance = (float) $last->saldo;
                } else {
                    $hasCargo = $this->hasCol('estados_cuenta', 'cargo', $admConn);
                    $hasAbono = $this->hasCol('estados_cuenta', 'abono', $admConn);

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

    /* =========================================================
     |             NUEVO: actualizar contraseña y teléfono
     |=========================================================*/

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = auth('web')->user();
        if (!$user) abort(403, 'Usuario no autenticado');

        $data = $request->validate([
            'current_password'      => ['required', 'string', 'min:6'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ]);

        $loginCtrl = app(\App\Http\Controllers\Cliente\Auth\LoginController::class);

        $normCurrent = method_exists($loginCtrl, 'normalizePassword')
            ? $loginCtrl->normalizePassword($data['current_password'])
            : trim($data['current_password']);

        $normNew = method_exists($loginCtrl, 'normalizePassword')
            ? $loginCtrl->normalizePassword($data['password'])
            : trim($data['password']);

        if (!Hash::check($normCurrent, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'La contraseña actual no es correcta.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user->password = Hash::make($normNew);

        $connName = $user->getConnectionName() ?: 'mysql_clientes';
        $table    = $user->getTable();

        if ($this->hasCol($table, 'must_change_password', $connName)) {
            $user->must_change_password = 0;
        }
        if ($this->hasCol($table, 'password_temp', $connName)) {
            $user->password_temp = null;
        }

        $user->save();

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }

    public function updatePhone(Request $request): RedirectResponse
    {
        $user = auth('web')->user();
        if (!$user) abort(403, 'Usuario no autenticado');

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
        ]);

        $connName = $user->getConnectionName() ?: 'mysql_clientes';
        $table    = $user->getTable();

        if ($this->hasCol($table, 'telefono', $connName)) $user->telefono = $data['phone'];
        if ($this->hasCol($table, 'phone', $connName))    $user->phone    = $data['phone'];

        $user->save();

        return back()->with('ok', 'Teléfono actualizado correctamente.');
    }

    public function settings(): View
    {
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        return view('cliente.perfil.settings', [
            'user'   => $user,
            'cuenta' => $cuenta,
        ]);
    }
}
