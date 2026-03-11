<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
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

        $qb = DB::connection($this->adm)->table('billing_invoices')->orderByDesc('id');

        if ($period !== '' && $has('period')) {
            $qb->where('period', $period);
        }

        if ($status !== '' && $has('status')) {
            $qb->where('status', $status);
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $has) {
                if ($has('id'))           $w->orWhere('id', 'like', "%{$q}%");
                if ($has('account_id'))   $w->orWhere('account_id', 'like', "%{$q}%");
                if ($has('request_id'))   $w->orWhere('request_id', 'like', "%{$q}%");
                if ($has('period'))       $w->orWhere('period', 'like', "%{$q}%");
                if ($has('cfdi_uuid'))    $w->orWhere('cfdi_uuid', 'like', "%{$q}%");
                if ($has('rfc'))          $w->orWhere('rfc', 'like', "%{$q}%");
                if ($has('razon_social')) $w->orWhere('razon_social', 'like', "%{$q}%");
                if ($has('status'))       $w->orWhere('status', 'like', "%{$q}%");
                if ($has('source'))       $w->orWhere('source', 'like', "%{$q}%");
                if ($has('notes'))        $w->orWhere('notes', 'like', "%{$q}%");
                if ($has('folio'))        $w->orWhere('folio', 'like', "%{$q}%");
                if ($has('serie'))        $w->orWhere('serie', 'like', "%{$q}%");
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();

            if (!empty($ids)) {
                $accounts = DB::connection($this->adm)->table('accounts')
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

                    $r->account_email  = $acc->email ?? null;
                    $r->account_rfc    = $acc->rfc ?? ($r->rfc ?? null);
                    $r->account_name   = $acc->razon_social ?? ($acc->name ?? null);
                    $r->recipient_list = $emails;

                    $r->display_total_mxn = $this->resolveInvoiceAmountMxn($r);

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

    public function show(int $id): View
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $invoice = DB::connection($this->adm)->table('billing_invoices')->where('id', $id)->first();
        abort_unless($invoice, 404, 'Factura no encontrada.');

        $account = null;
        if (Schema::connection($this->adm)->hasTable('accounts') && !empty($invoice->account_id)) {
            $account = DB::connection($this->adm)->table('accounts')
                ->where('id', (string) $invoice->account_id)
                ->first();
        }

        $requestRow = null;
        if (!empty($invoice->request_id)) {
            if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                $requestRow = DB::connection($this->adm)->table('billing_invoice_requests')
                    ->where('id', (int) $invoice->request_id)
                    ->first();
            }

            if (!$requestRow && Schema::connection($this->adm)->hasTable('invoice_requests')) {
                $requestRow = DB::connection($this->adm)->table('invoice_requests')
                    ->where('id', (int) $invoice->request_id)
                    ->first();
            }
        }

        $meta = [];
        if (property_exists($invoice, 'meta') || isset($invoice->meta)) {
            try {
                $meta = is_string($invoice->meta ?? null)
                    ? (json_decode((string) $invoice->meta, true) ?: [])
                    : (array) ($invoice->meta ?? []);
            } catch (Throwable $e) {
                $meta = [];
            }
        }

        $invoice->display_total_mxn = $this->resolveInvoiceAmountMxn($invoice);
        $invoice->resolved_recipients = $this->resolveRecipientsForInvoice($invoice, $account, null);

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

        $invoice = DB::connection($this->adm)->table('billing_invoices')->where('id', $id)->first();
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
        $mime = $kind === 'pdf' ? 'application/pdf' : 'application/xml';

        return Storage::disk($disk)->download($path, $downloadAs, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Alta manual unitaria.
     */
    public function storeManual(Request $request): RedirectResponse
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $data = $request->validate([
            'account_id'  => 'required|string|max:64',
            'period'      => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'cfdi_uuid'   => 'nullable|string|max:80',
            'serie'       => 'nullable|string|max:20',
            'folio'       => 'nullable|string|max:40',
            'status'      => 'nullable|string|max:40',
            'issued_at'   => 'nullable|date',
            'issued_date' => 'nullable|date',
            'amount_mxn'  => 'nullable|numeric|min:0|max:999999999',
            'source'      => 'nullable|string|max:30',
            'notes'       => 'nullable|string|max:5000',
            'pdf'         => 'nullable|file|mimes:pdf|max:20480',
            'xml'         => 'nullable|file|mimes:xml,txt|max:20480',
            'send_now'    => 'nullable',
            'to'          => 'nullable|string|max:5000',
        ]);

        if (!$request->hasFile('pdf') && !$request->hasFile('xml')) {
            return back()->withErrors(['invoice' => 'Debes adjuntar al menos PDF o XML.'])->withInput();
        }

        $accountId = trim((string) $data['account_id']);
        $period    = trim((string) $data['period']);
        $uuid      = trim((string) ($data['cfdi_uuid'] ?? ''));
        $source    = trim((string) ($data['source'] ?? 'manual_admin'));
        $status    = trim((string) ($data['status'] ?? 'issued'));
        $notes     = trim((string) ($data['notes'] ?? ''));

        $account = $this->findAccount($accountId);

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
            xmlFile: $request->file('xml')
        );

        if ($request->boolean('send_now')) {
            $sent = $this->sendInvoiceNow((int) ($saved->id ?? 0), (string) ($data['to'] ?? ''), false);

            if (!$sent['ok']) {
                return back()->withErrors(['mail' => $sent['message']])->with('ok', 'Factura guardada, pero el correo no se envió.');
            }

            return back()->with('ok', 'Factura guardada y enviada correctamente.');
        }

        return back()->with('ok', 'Factura manual guardada correctamente.');
    }

    /**
     * Alta manual masiva.
     * Espera arrays paralelos: account_id[], period[], cfdi_uuid[], etc. y archivos pdf_files[index], xml_files[index].
     */
    public function bulkStoreManual(Request $request): RedirectResponse
    {
        abort_unless(
            Schema::connection($this->adm)->hasTable('billing_invoices'),
            404,
            'No existe la tabla billing_invoices.'
        );

        $data = $request->validate([
            'rows'               => 'nullable|array',
            'account_id'         => 'nullable|array',
            'account_id.*'       => 'nullable|string|max:64',
            'period'             => 'nullable|array',
            'period.*'           => 'nullable|string|max:7',
            'cfdi_uuid'          => 'nullable|array',
            'cfdi_uuid.*'        => 'nullable|string|max:80',
            'serie'              => 'nullable|array',
            'serie.*'            => 'nullable|string|max:20',
            'folio'              => 'nullable|array',
            'folio.*'            => 'nullable|string|max:40',
            'status'             => 'nullable|array',
            'status.*'           => 'nullable|string|max:40',
            'amount_mxn'         => 'nullable|array',
            'amount_mxn.*'       => 'nullable',
            'issued_at'          => 'nullable|array',
            'issued_at.*'        => 'nullable|string|max:30',
            'issued_date'        => 'nullable|array',
            'issued_date.*'      => 'nullable|string|max:30',
            'source'             => 'nullable|array',
            'source.*'           => 'nullable|string|max:30',
            'notes'              => 'nullable|array',
            'notes.*'            => 'nullable|string|max:5000',
            'send_now'           => 'nullable',
            'to'                 => 'nullable|string|max:5000',
            'pdf_files'          => 'nullable|array',
            'pdf_files.*'        => 'nullable|file|mimes:pdf|max:20480',
            'xml_files'          => 'nullable|array',
            'xml_files.*'        => 'nullable|file|mimes:xml,txt|max:20480',
        ]);

        $accountIds = (array) ($data['account_id'] ?? []);
        $periods    = (array) ($data['period'] ?? []);

        $count = max(count($accountIds), count($periods));
        if ($count <= 0) {
            return back()->withErrors(['invoice' => 'No se recibieron filas para carga masiva.'])->withInput();
        }

        $created = 0;
        $failed  = 0;
        $errors  = [];
        $savedIds = [];

        for ($i = 0; $i < $count; $i++) {
            $accountId = trim((string) ($accountIds[$i] ?? ''));
            $period    = trim((string) ($periods[$i] ?? ''));

            if ($accountId === '' && $period === '') {
                continue;
            }

            if ($accountId === '' || !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
                $failed++;
                $errors[] = "Fila " . ($i + 1) . ": account_id o period inválidos.";
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
                    xmlFile: $request->file("xml_files.$i")
                );

                $savedIds[] = (int) ($saved->id ?? 0);
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "Fila " . ($i + 1) . ': ' . $e->getMessage();

                Log::error('[BILLING][INVOICES] bulkStoreManual failed', [
                    'row'       => $i,
                    'account_id'=> $accountId,
                    'period'    => $period,
                    'error'     => $e->getMessage(),
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

        $invoice = DB::connection($this->adm)->table('billing_invoices')->where('id', $id)->first();
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
            $prev = trim((string) ($invoice->notes ?? ''));
            $msg  = 'Factura cancelada manualmente desde admin el ' . now()->format('Y-m-d H:i:s');
            $upd['notes'] = $prev !== '' ? ($prev . "\n" . $msg) : $msg;
        }

        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (empty($upd)) {
            return back()->withErrors(['invoice' => 'La tabla billing_invoices no tiene columnas actualizables para cancelar.']);
        }

        try {
            DB::connection($this->adm)->table('billing_invoices')
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

            return back()->withErrors(['invoice' => 'No se pudo cancelar la factura: ' . $e->getMessage()]);
        }
    }

    private function bulkSendNow(array $invoiceIds, string $toRaw): array
    {
        $sent = 0;
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

            $invoice = DB::connection($this->adm)->table('billing_invoices')->where('id', $invoiceId)->first();
            if (!$invoice) {
                return ['ok' => false, 'message' => 'Factura no encontrada.'];
            }

            $account = $this->findAccount((string) ($invoice->account_id ?? ''));
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

            $hasPdf  = $pdfPath !== '' && Storage::disk($pdfDisk)->exists($pdfPath);
            $hasXml  = $xmlPath !== '' && Storage::disk($xmlDisk)->exists($xmlPath);

            if (!$hasPdf && !$hasXml) {
                return ['ok' => false, 'message' => 'La factura no tiene PDF/XML disponibles para enviar.'];
            }

            $period = (string) ($invoice->period ?? '');
            $portalUrl = url('/cliente/mi-cuenta/facturas');
            $subject = 'Pactopia360 · Factura disponible' . ($period !== '' ? (' · ' . $period) : '');

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
            $prev = trim((string) ($invoice->notes ?? ''));
            $msg = ($isResend ? 'Factura reenviada' : 'Factura enviada') . ' el ' . now()->format('Y-m-d H:i:s');
            $upd['notes'] = $prev !== '' ? ($prev . "\n" . $msg) : $msg;
        }

        if (!empty($upd)) {
            DB::connection($this->adm)->table('billing_invoices')
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
        mixed $xmlFile
    ): object {
        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $disk = 'local';
        $tag  = $uuid !== null && $uuid !== ''
            ? preg_replace('/[^A-Za-z0-9\-]/', '', $uuid)
            : ('manual_' . Str::upper(Str::random(10)));

        $dir = "billing/invoices/{$accountId}/{$period}";

        $payload = [];

        if ($has('account_id'))   $payload['account_id'] = $accountId;
        if ($has('period'))       $payload['period'] = $period;
        if ($has('request_id'))   $payload['request_id'] = $requestId;
        if ($has('source'))       $payload['source'] = $source;
        if ($has('cfdi_uuid'))    $payload['cfdi_uuid'] = $uuid;
        if ($has('status'))       $payload['status'] = $status;
        if ($has('disk'))         $payload['disk'] = $disk;
        if ($has('notes'))        $payload['notes'] = $notes;
        if ($has('serie'))        $payload['serie'] = $serie;
        if ($has('folio'))        $payload['folio'] = $folio;

        if ($has('rfc')) {
            $payload['rfc'] = $account->rfc ?? null;
        }
        if ($has('razon_social')) {
            $payload['razon_social'] = $account->razon_social ?? ($account->name ?? null);
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

        if ($has('currency')) {
            $payload['currency'] = 'MXN';
        }

        if ($pdfFile) {
            $pdfName = "CFDI_{$period}_{$tag}_" . now()->format('Ymd_His') . '.pdf';
            $pdfPath = $pdfFile->storeAs($dir, $pdfName, $disk);
            $pdfFull = Storage::disk($disk)->path($pdfPath);

            if ($has('pdf_path')) $payload['pdf_path'] = $pdfPath;
            if ($has('pdf_name')) $payload['pdf_name'] = $pdfName;
            if ($has('pdf_size')) $payload['pdf_size'] = @filesize($pdfFull) ?: null;
            if ($has('pdf_sha1')) $payload['pdf_sha1'] = @sha1_file($pdfFull) ?: null;
        }

        if ($xmlFile) {
            $xmlName = "CFDI_{$period}_{$tag}_" . now()->format('Ymd_His') . '.xml';
            $xmlPath = $xmlFile->storeAs($dir, $xmlName, $disk);
            $xmlFull = Storage::disk($disk)->path($xmlPath);

            if ($has('xml_path')) $payload['xml_path'] = $xmlPath;
            if ($has('xml_name')) $payload['xml_name'] = $xmlName;
            if ($has('xml_size')) $payload['xml_size'] = @filesize($xmlFull) ?: null;
            if ($has('xml_sha1')) $payload['xml_sha1'] = @sha1_file($xmlFull) ?: null;
        }

        $now = now();
        if ($has('updated_at')) $payload['updated_at'] = $now;

        $existing = null;

        if ($uuid !== null && $uuid !== '' && $has('cfdi_uuid')) {
            $existing = DB::connection($this->adm)->table('billing_invoices')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->where('cfdi_uuid', $uuid)
                ->first();
        }

        if (!$existing && $requestId !== null && $has('request_id')) {
            $existing = DB::connection($this->adm)->table('billing_invoices')
                ->where('request_id', $requestId)
                ->orderByDesc('id')
                ->first();
        }

        if (!$existing) {
            $existing = DB::connection($this->adm)->table('billing_invoices')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->orderByDesc('id')
                ->first();
        }

        if ($existing) {
            DB::connection($this->adm)->table('billing_invoices')
                ->where('id', (int) $existing->id)
                ->update($payload);

            return (object) DB::connection($this->adm)->table('billing_invoices')
                ->where('id', (int) $existing->id)
                ->first();
        }

        if ($has('created_at')) {
            $payload['created_at'] = $now;
        }

        $newId = (int) DB::connection($this->adm)->table('billing_invoices')->insertGetId($payload);

        return (object) DB::connection($this->adm)->table('billing_invoices')
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

        return DB::connection($this->adm)->table('accounts')
            ->where('id', $accountId)
            ->first();
    }

    private function normalizeIssuedAt(string $issuedAt, string $issuedDate): ?string
    {
        $issuedAt = trim($issuedAt);
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

    /**
     * @return array<int,string>
     */
    private function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $raw = str_replace([';', "\n", "\r", "\t"], ',', $raw);
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

    /**
     * @return array<int,string>
     */
    private function parseIdCsv(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $raw = str_replace([";", "\n", "\r", "\t", " "], ",", $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (!preg_match('/^[a-zA-Z0-9\-\_]{1,80}$/', $p)) continue;
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
            DB::connection($this->adm)->table($table)
                ->where('id', $requestId)
                ->update($upd);
        }
    }

    public function stamp(int $id)
    {
        try {

            $service = app(\App\Services\Billing\CfdiStampService::class);

            $result = $service->stamp($id);

            return redirect()
                ->back()
                ->with('ok', 'Factura timbrada correctamente UUID: ' . $result['uuid']);

        } catch (\Throwable $e) {

            return redirect()
                ->back()
                ->with('bad', $e->getMessage());
        }
    }
}