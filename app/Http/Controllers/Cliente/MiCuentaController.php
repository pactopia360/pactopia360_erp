<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Support\ClientAuth;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MiCuentaController extends Controller
{
    public function index(Request $request): View
    {
        // Refleja módulos desde sesión (cargados por ClientSessionConfig). Si no existieran, intenta SOT.
        $mods = session('p360.modules', []);
        if (!is_array($mods) || empty($mods)) {
            $mods = $this->loadModulesFromSotForCurrentUser();
            session(['p360.modules' => $mods, 'p360.modules_ts' => time()]);
        }

        return view('cliente.mi_cuenta.index', [
            'modules' => $mods,
        ]);
    }

    /**
     * API (JSON) - Historial de pagos/compras del cliente.
     * Ruta: cliente.mi_cuenta.pagos (GET)
     *
     * ✅ FIX SOT:
     * - Los pagos viven en mysql_admin (SOT billing), no en mysql_clientes.
     * - account_id para payments normalmente es admin_account_id.
     * - IMPORTANTÍSIMO: account_id puede ser STRING (UUID/ULID). NO castear a int.
     */
    public function pagos(Request $request)
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) {
            abort(403, 'No se encontró la cuenta del usuario.');
        }

        // ✅ Siempre SOT billing (admin)
        $admConn = (string) (config('p360.conn.admin') ?: (env('P360_BILLING_SOT_CONN') ?: 'mysql_admin'));

        // ✅ Resuelve el account_id correcto (admin_account_id) — STRING
        [$adminAccountId, $src] = $this->resolveAdminAccountId($request, $user, $cuenta, $admConn);

        $userId = $user->id ?? null;

        $limit = (int) $request->integer('limit', 200);
        if ($limit < 1) $limit = 200;
        if ($limit > 500) $limit = 500;

        Log::info('MiCuenta.pagos: start', [
            'user_id'          => $userId,
            'admin_account_id' => $adminAccountId,
            'src'              => $src,
            'conn'             => $admConn,
            'limit'            => $limit,
        ]);

        if (!$adminAccountId) {
            Log::warning('MiCuenta.pagos: admin_account_id unresolved', [
                'user_id'   => $userId,
                'src'       => $src,
                'session'   => [
                    'client.admin_account_id' => $request->session()->get('client.admin_account_id'),
                    'admin_account_id'        => $request->session()->get('admin_account_id'),
                    'client.account_id'       => $request->session()->get('client.account_id'),
                    'account_id'              => $request->session()->get('account_id'),
                    'client.cuenta_id'        => $request->session()->get('client.cuenta_id'),
                    'cuenta_id'               => $request->session()->get('cuenta_id'),
                    'client_account_id'       => $request->session()->get('client_account_id'),
                ],
            ]);

            return response()->json([
                'ok'   => true,
                'rows' => [],
            ]);
        }

        try {
            // 1) Preferencia: tabla payments (SOT)
            $rows = $this->loadPaymentsFromAdminSot($admConn, (string)$adminAccountId, $limit);

            // 2) Fallback: detector (en mysql_admin)
            if (empty($rows)) {
                $rows = $this->detectAndLoadPayments($admConn, (string)$adminAccountId, $userId, $limit);
            }

            Log::info('MiCuenta.pagos: done', [
                'user_id' => $userId,
                'rows'    => count($rows),
            ]);

            return response()->json([
                'ok'   => true,
                'rows' => $rows,
            ]);
        } catch (Throwable $e) {
            Log::error('MiCuenta.pagos: exception', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'error' => 'No se pudo cargar el historial. Revisa el log.',
                'rows'  => [],
            ], 500);
        }
    }

    /**
     * ✅ Carga pagos desde SOT mysql_admin.payments (si existe).
     * Normaliza amount cents -> MXN cuando aplica.
     *
     * IMPORTANTÍSIMO: account_id puede ser STRING.
     */
    private function loadPaymentsFromAdminSot(string $conn, string $adminAccountId, int $limit = 200): array
    {
        if (!Schema::connection($conn)->hasTable('payments')) {
            return [];
        }

        $cols = Schema::connection($conn)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id')) {
            return [];
        }

        $amountCol   = $has('amount') ? 'amount' : null; // normalmente cents (Stripe)
        $currencyCol = $has('currency') ? 'currency' : null;
        $statusCol   = $has('status') ? 'status' : null;
        $periodCol   = $has('period') ? 'period' : null;

        $dateCol = $has('paid_at') ? 'paid_at'
            : ($has('confirmed_at') ? 'confirmed_at'
            : ($has('captured_at') ? 'captured_at'
            : ($has('completed_at') ? 'completed_at'
            : ($has('updated_at') ? 'updated_at'
            : ($has('created_at') ? 'created_at' : null)))));

        $orderCol = $dateCol ?: ($has('id') ? 'id' : null);

        $q = DB::connection($conn)->table('payments')->where('account_id', $adminAccountId);
        if ($orderCol) $q->orderByDesc($orderCol);

        $rows = $q->limit($limit)->get()->map(function ($r) use ($amountCol, $currencyCol, $statusCol, $periodCol, $dateCol) {
            $arr = (array)$r;

            $id = $arr['id'] ?? ($arr['payment_id'] ?? null);

            $date = $dateCol ? ($arr[$dateCol] ?? '') : ($arr['created_at'] ?? '');
            $ts = 0;
            if (!empty($date)) {
                try { $ts = (int) strtotime((string)$date); } catch (Throwable $e) { $ts = 0; }
            }

            $currency = $currencyCol ? strtoupper((string)($arr[$currencyCol] ?? 'MXN')) : 'MXN';

            $amount = '';
            if ($amountCol && isset($arr[$amountCol]) && $arr[$amountCol] !== null && $arr[$amountCol] !== '') {
                // Heurística segura:
                // - Si es numérico entero grande -> cents
                // - Si ya viene decimal (string con punto) lo respetamos
                $raw = $arr[$amountCol];

                if (is_numeric($raw)) {
                    $rawStr = (string)$raw;
                    $isDecimal = str_contains($rawStr, '.') || str_contains($rawStr, ',');
                    if ($isDecimal) {
                        $amount = (string) str_replace(',', '.', $rawStr);
                    } else {
                        $cents = (int)$raw;
                        $amount = number_format($cents / 100, 2, '.', '');
                    }
                } else {
                    $amount = (string)$raw;
                }
            }

            $status   = $statusCol ? strtoupper((string)($arr[$statusCol] ?? '—')) : '—';
            $period   = $periodCol ? (string)($arr[$periodCol] ?? '') : '';

            $concept  = (string)($arr['concept'] ?? ($arr['description'] ?? 'Pago'));
            $method   = strtoupper((string)($arr['method'] ?? ($arr['provider'] ?? '—')));
            $ref      = (string)($arr['reference'] ?? ($arr['stripe_session_id'] ?? ($arr['payment_intent'] ?? '')));

            $invoiceUrl = (string)($arr['invoice_url'] ?? '');
            $receiptUrl = (string)($arr['receipt_url'] ?? '');

            return [
                'id'        => (string)($id ?? ''),
                'date'      => (string)($date ?? ''),
                'ts'        => $ts,
                'concept'   => $concept !== '' ? $concept : 'Pago',
                'period'    => $period,
                'amount'    => $amount,
                'currency'  => $currency,
                'method'    => $method,
                'status'    => $status,
                'reference' => $ref,
                'invoice'   => $invoiceUrl,
                'receipt'   => $receiptUrl,
                'source'    => 'payments',
            ];
        })->filter(fn ($x) => !empty($x['id']) || !empty($x['reference']) || !empty($x['amount']))
          ->values()->all();

        Log::info('MiCuenta.pagos: payments_sot_loaded', [
            'conn'       => $conn,
            'table'      => 'payments',
            'count'      => count($rows),
            'account_id' => $adminAccountId,
        ]);

        return $rows;
    }

    /**
     * ✅ Resuelve admin_account_id de manera robusta (STRING):
     * 1) cuenta->admin_account_id
     * 2) user->admin_account_id
     * 3) session: client.admin_account_id / admin_account_id / client.account_id / account_id
     * 4) meta en SOT accounts (mysql_admin.accounts.meta)
     */
    private function resolveAdminAccountId(Request $request, $user, $cuenta, string $admConn): array
    {
        // 1) Cuenta (mejor opción)
        if (!empty($cuenta->admin_account_id)) {
            return [(string)$cuenta->admin_account_id, 'cuenta.admin_account_id'];
        }

        // 2) Usuario
        if (!empty($user->admin_account_id)) {
            return [(string)$user->admin_account_id, 'user.admin_account_id'];
        }

        // 3) Sesión (varios keys posibles)
        $sid =
            $request->session()->get('client.admin_account_id')
            ?? $request->session()->get('admin_account_id')
            ?? $request->session()->get('client.account_id')
            ?? $request->session()->get('account_id');

        if (!empty($sid)) {
            return [(string)$sid, 'session.account_id'];
        }

        // 4) Buscar en SOT accounts.meta usando el id local de cuenta (clientes) como pista
        $localCuentaId = (string)($cuenta->id ?? '');
        if ($localCuentaId !== '') {
            try {
                if (Schema::connection($admConn)->hasTable('accounts')) {
                    $cols = Schema::connection($admConn)->getColumnListing('accounts');
                    $lc   = array_map('strtolower', $cols);
                    if (!in_array('meta', $lc, true)) {
                        return [null, 'adm.accounts.no_meta'];
                    }

                    $rows = DB::connection($admConn)->table('accounts')
                        ->select(['id', 'meta'])
                        ->limit(1000)
                        ->get();

                    foreach ($rows as $r) {
                        $raw = $r->meta ?? null;
                        $meta = [];
                        try {
                            if (is_string($raw) && $raw !== '') $meta = json_decode($raw, true) ?: [];
                            elseif (is_array($raw)) $meta = $raw;
                            elseif (is_object($raw)) $meta = (array)$raw;
                        } catch (Throwable $e) { $meta = []; }

                        $clientId = (string)(data_get($meta, 'client_cuenta_id') ?? data_get($meta, 'cuenta_id') ?? '');
                        if ($clientId !== '' && $clientId === $localCuentaId) {
                            return [(string)($r->id ?? ''), 'adm.accounts.meta.mapping'];
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('MiCuenta.resolveAdminAccountId: meta probe failed', [
                    'err' => $e->getMessage(),
                ]);
            }
        }

        return [null, 'unresolved'];
    }

    /**
     * Detecta tablas comunes de pagos/compras y normaliza salida.
     * No rompe si no existen: regresa [].
     *
     * IMPORTANTÍSIMO: $accountId puede ser STRING.
     */
    private function detectAndLoadPayments(string $conn, $accountId, $userId, int $limit = 200): array
    {
        $tables = [
            'billing_payments',
            'payments',
            'pagos',
            'orders',
            'compras',
            'transactions',
            'billing_transactions',
            'payment_transactions',
            'stripe_payments',
            'paypal_payments',
        ];

        $foundTables = [];
        $all = [];

        foreach ($tables as $table) {
            if (!Schema::connection($conn)->hasTable($table)) {
                continue;
            }

            $hasAmount = $this->hasAnyColumn($conn, $table, ['amount', 'amount_mxn', 'monto', 'total', 'importe']);
            $hasDate   = $this->hasAnyColumn($conn, $table, ['paid_at', 'created_at', 'fecha', 'date']);
            if (!$hasAmount && !$hasDate) {
                continue;
            }

            $foundTables[] = $table;

            $q = DB::connection($conn)->table($table);

            $filtersApplied = [];

            $accountCols = ['account_id','cuenta_id','cliente_id','tenant_id','customer_id'];
            $userCols    = ['user_id','usuario_id','created_by','customer_user_id'];

            $didFilter = false;

            foreach ($accountCols as $col) {
                if ($accountId !== null && Schema::connection($conn)->hasColumn($table, $col)) {
                    $q->where($col, (string)$accountId);
                    $filtersApplied[] = $col;
                    $didFilter = true;
                    break;
                }
            }

            if (!$didFilter) {
                foreach ($userCols as $col) {
                    if ($userId !== null && Schema::connection($conn)->hasColumn($table, $col)) {
                        $q->where($col, $userId);
                        $filtersApplied[] = $col;
                        $didFilter = true;
                        break;
                    }
                }
            }

            $orderCol = $this->firstExistingColumn($conn, $table, ['paid_at','fecha','date','created_at','updated_at','id']);
            if ($orderCol) {
                $q->orderByDesc($orderCol);
            }

            $rows = $q->limit($limit)->get()->map(function ($r) use ($conn, $table) {
                return $this->normalizePaymentRow($conn, $table, (array) $r);
            })->filter(fn ($r) => !empty($r['id']) || !empty($r['reference']) || !empty($r['amount'])
            )->values()->all();

            Log::info('MiCuenta.pagos: table_loaded', [
                'conn'   => $conn,
                'table'  => $table,
                'count'  => count($rows),
                'filter' => $filtersApplied,
            ]);

            $all = array_merge($all, $rows);

            if (count($all) >= $limit) {
                break;
            }
        }

        if (empty($foundTables)) {
            Log::info('MiCuenta.pagos: no_tables_found', [
                'conn' => $conn,
                'checked' => $tables,
            ]);
        }

        usort($all, function ($a, $b) {
            $ta = isset($a['ts']) ? (int)$a['ts'] : 0;
            $tb = isset($b['ts']) ? (int)$b['ts'] : 0;
            return $tb <=> $ta;
        });

        $seen = [];
        $out = [];
        foreach ($all as $row) {
            $key = ($row['source'] ?? '').'|'.($row['reference'] ?? '').'|'.($row['amount'] ?? '').'|'.($row['ts'] ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $row;
            if (count($out) >= $limit) break;
        }

        return $out;
    }

    private function normalizePaymentRow(string $conn, string $table, array $r): array
    {
        $id = $this->firstNonEmpty($r, ['id','payment_id','pago_id','order_id','transaction_id','uuid','ulid']);

        $date = $this->firstNonEmpty($r, ['paid_at','fecha','date','created_at','updated_at']);
        $ts = 0;
        if (!empty($date)) {
            try { $ts = (int) strtotime((string)$date); } catch (Throwable $e) { $ts = 0; }
        }

        $concept = $this->firstNonEmpty($r, ['concept','concepto','description','descripcion','item','producto','type','tipo']);
        if ($concept === '' || $concept === null) {
            $maybe = $this->firstNonEmpty($r, ['plan','plan_name','license','licencia','module','modulo']);
            $concept = $maybe ?: 'Pago';
        }

        $period = $this->firstNonEmpty($r, ['period','periodo','billing_period','month','mes','year','anio']);
        if (is_numeric($period)) $period = (string)$period;

        $amount = $this->firstNonEmpty($r, ['amount_mxn','amount','monto','total','importe','grand_total']);
        $currency = strtoupper((string)($this->firstNonEmpty($r, ['currency','moneda']) ?: 'MXN'));

        $method = strtoupper((string)($this->firstNonEmpty($r, ['method','metodo','payment_method','provider','gateway','pasarela']) ?: '—'));
        $status = strtoupper((string)($this->firstNonEmpty($r, ['status','estatus','state','payment_status']) ?: '—'));

        $reference = $this->firstNonEmpty($r, ['reference','referencia','folio','stripe_id','payment_intent','charge_id','paypal_id','txn_id','transaction_ref','external_id']);

        $invoiceUrl = $this->firstNonEmpty($r, ['invoice_url','factura_url','invoice_link']);
        $receiptUrl = $this->firstNonEmpty($r, ['receipt_url','recibo_url','receipt_link']);

        $invoicePath = $this->firstNonEmpty($r, ['invoice_path','factura_path','invoice_file','factura_file','xml_path','pdf_path']);
        if (!empty($invoicePath) && empty($invoiceUrl)) {
            try {
                if (Storage::disk('public')->exists((string)$invoicePath)) {
                    $invoiceUrl = Storage::disk('public')->url((string)$invoicePath);
                }
            } catch (Throwable $e) {}
        }

        $amountOut = '';
        if ($amount !== null && $amount !== '') {
            $amountOut = (string) $amount;
        }

        return [
            'id'        => (string)($id ?? ''),
            'date'      => (string)($date ?? ''),
            'ts'        => $ts,
            'concept'   => (string)$concept,
            'period'    => (string)($period ?? ''),
            'amount'    => $amountOut,
            'currency'  => $currency,
            'method'    => $method,
            'status'    => $status,
            'reference' => (string)($reference ?? ''),
            'invoice'   => (string)($invoiceUrl ?? ''),
            'receipt'   => (string)($receiptUrl ?? ''),
            'source'    => $table,
        ];
    }

    private function hasAnyColumn(string $conn, string $table, array $cols): bool
    {
        foreach ($cols as $c) {
            if (Schema::connection($conn)->hasColumn($table, $c)) return true;
        }
        return false;
    }

    private function firstExistingColumn(string $conn, string $table, array $cols): ?string
    {
        foreach ($cols as $c) {
            if (Schema::connection($conn)->hasColumn($table, $c)) return $c;
        }
        return null;
    }

    private function firstNonEmpty(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return null;
    }

    /* =========================================================
     |  MI CUENTA · CONTRATOS (lectura → firma → PDF)
     * ========================================================= */

    public function contratosIndex(Request $request): View
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) {
            abort(403, 'No se encontró la cuenta del usuario.');
        }

        $conn = method_exists($cuenta, 'getConnectionName')
            ? ($cuenta->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $accountId = (int)($cuenta->id ?? 0);
        if ($accountId <= 0) abort(403, 'Cuenta inválida.');

        $contract = $this->ensureCurrentContract($conn, $accountId);
        $datos = $this->extractContractDataFromCuenta($conn, $cuenta, $user);

        return view('cliente.mi_cuenta.contratos.index', [
            'contract' => $contract,
            'datos'    => $datos,
        ]);
    }

    public function showContract(int $contract): View
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) abort(403);

        $conn = method_exists($cuenta, 'getConnectionName')
            ? ($cuenta->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $accountId = (int)($cuenta->id ?? 0);
        if ($accountId <= 0) abort(403);

        $row = DB::connection($conn)->table('cliente_contracts')
            ->where('id', $contract)
            ->where('account_id', $accountId)
            ->first();

        if (!$row) abort(404, 'Contrato no encontrado.');

        $datos = $this->extractContractDataFromCuenta($conn, $cuenta, $user);

        if (($row->status ?? '') === 'signed' && !empty($row->snapshot_html)) {
            $html = (string)$row->snapshot_html;
        } else {
            $html = $this->buildContractHtml($datos);
        }

        return view('cliente.mi_cuenta.contratos.show', [
            'contract' => $row,
            'datos'    => $datos,
            'html'     => $html,
        ]);
    }

    public function signContract(Request $request, int $contract): RedirectResponse
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) abort(403);

        $request->validate([
            'accept' => ['required', 'in:1'],
        ], [
            'accept.required' => 'Debes confirmar que leíste y aceptas el contrato.',
            'accept.in'       => 'Debes confirmar que leíste y aceptas el contrato.',
        ]);

        $conn = method_exists($cuenta, 'getConnectionName')
            ? ($cuenta->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $accountId = (int)($cuenta->id ?? 0);
        if ($accountId <= 0) abort(403);

        $row = DB::connection($conn)->table('cliente_contracts')
            ->where('id', $contract)
            ->where('account_id', $accountId)
            ->first();

        if (!$row) {
            return back()->with('err', 'Contrato no encontrado.');
        }

        if (($row->status ?? '') === 'signed' && !empty($row->pdf_path)) {
            return back()->with('ok', 'Este contrato ya está firmado.');
        }

        $datos = $this->extractContractDataFromCuenta($conn, $cuenta, $user);
        $missing = $this->validateMinimumContractData($datos);

        if (!empty($missing)) {
            return back()->with('err', 'Completa tus datos de contrato antes de firmar: ' . implode(', ', $missing) . '.');
        }

        $html = $this->buildContractHtml($datos);
        $hash = hash('sha256', $html);

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cliente.mi_cuenta.contratos.pdf', [
                'datos' => $datos,
                'html'  => $html,
            ])->setPaper('letter', 'portrait');

            $dir = 'contracts/' . $accountId;
            $filename = 'contrato_timbrado_v' . ((int)($row->template_version ?? 1)) . '_' . date('Ymd_His') . '.pdf';
            $path = $dir . '/' . $filename;

            Storage::disk('local')->put($path, $pdf->output());
        } catch (Throwable $e) {
            Log::error('Contracts.sign: pdf_error', [
                'account_id' => $accountId,
                'contract_id'=> $contract,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('err', 'No se pudo generar el PDF. Revisa el log.');
        }

        try {
            DB::connection($conn)->table('cliente_contracts')
                ->where('id', $contract)
                ->where('account_id', $accountId)
                ->update([
                    'status'            => 'signed',
                    'snapshot_html'     => $html,
                    'content_hash'      => $hash,
                    'signed_at'         => now(),
                    'signed_by_user_id' => (string)($user->id ?? ''),
                    'signed_ip'         => (string)($request->ip() ?? ''),
                    'signed_user_agent' => Str::limit((string)($request->userAgent() ?? ''), 255, ''),
                    'pdf_path'          => $path,
                    'updated_at'        => now(),
                ]);
        } catch (Throwable $e) {
            Log::error('Contracts.sign: db_error', [
                'account_id' => $accountId,
                'contract_id'=> $contract,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('err', 'Se generó el PDF, pero no se pudo guardar la firma. Revisa el log.');
        }

        return redirect()
            ->route('cliente.mi_cuenta.contratos.index')
            ->with('ok', 'Contrato firmado correctamente. Ya puedes descargar tu PDF.');
    }

    public function downloadSignedPdf(int $contract)
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user   = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) abort(403);

        $conn = method_exists($cuenta, 'getConnectionName')
            ? ($cuenta->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $accountId = (int)($cuenta->id ?? 0);
        if ($accountId <= 0) abort(403);

        $row = DB::connection($conn)->table('cliente_contracts')
            ->where('id', $contract)
            ->where('account_id', $accountId)
            ->first();

        if (!$row || ($row->status ?? '') !== 'signed' || empty($row->pdf_path)) {
            abort(404, 'PDF firmado no disponible.');
        }

        $path = (string)$row->pdf_path;
        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'El archivo PDF no existe en el almacenamiento.');
        }

        $full = Storage::disk('local')->path($path);

        return response()->file($full, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato_firmado.pdf"',
        ]);
    }

    private function ensureCurrentContract(string $conn, int $accountId)
    {
        $row = DB::connection($conn)->table('cliente_contracts')
            ->where('account_id', $accountId)
            ->where('template_key', 'timbrado_general')
            ->where('template_version', 1)
            ->first();

        if ($row) return $row;

        $id = DB::connection($conn)->table('cliente_contracts')->insertGetId([
            'account_id'        => $accountId,
            'template_key'      => 'timbrado_general',
            'template_version'  => 1,
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return DB::connection($conn)->table('cliente_contracts')->where('id', $id)->first();
    }

    private function extractContractDataFromCuenta(string $conn, $cuenta, $user): array
    {
        $table = method_exists($cuenta, 'getTable') ? $cuenta->getTable() : null;

        $get = function (string $col, $default = '') use ($conn, $table, $cuenta) {
            try {
                if ($table && Schema::connection($conn)->hasColumn($table, $col)) {
                    return (string)($cuenta->{$col} ?? $default);
                }
            } catch (Throwable $e) {}
            return (string)($default ?? '');
        };

        $razonSocial     = $get('razon_social', '');
        $nombreComercial = $get('nombre_comercial', $get('brand_name', ''));
        $rfc             = $get('rfc', '');
        $email           = $get('email', $get('correo_facturacion', ''));
        $telefono        = $get('telefono_facturacion', $get('telefono', ''));

        $pais      = $get('pais', 'México');
        $calle     = $get('calle', '');
        $noExt     = $get('no_ext', '');
        $noInt     = $get('no_int', '');
        $colonia   = $get('colonia', '');
        $municipio = $get('municipio', '');
        $estado    = $get('estado', '');
        $cp        = $get('cp', '');

        $rep = $get('representante_legal', '');
        if ($rep === '') {
            $rep = (string)($user->nombre ?? 'Representante');
        }

        $ciudadContrato = $municipio !== '' ? $municipio : ($estado !== '' ? $estado : 'México');

        return [
            'cliente_razon_social'     => $razonSocial,
            'cliente_nombre_comercial' => $nombreComercial,
            'cliente_rfc'              => $rfc,
            'cliente_email'            => $email,
            'cliente_telefono'         => $telefono,
            'cliente_representante'    => $rep,

            'cliente_pais'      => $pais,
            'cliente_calle'     => $calle,
            'cliente_no_ext'    => $noExt,
            'cliente_no_int'    => $noInt,
            'cliente_colonia'   => $colonia,
            'cliente_municipio' => $municipio,
            'cliente_estado'    => $estado,
            'cliente_cp'        => $cp,

            'contrato_ciudad' => $ciudadContrato ?: 'México',
            'contrato_fecha'  => now()->format('d \d\e F \d\e Y'),
        ];
    }

    private function validateMinimumContractData(array $d): array
    {
        $missing = [];

        if (trim((string)($d['cliente_razon_social'] ?? '')) === '') $missing[] = 'Razón social';
        if (trim((string)($d['cliente_rfc'] ?? '')) === '') $missing[] = 'RFC';
        if (trim((string)($d['cliente_calle'] ?? '')) === '') $missing[] = 'Calle';
        if (trim((string)($d['cliente_no_ext'] ?? '')) === '') $missing[] = 'Número exterior';
        if (trim((string)($d['cliente_colonia'] ?? '')) === '') $missing[] = 'Colonia';
        if (trim((string)($d['cliente_municipio'] ?? '')) === '') $missing[] = 'Municipio';
        if (trim((string)($d['cliente_estado'] ?? '')) === '') $missing[] = 'Estado';
        if (trim((string)($d['cliente_cp'] ?? '')) === '') $missing[] = 'Código postal';

        return $missing;
    }

    private function buildContractHtml(array $d): string
    {
        $e = fn($v) => e((string)$v);

        $clienteNombre = $e($d['cliente_razon_social'] ?? '');
        $clienteRfc    = $e($d['cliente_rfc'] ?? '');
        $repCliente    = $e($d['cliente_representante'] ?? '');

        $dom = trim(
            $e($d['cliente_calle'] ?? '') . ' ' .
            ($e($d['cliente_no_ext'] ?? '') !== '' ? 'No. ' . $e($d['cliente_no_ext']) : '') .
            ($e($d['cliente_no_int'] ?? '') !== '' ? ' Int. ' . $e($d['cliente_no_int']) : '') .
            ', Col. ' . $e($d['cliente_colonia'] ?? '') .
            ', ' . $e($d['cliente_municipio'] ?? '') .
            ', ' . $e($d['cliente_estado'] ?? '') .
            ', C.P. ' . $e($d['cliente_cp'] ?? '') .
            ', ' . $e($d['cliente_pais'] ?? '')
        );

        $ciudad = $e($d['contrato_ciudad'] ?? 'México');
        $fecha  = $e($d['contrato_fecha'] ?? now()->format('Y-m-d'));

        return <<<HTML
<div class="c-doc">
  <h1 class="c-h1">CONTRATO DE PRESTACIÓN DE SERVICIOS</h1>
  <h2 class="c-h2">Procesamiento de Emisión y/o Cancelación de CFDI</h2>

  <p class="c-meta"><strong>{$ciudad}</strong>, a <strong>{$fecha}</strong>.</p>

  <p>
    El presente Contrato (el “Contrato”) se celebra por una parte <strong>{$clienteNombre}</strong>, RFC <strong>{$clienteRfc}</strong>,
    representada para efectos de aceptación electrónica por <strong>{$repCliente}</strong> (el “Cliente”); y por la otra
    <strong>WARETEK MEXICO, S.A.P.I. DE C.V.</strong>, RFC <strong>WME210827FB3</strong>, representada por
    <strong>DIEGO ESPINOSA DE LOS MONTEROS AVIÑA</strong> (el “Proveedor”); conjuntamente las “Partes”.
  </p>

  <h3 class="c-h3">DECLARACIONES</h3>

  <p><strong>I. Declara el Cliente que:</strong></p>
  <ol class="c-ol">
    <li>Cuenta con capacidad jurídica para obligarse en los términos del presente Contrato.</li>
    <li>Requiere servicios tecnológicos para la generación, emisión, certificación, cancelación y/o resguardo de CFDI.</li>
    <li>Su domicilio para efectos de este Contrato es: <strong>{$dom}</strong>.</li>
    <li>Es responsable de la información fiscal y operativa que utilice en la plataforma.</li>
  </ol>

  <p><strong>II. Declara el Proveedor que:</strong></p>
  <ol class="c-ol">
    <li>Es una persona moral legalmente constituida conforme a las leyes mexicanas.</li>
    <li>Su domicilio es: <strong>Calle Estrellita número 4, Colonia San Cristóbal Centro, C.P. 55000, Ecatepec de Morelos, Estado de México</strong>.</li>
    <li>Cuenta con recursos técnicos y operativos para prestar los servicios objeto de este Contrato.</li>
  </ol>

  <h3 class="c-h3">CLÁUSULAS</h3>

  <p><strong>Primera. Objeto.</strong> El Proveedor prestará al Cliente el servicio de procesamiento para la emisión y/o cancelación de CFDI,
  así como servicios accesorios relacionados, conforme al plan activo del Cliente dentro de la plataforma.</p>

  <p><strong>Segunda. Contraprestación.</strong> El Cliente pagará al Proveedor de conformidad con las tarifas vigentes publicadas en la plataforma
  y/o el plan contratado. Los importes no incluyen impuestos aplicables.</p>

  <p><strong>Tercera. Uso y Responsabilidad.</strong> El Cliente es responsable del contenido, datos y efectos fiscales de los CFDI procesados.
  El Proveedor no será responsable por información incorrecta proporcionada por el Cliente.</p>

  <p><strong>Cuarta. Vigencia.</strong> Este Contrato permanecerá vigente mientras el Cliente mantenga un plan activo, sin perjuicio de terminación
  por políticas del servicio o incumplimiento de pago.</p>

  <p><strong>Quinta. Confidencialidad y Datos Personales.</strong> Las Partes se obligan a mantener confidencialidad y a cumplir con la LFPDPPP.</p>

  <p><strong>Sexta. Jurisdicción.</strong> Las Partes se someten a las leyes mexicanas y a la jurisdicción de los tribunales competentes de
  <strong>Ecatepec de Morelos, Estado de México</strong>.</p>

  <h3 class="c-h3">ACEPTACIÓN ELECTRÓNICA</h3>
  <p>
    El Cliente manifiesta haber leído y aceptado el presente Contrato mediante su aceptación electrónica en la plataforma,
    quedando constancia de fecha, hora, IP y usuario firmante.
  </p>

  <div class="c-sign">
    <div class="c-sign-box">
      <div class="c-sign-title">EL CLIENTE</div>
      <div class="c-sign-name"><strong>{$clienteNombre}</strong></div>
      <div class="c-sign-line">Aceptación electrónica en plataforma</div>
    </div>

    <div class="c-sign-box">
      <div class="c-sign-title">EL PROVEEDOR</div>
      <div class="c-sign-name"><strong>WARETEK MEXICO, S.A.P.I. DE C.V.</strong></div>
      <div class="c-sign-line">Representante: Diego Espinosa de los Monteros Aviña</div>
    </div>
  </div>
</div>
HTML;
    }

    public function profileUpdate(Request $request): RedirectResponse
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user = auth('web')->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        $validated = $request->validate([
            'nombre'    => ['nullable','string','max:255'],
            'email'     => ['nullable','string','max:255'],
            'telefono'  => ['nullable','string','max:50'],
            'puesto'    => ['nullable','string','max:120'],
        ]);

        $table = method_exists($user, 'getTable') ? $user->getTable() : 'usuarios_cuenta';
        $conn  = method_exists($user, 'getConnectionName')
            ? ($user->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $map = [
            'nombre'   => ['nombre', 'name', 'full_name'],
            'email'    => ['email', 'correo'],
            'telefono' => ['telefono', 'tel', 'phone', 'telefono_movil'],
            'puesto'   => ['puesto', 'job_title', 'cargo'],
        ];

        $dataToSave = [];
        $savedKeys  = [];
        $skipped    = [];

        Log::info('MiCuenta.profileUpdate: start', [
            'user_id' => $user->id ?? null,
            'conn'    => $conn,
            'table'   => $table,
            'inputs'  => array_keys($validated),
        ]);

        try {
            foreach ($map as $input => $columns) {
                if (!array_key_exists($input, $validated)) {
                    continue;
                }

                $val = $validated[$input];

                $saved = false;

                foreach ($columns as $col) {
                    if (Schema::connection($conn)->hasColumn($table, $col)) {
                        $dataToSave[$col] = $val;
                        $savedKeys[] = $input . '->' . $col;
                        $saved = true;
                        break;
                    }
                }

                if (!$saved) {
                    $skipped[$input] = 'missing_columns:' . implode('|', $columns);
                }
            }

            if (!empty($dataToSave)) {
                $user->forceFill($dataToSave)->save();
            }

            Log::info('MiCuenta.profileUpdate: done', [
                'user_id'       => $user->id ?? null,
                'saved_columns' => array_keys($dataToSave),
                'saved_keys'    => $savedKeys,
                'skipped'       => $skipped,
            ]);
        } catch (Throwable $e) {
            Log::error('MiCuenta.profileUpdate: exception', [
                'user_id'  => $user->id ?? null,
                'conn'     => $conn,
                'table'    => $table,
                'data_cols'=> array_keys($dataToSave),
                'skipped'  => $skipped,
                'error'    => $e->getMessage(),
            ]);

            return back()->with('err', 'No se pudieron guardar los datos de perfil. Revisa el log.');
        }

        $savedCount   = count($dataToSave);
        $skippedCount = count($skipped);

        if ($savedCount <= 0) {
            return back()->with('err', 'No se guardó nada: tu tabla de usuario no tiene columnas compatibles. Revisa el log.');
        }

        if ($skippedCount > 0) {
            return back()->with('ok', "Perfil guardado parcialmente ({$savedCount} campos). Faltan columnas para {$skippedCount} campos; revisa el log.");
        }

        return back()->with('ok', 'Perfil guardado correctamente.');
    }

    public function securityUpdate(Request $request): RedirectResponse
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user = auth('web')->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        $validated = $request->validate([
            'current_password' => ['nullable','string','max:100'],
            'password'         => ['required','string','min:8','max:100','confirmed'],
        ], [
            'password.required'  => 'Ingresa tu nueva contraseña.',
            'password.min'       => 'Tu contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide.',
        ]);

        $table = method_exists($user, 'getTable') ? $user->getTable() : 'usuarios_cuenta';
        $conn  = method_exists($user, 'getConnectionName')
            ? ($user->getConnectionName() ?: config('database.default'))
            : config('database.default');

        Log::info('MiCuenta.securityUpdate: start', [
            'user_id' => $user->id ?? null,
            'conn'    => $conn,
            'table'   => $table,
            'has_current' => !empty($validated['current_password'] ?? ''),
        ]);

        try {
            if (!empty($validated['current_password'] ?? '') && Schema::connection($conn)->hasColumn($table, 'password')) {
                $raw = (string) $user->getRawOriginal('password');
                $ok  = $raw !== '' && ClientAuth::check((string) $validated['current_password'], $raw);

                if (!$ok) {
                    return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
                }
            }

            $dataToSave = [];

            if (Schema::connection($conn)->hasColumn($table, 'password')) {
                $dataToSave['password'] = ClientAuth::make((string) $validated['password']);
            }

            if (Schema::connection($conn)->hasColumn($table, 'must_change_password')) {
                $dataToSave['must_change_password'] = 0;
            }

            if (empty($dataToSave)) {
                return back()->with('err', 'No se pudo actualizar: no hay columnas compatibles (password/must_change_password).');
            }

            $user->forceFill($dataToSave)->save();

            Log::info('MiCuenta.securityUpdate: done', [
                'user_id' => $user->id ?? null,
                'saved_columns' => array_keys($dataToSave),
            ]);

            return back()->with('ok', 'Seguridad actualizada correctamente.');
        } catch (Throwable $e) {
            Log::error('MiCuenta.securityUpdate: exception', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);

            return back()->with('err', 'No se pudo actualizar la seguridad. Revisa el log.');
        }
    }

    public function preferencesUpdate(Request $request): RedirectResponse
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user = auth('web')->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        $validated = $request->validate([
            'theme'     => ['nullable','in:light,dark,system'],
            'timezone'  => ['nullable','string','max:80'],
            'language'  => ['nullable','string','max:10'],
            'demo_mode' => ['nullable'],
        ]);

        $demoMode = $request->has('demo_mode') ? 1 : 0;

        if (!empty($validated['theme'])) {
            session(['client_ui.theme' => $validated['theme']]);
        }
        if (!empty($validated['timezone'])) {
            session(['client_ui.timezone' => $validated['timezone']]);
        }
        if (!empty($validated['language'])) {
            session(['client_ui.language' => $validated['language']]);
        }
        session(['client_ui.demo_mode' => $demoMode]);

        $table = method_exists($user, 'getTable') ? $user->getTable() : 'usuarios_cuenta';
        $conn  = method_exists($user, 'getConnectionName')
            ? ($user->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $dataToSave = [];
        $savedKeys  = [];
        $skipped    = [];

        Log::info('MiCuenta.preferencesUpdate: start', [
            'user_id' => $user->id ?? null,
            'conn'    => $conn,
            'table'   => $table,
            'inputs'  => array_keys($validated),
            'demo_mode' => $demoMode,
        ]);

        try {
            $prefMap = [
                'theme'     => ['ui_theme','theme','client_theme'],
                'timezone'  => ['timezone','tz'],
                'language'  => ['language','lang','locale'],
                'demo_mode' => ['demo_mode','ui_demo_mode'],
            ];

            foreach ($prefMap as $input => $columns) {
                if ($input === 'demo_mode') {
                    $val = $demoMode;
                } else {
                    if (!array_key_exists($input, $validated) || $validated[$input] === null || $validated[$input] === '') {
                        continue;
                    }
                    $val = $validated[$input];
                }

                $saved = false;
                foreach ($columns as $col) {
                    if (Schema::connection($conn)->hasColumn($table, $col)) {
                        $dataToSave[$col] = $val;
                        $savedKeys[] = $input . '->' . $col;
                        $saved = true;
                        break;
                    }
                }
                if (!$saved) {
                    $skipped[$input] = 'missing_columns:' . implode('|', $columns);
                }
            }

            if (!empty($dataToSave)) {
                $user->forceFill($dataToSave)->save();
            }

            Log::info('MiCuenta.preferencesUpdate: done', [
                'user_id'       => $user->id ?? null,
                'saved_columns' => array_keys($dataToSave),
                'saved_keys'    => $savedKeys,
                'skipped'       => $skipped,
            ]);
        } catch (Throwable $e) {
            Log::error('MiCuenta.preferencesUpdate: exception', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);

            return back()->with('err', 'No se pudieron guardar las preferencias. Revisa el log.');
        }

        return back()->with('ok', 'Preferencias actualizadas.');
    }

    public function brandUpdate(Request $request): RedirectResponse
    {
        /** @var \App\Models\Cliente\UsuarioCuenta|null $user */
        $user = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) {
            abort(403, 'No se encontró la cuenta del usuario.');
        }

        // ✅ FIX: brand_accent se valida y se persiste (antes no)
        $validated = $request->validate([
            'brand_name'   => ['nullable','string','max:120'],
            'brand_accent' => ['nullable','string','max:16'], // #RRGGBB
            'logo'         => ['nullable','file','mimes:png,jpg,jpeg,webp','max:2048'],
        ]);

        if (!empty($validated['brand_accent'])) {
            session(['client_ui.accent' => (string)$validated['brand_accent']]);
        }

        $table = method_exists($cuenta, 'getTable') ? $cuenta->getTable() : null;
        $conn  = method_exists($cuenta, 'getConnectionName')
            ? ($cuenta->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $dataToSave = [];
        $savedKeys  = [];
        $skipped    = [];

        Log::info('MiCuenta.brandUpdate: start', [
            'user_id'    => $user->id ?? null,
            'account_id' => $cuenta->id ?? null,
            'conn'       => $conn,
            'table'      => $table,
            'has_logo'   => $request->hasFile('logo'),
        ]);

        try {
            if (!empty($validated['brand_name'] ?? '')) {
                if ($table) {
                    $candidates = ['brand_name','nombre_marca','nombre_comercial','nombre_comercial_ui'];
                    $saved = false;
                    foreach ($candidates as $col) {
                        if (Schema::connection($conn)->hasColumn($table, $col)) {
                            $dataToSave[$col] = (string)$validated['brand_name'];
                            $savedKeys[] = 'brand_name->' . $col;
                            $saved = true;
                            break;
                        }
                    }
                    if (!$saved) {
                        $skipped['brand_name'] = 'missing_columns:' . implode('|', $candidates);
                    }
                } else {
                    $skipped['brand_name'] = 'no_table_detected';
                }
            }

            if (!empty($validated['brand_accent'] ?? '')) {
                if ($table) {
                    $accentCols = ['brand_accent','accent','accent_color','ui_accent','color_acento'];
                    $saved = false;
                    foreach ($accentCols as $col) {
                        if (Schema::connection($conn)->hasColumn($table, $col)) {
                            $dataToSave[$col] = (string)$validated['brand_accent'];
                            $savedKeys[] = 'brand_accent->' . $col;
                            $saved = true;
                            break;
                        }
                    }
                    if (!$saved) {
                        $skipped['brand_accent'] = 'missing_columns:' . implode('|', $accentCols);
                    }
                } else {
                    $skipped['brand_accent'] = 'no_table_detected';
                }
            }

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $ext  = strtolower($file->getClientOriginalExtension() ?: 'png');
                $safe = 'logo_' . Str::lower(Str::ulid()) . '.' . $ext;

                $dir  = 'p360/brands/' . (string)($cuenta->id ?? 'account');
                $path = $file->storeAs($dir, $safe, ['disk' => 'public']);

                if ($table) {
                    $logoCols = ['brand_logo','logo','logo_path','logo_url','brand_logo_path'];
                    $saved = false;
                    foreach ($logoCols as $col) {
                        if (Schema::connection($conn)->hasColumn($table, $col)) {
                            $dataToSave[$col] = $path;
                            $savedKeys[] = 'logo->' . $col;
                            $saved = true;
                            break;
                        }
                    }
                    if (!$saved) {
                        $skipped['logo'] = 'missing_columns:' . implode('|', $logoCols);
                    }
                } else {
                    $skipped['logo'] = 'no_table_detected';
                }
            }

            if (!empty($dataToSave)) {
                $cuenta->forceFill($dataToSave)->save();
            }

            Log::info('MiCuenta.brandUpdate: done', [
                'account_id'    => $cuenta->id ?? null,
                'saved_columns' => array_keys($dataToSave),
                'saved_keys'    => $savedKeys,
                'skipped'       => $skipped,
            ]);

        } catch (Throwable $e) {
            Log::error('MiCuenta.brandUpdate: exception', [
                'account_id' => $cuenta->id ?? null,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('err', 'No se pudo actualizar marca/branding. Revisa el log.');
        }

        if (!empty($skipped)) {
            $savedCount = count($dataToSave);
            $skippedCount = count($skipped);
            if ($savedCount > 0) {
                return back()->with('ok', "Marca guardada parcialmente ({$savedCount} campos). Faltan columnas para {$skippedCount} campos; revisa el log.");
            }
        }

        return back()->with('ok', 'Marca actualizada correctamente.');
    }

    private function loadModulesFromSotForCurrentUser(): array
    {
        $catalog = [
            'sat_descargas' => true,
            'boveda_fiscal' => true,
            'facturacion'   => true,
            'nomina'        => true,
            'crm'           => true,
            'pos'           => true,
        ];

        $user = auth('web')->user();
        $cuenta = $user?->cuenta;

        // ✅ FIX: usar admin_account_id si existe; si no, fallback a id local
        $accountId =
            (string)($cuenta->admin_account_id ?? '')
            ?: (string)($cuenta->id ?? '');

        if ($accountId === '') {
            return $catalog;
        }

        $conn = (string) (env('P360_BILLING_SOT_CONN') ?: 'mysql_admin');
        $table = (string) (env('P360_BILLING_SOT_TABLE') ?: 'accounts');
        $metaCol = (string) (env('P360_BILLING_META_COL') ?: 'meta');

        try {
            $row = DB::connection($conn)->table($table)->select(['id', $metaCol])->where('id', $accountId)->first();
            if (!$row) return $catalog;

            $raw = $row->{$metaCol} ?? null;
            $meta = [];

            if (is_string($raw) && $raw !== '') $meta = json_decode($raw, true) ?: [];
            elseif (is_array($raw)) $meta = $raw;
            elseif (is_object($raw)) $meta = (array)$raw;

            $mods = data_get($meta, 'modules', []);
            if (!is_array($mods) || empty($mods)) return $catalog;

            foreach ($catalog as $k => $v) {
                if (!array_key_exists($k, $mods)) $mods[$k] = true;
            }
            foreach ($mods as $k => $v) {
                $mods[$k] = (bool)$v;
            }

            return $mods;
        } catch (Throwable $e) {
            Log::error('MiCuenta.modulesSot.exception', [
                'account_id' => (string)$accountId,
                'error' => $e->getMessage(),
            ]);
            return $catalog;
        }
    }

    public function billingUpdate(Request $request): RedirectResponse
    {
        $user = auth('web')->user();
        $cuenta = $user?->cuenta;

        if (!$user || !$cuenta) {
            abort(403, 'No se encontró la cuenta del usuario.');
        }

        $validated = $request->validate([
            'razon_social'     => ['nullable','string','max:255'],
            'nombre_comercial' => ['nullable','string','max:255'],
            'rfc'              => ['nullable','string','max:20'],
            'correo'           => ['nullable','string','max:255'],
            'telefono'         => ['nullable','string','max:50'],
            'pais'             => ['nullable','string','max:80'],
            'calle'            => ['nullable','string','max:255'],
            'no_ext'           => ['nullable','string','max:50'],
            'no_int'           => ['nullable','string','max:50'],
            'colonia'          => ['nullable','string','max:255'],
            'municipio'        => ['nullable','string','max:255'],
            'estado'           => ['nullable','string','max:255'],
            'cp'               => ['nullable','string','max:20'],
            'regimen_fiscal'   => ['nullable','string','max:10'],
            'uso_cfdi'         => ['nullable','string','max:10'],
            'metodo_pago'      => ['nullable','string','max:10'],
            'forma_pago'       => ['nullable','string','max:10'],
            'leyenda_pdf'      => ['nullable','string','max:255'],
            'pdf_mostrar_nombre_comercial' => ['nullable'],
            'pdf_mostrar_telefono'         => ['nullable'],
        ]);

        $validated['pdf_mostrar_nombre_comercial'] = $request->has('pdf_mostrar_nombre_comercial') ? 1 : 0;
        $validated['pdf_mostrar_telefono']         = $request->has('pdf_mostrar_telefono') ? 1 : 0;

        $map = [
            'razon_social'                 => 'razon_social',
            'nombre_comercial'             => 'nombre_comercial',
            'rfc'                          => 'rfc',
            'correo'                       => 'email',
            'telefono'                     => 'telefono_facturacion',
            'pais'                         => 'pais',
            'calle'                        => 'calle',
            'no_ext'                       => 'no_ext',
            'no_int'                       => 'no_int',
            'colonia'                      => 'colonia',
            'municipio'                    => 'municipio',
            'estado'                       => 'estado',
            'cp'                           => 'cp',
            'regimen_fiscal'               => 'regimen_fiscal',
            'uso_cfdi'                     => 'uso_cfdi',
            'metodo_pago'                  => 'metodo_pago',
            'forma_pago'                   => 'forma_pago',
            'leyenda_pdf'                  => 'leyenda_pdf',
            'pdf_mostrar_nombre_comercial' => 'pdf_mostrar_nombre_comercial',
            'pdf_mostrar_telefono'         => 'pdf_mostrar_telefono',
        ];

        $table = method_exists($cuenta, 'getTable') ? $cuenta->getTable() : null;
        $conn  = method_exists($cuenta, 'getConnectionName')
            ? ($cuenta->getConnectionName() ?: config('database.default'))
            : config('database.default');

        $dataToSave = [];
        $savedKeys  = [];
        $skipped    = [];

        Log::info('MiCuenta.billingUpdate: start', [
            'user_id'    => $user->id ?? null,
            'account_id' => $cuenta->id ?? null,
            'conn'       => $conn,
            'table'      => $table,
            'inputs'     => array_keys($validated),
        ]);

        try {
            foreach ($map as $input => $column) {
                if (!array_key_exists($input, $validated)) {
                    continue;
                }

                if (!$table) {
                    $skipped[$input] = 'no_table_detected';
                    continue;
                }

                if (Schema::connection($conn)->hasColumn($table, $column)) {
                    $dataToSave[$column] = $validated[$input];
                    $savedKeys[] = $input.'->'.$column;
                    continue;
                }

                if ($column === 'correo_facturacion' || $input === 'correo') {
                    $found = false;
                    foreach (['correo_facturacion','email_facturacion','correo','email'] as $alt) {
                        if (Schema::connection($conn)->hasColumn($table, $alt)) {
                            $dataToSave[$alt] = $validated[$input];
                            $savedKeys[] = $input.'->'.$alt;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) $skipped[$input] = 'missing_column:correo_facturacion/email_facturacion/correo/email';
                    continue;
                }

                if ($column === 'telefono_facturacion' || $input === 'telefono') {
                    $found = false;
                    foreach (['telefono_facturacion','telefono','tel','phone'] as $alt) {
                        if (Schema::connection($conn)->hasColumn($table, $alt)) {
                            $dataToSave[$alt] = $validated[$input];
                            $savedKeys[] = $input.'->'.$alt;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) $skipped[$input] = 'missing_column:telefono_facturacion/telefono/tel/phone';
                    continue;
                }

                $skipped[$input] = 'missing_column:'.$column;
            }

            if (!empty($dataToSave)) {
                $cuenta->forceFill($dataToSave)->save();
            }

            Log::info('MiCuenta.billingUpdate: done', [
                'account_id'    => $cuenta->id ?? null,
                'saved_columns' => array_keys($dataToSave),
                'saved_keys'    => $savedKeys,
                'skipped'       => $skipped,
            ]);

        } catch (Throwable $e) {
            Log::error('MiCuenta.billingUpdate: exception', [
                'account_id' => $cuenta->id ?? null,
                'conn'       => $conn,
                'table'      => $table,
                'data_cols'  => array_keys($dataToSave),
                'skipped'    => $skipped,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('err', 'No se pudieron guardar los datos. Revisa el log del sistema.');
        }

        $savedCount   = count($dataToSave);
        $skippedCount = count($skipped);

        if ($savedCount <= 0) {
            return back()->with('err', 'No se guardó nada: tu tabla no tiene columnas compatibles. Revisa el log.');
        }

        if ($skippedCount > 0) {
            return back()->with('ok', "Datos guardados parcialmente ({$savedCount} campos). Faltan columnas para {$skippedCount} campos; revisa el log.");
        }

        return back()->with('ok', 'Datos de facturación guardados correctamente.');
    }
}
