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
     * Si no existe en ninguna, regresa $preferred (no rompe; las consultas
     * posteriores deben revisar tableExists).
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
        $plan   = strtoupper($cuenta->plan_actual ?? 'FREE');
        $isPro  = ($plan === 'PRO');

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

            // Campos opcionales para enriquecer sin tronar
            $cols = ['id','rfc','razon_social'];
            foreach (['nombre_comercial','email','regimen_fiscal','grupo','logo_path','direccion_json','certificados_json','series_json'] as $c) {
                if ($this->hasCol('emisores',$c,$cliConn)) $cols[] = $c;
            }
            $emisores = $q->get($cols)->map(function ($e) use ($cliConn) {
                // logo_url si hay logo_path
                $e->logo_url = null;
                if (property_exists($e, 'logo_path') && $e->logo_path) {
                    // Storage público
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

            // Si existen relación cliente/emisor por cliente_id => filtramos por emisores del usuario
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

            // recientes
            $cols = ['id','uuid','serie','folio','total','fecha','estatus'];
            foreach (['cliente_id','receptor_id','moneda'] as $c) {
                if ($this->hasCol('cfdis',$c,$factConn)) $cols[] = $c;
            }
            $facturasRecientes = $cfdiQ->orderByDesc('fecha')->limit(10)->get($cols);
        }

        // ====== Pagos / Estado de cuenta ======
        // Buscamos nombres de tabla comunes y tomamos lo que exista.
        $estadoCuenta = [
            'saldo'     => 0.0,
            'movimientos_recientes' => collect(),
        ];

        if ($acctConn) {
            // Movimientos (charges/credits)
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

                // saldo (último saldo o sumatoria)
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
        ]);
    }

    /* =========================================================
     |               Endpoints opcionales (Empresas)
     |=========================================================*/

    /**
     * Guardar un Emisor desde Perfil (compatible con payload de la vista y con Waretek).
     * - Soporta: rfc, email, razon_social, nombre_comercial, regimen_fiscal, grupo,
     *            direccion[cp|direccion|ciudad|estado],
     *            certificados[csd_*|fiel_*] (valores en base64),
     *            series_json (JSON string).
     * - Esquema laxo: si existen columnas JSON (direccion_json, certificados_json, series_json) las rellena.
     * - Si no, ignora/evita romper.
     */
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

            'series_json'        => 'nullable|string', // JSON de [{tipo,serie,folio}]
        ]);

        $conn = $this->pickConn('emisores','mysql_clientes');
        if (!$this->tableExists('emisores', $conn)) {
            return back()->with('err','No existe la tabla de emisores en la conexión esperada.');
        }

        $insert = [
            'rfc'              => $data['rfc'],
            'razon_social'     => $data['razon_social'],
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

        // JSON compactos si existen columnas
        if ($this->hasCol('emisores','direccion_json',$conn)) {
            $insert['direccion_json'] = json_encode($data['direccion'] ?? [], JSON_UNESCAPED_UNICODE);
        } else {
            // Si no hay json, intenta columnas sueltas comunes
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

    /**
     * Subir/actualizar logo de emisor. Guarda en storage/public/emisores/{id}.png
     * Requiere columna 'logo_path' en la tabla si se desea persistir ruta.
     */
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

        $ext = $r->file('logo')->getClientOriginalExtension();
        $path = "emisores/{$id}/logo.".$ext;
        Storage::disk('public')->put($path, file_get_contents($r->file('logo')->getRealPath()));

        if ($this->hasCol('emisores','logo_path',$conn)) {
            DB::connection($conn)->table('emisores')->where('id',$id)->update(['logo_path'=>$path]);
        }

        return back()->with('ok','Logo actualizado.');
    }

    /**
     * Importación masiva (solo PRO idealmente).
     * Acepta CSV o JSON con campos: rfc, email, razon_social, nombre_comercial,
     * regimen_fiscal, grupo, cp, direccion, ciudad, estado, csd_*, fiel_*, series_json...
     */
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
            // CSV
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

                // Direccion
                $addr = [
                    'cp'        => (string)($row['cp'] ?? ''),
                    'direccion' => (string)($row['direccion'] ?? ''),
                    'ciudad'    => (string)($row['ciudad'] ?? ''),
                    'estado'    => (string)($row['estado'] ?? ''),
                ];
                if ($this->hasCol('emisores','direccion_json',$conn)) {
                    $insert['direccion_json'] = json_encode($addr, JSON_UNESCAPED_UNICODE);
                }

                // Certificados
                $certs = [];
                foreach (['csd_cer','csd_key','csd_password','fiel_cer','fiel_key','fiel_password'] as $k) {
                    if (isset($row[$k])) $certs[$k] = (string)$row[$k];
                }
                if ($this->hasCol('emisores','certificados_json',$conn)) {
                    $insert['certificados_json'] = json_encode($certs, JSON_UNESCAPED_UNICODE);
                }

                // Series
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

    /**
     * Devuelve la primera tabla existente del arreglo dado en la conexión.
     */
    protected function firstExisting(string $conn, array $tables): ?string
    {
        foreach ($tables as $t) if ($this->tableExists($t, $conn)) return $t;
        return null;
    }

    /**
     * Adivina la conexión para movimientos/estado de cuenta/pagos.
     */
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

    /**
     * Adivina la conexión para órdenes/compras/tienda.
     */
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
}
