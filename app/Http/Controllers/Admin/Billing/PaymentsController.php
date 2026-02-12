<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Services\Admin\Billing\AccountBillingStateService;

final class PaymentsController extends Controller
{
    private string $adm = 'mysql_admin';

    public function index(Request $req): View
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return view('admin.billing.payments.index', [
                'rows' => collect(),
                'q' => '',
                'status' => '',
                'error' => 'No existe la tabla payments en p360v1_admin.',
            ]);
        }

        $q = trim((string) $req->get('q',''));
        $status = trim((string) $req->get('status',''));

        $qb = DB::connection($this->adm)->table('payments')->orderByDesc('id');

        if ($status !== '') {
            $qb->where('status', $status);
        }

        if ($q !== '') {
            $qb->where(function($w) use ($q) {
                $w->where('account_id', $q)
                  ->orWhere('stripe_session_id','like',"%{$q}%")
                  ->orWhere('stripe_payment_intent','like',"%{$q}%")
                  ->orWhere('stripe_invoice_id','like',"%{$q}%")
                  ->orWhere('reference','like',"%{$q}%");
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();
            $acc = DB::connection($this->adm)->table('accounts')
                ->select('id','email','rfc','razon_social','name')
                ->whereIn('id',$ids)
                ->get()
                ->keyBy('id');

            $rows->getCollection()->transform(function($p) use ($acc) {
                $a = $acc[$p->account_id] ?? null;
                $p->account_rfc = $a->rfc ?? null;
                $p->account_email = $a->email ?? null;
                $p->account_name = $a->razon_social ?? ($a->name ?? null);
                return $p;
            });
        }

        return view('admin.billing.payments.index', [
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'error' => null,
        ]);
    }

    public function manual(Request $req): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        $data = $req->validate([
            'account_id' => 'required|integer|min:1',
            'amount_pesos' => 'required|numeric|min:0.01|max:99999999',
            'currency' => 'nullable|string|max:10',
            'concept' => 'nullable|string|max:255',
            'period' => ['nullable','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'also_apply_statement' => 'nullable|boolean',
        ]);

        $accountId = (int) $data['account_id'];
        $amountCents = (int) round(((float) $data['amount_pesos']) * 100);
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'MXN')));
        if ($currency === '') $currency = 'MXN';

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc = array_map('strtolower', $cols);
        $has = fn(string $c) => in_array(strtolower($c), $lc, true);

        $row = [
            'account_id' => $accountId,
            'amount' => $amountCents,
            'currency' => $currency,
            'status' => 'paid',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($has('due_date')) $row['due_date'] = now();
        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type' => 'manual',
                'concept' => $data['concept'] ?? null,
                'period' => $data['period'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        }
        if ($has('reference')) $row['reference'] = 'manual:' . now()->format('YmdHis');
        if ($has('provider')) $row['provider'] = 'manual';
        if ($has('method')) $row['method'] = 'transfer';
        if ($has('concept') && !empty($data['concept'])) $row['concept'] = $data['concept'];
        if ($has('period') && !empty($data['period'])) $row['period'] = $data['period'];

        DB::connection($this->adm)->table('payments')->insert($row);

                 if (($data['also_apply_statement'] ?? false) === true) {
             if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) {
                 return back()->withErrors(['estados_cuenta' => 'No existe estados_cuenta; se registró el pago pero no se aplicó al estado de cuenta.']);
             }

             $period = $data['period'] ?? now()->format('Y-m');

             DB::connection($this->adm)->transaction(function () use ($accountId, $period, $data) {
                 DB::connection($this->adm)->table('estados_cuenta')->insert([
                     'account_id' => $accountId,
                     'periodo' => $period,
                     'concepto' => ($data['concept'] ?: 'Pago recibido (manual)'),
                     'detalle' => 'Registro manual en admin',
                     'cargo' => 0.00,
                     'abono' => round((float) $data['amount_pesos'], 2),
                     'saldo' => null,
                     'created_at' => now(),
                     'updated_at' => now(),
                 ]);

                 // recalcula saldo y lo guarda en el último movimiento
                 $items = DB::connection($this->adm)->table('estados_cuenta')
                     ->where('account_id', $accountId)
                     ->where('periodo', '=', $period)
                     ->orderByDesc('id')
                     ->get();

                 $saldo = max(0, (float) $items->sum('cargo') - (float) $items->sum('abono'));
                 $lastId = (int) ($items->first()->id ?? 0);

                 if ($lastId > 0) {
                     DB::connection($this->adm)->table('estados_cuenta')->where('id', $lastId)->update([
                         'saldo' => round($saldo, 2),
                         'updated_at' => now(),
                     ]);
                 }
             });
         }

        // ✅ P360: mantener accounts.estado_cuenta/billing_status alineado con billing_statements
        // (evita "Admin pendiente" cuando statements ya están paid/saldo=0)
        AccountBillingStateService::sync($accountId, 'admin.payments.manual');

         return back()->with('ok', 'Pago manual registrado.');
    }

    public function emailReceipt(Request $req, int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        $pay = DB::connection($this->adm)->table('payments')->where('id', $id)->first();
        abort_unless($pay, 404);

        $to = trim((string) $req->get('to',''));

        $acc = null;
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $acc = DB::connection($this->adm)->table('accounts')->where('id', (int) $pay->account_id)->first();
        }
        if ($to === '') $to = (string) ($acc->email ?? '');

        if ($to === '') return back()->withErrors(['to' => 'No hay correo destino.']);

        $data = [
            'payment' => $pay,
            'account' => $acc,
            'amount_pesos' => round(((int) $pay->amount) / 100, 2),
            'generated_at' => now(),
        ];

        Mail::send('admin.mail.payment_receipt', $data, function ($m) use ($to, $pay) {
            $m->to($to)->subject('Pactopia360 · Recibo de pago #'.$pay->id);
        });

        return back()->with('ok', 'Recibo reenviado por correo.');
    }
}
