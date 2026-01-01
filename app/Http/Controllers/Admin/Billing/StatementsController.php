<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Mail\Admin\Billing\StatementMail;
use App\Models\Admin\Billing\BillingStatement;
use App\Models\Admin\Billing\BillingStatementEvent;
use App\Models\Admin\Billing\BillingStatementItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class StatementsController extends Controller
{
    public function index(Request $req): View
    {
        $q      = trim((string)$req->get('q',''));
        $period = trim((string)$req->get('period', now()->format('Y-m')));
        $error  = null;

        try {
            $rows = BillingStatement::query()
                ->when($period !== '', fn($x) => $x->where('period', $period))
                ->when($q !== '', function($x) use ($q){
                    $like = '%'.$q.'%';
                    $x->where(function($w) use ($like){
                        $w->where('account_id','like',$like)
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(snapshot,'$.account.razon_social')) LIKE ?", [$like])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(snapshot,'$.account.nombre_comercial')) LIKE ?", [$like])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(snapshot,'$.account.rfc')) LIKE ?", [$like])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(snapshot,'$.account.email')) LIKE ?", [$like]);
                    });
                })
                ->orderBy('id','desc')
                ->paginate(20)
                ->withQueryString();
        } catch (\Throwable $e) {
            $rows = BillingStatement::query()->orderBy('id','desc')->paginate(20);
            $error = 'Error al cargar: '.$e->getMessage();
            Log::error('StatementsController.index.failed', ['err'=>$e->getMessage()]);
        }

        return view('admin.billing.statements.index', compact('rows','q','period','error'));
    }

    public function show(int $id): View
    {
        $st = BillingStatement::query()
            ->with(['items' => fn($q) => $q->orderBy('id','asc')])
            ->findOrFail($id);

        $snap = (array)($st->snapshot ?? []);
        $acc  = (array)($snap['account'] ?? []);

        // compat con tu blade show (variables existentes)
        $account = (object)[
            'id'            => (string)($acc['id'] ?? $st->account_id),
            'razon_social'   => (string)($acc['razon_social'] ?? ''),
            'name'          => (string)($acc['nombre_comercial'] ?? ''),
            'rfc'           => (string)($acc['rfc'] ?? ''),
            'email'         => (string)($acc['email'] ?? ''),
        ];

        $period = (string)$st->period;
        $period_label = $period;

        $cargo = (float)$st->total_cargo;
        $abono = (float)$st->total_abono;
        $saldo = (float)$st->saldo;

        // compat items: tu show.blade espera concepto/detalle/cargo/abono/saldo/created_at
        $running = 0.0;
        $items = $st->items->map(function($it) use (&$running){
            $amount = (float)$it->amount;
            $cargo  = $amount > 0 ? $amount : 0.0;
            $abono  = $amount < 0 ? abs($amount) : 0.0;
            $running += ($cargo - $abono);

            return (object)[
                'id'         => $it->id,
                'concepto'   => (string)$it->description,
                'detalle'    => (string)($it->meta['detail'] ?? $it->meta['notes'] ?? ''),
                'cargo'      => $cargo,
                'abono'      => $abono,
                'saldo'      => $running,
                'created_at' => $it->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return view('admin.billing.statements.show', compact(
            'st','account','period','period_label','cargo','abono','saldo','items'
        ));
    }

    public function email(Request $req, int $id)
    {
        $st = BillingStatement::query()->with('emails')->findOrFail($id);

        $to = trim((string)$req->input('to',''));
        $list = [];

        if ($to !== '') {
            $list = [mb_strtolower($to)];
        } else {
            // si va vacío, usa los correos sincronizados + fallback al snapshot account.email
            $list = $st->emails->pluck('email')->filter()->map(fn($x)=>mb_strtolower((string)$x))->values()->all();
            if (!$list) {
                $snap = (array)($st->snapshot ?? []);
                $acc  = (array)($snap['account'] ?? []);
                $em   = trim((string)($acc['email'] ?? ''));
                if ($em !== '') $list = [mb_strtolower($em)];
            }
        }

        if (!$list) {
            return back()->withErrors(['No hay correo destino disponible.']);
        }

        Mail::to($list)->send(new StatementMail($st->id));

        $st->sent_at = now();
        $st->save();

        BillingStatementEvent::create([
            'statement_id' => $st->id,
            'event'        => 'sent',
            'actor'        => 'admin',
            'notes'        => 'Sent from admin drawer',
            'meta'         => ['emails' => $list],
        ]);

        return back()->with('ok', 'Estado enviado a: '.implode(', ', $list));
    }

    public function addItem(Request $req, int $id)
    {
        $st = BillingStatement::query()->findOrFail($id);

        if ($st->is_locked) {
            return back()->withErrors(['El estado está bloqueado (pagado). Desbloquéalo antes de modificar.']);
        }

        $concepto = trim((string)$req->input('concepto',''));
        $detalle  = trim((string)$req->input('detalle',''));
        $cargoIn  = trim((string)$req->input('cargo',''));
        $abonoIn  = trim((string)$req->input('abono',''));

        if ($concepto === '') {
            return back()->withErrors(['Concepto es requerido.']);
        }

        $cargo = is_numeric($cargoIn) ? (float)$cargoIn : 0.0;
        $abono = is_numeric($abonoIn) ? (float)$abonoIn : 0.0;

        // Reglas: SOLO uno debe venir >0
        if ($cargo > 0 && $abono > 0) {
            return back()->withErrors(['Usa solo cargo o solo abono, no ambos.']);
        }
        if ($cargo <= 0 && $abono <= 0) {
            return back()->withErrors(['Debes indicar un cargo o un abono mayor a 0.']);
        }

        $amount = $cargo > 0 ? $cargo : -abs($abono);

        BillingStatementItem::create([
            'statement_id' => $st->id,
            'type'         => 'adjustment',
            'code'         => 'MANUAL',
            'description'  => $concepto,
            'qty'          => 1,
            'unit_price'   => abs($amount),
            'amount'       => $amount,
            'meta'         => [
                'detail' => $detalle,
                'source' => 'admin',
            ],
        ]);

        // Recalc totales rápido
        $items = BillingStatementItem::query()->where('statement_id',$st->id)->get(['amount']);
        $cargoT = 0.0; $abonoT = 0.0;
        foreach ($items as $it) {
            $a = (float)$it->amount;
            if ($a >= 0) $cargoT += $a;
            else $abonoT += abs($a);
        }
        $saldo = $cargoT - $abonoT;
        $status = $saldo == 0.0 ? 'paid' : ($saldo < 0.0 ? 'credit' : 'pending');

        $st->total_cargo = $cargoT;
        $st->total_abono = $abonoT;
        $st->saldo       = $saldo;
        $st->status      = $status;
        $st->save();

        BillingStatementEvent::create([
            'statement_id' => $st->id,
            'event'        => 'updated',
            'actor'        => 'admin',
            'notes'        => 'Manual item added',
            'meta'         => ['amount'=>$amount,'concepto'=>$concepto],
        ]);

        // auto-email opcional
        if ($req->boolean('send_email')) {
            try {
                $this->email(new Request(['to'=>'']), $st->id);
            } catch (\Throwable $e) {
                Log::warning('StatementsController.addItem.autoemail_failed', ['err'=>$e->getMessage()]);
            }
        }

        return back()->with('ok', 'Movimiento guardado.');
    }

    public function pdf(int $id)
    {
        // Stub: si aún no generas PDF real, devolvemos un HTML descargable (rápido) para no romper el botón.
        $st = BillingStatement::query()->with('items')->findOrFail($id);

        $html = view('emails.admin.billing.statement', ['st' => $st])->render();

        return Response::make($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="estado-cuenta-'.$st->period.'-'.$st->id.'.html"',
        ]);
    }
}
