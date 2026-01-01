<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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

        $period = $validated['period'];
        $amount = round((float)$validated['amount'], 2);
        $ref    = trim((string)($validated['ref'] ?? ''));
        $det    = trim((string)($validated['detalle'] ?? ''));

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $account)->first();
        abort_if(!$acc, 404);

        // Idempotencia simple por ref (si viene). Si no viene ref, insertamos.
        if ($ref !== '') {
            $exists = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $account)
                ->where('periodo', $period)
                ->where('source', 'manual')
                ->where('ref', $ref)
                ->exists();
            if ($exists) {
                return back()->with('ok', 'Abono manual ya existía (ref).');
            }
        }

        DB::connection($this->adm)->table('estados_cuenta')->insert([
            'account_id' => $account,
            'periodo'    => $period,
            'concepto'   => 'Pago manual (transferencia)',
            'detalle'    => $det !== '' ? $det : ('Registrado por Admin · ' . now()->toDateTimeString()),
            'cargo'      => 0,
            'abono'      => $amount,
            'saldo'      => null,
            'source'     => 'manual',
            'ref'        => $ref !== '' ? $ref : null,
            'meta'       => json_encode([
                'type'   => 'manual_payment',
                'by'     => 'admin',
                'at'     => now()->toISOString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('ok', 'Abono manual registrado.');
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
