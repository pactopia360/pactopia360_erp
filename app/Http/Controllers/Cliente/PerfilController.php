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

    public function show(): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

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
        ]);
    }

    /* =========================================================
     |               Endpoints opcionales (Empresas)
     |=========================================================*/

    public function storeEmisor(Request $r): RedirectResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $data = $r->validate([
            'rfc'               => 'required|string|max:13',
            'email'             => 'required|email|max:190',
            'razon_social'      => 'required|string|max:190',
            'nombre_comercial'  => 'nullable|string|max:190',
            'regimen_fiscal'    => 'required|string|max:10',
            'grupo'             => 'nullable|string|max:60',

            'direccion.cp'         => 'required|string|max:10',
            'direccion.direccion'  => 'nullable|string|max:250',
            'direccion.ciudad'     => 'nullable|string|max:120',
            'direccion.estado'     => 'nullable|string|max:120',

            'certificados.csd_cer'     => 'nullable|string',
            'certificados.csd_key'     => 'nullable|string',
            'certificados.csd_password'=> 'nullable|string|max:120',
            'certificados.fiel_cer'    => 'nullable|string',
            'certificados.fiel_key'    => 'nullable|string',
            'certificados.fiel_password'=> 'nullable|string|max:120',

            'series_json'        => 'nullable|string',
        ]);

        $conn = $this->pickConn('emisores','mysql_clientes');
        if (!$this->tableExists('emisores', $conn)) {
            return back()->with('err','No existe la tabla de emisores en la conexión esperada.');
        }

        $insert = [
            'rfc'          => $data['rfc'],
            'razon_social' => $data['razon_social'],
        ];

        $map = [
            'email'            => 'email',
            'nombre_comercial' => 'nombre_comercial',
            'regimen_fiscal'   => 'regimen_fiscal',
            'grupo'            => 'grupo',
        ];
        foreach ($map as $in => $col) {
            if (isset($data[$in]) && $this->hasCol('emisores',$col,$conn)) {
                $insert[$col] = $data[$in];
            }
        }

        if ($cuenta && $this->hasCol('emisores','cuenta_id',$conn)) {
            $insert['cuenta_id'] = $cuenta->id;
        }

        if ($this->hasCol('emisores','direccion_json',$conn)) {
            $insert['direccion_json'] = json_encode($data['direccion'] ?? [], JSON_UNESCAPED_UNICODE);
        } else {
            foreach (['cp','direccion','ciudad','estado'] as $k) {
                $col = 'dir_'.$k;
                if (isset($data['direccion'][$k]) && $this->hasCol('emisores',$col,$conn)) {
                    $insert[$col] = $data['direccion'][$k];
                }
            }
        }
        if ($this->hasCol('emisores','certificados_json',$conn)) {
            $insert['certificados_json'] = json_encode($data['certificados'] ?? [], JSON_UNESCAPED_UNICODE);
        }
        if ($this->hasCol('emisores','series_json',$conn)) {
            $insert['series_json'] = $data['series_json'] ?? '[]';
        }

        $id = DB::connection($conn)->table('emisores')->insertGetId($insert);

        return redirect()->route('cliente.perfil')->with('ok', 'Emisor creado (#'.$id.')');
    }

    public function uploadEmisorLogo(Request $r, int $id): RedirectResponse
    {
        $r->validate([
            'logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $conn = $this->pickConn('emisores','mysql_clientes');
        if (!$this->tableExists('emisores',$conn)) {
            return back()->with('err','Tabla emisores no existe.');
        }

        $emisor = DB::connection($conn)->table('emisores')->where('id',$id)->first(['id']);
        if (!$emisor) return back()->with('err','Emisor no encontrado.');

        $ext  = $r->file('logo')->getClientOriginalExtension();
        $path = "emisores/{$id}/logo.".$ext;
        Storage::disk('public')->put($path, file_get_contents($r->file('logo')->getRealPath()));

        if ($this->hasCol('emisores','logo_path',$conn)) {
            DB::connection($conn)->table('emisores')->where('id',$id)->update(['logo_path'=>$path]);
        }

        return back()->with('ok','Logo actualizado.');
    }

    public function importEmisores(Request $r): RedirectResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;
        $plan   = strtoupper($cuenta->plan_actual ?? 'FREE');
        if ($plan !== 'PRO') {
            return back()->with('err','La importación masiva es para plan PRO.');
        }

        $r->validate([
            'file' => 'required|file|mimes:csv,txt,json|max:10240',
        ]);

        $conn = $this->pickConn('emisores','mysql_clientes');
        if (!$this->tableExists('emisores',$conn)) {
            return back()->with('err','No existe tabla emisores en la conexión.');
        }

        $path = $r->file('file')->getRealPath();
        $ext  = strtolower($r->file('file')->getClientOriginalExtension());

        $rows = collect();
        if ($ext === 'json') {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json)) $rows = collect($json);
        } else {
            $fh = fopen($path,'r');
            if ($fh) {
                $headers = [];
                while (($line = fgetcsv($fh, 0, ',')) !== false) {
                    if (empty($headers)) { $headers = $line; continue; }
                    $rows->push(array_combine($headers, $line));
                }
                fclose($fh);
            }
        }

        $insCount = 0;
        DB::connection($conn)->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!isset($row['rfc']) || !isset($row['razon_social'])) continue;

                $insert = [
                    'rfc'          => trim((string)$row['rfc']),
                    'razon_social' => trim((string)$row['razon_social']),
                ];
                foreach (['email','nombre_comercial','regimen_fiscal','grupo'] as $k) {
                    if (isset($row[$k]) && $this->hasCol('emisores',$k,$conn)) {
                        $insert[$k] = trim((string)$row[$k]);
                    }
                }
                if ($cuenta && $this->hasCol('emisores','cuenta_id',$conn)) {
                    $insert['cuenta_id'] = $cuenta->id;
                }

                $addr = [
                    'cp'        => (string)($row['cp'] ?? ''),
                    'direccion' => (string)($row['direccion'] ?? ''),
                    'ciudad'    => (string)($row['ciudad'] ?? ''),
                    'estado'    => (string)($row['estado'] ?? ''),
                ];
                if ($this->hasCol('emisores','direccion_json',$conn)) {
                    $insert['direccion_json'] = json_encode($addr, JSON_UNESCAPED_UNICODE);
                }

                $certs = [];
                foreach (['csd_cer','csd_key','csd_password','fiel_cer','fiel_key','fiel_password'] as $k) {
                    if (isset($row[$k])) $certs[$k] = (string)$row[$k];
                }
                if ($this->hasCol('emisores','certificados_json',$conn)) {
                    $insert['certificados_json'] = json_encode($certs, JSON_UNESCAPED_UNICODE);
                }

                if ($this->hasCol('emisores','series_json',$conn)) {
                    $insert['series_json'] = isset($row['series_json']) ? (string)$row['series_json'] : '[]';
                }

                DB::connection($conn)->table('emisores')->insert($insert);
                $insCount++;
            }
            DB::connection($conn)->commit();
        } catch (\Throwable $e) {
            DB::connection($conn)->rollBack();
            return back()->with('err','Error al importar: '.$e->getMessage());
        }

        return back()->with('ok', "Importación completada: {$insCount} emisores.");
    }

    /* =========================================================
     |                         Helpers varios
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

    /**
     * Sube y actualiza la foto de perfil del usuario.
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $user = auth('web')->user();
        if (!$user) {
            abort(403, 'Usuario no autenticado');
        }

        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->avatar_url = '/storage/'.$path;
        $user->save();

        return back()->with('ok', 'Foto de perfil actualizada correctamente.');
    }

    /* =========================================================
     |             NUEVO: actualizar contraseña y teléfono
     |=========================================================*/

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = auth('web')->user();
        if (!$user) {
            abort(403, 'Usuario no autenticado');
        }

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
        if (!$user) {
            abort(403, 'Usuario no autenticado');
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
        ]);

        $connName = $user->getConnectionName() ?: 'mysql_clientes';
        $table    = $user->getTable();

        if ($this->hasCol($table, 'telefono', $connName)) {
            $user->telefono = $data['phone'];
        }
        if ($this->hasCol($table, 'phone', $connName)) {
            $user->phone = $data['phone'];
        }

        $user->save();

        return back()->with('ok', 'Teléfono actualizado correctamente.');
    }

    public function settings()
    {
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        return view('cliente.perfil.settings', [
            'user'   => $user,
            'cuenta' => $cuenta,
        ]);
    }



}
