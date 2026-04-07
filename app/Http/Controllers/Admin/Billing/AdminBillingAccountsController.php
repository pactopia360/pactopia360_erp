<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminBillingAccountsController extends Controller
{
    private string $adm = 'mysql_admin';

    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        $rows = DB::connection($this->adm)->table('accounts as a')
            ->selectRaw("a.id, a.email, a.rfc, a.razon_social, a.modo_cobro,
                JSON_UNQUOTE(JSON_EXTRACT(a.meta,'$.billing.price_key')) as price_key,
                JSON_UNQUOTE(JSON_EXTRACT(a.meta,'$.billing.billing_cycle')) as billing_cycle")
            ->when($q !== '', function ($w) use ($q) {
                $w->where('a.email', 'like', "%{$q}%")
                  ->orWhere('a.rfc', 'like', "%{$q}%")
                  ->orWhere('a.razon_social', 'like', "%{$q}%")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(a.meta,'$.billing.price_key')) like ?", ["%{$q}%"]);
            })
            ->orderByDesc('a.id')
            ->paginate(20)
            ->withQueryString();

        $prices = DB::connection($this->adm)->table('stripe_price_list')
            ->select('price_key','plan','billing_cycle','display_amount','currency','is_active')
            ->where('is_active', 1)
            ->orderBy('plan')
            ->orderBy('billing_cycle')
            ->get();

        return view('admin.billing.accounts.index', [
            'rows'   => $rows,
            'prices' => $prices,
            'q'      => $q,
        ]);
    }

    public function edit(Request $request, int $account)
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $account)->first();
        abort_if(!$acc, 404);

        $prices = DB::connection($this->adm)->table('stripe_price_list')
            ->select('price_key','plan','billing_cycle','display_amount','currency','is_active')
            ->where('is_active', 1)
            ->orderBy('plan')->orderBy('billing_cycle')
            ->get();

        $billing = [
            'price_key'     => $this->jsonGet($acc->meta ?? null, '$.billing.price_key'),
            'billing_cycle' => $this->jsonGet($acc->meta ?? null, '$.billing.billing_cycle'),
            'concept'       => $this->jsonGet($acc->meta ?? null, '$.billing.concept'),
        ];

        return view('admin.billing.accounts.edit', [
            'acc'     => $acc,
            'prices'  => $prices,
            'billing' => $billing,
        ]);
    }

    public function update(Request $request, int $account)
    {
        $validated = $request->validate([
            'price_key'     => ['required', 'string', 'max:80'],
            'billing_cycle' => ['required', 'in:mensual,anual'],
            'concept'       => ['required', 'string', 'max:255'],
        ]);

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $account)->first();
        abort_if(!$acc, 404);

        // Validar que price_key exista y esté activa
        $price = DB::connection($this->adm)->table('stripe_price_list')
            ->where('is_active', 1)
            ->whereRaw('LOWER(price_key)=?', [strtolower($validated['price_key'])])
            ->first();

        if (!$price) {
            throw ValidationException::withMessages(['price_key' => 'price_key no existe o no está activa en stripe_price_list.']);
        }

        // MariaDB-safe: JSON_MERGE_PATCH crea el objeto billing si no existe.
        DB::connection($this->adm)->table('accounts')->where('id', $account)->update([
            'meta' => DB::raw("JSON_MERGE_PATCH(COALESCE(meta, JSON_OBJECT()), " .
                "JSON_OBJECT('billing', JSON_OBJECT(" .
                "'price_key','" . addslashes($validated['price_key']) . "'," .
                "'billing_cycle','" . addslashes($validated['billing_cycle']) . "'," .
                "'concept','" . addslashes($validated['concept']) . "'" .
                ")))"
            ),
            'modo_cobro'  => $validated['billing_cycle'],
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.billing.accounts.edit', $account)->with('ok', 'Billing actualizado.');
    }

    public function statement(Request $request, int $account)
    {
        $period = trim((string)$request->get('period', now()->format('Y-m')));
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            $period = now()->format('Y-m');
        }

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $account)->first();
        abort_if(!$acc, 404);

        $movs = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $account)
            ->where('periodo', $period)
            ->orderBy('id', 'asc')
            ->get();

        $sum = DB::connection($this->adm)->table('estados_cuenta')
            ->selectRaw("COALESCE(SUM(cargo),0) cargo, COALESCE(SUM(abono),0) abono, (COALESCE(SUM(cargo),0)-COALESCE(SUM(abono),0)) saldo")
            ->where('account_id', $account)
            ->where('periodo', $period)
            ->first();

        $saldo = (float)($sum->saldo ?? 0);

        // URL del portal cliente (ajusta si tu login/portal es distinto)
        $portalUrl = url('/cliente');

        return view('admin.billing.accounts.statement', [
            'acc'      => $acc,
            'period'   => $period,
            'movs'     => $movs,
            'sum'      => $sum,
            'saldo'    => $saldo,
            'portalUrl'=> $portalUrl,
        ]);
    }

    public function manualPayment(Request $request, int $account)
    {
        $validated = $request->validate([
            'period'   => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'ref'      => ['nullable', 'string', 'max:191'],
            'detalle'  => ['nullable', 'string', 'max:2000'],
        ]);

        $period = (string) $validated['period'];
        $amount = round((float) $validated['amount'], 2);
        $ref    = trim((string) ($validated['ref'] ?? ''));
        $det    = trim((string) ($validated['detalle'] ?? ''));

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $account)->first();
        abort_if(!$acc, 404);

        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            return back()->withErrors(['estados_cuenta' => 'No existe la tabla estados_cuenta.']);
        }

        $paymentsCols = Schema::connection($this->adm)->getColumnListing('payments');
        $paymentsLc   = array_map('strtolower', $paymentsCols);
        $payHas       = static fn (string $c): bool => in_array(strtolower($c), $paymentsLc, true);

        $now         = now();
        $amountCents = (int) round($amount * 100);
        $reference   = $ref !== '' ? $ref : sprintf(
            'hub-manual:%d:%s:%s',
            $account,
            $period,
            strtoupper(Str::random(10))
        );

        DB::connection($this->adm)->transaction(function () use (
            $account,
            $period,
            $amount,
            $amountCents,
            $reference,
            $det,
            $now,
            $payHas
        ) {
            // 1) Idempotencia real en payments por referencia
            if ($payHas('reference')) {
                $existingPayment = DB::connection($this->adm)->table('payments')
                    ->where('account_id', $account)
                    ->where('reference', $reference)
                    ->first();

                if ($existingPayment) {
                    return;
                }
            }

            // 2) Insertar pago real en payments
            $paymentRow = [
                'account_id' => $account,
                'amount'     => $amountCents,
                'currency'   => 'MXN',
                'status'     => 'paid',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($payHas('paid_at')) {
                $paymentRow['paid_at'] = $now;
            }

            if ($payHas('due_date')) {
                $paymentRow['due_date'] = $now;
            }

            if ($payHas('period')) {
                $paymentRow['period'] = $period;
            }

            if ($payHas('method')) {
                $paymentRow['method'] = 'transfer';
            }

            if ($payHas('provider')) {
                $paymentRow['provider'] = 'manual';
            }

            if ($payHas('concept')) {
                $paymentRow['concept'] = 'Pago manual (transferencia)';
            }

            if ($payHas('reference')) {
                $paymentRow['reference'] = $reference;
            }

            if ($payHas('amount_mxn')) {
                $paymentRow['amount_mxn'] = $amount;
            }

            if ($payHas('monto_mxn')) {
                $paymentRow['monto_mxn'] = $amount;
            }

            if ($payHas('meta')) {
                $paymentRow['meta'] = json_encode([
                    'type'         => 'manual',
                    'source'       => 'admin.billing.statements_hub.manual_payment',
                    'period'       => $period,
                    'amount_pesos' => $amount,
                    'ref'          => $reference,
                    'detalle'      => $det !== '' ? $det : null,
                    'captured_at'  => $now->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            DB::connection($this->adm)->table('payments')->insert($paymentRow);

            // 3) Insertar abono espejo en estados_cuenta solo si no existe ya esa referencia
            $existsEstado = false;
            $estadoCols = Schema::connection($this->adm)->getColumnListing('estados_cuenta');
            $estadoLc   = array_map('strtolower', $estadoCols);
            $edoHas     = static fn (string $c): bool => in_array(strtolower($c), $estadoLc, true);

            if ($edoHas('source') && $edoHas('ref')) {
                $existsEstado = DB::connection($this->adm)->table('estados_cuenta')
                    ->where('account_id', $account)
                    ->where('periodo', $period)
                    ->where('source', 'manual')
                    ->where('ref', $reference)
                    ->exists();
            }

            if (!$existsEstado) {
                $estadoRow = [
                    'account_id' => $account,
                    'periodo'    => $period,
                    'concepto'   => 'Pago manual (transferencia)',
                    'detalle'    => $det !== '' ? $det : ('Registrado por Admin · ' . $now->toDateTimeString()),
                    'cargo'      => 0,
                    'abono'      => $amount,
                    'saldo'      => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($edoHas('source')) {
                    $estadoRow['source'] = 'manual';
                }

                if ($edoHas('ref')) {
                    $estadoRow['ref'] = $reference;
                }

                if ($edoHas('meta')) {
                    $estadoRow['meta'] = json_encode([
                        'type'         => 'manual_payment',
                        'source'       => 'admin.billing.statements_hub.manual_payment',
                        'period'       => $period,
                        'amount_pesos' => $amount,
                        'ref'          => $reference,
                        'at'           => $now->toISOString(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                DB::connection($this->adm)->table('estados_cuenta')->insert($estadoRow);
            }

            // 4) Recalcular saldos corridos de estados_cuenta del periodo
            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $account)
                ->where('periodo', $period)
                ->orderBy('id')
                ->get(['id', 'cargo', 'abono']);

            $runningSaldo = 0.0;

            foreach ($items as $it) {
                $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                $abono = is_numeric($it->abono ?? null) ? (float) $it->abono : 0.0;

                $runningSaldo = max(0.0, $runningSaldo + $cargo - $abono);

                DB::connection($this->adm)->table('estados_cuenta')
                    ->where('id', (int) $it->id)
                    ->update([
                        'saldo'      => round($runningSaldo, 2),
                        'updated_at' => $now,
                    ]);
            }

            // 5) Reconciliar billing_statements del periodo
            if (Schema::connection($this->adm)->hasTable('billing_statements')) {
                $statement = DB::connection($this->adm)->table('billing_statements')
                    ->where('account_id', $account)
                    ->where('period', $period)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();

                if ($statement) {
                    $cargoTotal = round((float) ($statement->total_cargo ?? 0), 2);

                    $paidTotal = 0.0;
                    $paidQ = DB::connection($this->adm)->table('payments')
                        ->where('account_id', $account)
                        ->where(function ($w) use ($period) {
                            $w->where('period', $period)
                            ->orWhere('period', 'like', $period . '%');
                        })
                        ->whereIn(DB::raw('LOWER(status)'), [
                            'paid',
                            'pagado',
                            'succeeded',
                            'success',
                            'completed',
                            'complete',
                            'captured',
                            'authorized',
                            'paid_ok',
                            'ok',
                        ]);

                    if ($payHas('amount_mxn')) {
                        $paidTotal = round((float) ($paidQ->sum('amount_mxn') ?? 0), 2);
                    } elseif ($payHas('monto_mxn')) {
                        $paidTotal = round((float) ($paidQ->sum('monto_mxn') ?? 0), 2);
                    } elseif ($payHas('amount_cents')) {
                        $paidTotal = round(((float) ($paidQ->sum('amount_cents') ?? 0)) / 100, 2);
                    } else {
                        $paidTotal = round(((float) ($paidQ->sum('amount') ?? 0)) / 100, 2);
                    }

                    $saldo = round(max(0.0, $cargoTotal - $paidTotal), 2);

                    $status = 'pending';
                    $paidAt = null;

                    if ($cargoTotal <= 0.00001 && $paidTotal <= 0.00001) {
                        $status = 'void';
                    } elseif ($saldo <= 0.00001 && $paidTotal > 0.00001) {
                        $status = 'paid';
                        $paidAt = $now;
                    } elseif ($paidTotal > 0.00001) {
                        $status = 'partial';
                    }

                    DB::connection($this->adm)->table('billing_statements')
                        ->where('id', (int) $statement->id)
                        ->update([
                            'total_abono' => $paidTotal,
                            'saldo'       => $saldo,
                            'status'      => $status,
                            'paid_at'     => $paidAt,
                            'updated_at'  => $now,
                        ]);
                }
            }

            // 6) Si había override visual del periodo, alinearlo al estado real
            if (Schema::connection($this->adm)->hasTable('billing_statement_status_overrides')) {
                $ov = DB::connection($this->adm)->table('billing_statement_status_overrides')
                    ->where('account_id', $account)
                    ->where('period', $period)
                    ->first();

                if ($ov) {
                    $statementNow = DB::connection($this->adm)->table('billing_statements')
                        ->where('account_id', $account)
                        ->where('period', $period)
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($statementNow) {
                        $newStatus = strtolower((string) ($statementNow->status ?? 'pending'));
                        $newStatus = match ($newStatus) {
                            'paid'    => 'pagado',
                            'partial' => 'parcial',
                            'void'    => 'sin_mov',
                            'overdue' => 'vencido',
                            default   => 'pendiente',
                        };

                        DB::connection($this->adm)->table('billing_statement_status_overrides')
                            ->where('id', (int) $ov->id)
                            ->update([
                                'status_override' => $newStatus,
                                'updated_at'      => $now,
                            ]);
                    }
                }
            }
        });

        if (class_exists(\App\Services\Admin\Billing\AccountBillingStateService::class)) {
            \App\Services\Admin\Billing\AccountBillingStateService::sync($account, 'admin.billing.statements_hub.manual_payment');
        }

        return back()->with('ok', 'Pago manual registrado y conciliado.');
    }

    public function sendStatementEmail(Request $request, int $account)
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'email'  => ['nullable', 'email'],
        ]);

        $period = $validated['period'];

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $account)->first();
        abort_if(!$acc, 404);

        $sum = DB::connection($this->adm)->table('estados_cuenta')
            ->selectRaw("COALESCE(SUM(cargo),0) cargo, COALESCE(SUM(abono),0) abono, (COALESCE(SUM(cargo),0)-COALESCE(SUM(abono),0)) total")
            ->where('account_id', $account)
            ->where('periodo', $period)
            ->first();

        $to = (string)($validated['email'] ?? $acc->email ?? '');
        if ($to === '') {
            throw ValidationException::withMessages(['email' => 'La cuenta no tiene email.']);
        }

        // Pay URL (por ahora solo portal; luego amarramos a tu flujo de checkout billing_pending_total o statement pay)
        $payUrl = null; // se activa cuando integremos “Pay statement” de forma formal

        // PDF URL (si ya tienes endpoint firmado / publicPdf, lo conectamos después)
        $pdfUrl = null;

        $periodLabel = Carbon::createFromFormat('Y-m', $period)->locale('es')->translatedFormat('F Y');

        Mail::send('admin.mail.statement', [
            'period'       => $period,
            'period_label' => $periodLabel,
            'cargo'        => (float)($sum->cargo ?? 0),
            'abono'        => (float)($sum->abono ?? 0),
            'total'        => (float)($sum->total ?? 0),
            'pdf_url'      => $pdfUrl,
            'pay_url'      => $payUrl,
            'portal_url'   => url('/cliente'),
        ], function ($m) use ($to, $period) {
            $m->to($to)->subject("Pactopia360 · Estado de cuenta {$period}");
        });

        Log::info('Admin send statement email', [
            'account_id' => $account,
            'period'     => $period,
            'to'         => $to,
        ]);

        return back()->with('ok', 'Correo enviado.');
    }

    private function jsonGet($meta, string $path): ?string
    {
        try {
            if (is_string($meta) && $meta !== '') {
                $decoded = json_decode($meta, true);
                if (!is_array($decoded)) return null;
                $meta = $decoded;
            }
            if (!is_array($meta)) return null;

            // Soporta path simple $.a.b.c
            $path = ltrim($path, '$.');
            $keys = $path === '' ? [] : explode('.', $path);

            $cur = $meta;
            foreach ($keys as $k) {
                if (!is_array($cur) || !array_key_exists($k, $cur)) return null;
                $cur = $cur[$k];
            }
            return is_scalar($cur) ? (string)$cur : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
