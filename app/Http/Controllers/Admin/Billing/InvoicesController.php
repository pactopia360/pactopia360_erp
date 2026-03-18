<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class InvoicesController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $request): View
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return view('admin.billing.invoicing.invoices.index', [
                'rows'   => collect(),
                'error'  => 'No existe la tabla billing_invoices.',
                'status' => trim((string) $request->query('status', '')),
                'period' => trim((string) $request->query('period', '')),
                'q'      => trim((string) $request->query('q', '')),
            ]);
        }

        $q      = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $period = trim((string) $request->query('period', ''));

        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $qb = DB::connection($this->adm)
            ->table('billing_invoices')
            ->orderByDesc('id');

        if ($period !== '' && $has('period')) {
            $qb->where('period', $period);
        }

        if ($status !== '' && $has('status')) {
            $qb->where('status', $status);
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $has) {
                if ($has('id')) {
                    $w->orWhere('id', 'like', "%{$q}%");
                }
                if ($has('account_id')) {
                    $w->orWhere('account_id', 'like', "%{$q}%");
                }
                if ($has('request_id')) {
                    $w->orWhere('request_id', 'like', "%{$q}%");
                }
                if ($has('period')) {
                    $w->orWhere('period', 'like', "%{$q}%");
                }
                if ($has('cfdi_uuid')) {
                    $w->orWhere('cfdi_uuid', 'like', "%{$q}%");
                }
                if ($has('rfc')) {
                    $w->orWhere('rfc', 'like', "%{$q}%");
                }
                if ($has('razon_social')) {
                    $w->orWhere('razon_social', 'like', "%{$q}%");
                }
                if ($has('status')) {
                    $w->orWhere('status', 'like', "%{$q}%");
                }
                if ($has('source')) {
                    $w->orWhere('source', 'like', "%{$q}%");
                }
                if ($has('notes')) {
                    $w->orWhere('notes', 'like', "%{$q}%");
                }
                if ($has('folio')) {
                    $w->orWhere('folio', 'like', "%{$q}%");
                }
                if ($has('serie')) {
                    $w->orWhere('serie', 'like', "%{$q}%");
                }
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();

            if (!empty($ids)) {
                $accounts = DB::connection($this->adm)
                    ->table('accounts')
                    ->select(['id', 'email', 'rfc', 'razon_social', 'name', 'meta'])
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

                $rows->getCollection()->transform(function ($r) use ($accounts) {
                    $acc = $accounts[$r->account_id] ?? null;

                    $emails = [];
                    if ($acc && isset($acc->meta)) {
                        $emails = $this->extractEmailsFromMeta($acc->meta);
                    }

                    $meta = $this->decodeInvoiceMeta(data_get($r, 'meta'));

                    $r->account_email      = $acc->email ?? null;
                    $r->account_rfc        = $acc->rfc ?? ($r->rfc ?? null);
                    $r->account_name       = $acc->razon_social ?? ($acc->name ?? null);
                    $r->recipient_list     = $emails;
                    $r->display_total_mxn  = $this->resolveInvoiceAmountMxn($r);
                    $r->ui_tipo            = (string) ($meta['tipo_comprobante'] ?? data_get($r, 'tipo_comprobante', 'I'));
                    $r->ui_metodo_pago     = (string) ($meta['metodo_pago'] ?? data_get($r, 'metodo_pago', ''));
                    $r->ui_forma_pago      = (string) ($meta['forma_pago'] ?? data_get($r, 'forma_pago', ''));
                    $r->ui_complemento     = (string) ($meta['complemento'] ?? data_get($r, 'complemento', 'none'));
                    $r->ui_payment_status  = (string) ($meta['payment_status'] ?? 'pending');
                    $r->ui_has_complemento = $this->invoiceHasPaymentComplement($meta);

                    return $r;
                });
            }
        }

        return view('admin.billing.invoicing.invoices.index', [
            'rows'   => $rows,
            'error'  => null,
            'status' => $status,
            'period' => $period,
            'q'      => $q,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.billing.invoicing.invoices.create', [
            'routeStoreOne'         => route('admin.billing.invoicing.invoices.store_manual'),
            'routeFormSeed'         => route('admin.billing.invoicing.invoices.form_seed'),
            'routeSearchEmisores'   => route('admin.billing.invoicing.invoices.search_emisores'),
            'routeSearchReceptores' => route('admin.billing.invoicing.invoices.search_receptores'),
            'routeIndex'            => route('admin.billing.invoicing.invoices.index'),
            'routeDashboard'        => route('admin.billing.invoicing.dashboard'),
            'hasAnyErrors'          => $request->session()->get('errors')?->any() ?? false,
        ]);
    }

    public function show(int $id): View
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $invoice = DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $id)
            ->first();

        abort_unless($invoice, 404, 'Factura no encontrada.');

        $account = null;
        if (Schema::connection($this->adm)->hasTable('accounts') && !empty($invoice->account_id)) {
            $account = DB::connection($this->adm)
                ->table('accounts')
                ->where('id', (string) $invoice->account_id)
                ->first();
        }

        $requestRow = null;
        if (!empty($invoice->request_id)) {
            if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                $requestRow = DB::connection($this->adm)
                    ->table('billing_invoice_requests')
                    ->where('id', (int) $invoice->request_id)
                    ->first();
            }

            if (!$requestRow && Schema::connection($this->adm)->hasTable('invoice_requests')) {
                $requestRow = DB::connection($this->adm)
                    ->table('invoice_requests')
                    ->where('id', (int) $invoice->request_id)
                    ->first();
            }
        }

        $meta = $this->decodeInvoiceMeta(data_get($invoice, 'meta'));
        $invoice->display_total_mxn   = $this->resolveInvoiceAmountMxn($invoice);
        $invoice->resolved_recipients = $this->resolveRecipientsForInvoice($invoice, $account, null);

        $invoice->ui_tipo            = (string) ($meta['tipo_comprobante'] ?? data_get($invoice, 'tipo_comprobante', 'I'));
        $invoice->ui_complemento     = (string) ($meta['complemento'] ?? data_get($invoice, 'complemento', 'none'));
        $invoice->ui_metodo_pago     = (string) ($meta['metodo_pago'] ?? data_get($invoice, 'metodo_pago', ''));
        $invoice->ui_forma_pago      = (string) ($meta['forma_pago'] ?? data_get($invoice, 'forma_pago', ''));
        $invoice->ui_payment_status  = (string) ($meta['payment_status'] ?? 'pending');
        $invoice->ui_payment_summary = $meta['payment_summary'] ?? [
            'paid_amount'      => 0,
            'pending_amount'   => $invoice->display_total_mxn ?? 0,
            'partiality_count' => 0,
            'complements_count'=> 0,
        ];

        return view('admin.billing.invoicing.invoices.show', [
            'invoice'    => $invoice,
            'account'    => $account,
            'requestRow' => $requestRow,
            'meta'       => $meta,
        ]);
    }

    public function download(int $id, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['pdf', 'xml'], true), 404);
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $invoice = DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $id)
            ->first();

        abort_unless($invoice, 404, 'Factura no encontrada.');

        $disk = (string) ($invoice->disk ?? 'local');
        $path = $kind === 'pdf'
            ? (string) ($invoice->pdf_path ?? '')
            : (string) ($invoice->xml_path ?? '');

        $name = $kind === 'pdf'
            ? (string) ($invoice->pdf_name ?? '')
            : (string) ($invoice->xml_name ?? '');

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404, 'Archivo no disponible.');

        $downloadAs = $name !== '' ? $name : basename($path);
        $mime       = $kind === 'pdf' ? 'application/pdf' : 'application/xml';

        return Storage::disk($disk)->download($path, $downloadAs, [
            'Content-Type' => $mime,
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $data = $request->validate([
            'account_id'         => 'required|string|max:64',
            'period'             => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'tipo_comprobante'   => 'nullable|string|in:I,E,P,N,T',
            'complemento'        => 'nullable|string|max:50',
            'cfdi_uuid'          => 'nullable|string|max:80',
            'serie'              => 'nullable|string|max:20',
            'folio'              => 'nullable|string|max:40',
            'status'             => 'nullable|string|max:40',
            'issued_at'          => 'nullable|date',
            'issued_date'        => 'nullable|date',
            'amount_mxn'         => 'nullable|numeric|min:0|max:999999999',
            'source'             => 'nullable|string|max:50',
            'notes'              => 'nullable|string|max:5000',

            'emisor_id'          => 'nullable|string|max:50',
            'receptor_id'        => 'nullable|string|max:50',
            'uso_cfdi'           => 'nullable|string|max:10',
            'regimen_fiscal'     => 'nullable|string|max:10',
            'forma_pago'         => 'nullable|string|max:10',
            'metodo_pago'        => 'nullable|string|max:10',
            'moneda'             => 'nullable|string|max:10',
            'exportacion'        => 'nullable|string|max:10',
            'objeto_impuesto'    => 'nullable|string|max:10',
            'tasa_iva'           => 'nullable|string|max:20',

            'wizard_step'        => 'nullable|string|max:10',
            'clone_mode'         => 'nullable|string|max:20',

            'pdf'                => 'nullable|file|mimes:pdf|max:20480',
            'xml'                => 'nullable|file|mimes:xml,txt|max:20480',
        ]);

        if (!$request->hasFile('pdf') && !$request->hasFile('xml')) {
            return back()
                ->withErrors(['invoice' => 'Debes adjuntar al menos PDF o XML.'])
                ->withInput();
        }

        $accountId = trim((string) $data['account_id']);
        $period    = trim((string) $data['period']);
        $uuid      = trim((string) ($data['cfdi_uuid'] ?? ''));
        $source    = trim((string) ($data['source'] ?? 'manual_admin'));
        $status    = trim((string) ($data['status'] ?? 'issued'));
        $notes     = trim((string) ($data['notes'] ?? ''));

        $account = $this->findAccount($accountId);

        $smartMeta = $this->buildInvoiceSmartMeta(
            request: $request,
            data: $data,
            amountMxn: isset($data['amount_mxn']) ? (float) $data['amount_mxn'] : null
        );

        $saved = $this->upsertInvoiceWithFiles(
            accountId: $accountId,
            period: $period,
            requestId: null,
            uuid: $uuid !== '' ? $uuid : null,
            source: $source !== '' ? $source : 'manual_admin',
            status: $status !== '' ? $status : 'issued',
            issuedAt: $this->normalizeIssuedAt(
                (string) ($data['issued_at'] ?? ''),
                (string) ($data['issued_date'] ?? '')
            ),
            amountMxn: isset($data['amount_mxn']) ? (float) $data['amount_mxn'] : null,
            notes: $notes !== '' ? $notes : null,
            serie: ($data['serie'] ?? null) ?: null,
            folio: ($data['folio'] ?? null) ?: null,
            account: $account,
            pdfFile: $request->file('pdf'),
            xmlFile: $request->file('xml'),
            smartMeta: $smartMeta
        );

        return redirect()
            ->route('admin.billing.invoicing.invoices.show', (int) ($saved->id ?? 0))
            ->with('ok', 'Factura manual guardada correctamente.')
            ->with('open_delivery_modal', true);
    }

    public function bulkStoreManual(Request $request): RedirectResponse
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $data = $request->validate([
            'rows'          => 'nullable|array',
            'account_id'    => 'nullable|array',
            'account_id.*'  => 'nullable|string|max:64',
            'period'        => 'nullable|array',
            'period.*'      => 'nullable|string|max:7',
            'cfdi_uuid'     => 'nullable|array',
            'cfdi_uuid.*'   => 'nullable|string|max:80',
            'serie'         => 'nullable|array',
            'serie.*'       => 'nullable|string|max:20',
            'folio'         => 'nullable|array',
            'folio.*'       => 'nullable|string|max:40',
            'status'        => 'nullable|array',
            'status.*'      => 'nullable|string|max:40',
            'amount_mxn'    => 'nullable|array',
            'amount_mxn.*'  => 'nullable',
            'issued_at'     => 'nullable|array',
            'issued_at.*'   => 'nullable|string|max:30',
            'issued_date'   => 'nullable|array',
            'issued_date.*' => 'nullable|string|max:30',
            'source'        => 'nullable|array',
            'source.*'      => 'nullable|string|max:30',
            'notes'         => 'nullable|array',
            'notes.*'       => 'nullable|string|max:5000',
            'send_now'      => 'nullable',
            'to'            => 'nullable|string|max:5000',
            'pdf_files'     => 'nullable|array',
            'pdf_files.*'   => 'nullable|file|mimes:pdf|max:20480',
            'xml_files'     => 'nullable|array',
            'xml_files.*'   => 'nullable|file|mimes:xml,txt|max:20480',
        ]);

        $accountIds = (array) ($data['account_id'] ?? []);
        $periods    = (array) ($data['period'] ?? []);

        $count = max(count($accountIds), count($periods));
        if ($count <= 0) {
            return back()
                ->withErrors(['invoice' => 'No se recibieron filas para carga masiva.'])
                ->withInput();
        }

        $created  = 0;
        $failed   = 0;
        $errors   = [];
        $savedIds = [];

        for ($i = 0; $i < $count; $i++) {
            $accountId = trim((string) ($accountIds[$i] ?? ''));
            $period    = trim((string) ($periods[$i] ?? ''));

            if ($accountId === '' && $period === '') {
                continue;
            }

            if ($accountId === '' || !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
                $failed++;
                $errors[] = 'Fila ' . ($i + 1) . ': account_id o period inválidos.';
                continue;
            }

            try {
                $account = $this->findAccount($accountId);

                $saved = $this->upsertInvoiceWithFiles(
                    accountId: $accountId,
                    period: $period,
                    requestId: null,
                    uuid: $this->nullableString(($data['cfdi_uuid'][$i] ?? null)),
                    source: $this->nullableString(($data['source'][$i] ?? null)) ?: 'manual_bulk_admin',
                    status: $this->nullableString(($data['status'][$i] ?? null)) ?: 'issued',
                    issuedAt: $this->normalizeIssuedAt(
                        (string) (($data['issued_at'][$i] ?? '') ?: ''),
                        (string) (($data['issued_date'][$i] ?? '') ?: '')
                    ),
                    amountMxn: $this->nullableFloat($data['amount_mxn'][$i] ?? null),
                    notes: $this->nullableString(($data['notes'][$i] ?? null)),
                    serie: $this->nullableString(($data['serie'][$i] ?? null)),
                    folio: $this->nullableString(($data['folio'][$i] ?? null)),
                    account: $account,
                    pdfFile: $request->file("pdf_files.$i"),
                    xmlFile: $request->file("xml_files.$i"),
                    smartMeta: null
                );

                $savedIds[] = (int) ($saved->id ?? 0);
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = 'Fila ' . ($i + 1) . ': ' . $e->getMessage();

                Log::error('[BILLING][INVOICES] bulkStoreManual failed', [
                    'row'        => $i,
                    'account_id' => $accountId,
                    'period'     => $period,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($created <= 0) {
            return back()->withErrors([
                'invoice' => 'No se pudo guardar ninguna factura. ' . implode(' | ', array_slice($errors, 0, 5)),
            ])->withInput();
        }

        if ($request->boolean('send_now') && !empty($savedIds)) {
            $sent = $this->bulkSendNow($savedIds, (string) ($data['to'] ?? ''));

            $msg = "Carga masiva completada. guardadas={$created}, fallidas={$failed}, enviadas={$sent['sent']}, envio_fallidas={$sent['failed']}.";
            if (!empty($errors)) {
                $msg .= ' Detalle: ' . implode(' | ', array_slice($errors, 0, 3));
            }

            return back()->with('ok', $msg);
        }

        $msg = "Carga masiva completada. guardadas={$created}, fallidas={$failed}.";
        if (!empty($errors)) {
            $msg .= ' Detalle: ' . implode(' | ', array_slice($errors, 0, 3));
        }

        return back()->with('ok', $msg);
    }

    public function send(Request $request, int $id): RedirectResponse
    {
        $result = $this->sendInvoiceNow($id, (string) $request->input('to', ''), false);

        if (!$result['ok']) {
            return back()->withErrors(['mail' => $result['message']]);
        }

        return back()->with('ok', $result['message']);
    }

    public function resend(Request $request, int $id): RedirectResponse
    {
        $result = $this->sendInvoiceNow($id, (string) $request->input('to', ''), true);

        if (!$result['ok']) {
            return back()->withErrors(['mail' => $result['message']]);
        }

        return back()->with('ok', $result['message']);
    }

    public function bulkSend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_ids' => 'required|string|max:30000',
            'to'          => 'nullable|string|max:5000',
        ]);

        $ids = $this->parseIdCsv((string) $data['invoice_ids']);
        if (empty($ids)) {
            return back()->withErrors(['mail' => 'No hay facturas seleccionadas para envío.']);
        }

        $result = $this->bulkSendNow(array_map('intval', $ids), (string) ($data['to'] ?? ''));

        $msg = "Envío masivo completado. enviadas={$result['sent']}, fallidas={$result['failed']}.";
        if (!empty($result['errors'])) {
            $msg .= ' Detalle: ' . implode(' | ', array_slice($result['errors'], 0, 3));
        }

        if ($result['sent'] <= 0) {
            return back()->withErrors(['mail' => $msg]);
        }

        return back()->with('ok', $msg);
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $invoice = DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $id)
            ->first();

        if (!$invoice) {
            return back()->withErrors(['invoice' => 'Factura no encontrada.']);
        }

        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $upd = [];

        if ($has('status')) {
            $upd['status'] = 'cancelled';
        }

        if ($has('notes')) {
            $prev         = trim((string) ($invoice->notes ?? ''));
            $msg          = 'Factura cancelada manualmente desde admin el ' . now()->format('Y-m-d H:i:s');
            $upd['notes'] = $prev !== '' ? ($prev . "\n" . $msg) : $msg;
        }

        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (!empty($upd)) {
            $meta = $this->decodeInvoiceMeta(data_get($invoice, 'meta'));
            $meta['ui'] = array_merge((array) ($meta['ui'] ?? []), [
                'cancelled_at' => now()->format('Y-m-d H:i:s'),
                'cancelled_by' => 'admin',
            ]);

            if ($has('meta')) {
                $upd['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }
        }

        if (empty($upd)) {
            return back()->withErrors([
                'invoice' => 'La tabla billing_invoices no tiene columnas actualizables para cancelar.',
            ]);
        }

        try {
            DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('id', $id)
                ->update($upd);

            if (!empty($invoice->request_id)) {
                if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                    $this->touchInvoiceRequest('billing_invoice_requests', (int) $invoice->request_id);
                } elseif (Schema::connection($this->adm)->hasTable('invoice_requests')) {
                    $this->touchInvoiceRequest('invoice_requests', (int) $invoice->request_id);
                }
            }

            return back()->with('ok', 'Factura cancelada correctamente.');
        } catch (Throwable $e) {
            Log::error('[BILLING][INVOICES] cancel failed', [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return back()->withErrors([
                'invoice' => 'No se pudo cancelar la factura: ' . $e->getMessage(),
            ]);
        }
    }

    private function bulkSendNow(array $invoiceIds, string $toRaw): array
    {
        $sent   = 0;
        $failed = 0;
        $errors = [];

        foreach ($invoiceIds as $invoiceId) {
            $result = $this->sendInvoiceNow((int) $invoiceId, $toRaw, false);

            if ($result['ok']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = 'Factura #' . $invoiceId . ': ' . $result['message'];
            }
        }

        return [
            'sent'   => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function sendInvoiceNow(int $invoiceId, string $toRaw, bool $isResend): array
    {
        try {
            abort_unless(
                Schema::connection($this->adm)->hasTable('billing_invoices'),
                404,
                'No existe la tabla billing_invoices.'
            );

            $invoice = DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('id', $invoiceId)
                ->first();

            if (!$invoice) {
                return ['ok' => false, 'message' => 'Factura no encontrada.'];
            }

            $account    = $this->findAccount((string) ($invoice->account_id ?? ''));
            $recipients = $this->resolveRecipientsForInvoice($invoice, $account, $toRaw);

            if (empty($recipients)) {
                return ['ok' => false, 'message' => 'No hay correos destino válidos.'];
            }

            $pdfDisk = (string) ($invoice->disk ?? 'local');
            $pdfPath = (string) ($invoice->pdf_path ?? '');
            $pdfName = (string) ($invoice->pdf_name ?? '');

            $xmlDisk = (string) ($invoice->disk ?? 'local');
            $xmlPath = (string) ($invoice->xml_path ?? '');
            $xmlName = (string) ($invoice->xml_name ?? '');

            $hasPdf = $pdfPath !== '' && Storage::disk($pdfDisk)->exists($pdfPath);
            $hasXml = $xmlPath !== '' && Storage::disk($xmlDisk)->exists($xmlPath);

            if (!$hasPdf && !$hasXml) {
                return ['ok' => false, 'message' => 'La factura no tiene PDF/XML disponibles para enviar.'];
            }

            $period    = (string) ($invoice->period ?? '');
            $portalUrl = url('/cliente/mi-cuenta/facturas');
            $subject   = 'Pactopia360 · Factura disponible' . ($period !== '' ? (' · ' . $period) : '');

            $bodyHtml = view('admin.mail.invoice_ready_simple', [
                'account'   => $account,
                'req'       => (object) [
                    'account_name' => $account->razon_social ?? ($account->name ?? ''),
                ],
                'invoice'   => $invoice,
                'period'    => $period,
                'portalUrl' => $portalUrl,
                'hasZip'    => false,
                'hasPdf'    => $hasPdf,
                'hasXml'    => $hasXml,
                'emails'    => $recipients,
            ])->render();

            Mail::send([], [], function ($m) use (
                $recipients,
                $subject,
                $bodyHtml,
                $hasPdf,
                $pdfDisk,
                $pdfPath,
                $pdfName,
                $hasXml,
                $xmlDisk,
                $xmlPath,
                $xmlName
            ) {
                $targets = array_values($recipients);
                $primary = array_shift($targets);

                $m->to($primary)->subject($subject);

                foreach ($targets as $cc) {
                    $m->cc($cc);
                }

                $m->html($bodyHtml);

                if ($hasPdf) {
                    $stream = Storage::disk($pdfDisk)->readStream($pdfPath);
                    if (is_resource($stream)) {
                        $m->attachData(
                            (string) stream_get_contents($stream),
                            $pdfName !== '' ? $pdfName : basename($pdfPath),
                            ['mime' => 'application/pdf']
                        );
                        @fclose($stream);
                    }
                }

                if ($hasXml) {
                    $stream = Storage::disk($xmlDisk)->readStream($xmlPath);
                    if (is_resource($stream)) {
                        $m->attachData(
                            (string) stream_get_contents($stream),
                            $xmlName !== '' ? $xmlName : basename($xmlPath),
                            ['mime' => 'application/xml']
                        );
                        @fclose($stream);
                    }
                }
            });

            $this->markInvoiceSent($invoiceId, $invoice, $recipients, $isResend);

            return [
                'ok'      => true,
                'message' => $isResend ? 'Factura reenviada correctamente.' : 'Factura enviada correctamente.',
            ];
        } catch (Throwable $e) {
            Log::error('[BILLING][INVOICES] sendInvoiceNow failed', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);

            return [
                'ok'      => false,
                'message' => 'Falló el envío: ' . $e->getMessage(),
            ];
        }
    }

    private function markInvoiceSent(int $invoiceId, object $invoice, array $recipients, bool $isResend): void
    {
        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $upd = [];

        if ($has('status')) {
            $upd['status'] = 'sent';
        }
        if ($has('emailed_to')) {
            $upd['emailed_to'] = json_encode(array_values($recipients), JSON_UNESCAPED_UNICODE);
        }
        if ($has('sent_at')) {
            $upd['sent_at'] = now();
        }
        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }
        if ($has('notes')) {
            $prev         = trim((string) ($invoice->notes ?? ''));
            $msg          = ($isResend ? 'Factura reenviada' : 'Factura enviada') . ' el ' . now()->format('Y-m-d H:i:s');
            $upd['notes'] = $prev !== '' ? ($prev . "\n" . $msg) : $msg;
        }

        if ($has('meta')) {
            $meta = $this->decodeInvoiceMeta(data_get($invoice, 'meta'));
            $meta['delivery'] = array_merge((array) ($meta['delivery'] ?? []), [
                'last_sent_at'  => now()->format('Y-m-d H:i:s'),
                'last_sent_to'  => array_values($recipients),
                'last_sent_kind'=> $isResend ? 'resend' : 'send',
            ]);

            $upd['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($upd)) {
            DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('id', $invoiceId)
                ->update($upd);
        }
    }

    private function upsertInvoiceWithFiles(
        string $accountId,
        string $period,
        ?int $requestId,
        ?string $uuid,
        string $source,
        string $status,
        ?string $issuedAt,
        ?float $amountMxn,
        ?string $notes,
        ?string $serie,
        ?string $folio,
        ?object $account,
        mixed $pdfFile,
        mixed $xmlFile,
        ?array $smartMeta = null
    ): object {
        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $disk = 'local';
        $tag  = $uuid !== null && $uuid !== ''
            ? preg_replace('/[^A-Za-z0-9\-]/', '', $uuid)
            : ('manual_' . Str::upper(Str::random(10)));

        $dir     = "billing/invoices/{$accountId}/{$period}";
        $payload = [];

        if ($has('account_id')) {
            $payload['account_id'] = $accountId;
        }
        if ($has('period')) {
            $payload['period'] = $period;
        }
        if ($has('request_id')) {
            $payload['request_id'] = $requestId;
        }
        if ($has('source')) {
            $payload['source'] = $source;
        }
        if ($has('cfdi_uuid')) {
            $payload['cfdi_uuid'] = $uuid;
        }
        if ($has('status')) {
            $payload['status'] = $status;
        }
        if ($has('disk')) {
            $payload['disk'] = $disk;
        }
        if ($has('notes')) {
            $payload['notes'] = $notes;
        }
        if ($has('serie')) {
            $payload['serie'] = $serie;
        }
        if ($has('folio')) {
            $payload['folio'] = $folio;
        }

        if ($has('rfc')) {
            $payload['rfc'] = $account->rfc ?? null;
        }
        if ($has('razon_social')) {
            $payload['razon_social'] = $account->razon_social ?? ($account->name ?? null);
        }

        if ($smartMeta !== null) {
            if ($has('tipo_comprobante')) {
                $payload['tipo_comprobante'] = (string) ($smartMeta['tipo_comprobante'] ?? 'I');
            }
            if ($has('complemento')) {
                $payload['complemento'] = (string) ($smartMeta['complemento'] ?? 'none');
            }
            if ($has('uso_cfdi')) {
                $payload['uso_cfdi'] = $this->nullableString($smartMeta['uso_cfdi'] ?? null);
            }
            if ($has('regimen_fiscal')) {
                $payload['regimen_fiscal'] = $this->nullableString($smartMeta['regimen_fiscal'] ?? null);
            }
            if ($has('forma_pago')) {
                $payload['forma_pago'] = $this->nullableString($smartMeta['forma_pago'] ?? null);
            }
            if ($has('metodo_pago')) {
                $payload['metodo_pago'] = $this->nullableString($smartMeta['metodo_pago'] ?? null);
            }
            if ($has('moneda')) {
                $payload['moneda'] = $this->nullableString($smartMeta['moneda'] ?? null) ?: 'MXN';
            }
            if ($has('currency') && empty($payload['currency'])) {
                $payload['currency'] = $this->nullableString($smartMeta['moneda'] ?? null) ?: 'MXN';
            }
        }

        if ($issuedAt !== null) {
            if ($has('issued_at')) {
                $payload['issued_at'] = $issuedAt;
            }
            if ($has('issued_date')) {
                $payload['issued_date'] = Carbon::parse($issuedAt)->toDateString();
            }
        }

        if ($amountMxn !== null) {
            if ($has('amount_mxn')) {
                $payload['amount_mxn'] = round($amountMxn, 2);
            }
            if ($has('monto_mxn')) {
                $payload['monto_mxn'] = round($amountMxn, 2);
            }
            if ($has('amount_cents')) {
                $payload['amount_cents'] = (int) round($amountMxn * 100);
            }
            if ($has('amount')) {
                $payload['amount'] = (int) round($amountMxn * 100);
            }
            if ($has('total')) {
                $payload['total'] = round($amountMxn, 2);
            }
            if ($has('subtotal')) {
                $payload['subtotal'] = round($amountMxn, 2);
            }
        }

        if ($has('currency') && empty($payload['currency'])) {
            $payload['currency'] = 'MXN';
        }

        if ($pdfFile) {
            $pdfName = "CFDI_{$period}_{$tag}_" . now()->format('Ymd_His') . '.pdf';
            $pdfPath = $pdfFile->storeAs($dir, $pdfName, $disk);
            $pdfFull = Storage::disk($disk)->path($pdfPath);

            if ($has('pdf_path')) {
                $payload['pdf_path'] = $pdfPath;
            }
            if ($has('pdf_name')) {
                $payload['pdf_name'] = $pdfName;
            }
            if ($has('pdf_size')) {
                $payload['pdf_size'] = @filesize($pdfFull) ?: null;
            }
            if ($has('pdf_sha1')) {
                $payload['pdf_sha1'] = @sha1_file($pdfFull) ?: null;
            }
        }

        if ($xmlFile) {
            $xmlName = "CFDI_{$period}_{$tag}_" . now()->format('Ymd_His') . '.xml';
            $xmlPath = $xmlFile->storeAs($dir, $xmlName, $disk);
            $xmlFull = Storage::disk($disk)->path($xmlPath);

            if ($has('xml_path')) {
                $payload['xml_path'] = $xmlPath;
            }
            if ($has('xml_name')) {
                $payload['xml_name'] = $xmlName;
            }
            if ($has('xml_size')) {
                $payload['xml_size'] = @filesize($xmlFull) ?: null;
            }
            if ($has('xml_sha1')) {
                $payload['xml_sha1'] = @sha1_file($xmlFull) ?: null;
            }
        }

        $now = now();
        if ($has('updated_at')) {
            $payload['updated_at'] = $now;
        }

        $existing = null;

        if ($uuid !== null && $uuid !== '' && $has('cfdi_uuid')) {
            $existing = DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->where('cfdi_uuid', $uuid)
                ->first();
        }

        if (!$existing && $requestId !== null && $has('request_id')) {
            $existing = DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('request_id', $requestId)
                ->orderByDesc('id')
                ->first();
        }

        if (!$existing) {
            $existing = DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->orderByDesc('id')
                ->first();
        }

        if ($smartMeta !== null && $has('meta')) {
            $prevMeta = $existing ? $this->decodeInvoiceMeta(data_get($existing, 'meta')) : [];
            $payload['meta'] = json_encode(
                $this->mergeInvoiceMeta($prevMeta, $smartMeta),
                JSON_UNESCAPED_UNICODE
            );
        }

        if ($existing) {
            DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('id', (int) $existing->id)
                ->update($payload);

            return (object) DB::connection($this->adm)
                ->table('billing_invoices')
                ->where('id', (int) $existing->id)
                ->first();
        }

        if ($has('created_at')) {
            $payload['created_at'] = $now;
        }

        $newId = (int) DB::connection($this->adm)
            ->table('billing_invoices')
            ->insertGetId($payload);

        return (object) DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $newId)
            ->first();
    }

    private function resolveRecipientsForInvoice(object $invoice, ?object $account, ?string $manualTo): array
    {
        $all = [];

        $manual = $this->parseToList((string) ($manualTo ?? ''));
        foreach ($manual as $mail) {
            $all[] = $mail;
        }

        if ($account) {
            $mail = strtolower(trim((string) ($account->email ?? '')));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $all[] = $mail;
            }

            if (isset($account->meta)) {
                foreach ($this->extractEmailsFromMeta($account->meta) as $mail) {
                    $all[] = $mail;
                }
            }
        }

        if (isset($invoice->emailed_to) && is_string($invoice->emailed_to) && trim($invoice->emailed_to) !== '') {
            $decoded = json_decode((string) $invoice->emailed_to, true);
            if (is_array($decoded)) {
                foreach ($decoded as $mail) {
                    if (is_string($mail) && filter_var(trim($mail), FILTER_VALIDATE_EMAIL)) {
                        $all[] = strtolower(trim($mail));
                    }
                }
            }
        }

        $meta = $this->decodeInvoiceMeta(data_get($invoice, 'meta'));
        $metaEmail = trim((string) data_get($meta, 'partes.receptor.email', ''));
        if ($metaEmail !== '' && filter_var($metaEmail, FILTER_VALIDATE_EMAIL)) {
            $all[] = strtolower($metaEmail);
        }

        return array_values(array_unique(array_filter($all)));
    }

    private function extractEmailsFromMeta(mixed $meta): array
    {
        $arr = [];

        try {
            if (is_string($meta) && trim($meta) !== '') {
                $arr = json_decode($meta, true) ?: [];
            } elseif (is_array($meta)) {
                $arr = $meta;
            } elseif (is_object($meta)) {
                $arr = (array) $meta;
            }
        } catch (Throwable $e) {
            $arr = [];
        }

        $emails = [];
        foreach ([
            data_get($arr, 'billing.emails'),
            data_get($arr, 'billing.recipients'),
            data_get($arr, 'invoicing.emails'),
        ] as $value) {
            if (is_array($value)) {
                foreach ($value as $mail) {
                    if (is_string($mail) && filter_var(trim($mail), FILTER_VALIDATE_EMAIL)) {
                        $emails[] = strtolower(trim($mail));
                    }
                }
            } elseif (is_string($value) && trim($value) !== '') {
                foreach (preg_split('/[;,]+/', $value) ?: [] as $mail) {
                    $mail = strtolower(trim((string) $mail));
                    if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $mail;
                    }
                }
            }
        }

        return array_values(array_unique($emails));
    }

    private function resolveInvoiceAmountMxn(object $row): ?float
    {
        foreach (['amount_mxn', 'monto_mxn', 'total', 'subtotal'] as $k) {
            $v = data_get($row, $k);
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return round((float) $v, 2);
            }
        }

        foreach (['amount_cents', 'amount'] as $k) {
            $v = data_get($row, $k);
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return round(((float) $v) / 100, 2);
            }
        }

        return null;
    }

    private function findAccount(string $accountId): ?object
    {
        if ($accountId === '') {
            return null;
        }

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return null;
        }

        return DB::connection($this->adm)
            ->table('accounts')
            ->where('id', $accountId)
            ->first();
    }

    private function normalizeIssuedAt(string $issuedAt, string $issuedDate): ?string
    {
        $issuedAt   = trim($issuedAt);
        $issuedDate = trim($issuedDate);

        try {
            if ($issuedAt !== '') {
                return Carbon::parse($issuedAt)->format('Y-m-d H:i:s');
            }

            if ($issuedDate !== '') {
                return Carbon::parse($issuedDate)->startOfDay()->format('Y-m-d H:i:s');
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        return $s !== '' ? $s : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $s = str_replace(['$', ',', 'MXN', 'mxn', ' '], '', (string) $value);
        return is_numeric($s) ? (float) $s : null;
    }

    private function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $raw   = str_replace([';', "\n", "\r", "\t"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $p = trim((string) $m[1]);
            }

            if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($p);
            }
        }

        return array_values(array_unique(array_slice($out, 0, 50)));
    }

    private function parseIdCsv(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $raw   = str_replace([";", "\n", "\r", "\t", ' '], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (!preg_match('/^[a-zA-Z0-9\-\_]{1,80}$/', $p)) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    private function touchInvoiceRequest(string $table, int $requestId): void
    {
        if (!Schema::connection($this->adm)->hasTable($table)) {
            return;
        }

        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $upd = [];

        if ($has('status')) {
            $upd['status'] = 'rejected';
        }

        if ($has('estatus')) {
            $upd['estatus'] = 'rejected';
        }

        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (!empty($upd)) {
            DB::connection($this->adm)
                ->table($table)
                ->where('id', $requestId)
                ->update($upd);
        }
    }

    public function stamp(Request $request, int $id): RedirectResponse
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $invoice = DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $id)
            ->first();

        if (!$invoice) {
            return redirect()->back()->withErrors([
                'invoice' => 'Factura no encontrada.',
            ]);
        }

        if (!empty($invoice->cfdi_uuid)) {
            return redirect()->back()->withErrors([
                'invoice' => 'La factura ya está timbrada y tiene UUID asignado.',
            ]);
        }

        $requestId = (int) ($invoice->request_id ?? 0);
        if ($requestId <= 0) {
            return redirect()->back()->withErrors([
                'invoice' => 'Esta factura no está vinculada a una solicitud real de facturación. El timbrado manual legacy fue deshabilitado; genera o vincula primero la solicitud y luego timbra desde el flujo oficial de Facturotopia.',
            ]);
        }

        $requestExists = false;

        if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $requestExists = DB::connection($this->adm)
                ->table('billing_invoice_requests')
                ->where('id', $requestId)
                ->exists();
        }

        if (!$requestExists && Schema::connection($this->adm)->hasTable('invoice_requests')) {
            $requestExists = DB::connection($this->adm)
                ->table('invoice_requests')
                ->where('id', $requestId)
                ->exists();
        }

        if (!$requestExists) {
            return redirect()->back()->withErrors([
                'invoice' => 'La solicitud asociada a esta factura ya no existe. No se puede timbrar por el flujo oficial.',
            ]);
        }

        try {
            /** @var InvoiceRequestsController $controller */
            $controller = app(InvoiceRequestsController::class);

            return $controller->approveAndGenerate($request, $requestId);
        } catch (Throwable $e) {
            Log::error('[BILLING][INVOICES] stamp redirect to request flow failed', [
                'invoice_id' => $id,
                'request_id' => $requestId,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'invoice' => 'No se pudo timbrar por el flujo oficial de Facturotopia: ' . $e->getMessage(),
            ]);
        }
    }

    public function formSeed(Request $request): JsonResponse
    {
        $catalogos = $this->loadInvoiceCatalogs();

        $defaults = [
            'tipo_comprobante' => 'I',
            'moneda'           => 'MXN',
            'exportacion'      => '01',
            'metodo_pago'      => 'PUE',
            'forma_pago'       => '03',
            'uso_cfdi'         => 'G03',
            'objeto_impuesto'  => '02',
            'clave_unidad'     => 'E48',
            'unidad'           => 'Servicio',
            'clave_prod_serv'  => '81112100',
            'impuesto'         => '002',
            'tipo_factor'      => 'Tasa',
            'tasa_cuota'       => '0.160000',
            'source'           => 'manual_admin_ai',
            'status'           => 'issued',
            'complemento'      => 'none',
        ];

        return response()->json([
            'ok' => true,
            'data' => [
                'usos_cfdi'          => $catalogos['usos_cfdi'] ?? [],
                'regimenes_fiscales' => $catalogos['regimenes_fiscales'] ?? [],
                'formas_pago'        => $catalogos['formas_pago'] ?? [],
                'metodos_pago'       => $catalogos['metodos_pago'] ?? [],
                'monedas'            => $catalogos['monedas'] ?? [],
                'exportaciones'      => $catalogos['exportaciones'] ?? [],
                'objetos_impuesto'   => $catalogos['objetos_impuesto'] ?? [],
                'tipos_comprobante'  => $catalogos['tipos_comprobante'] ?? [],
                'impuestos'          => $catalogos['impuestos'] ?? [],
                'tipos_factor'       => $catalogos['tipos_factor'] ?? [],
                'complementos'       => $catalogos['complementos'] ?? [],
            ],
            'catalogos' => $catalogos,
            'defaults'  => $defaults,
            'assistant' => [
                'enabled' => true,
                'label'   => 'Copiloto de facturación',
                'tips'    => [
                    'Primero selecciona emisor y receptor para rellenar datos más rápido.',
                    'Si el receptor tiene email, se sugerirá automáticamente en el campo de envío.',
                    'Si el receptor trae régimen fiscal, se intentará aplicar al formulario.',
                    'El periodo actual y el origen sugerido pueden llenarse con un clic.',
                    'Si eliges PPD, esta factura deberá mostrar luego estado pendiente, parcial o pagada.',
                    'Si hay PPD, más adelante podrás administrar varios complementos de pago.',
                ],
                'quick_actions' => [
                    ['key' => 'fill_current_period', 'label' => 'Periodo actual'],
                    ['key' => 'fill_today', 'label' => 'Fecha actual'],
                    ['key' => 'fill_source', 'label' => 'Origen sugerido'],
                    ['key' => 'focus_emisor', 'label' => 'Buscar emisor'],
                    ['key' => 'focus_receptor', 'label' => 'Buscar receptor'],
                    ['key' => 'detect_ppd', 'label' => 'Detectar flujo PPD'],
                ],
            ],
        ]);
    }

    public function searchEmisores(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'ok'   => true,
                'rows' => [],
                'data' => [],
            ]);
        }

        if (!Schema::connection('mysql_clientes')->hasTable('emisores')) {
            return response()->json([
                'ok'      => false,
                'rows'    => [],
                'data'    => [],
                'message' => 'No existe la tabla emisores.',
            ], 404);
        }

        $rows = DB::connection('mysql_clientes')
            ->table('emisores')
            ->whereNull('deleted_at')
            ->where(function ($w) use ($q) {
                $w->where('rfc', 'like', "%{$q}%")
                    ->orWhere('razon_social', 'like', "%{$q}%")
                    ->orWhere('nombre_comercial', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('ext_id', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $items = $rows->map(function ($row) {
            $direccion = $this->decodeJsonSafe($row->direccion ?? null);

            return [
                'id'               => (int) ($row->id ?? 0),
                'ext_id'           => (string) ($row->ext_id ?? ''),
                'cuenta_id'        => $row->cuenta_id ?? null,
                'account_id'       => $row->cuenta_id ?? null,
                'rfc'              => (string) ($row->rfc ?? ''),
                'razon_social'     => (string) ($row->razon_social ?? ''),
                'nombre_comercial' => (string) ($row->nombre_comercial ?? ''),
                'email'            => (string) ($row->email ?? ''),
                'regimen_fiscal'   => (string) ($row->regimen_fiscal ?? ''),
                'status'           => (string) ($row->status ?? ''),
                'cp'               => (string) data_get($direccion, 'cp', ''),
                'direccion'        => [
                    'cp'        => (string) data_get($direccion, 'cp', ''),
                    'direccion' => (string) data_get($direccion, 'direccion', ''),
                    'ciudad'    => (string) data_get($direccion, 'ciudad', ''),
                    'estado'    => (string) data_get($direccion, 'estado', ''),
                ],
                'label' => trim((string) ($row->razon_social ?? '')) . ' · ' . trim((string) ($row->rfc ?? '')),
                'smart' => [
                    'can_autofill_account' => !empty($row->cuenta_id),
                    'can_autofill_email'   => !empty($row->email),
                    'confidence'           => 'high',
                ],
            ];
        })->values()->all();

        return response()->json([
            'ok'   => true,
            'rows' => $items,
            'data' => $items,
        ]);
    }

    public function searchReceptores(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'ok'   => true,
                'rows' => [],
                'data' => [],
            ]);
        }

        if (!Schema::connection('mysql_clientes')->hasTable('receptores')) {
            return response()->json([
                'ok'      => false,
                'rows'    => [],
                'data'    => [],
                'message' => 'No existe la tabla receptores.',
            ], 404);
        }

        $rows = DB::connection('mysql_clientes')
            ->table('receptores')
            ->where(function ($w) use ($q) {
                $w->where('rfc', 'like', "%{$q}%")
                    ->orWhere('razon_social', 'like', "%{$q}%")
                    ->orWhere('nombre_comercial', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('codigo_postal', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'id'               => (int) ($row->id ?? 0),
                'cuenta_id'        => $row->cuenta_id ?? null,
                'account_id'       => $row->cuenta_id ?? null,
                'rfc'              => (string) ($row->rfc ?? ''),
                'razon_social'     => (string) ($row->razon_social ?? ''),
                'nombre_comercial' => (string) ($row->nombre_comercial ?? ''),
                'uso_cfdi'         => (string) ($row->uso_cfdi ?? ''),
                'regimen_fiscal'   => (string) ($row->regimen_fiscal ?? ''),
                'forma_pago'       => (string) ($row->forma_pago ?? ''),
                'metodo_pago'      => (string) ($row->metodo_pago ?? ''),
                'codigo_postal'    => (string) ($row->codigo_postal ?? ''),
                'cp'               => (string) ($row->codigo_postal ?? ''),
                'pais'             => (string) ($row->pais ?? 'MEX'),
                'estado'           => (string) ($row->estado ?? ''),
                'municipio'        => (string) ($row->municipio ?? ''),
                'colonia'          => (string) ($row->colonia ?? ''),
                'calle'            => (string) ($row->calle ?? ''),
                'no_ext'           => (string) ($row->no_ext ?? ''),
                'no_int'           => (string) ($row->no_int ?? ''),
                'email'            => (string) ($row->email ?? ''),
                'telefono'         => (string) ($row->telefono ?? ''),
                'label'            => trim((string) ($row->razon_social ?? '')) . ' · ' . trim((string) ($row->rfc ?? '')),
                'smart' => [
                    'suggest_uso_cfdi'       => (string) ($row->uso_cfdi ?? ''),
                    'suggest_regimen_fiscal' => (string) ($row->regimen_fiscal ?? ''),
                    'suggest_forma_pago'     => (string) ($row->forma_pago ?? ''),
                    'suggest_metodo_pago'    => (string) ($row->metodo_pago ?? ''),
                    'suggest_email'          => (string) ($row->email ?? ''),
                    'confidence'             => 'high',
                ],
            ];
        })->values()->all();

        return response()->json([
            'ok'   => true,
            'rows' => $items,
            'data' => $items,
        ]);
    }

    private function loadInvoiceCatalogs(): array
    {
        return [
            'tipos_comprobante' => [
                ['clave' => 'I', 'descripcion' => 'Ingreso'],
                ['clave' => 'E', 'descripcion' => 'Egreso'],
                ['clave' => 'P', 'descripcion' => 'Pago'],
                ['clave' => 'N', 'descripcion' => 'Nómina'],
                ['clave' => 'T', 'descripcion' => 'Traslado'],
            ],
            'exportaciones' => [
                ['clave' => '01', 'descripcion' => 'No aplica'],
                ['clave' => '02', 'descripcion' => 'Definitiva'],
                ['clave' => '03', 'descripcion' => 'Temporal'],
            ],
            'monedas' => [
                ['clave' => 'MXN', 'descripcion' => 'Peso Mexicano'],
                ['clave' => 'USD', 'descripcion' => 'Dólar Americano'],
                ['clave' => 'EUR', 'descripcion' => 'Euro'],
            ],
            'regimenes_fiscales' => $this->loadCliCatalog('sat_regimenes_fiscales', ['clave', 'descripcion']),
            'usos_cfdi'          => $this->loadCliCatalog('sat_usos_cfdi', ['clave', 'descripcion']),
            'formas_pago'        => $this->loadCliCatalog('sat_formas_pago', ['clave', 'descripcion']),
            'metodos_pago'       => $this->loadCliCatalog('sat_metodos_pago', ['clave', 'descripcion']),
            'objetos_impuesto'   => [
                ['clave' => '01', 'descripcion' => 'No objeto de impuesto'],
                ['clave' => '02', 'descripcion' => 'Sí objeto de impuesto'],
                ['clave' => '03', 'descripcion' => 'Sí objeto del impuesto y no obligado al desglose'],
            ],
            'impuestos' => [
                ['clave' => '001', 'descripcion' => 'ISR'],
                ['clave' => '002', 'descripcion' => 'IVA'],
                ['clave' => '003', 'descripcion' => 'IEPS'],
            ],
            'tipos_factor' => [
                ['clave' => 'Tasa', 'descripcion' => 'Tasa'],
                ['clave' => 'Cuota', 'descripcion' => 'Cuota'],
                ['clave' => 'Exento', 'descripcion' => 'Exento'],
            ],
            'complementos' => [
                ['clave' => 'none', 'descripcion' => 'Sin complemento'],
                ['clave' => 'pago20', 'descripcion' => 'Recepción de pagos 2.0'],
                ['clave' => 'carta_porte', 'descripcion' => 'Carta Porte'],
                ['clave' => 'comercio_exterior', 'descripcion' => 'Comercio Exterior'],
                ['clave' => 'nomina12', 'descripcion' => 'Nómina 1.2'],
            ],
        ];
    }

    private function loadCliCatalog(string $table, array $allowedColumns): array
    {
        try {
            if (!Schema::connection('mysql_clientes')->hasTable($table)) {
                return [];
            }

            $cols   = Schema::connection('mysql_clientes')->getColumnListing($table);
            $select = array_values(array_filter($allowedColumns, fn($c) => in_array($c, $cols, true)));

            if (empty($select) || !in_array('clave', $select, true)) {
                return [];
            }

            return DB::connection('mysql_clientes')
                ->table($table)
                ->orderBy('clave')
                ->get($select)
                ->map(function ($r) {
                    return [
                        'clave'       => (string) ($r->clave ?? ''),
                        'descripcion' => (string) ($r->descripcion ?? ''),
                    ];
                })
                ->filter(fn($x) => $x['clave'] !== '')
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('[BILLING][INVOICES] loadCliCatalog failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function decodeJsonSafe(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function inferSmartDefaultsFromReceptor(array $receptor): array
    {
        return [
            'uso_cfdi'       => (string) ($receptor['uso_cfdi'] ?? ''),
            'regimen_fiscal' => (string) ($receptor['regimen_fiscal'] ?? ''),
            'forma_pago'     => (string) ($receptor['forma_pago'] ?? ''),
            'metodo_pago'    => (string) ($receptor['metodo_pago'] ?? ''),
            'email'          => (string) ($receptor['email'] ?? ''),
            'codigo_postal'  => (string) ($receptor['codigo_postal'] ?? ($receptor['cp'] ?? '')),
        ];
    }

    private function inferSmartDefaultsFromEmisor(array $emisor): array
    {
        return [
            'account_id'     => (string) ($emisor['cuenta_id'] ?? ($emisor['account_id'] ?? '')),
            'email'          => (string) ($emisor['email'] ?? ''),
            'regimen_fiscal' => (string) ($emisor['regimen_fiscal'] ?? ''),
            'ext_id'         => (string) ($emisor['ext_id'] ?? ''),
        ];
    }

    private function buildInvoiceSmartMeta(Request $request, array $data, ?float $amountMxn): array
    {
        $tipoComprobante = (string) ($data['tipo_comprobante'] ?? 'I');
        $complemento     = (string) ($data['complemento'] ?? 'none');
        $metodoPago      = trim((string) ($data['metodo_pago'] ?? ''));
        $formaPago       = trim((string) ($data['forma_pago'] ?? ''));
        $moneda          = trim((string) ($data['moneda'] ?? 'MXN'));
        $ppdDetected     = strtoupper($metodoPago) === 'PPD' || $tipoComprobante === 'P' || $complemento === 'pago20';

        $paymentStatus = 'pending';
        if (strtolower((string) ($data['status'] ?? '')) === 'paid') {
            $paymentStatus = 'paid';
        }

        $totalAmount = round((float) ($amountMxn ?? 0), 2);
        $pendingAmount = $paymentStatus === 'paid' ? 0.00 : $totalAmount;

        return [
            'version' => 1,
            'wizard'  => [
                'step'       => (string) ($data['wizard_step'] ?? '1'),
                'clone_mode' => (string) ($data['clone_mode'] ?? 'skip'),
                'guided'     => true,
                'assistant'  => true,
            ],
            'tipo_comprobante' => $tipoComprobante,
            'complemento'      => $complemento,
            'sat' => [
                'uso_cfdi'        => (string) ($data['uso_cfdi'] ?? ''),
                'regimen_fiscal'  => (string) ($data['regimen_fiscal'] ?? ''),
                'forma_pago'      => $formaPago,
                'metodo_pago'     => $metodoPago,
                'moneda'          => $moneda !== '' ? $moneda : 'MXN',
                'exportacion'     => (string) ($data['exportacion'] ?? ''),
                'objeto_impuesto' => (string) ($data['objeto_impuesto'] ?? ''),
                'tasa_iva'        => (string) ($data['tasa_iva'] ?? '0.160000'),
            ],
            'partes' => [
                'emisor_id'   => (string) ($data['emisor_id'] ?? ''),
                'receptor_id' => (string) ($data['receptor_id'] ?? ''),
            ],
            'billing' => [
                'is_ppd_flow'            => $ppdDetected,
                'requires_payment_admin' => $ppdDetected,
                'allow_multi_complements'=> $ppdDetected,
            ],
            'payment_status' => $paymentStatus,
            'payment_summary' => [
                'total_amount'      => $totalAmount,
                'paid_amount'       => $paymentStatus === 'paid' ? $totalAmount : 0.00,
                'pending_amount'    => $pendingAmount,
                'partiality_count'  => 0,
                'complements_count' => 0,
                'last_payment_at'   => null,
            ],
            'payment_complements' => [],
            'assistant' => [
                'enabled'     => true,
                'source'      => 'admin_wizard_ai',
                'prompt_hint' => trim((string) $request->input('ai_prompt', '')),
            ],
            'ui' => [
                'created_from' => 'admin_invoicing_wizard',
                'show_payment_badges' => true,
                'show_ai_badges'      => true,
            ],
        ];
    }

    private function decodeInvoiceMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (!is_string($meta) || trim($meta) === '') {
            return [];
        }

        try {
            $decoded = json_decode($meta, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function mergeInvoiceMeta(array $prev, array $new): array
    {
        $merged = array_replace_recursive($prev, $new);

        if (!isset($merged['payment_complements']) || !is_array($merged['payment_complements'])) {
            $merged['payment_complements'] = [];
        }

        if (!isset($merged['payment_summary']) || !is_array($merged['payment_summary'])) {
            $merged['payment_summary'] = [
                'total_amount'      => 0,
                'paid_amount'       => 0,
                'pending_amount'    => 0,
                'partiality_count'  => 0,
                'complements_count' => 0,
                'last_payment_at'   => null,
            ];
        }

        return $merged;
    }

    private function invoiceHasPaymentComplement(array $meta): bool
    {
        $complements = $meta['payment_complements'] ?? [];
        if (is_array($complements) && count($complements) > 0) {
            return true;
        }

        return (string) ($meta['complemento'] ?? 'none') === 'pago20';
    }
}