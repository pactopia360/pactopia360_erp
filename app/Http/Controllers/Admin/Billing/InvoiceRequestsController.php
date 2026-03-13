<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class InvoiceRequestsController extends Controller
{
    private string $adm;
    private string $cli;

    private string $ftMode;
    private string $ftBase;
    private string $ftToken;
    private string $ftFlow;
    private string $ftEmisorId;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (config('p360.conn.clientes') ?: config('p360.conn.clients') ?: 'mysql_clientes');

        $this->ftMode     = 'sandbox';
        $this->ftBase     = '';
        $this->ftToken    = '';
        $this->ftFlow     = 'api_comprobantes';
        $this->ftEmisorId = '';

        $this->bootFacturotopiaConfig();
    }

        private function bootFacturotopiaConfig(): void
    {
        $settings = $this->loadBillingSettings();

        $mode = strtolower(trim((string) ($settings['facturotopia_mode'] ?? config('services.facturotopia.mode', 'sandbox'))));
        if (!in_array($mode, ['sandbox', 'production'], true)) {
            $mode = 'sandbox';
        }

        $flow = strtolower(trim((string) ($settings['facturotopia_flow'] ?? 'api_comprobantes')));
        if (!in_array($flow, ['api_comprobantes', 'xml_timbrado'], true)) {
            $flow = 'api_comprobantes';
        }

        $sandboxBase = (string) data_get(config('services.facturotopia'), 'sandbox.base', 'https://api-demo.facturotopia.com');
        $prodBase    = (string) data_get(config('services.facturotopia'), 'production.base', 'https://api.facturotopia.com');

        $sandboxToken = trim((string) (
            $settings['facturotopia_api_key_test']
            ?? data_get(config('services.facturotopia'), 'sandbox.token', config('services.facturotopia.api_key_test', ''))
        ));

        $prodToken = trim((string) (
            $settings['facturotopia_api_key_live']
            ?? data_get(config('services.facturotopia'), 'production.token', config('services.facturotopia.api_key_live', ''))
        ));

        $baseOverride = trim((string) ($settings['facturotopia_base'] ?? ''));

        $base = $baseOverride !== ''
            ? $baseOverride
            : ($mode === 'production' ? $prodBase : $sandboxBase);

        $this->ftMode     = $mode;
        $this->ftFlow     = $flow;
        $this->ftBase     = $this->normalizeFacturotopiaBase($base);
        $this->ftToken    = $mode === 'production' ? $prodToken : $sandboxToken;
        $this->ftEmisorId = trim((string) ($settings['facturotopia_emisor_id'] ?? ''));
    }

    /**
     * @return array<string,string>
     */
    private function loadBillingSettings(): array
    {
        try {
            if (!Schema::connection($this->adm)->hasTable('billing_settings')) {
                return [];
            }

            $rows = DB::connection($this->adm)
                ->table('billing_settings')
                ->pluck('value', 'key');

            $out = [];
            foreach ($rows as $k => $v) {
                $out[(string) $k] = is_string($v) ? $v : (string) $v;
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('[FACTUROTOPIA] loadBillingSettings failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeFacturotopiaBase(string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            return '';
        }

        return rtrim($base, '/');
    }

    public function index(Request $req): View
    {
        [$table, $mode, $error] = $this->resolveInvoiceRequestsTableSmart($req);

        if ($table === null) {
            return view('admin.billing.invoices.requests', [
                'rows'  => collect(),
                'error' => $error ?: 'No existe billing_invoice_requests (ni invoice_requests).',
                'mode'  => 'missing',
            ]);
        }

        $q      = trim((string) $req->get('q', ''));
        $status = trim((string) $req->get('status', ''));
        $period = trim((string) $req->get('period', ''));

        $qb = DB::connection($this->adm)->table($table)->orderByDesc('id');

        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        if ($period !== '') {
            if ($has('period')) {
                $qb->where('period', $period);
            }
            if ($has('periodo')) {
                $qb->where('periodo', $period);
            }
        }

        if ($status !== '') {
            $dbStatus = $this->normalizeStatusForDb($status, $mode);
            if ($has('status')) {
                $qb->where('status', $dbStatus);
            }
            if ($has('estatus')) {
                $qb->where('estatus', $dbStatus);
            }
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $has) {
                if ($has('id')) {
                    $w->orWhere('id', 'like', "%{$q}%");
                }
                if ($has('account_id')) {
                    $w->orWhere('account_id', 'like', "%{$q}%");
                }
                if ($has('statement_id')) {
                    $w->orWhere('statement_id', 'like', "%{$q}%");
                }

                if ($has('period')) {
                    $w->orWhere('period', 'like', "%{$q}%");
                }
                if ($has('periodo')) {
                    $w->orWhere('periodo', 'like', "%{$q}%");
                }

                if ($has('status')) {
                    $w->orWhere('status', 'like', "%{$q}%");
                }
                if ($has('estatus')) {
                    $w->orWhere('estatus', 'like', "%{$q}%");
                }

                if ($has('cfdi_uuid')) {
                    $w->orWhere('cfdi_uuid', 'like', "%{$q}%");
                }
                if ($has('notes')) {
                    $w->orWhere('notes', 'like', "%{$q}%");
                }

                if ($has('email')) {
                    $w->orWhere('email', 'like', "%{$q}%");
                }
                if ($has('rfc')) {
                    $w->orWhere('rfc', 'like', "%{$q}%");
                }
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();

            if (!empty($ids)) {
                $acc = DB::connection($this->adm)->table('accounts')
                    ->select(['id', 'email', 'rfc', 'razon_social', 'name', 'meta'])
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

                $rows->getCollection()->transform(function ($r) use ($acc) {
                    $a = $acc[$r->account_id] ?? null;

                    $meta = [];
                    try {
                        $meta = is_string($a->meta ?? null)
                            ? (json_decode((string) $a->meta, true) ?: [])
                            : (array) ($a->meta ?? []);
                    } catch (Throwable $e) {
                        $meta = [];
                    }

                    $billing = (array) ($meta['billing'] ?? []);

                    $r->account_rfc   = $a->rfc ?? ($r->rfc ?? ($billing['rfc'] ?? null));
                    $r->account_email = $a->email ?? ($r->email ?? ($billing['email'] ?? null));
                    $r->account_name  = $a->razon_social ?? ($a->name ?? ($billing['razon_social'] ?? null));

                    return $r;
                });
            }
        }

        $this->enrichRowsWithInvoiceState($rows);

        return view('admin.billing.invoices.requests', [
            'rows'  => $rows,
            'error' => $error,
            'mode'  => $mode,
        ]);
    }

    public function show(Request $req, int $id): View
    {
        [$table, $mode, $error] = $this->resolveInvoiceRequestsTableSmart($req);
        abort_unless($table !== null, 404, $error ?: 'Tabla de solicitudes no encontrada.');

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        abort_unless($row, 404, 'Solicitud no encontrada.');

        $account = null;
        if (Schema::connection($this->adm)->hasTable('accounts') && !empty($row->account_id)) {
            $account = DB::connection($this->adm)->table('accounts')
                ->where('id', (string) $row->account_id)
                ->first();
        }

        $invoice = $this->findInvoiceForRequest($row);

        return view('admin.billing.invoices.show', [
            'row'     => $row,
            'mode'    => $mode,
            'account' => $account,
            'invoice' => $invoice,
        ]);
    }

    /**
     * Guardar status + uuid + notes + zip (si viene)
     */
    public function setStatus(Request $req, int $id): RedirectResponse
    {
        [$table, $mode] = $this->resolveInvoiceRequestsTableSmart($req);
        abort_unless($table !== null, 404);

        $data = $req->validate([
            'status'    => 'required|string|max:40',
            'cfdi_uuid' => 'nullable|string|max:80',
            'notes'     => 'nullable|string|max:5000',
            'zip'       => 'nullable|file|mimes:zip|max:51200',
        ]);

        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        if (!$row) {
            return back()->withErrors(['status' => 'Solicitud no encontrada.']);
        }

        $statusDb = $this->normalizeStatusForDb(trim((string) $data['status']), $mode);

        $upd = [];

        if ($has('status')) {
            $upd['status'] = $statusDb;
        }
        if ($has('estatus')) {
            $upd['estatus'] = $statusDb;
        }

        if ($has('cfdi_uuid')) {
            $upd['cfdi_uuid'] = ($data['cfdi_uuid'] ?? null) ?: null;
        }
        if ($has('notes')) {
            $upd['notes'] = ($data['notes'] ?? null) ?: null;
        }

        if ($req->hasFile('zip')) {
            $accId  = (string) ($row->account_id ?? '');
            $period = (string) ($row->period ?? ($row->periodo ?? ''));

            if ($accId === '' || $period === '') {
                return back()->withErrors(['zip' => 'No se puede adjuntar ZIP: falta account_id/period en la solicitud.']);
            }

            $file = $req->file('zip');
            $disk = 'local';

            $dir  = "billing/invoices/{$accId}/{$period}";
            $name = "factura_{$period}_req{$id}_" . date('Ymd_His') . ".zip";

            $path = $file->storeAs($dir, $name, $disk);

            $full = Storage::disk($disk)->path($path);
            $size = @filesize($full) ?: null;
            $sha1 = @sha1_file($full) ?: null;

            if ($has('zip_disk')) {
                $upd['zip_disk'] = $disk;
            }
            if ($has('zip_path')) {
                $upd['zip_path'] = $path;
            }
            if ($has('zip_name')) {
                $upd['zip_name'] = $name;
            }
            if ($has('zip_size')) {
                $upd['zip_size'] = $size;
            }
            if ($has('zip_sha1')) {
                $upd['zip_sha1'] = $sha1;
            }
            if ($has('zip_ready_at')) {
                $upd['zip_ready_at'] = now();
            }
        }

        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (empty($upd)) {
            return back()->withErrors(['status' => 'La tabla no tiene columnas editables.']);
        }

        DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);

        if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $this->syncToHubFromLegacy($id);
        }

        if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
            $this->syncToLegacyFromHub($id);
        }

        return back()->with('ok', 'Estatus actualizado.');
    }

    /**
     * Aprobar + generar CFDI real con Facturotopia.
     */
    public function approveAndGenerate(Request $req, int $id): RedirectResponse
    {
        [$table, $mode] = $this->resolveInvoiceRequestsTableSmart($req);
        abort_unless($table !== null, 404);

        if (!$this->facturotopiaConfigured()) {
            return back()->withErrors([
                'facturotopia' => 'Facturotopia no está configurado. Revisa billing_settings: modo, flow, API key y emisor_id.',
            ]);
        }

        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return back()->withErrors([
                'invoice' => 'Falta tabla billing_invoices. Corre migraciones antes de generar facturas.',
            ]);
        }

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        if (!$row) {
            return back()->withErrors(['invoice' => 'Solicitud no encontrada.']);
        }

        $accountId = (string) ($row->account_id ?? '');
        $period    = (string) ($row->period ?? ($row->periodo ?? ''));

        if ($accountId === '' || $period === '') {
            return back()->withErrors(['invoice' => 'La solicitud no tiene account_id o period válidos.']);
        }

        try {
            $account = $this->loadAccountForInvoice($accountId);
            if (!$account) {
                return back()->withErrors(['invoice' => 'No se encontró la cuenta admin para generar la factura.']);
            }

            $billingData = $this->resolveBillingDataForAccount($account, $accountId);
            $validation  = $this->validateBillingData($billingData);

            if (!empty($validation)) {
                return back()->withErrors([
                    'invoice' => 'Faltan datos fiscales: ' . implode(', ', $validation) . '.',
                ]);
            }

            $statement = $this->loadStatementForInvoice($accountId, $period);
            if (!$statement) {
                return back()->withErrors([
                    'invoice' => 'No existe estado de cuenta para ese periodo. Primero genera o sincroniza billing_statements.',
                ]);
            }

            $items = $this->loadStatementItemsForInvoice((int) $statement->id, $period, $statement);
            if (empty($items)) {
                return back()->withErrors([
                    'invoice' => 'No hay conceptos para facturar en el estado de cuenta.',
                ]);
            }

             $externalPayload = $this->buildFacturotopiaPayload($row, $account, $statement, $billingData, $items);

            $attempts = $this->facturotopiaRequestAttempts();
            $response = null;

            foreach ($attempts as $uri) {
                $response = $this->facturotopiaPost($uri, $externalPayload);
                if (($response['ok'] ?? false) === true) {
                    break;
                }
            }

            if (!$response || !$response['ok']) {
                $message = (string) ($response['message'] ?? 'Error desconocido al generar CFDI.');
                $this->markRequestAsError($table, $id, $mode, $message);

                return back()->withErrors([
                    'facturotopia' => 'Facturotopia rechazó la generación: ' . $message,
                ]);
            }

            $parsed = $this->extractFacturotopiaInvoiceData($response['json']);
            if (($parsed['uuid'] ?? '') === '') {
                $this->markRequestAsError($table, $id, $mode, 'Facturotopia respondió sin UUID.');

                return back()->withErrors([
                    'facturotopia' => 'Facturotopia respondió, pero no entregó UUID.',
                ]);
            }

            $saved = $this->persistGeneratedInvoice(
                requestId: $id,
                requestRow: $row,
                account: $account,
                statement: $statement,
                billingData: $billingData,
                generated: $parsed,
                sourcePayload: $externalPayload
            );

            $this->markRequestAsIssued(
                table: $table,
                id: $id,
                mode: $mode,
                uuid: (string) ($saved['cfdi_uuid'] ?? $parsed['uuid']),
                notes: 'Factura generada automáticamente por admin (' . $this->ftMode . ').'
            );

            return back()->with('ok', 'Factura generada y timbrada correctamente.');
        } catch (Throwable $e) {
            Log::error('[BILLING][INVOICE_REQ] approveAndGenerate failed', [
                'id'    => $id,
                'mode'  => $mode,
                'error' => $e->getMessage(),
            ]);

            $this->markRequestAsError($table, $id, $mode, $e->getMessage());

            return back()->withErrors([
                'invoice' => 'No se pudo generar la factura: ' . $e->getMessage(),
            ]);
        }
    }

    public function stamp(Request $req, int $id): RedirectResponse
    {
        return $this->approveAndGenerate($req, $id);
    }

    public function retryStamp(Request $req, int $id): RedirectResponse
    {
        return $this->approveAndGenerate($req, $id);
    }

    /**
     * Adjuntar factura real (PDF/XML) a una solicitud.
     */
    public function attachInvoice(Request $req, int $id): RedirectResponse
    {
        [$table, $mode] = $this->resolveInvoiceRequestsTableSmart($req);
        abort_unless($table !== null, 404);

        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return back()->withErrors(['invoice' => 'Falta tabla billing_invoices. Corre migraciones.']);
        }

        $data = $req->validate([
            'cfdi_uuid' => 'nullable|string|max:80',
            'notes'     => 'nullable|string|max:5000',
            'pdf'       => 'nullable|file|mimes:pdf|max:20480',
            'xml'       => 'nullable|file|mimes:xml,txt|max:20480',
        ]);

        if (!$req->hasFile('pdf') && !$req->hasFile('xml')) {
            return back()->withErrors(['invoice' => 'Sube al menos PDF o XML.']);
        }

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        if (!$row) {
            return back()->withErrors(['invoice' => 'Solicitud no encontrada.']);
        }

        $accountId = (string) ($row->account_id ?? '');
        $period    = (string) ($row->period ?? ($row->periodo ?? ''));

        if ($accountId === '' || $period === '') {
            return back()->withErrors(['invoice' => 'No se puede adjuntar: falta account_id/period en la solicitud.']);
        }

        $acc = null;
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $acc = DB::connection($this->adm)->table('accounts')
                ->select(['id', 'rfc', 'razon_social', 'name', 'email'])
                ->where('id', $accountId)
                ->first();
        }

        $uuid = trim((string) ($data['cfdi_uuid'] ?? ''));
        if ($uuid === '' && !empty($row->cfdi_uuid)) {
            $uuid = trim((string) $row->cfdi_uuid);
        }

        $disk = 'local';
        $dir  = "billing/invoices/{$accountId}/{$period}";
        $tag  = $uuid !== '' ? preg_replace('/[^A-Za-z0-9\-]/', '', $uuid) : ('req' . $id);

        $payload = [
            'account_id'    => $accountId,
            'period'        => $period,
            'request_id'    => $id,
            'source'        => 'admin',
            'cfdi_uuid'     => $uuid !== '' ? $uuid : null,
            'rfc'           => ($acc->rfc ?? null) ?: ($row->rfc ?? null),
            'razon_social'  => ($acc->razon_social ?? ($acc->name ?? null)) ?: ($row->razon_social ?? null),
            'status'        => 'active',
            'issued_at'     => now(),
            'disk'          => $disk,
            'notes'         => ($data['notes'] ?? null) ?: null,
            'updated_at'    => now(),
            'created_at'    => now(),
        ];

        if ($req->hasFile('pdf')) {
            $pdfFile = $req->file('pdf');
            $pdfName = "CFDI_{$period}_{$tag}_" . date('Ymd_His') . ".pdf";
            $pdfPath = $pdfFile->storeAs($dir, $pdfName, $disk);

            $pdfFull = Storage::disk($disk)->path($pdfPath);
            $payload['pdf_path'] = $pdfPath;
            $payload['pdf_name'] = $pdfName;
            $payload['pdf_size'] = @filesize($pdfFull) ?: null;
            $payload['pdf_sha1'] = @sha1_file($pdfFull) ?: null;
        }

        if ($req->hasFile('xml')) {
            $xmlFile = $req->file('xml');
            $xmlName = "CFDI_{$period}_{$tag}_" . date('Ymd_His') . ".xml";
            $xmlPath = $xmlFile->storeAs($dir, $xmlName, $disk);

            $xmlFull = Storage::disk($disk)->path($xmlPath);
            $payload['xml_path'] = $xmlPath;
            $payload['xml_name'] = $xmlName;
            $payload['xml_size'] = @filesize($xmlFull) ?: null;
            $payload['xml_sha1'] = @sha1_file($xmlFull) ?: null;
        }

        $this->upsertBillingInvoice($payload, $row, $uuid !== '' ? $uuid : null);

        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $statusTo = ($mode === 'hub') ? 'issued' : 'done';
        $updReq   = [];

        if ($has('status')) {
            $updReq['status'] = $statusTo;
        }
        if ($has('estatus')) {
            $updReq['estatus'] = $statusTo;
        }
        if ($has('cfdi_uuid') && $uuid !== '') {
            $updReq['cfdi_uuid'] = $uuid;
        }
        if ($has('updated_at')) {
            $updReq['updated_at'] = now();
        }

        if (!empty($updReq)) {
            DB::connection($this->adm)->table($table)->where('id', $id)->update($updReq);

            if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                $this->syncToHubFromLegacy($id);
            }
            if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
                $this->syncToLegacyFromHub($id);
            }
        }

        return back()->with('ok', 'Factura adjuntada (PDF/XML) y solicitud marcada como emitida.');
    }

    /**
     * Descargar PDF/XML de una factura.
     */
    public function downloadInvoice(int $invoiceId, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['pdf', 'xml'], true), 404);
        abort_unless(Schema::connection($this->adm)->hasTable('billing_invoices'), 404);

        $inv = DB::connection($this->adm)->table('billing_invoices')->where('id', $invoiceId)->first();
        abort_unless($inv, 404);

        $disk = (string) ($inv->disk ?? 'local');
        $path = $kind === 'pdf' ? (string) ($inv->pdf_path ?? '') : (string) ($inv->xml_path ?? '');
        $name = $kind === 'pdf' ? (string) ($inv->pdf_name ?? '') : (string) ($inv->xml_name ?? '');

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404);

        $as   = $name !== '' ? $name : basename($path);
        $mime = $kind === 'pdf' ? 'application/pdf' : 'application/xml';

        return Storage::disk($disk)->download($path, $as, ['Content-Type' => $mime]);
    }

    public function sendInvoice(Request $req, int $id): RedirectResponse
    {
        return $this->dispatchInvoiceEmail($req, $id, false);
    }

    public function resendInvoice(Request $req, int $id): RedirectResponse
    {
        return $this->dispatchInvoiceEmail($req, $id, true);
    }

    public function emailReady(Request $req, int $id): RedirectResponse
    {
        return $this->dispatchInvoiceEmail($req, $id, false);
    }

    private function dispatchInvoiceEmail(Request $req, int $id, bool $isResend): RedirectResponse
    {
        [$table, $mode] = $this->resolveInvoiceRequestsTableSmart($req);
        abort_unless($table !== null, 404);

        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        abort_unless($row, 404);

        $invoice = $this->findInvoiceForRequest($row);

        if (!$invoice) {
            return back()->withErrors([
                'mail' => 'La solicitud todavía no tiene factura generada.',
            ]);
        }

        $acc = null;
        if (Schema::connection($this->adm)->hasTable('accounts') && !empty($row->account_id)) {
            $acc = DB::connection($this->adm)->table('accounts')
                ->where('id', (string) $row->account_id)
                ->first();
        }

        $destinations = $this->resolveRecipientsForInvoice($req, $row, $acc);
        if (empty($destinations)) {
            return back()->withErrors(['to' => 'No hay correos destino válidos.']);
        }

        $period    = (string) ($row->period ?? ($row->periodo ?? ''));
        $portalUrl = url('/cliente/mi-cuenta/facturas');
        $subject   = 'Pactopia360 · Factura disponible' . ($period !== '' ? (' · ' . $period) : '');

        $pdfDisk = (string) ($invoice->disk ?? 'local');
        $pdfPath = (string) ($invoice->pdf_path ?? '');
        $pdfName = (string) ($invoice->pdf_name ?? '');

        $xmlDisk = (string) ($invoice->disk ?? 'local');
        $xmlPath = (string) ($invoice->xml_path ?? '');
        $xmlName = (string) ($invoice->xml_name ?? '');

        $hasPdf  = $pdfPath !== '' && Storage::disk($pdfDisk)->exists($pdfPath);
        $hasXml  = $xmlPath !== '' && Storage::disk($xmlDisk)->exists($xmlPath);

        // Compat con vista existente
        $bodyHtml = view('admin.mail.invoice_ready_simple', [
            'account'   => $acc,
            'req'       => $row,
            'invoice'   => $invoice,
            'period'    => $period,
            'portalUrl' => $portalUrl,
            'hasZip'    => false,
            'hasPdf'    => $hasPdf,
            'hasXml'    => $hasXml,
            'emails'    => $destinations,
        ])->render();

        try {
            $allRecipients = $destinations;

            Mail::send([], [], function ($m) use ($allRecipients, $subject, $bodyHtml, $hasPdf, $pdfDisk, $pdfPath, $pdfName, $hasXml, $xmlDisk, $xmlPath, $xmlName) {
                $recipients = array_values($allRecipients);
                $primary = array_shift($recipients);

                $m->to($primary)->subject($subject);

                foreach ($recipients as $mail) {
                    $m->cc($mail);
                }

                $m->html($bodyHtml);

                if ($hasPdf) {
                    $stream = Storage::disk($pdfDisk)->readStream($pdfPath);
                    if (is_resource($stream)) {
                        $m->attachData(stream_get_contents($stream), $pdfName !== '' ? $pdfName : basename($pdfPath), [
                            'mime' => 'application/pdf',
                        ]);
                        @fclose($stream);
                    }
                }

                if ($hasXml) {
                    $stream = Storage::disk($xmlDisk)->readStream($xmlPath);
                    if (is_resource($stream)) {
                        $m->attachData(stream_get_contents($stream), $xmlName !== '' ? $xmlName : basename($xmlPath), [
                            'mime' => 'application/xml',
                        ]);
                        @fclose($stream);
                    }
                }
            });

            $this->markInvoiceAsSent($table, $id, $mode);

            if (Schema::connection($this->adm)->hasTable('billing_invoices')) {
                $invUpd = [];
                if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'updated_at')) {
                    $invUpd['updated_at'] = now();
                }
                if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'sent_at')) {
                    $invUpd['sent_at'] = now();
                }
                if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'emailed_to')) {
                    $invUpd['emailed_to'] = json_encode($destinations, JSON_UNESCAPED_UNICODE);
                }

                if (!empty($invUpd)) {
                    DB::connection($this->adm)->table('billing_invoices')
                        ->where('id', (int) $invoice->id)
                        ->update($invUpd);
                }
            }

            return back()->with('ok', $isResend ? 'Factura reenviada correctamente.' : 'Factura enviada correctamente.');
        } catch (Throwable $e) {
            Log::error('[BILLING][INVOICE_REQ] sendInvoice failed', [
                'request_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return back()->withErrors(['mail' => 'Falló el envío: ' . $e->getMessage()]);
        }
    }

        private function findInvoiceForRequest(object $row): ?object
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return null;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $requestId = (int) ($row->id ?? 0);
        $accountId = trim((string) ($row->account_id ?? ''));
        $period    = trim((string) ($row->period ?? ($row->periodo ?? '')));
        $uuid      = trim((string) ($row->cfdi_uuid ?? ''));

        if ($requestId > 0 && $has('request_id')) {
            $inv = DB::connection($this->adm)->table('billing_invoices')
                ->where('request_id', $requestId)
                ->orderByDesc('id')
                ->first();

            if ($inv) {
                return $inv;
            }
        }

        if ($uuid !== '' && $has('cfdi_uuid')) {
            $q = DB::connection($this->adm)->table('billing_invoices');

            if ($accountId !== '' && $has('account_id')) {
                $q->where('account_id', $accountId);
            }

            if ($period !== '' && $has('period')) {
                $q->where('period', $period);
            }

            $inv = $q->where('cfdi_uuid', $uuid)
                ->orderByDesc('id')
                ->first();

            if ($inv) {
                return $inv;
            }
        }

        if ($accountId !== '' && $period !== '' && $has('account_id') && $has('period')) {
            $inv = DB::connection($this->adm)->table('billing_invoices')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->orderByDesc('id')
                ->first();

            if ($inv) {
                return $inv;
            }
        }

        if ($accountId !== '' && $has('account_id')) {
            $q = DB::connection($this->adm)->table('billing_invoices')
                ->where('account_id', $accountId);

            if ($period !== '' && $has('period')) {
                $q->where('period', $period);
            }

            return $q->orderByDesc('id')->first();
        }

        return null;
    }

    private function upsertBillingInvoice(array $payload, object $row, ?string $uuid = null): object
    {
        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($has($key)) {
                $filtered[$key] = $value;
            }
        }

        $existing = $this->findInvoiceForRequest($row);

        if (!$existing && $uuid !== '' && $has('cfdi_uuid')) {
            $q = DB::connection($this->adm)->table('billing_invoices')->where('cfdi_uuid', $uuid);

            if (!empty($row->account_id) && $has('account_id')) {
                $q->where('account_id', (string) $row->account_id);
            }

            if (!empty($row->period) && $has('period')) {
                $q->where('period', (string) $row->period);
            } elseif (!empty($row->periodo) && $has('period')) {
                $q->where('period', (string) $row->periodo);
            }

            $existing = $q->orderByDesc('id')->first();
        }

        if ($existing) {
            unset($filtered['created_at']);

            DB::connection($this->adm)->table('billing_invoices')
                ->where('id', (int) $existing->id)
                ->update($filtered);

            return (object) DB::connection($this->adm)->table('billing_invoices')
                ->where('id', (int) $existing->id)
                ->first();
        }

        $newId = DB::connection($this->adm)->table('billing_invoices')->insertGetId($filtered);

        return (object) DB::connection($this->adm)->table('billing_invoices')
            ->where('id', (int) $newId)
            ->first();
    }

        private function enrichRowsWithInvoiceState($rows): void
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return;
        }

        $collection = method_exists($rows, 'getCollection')
            ? $rows->getCollection()
            : collect($rows);

        if ($collection->isEmpty()) {
            return;
        }

        $accountIds = $collection->pluck('account_id')->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();
        $periods    = $collection->map(fn ($r) => (string) ($r->period ?? ($r->periodo ?? '')))->filter()->unique()->values()->all();

        if (empty($accountIds) || empty($periods)) {
            return;
        }

        $invoiceCols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $invoiceLc   = array_map('strtolower', $invoiceCols);
        $invHas      = fn(string $c): bool => in_array(strtolower($c), $invoiceLc, true);

        $invoiceRows = DB::connection($this->adm)->table('billing_invoices')
            ->whereIn('account_id', $accountIds)
            ->whereIn('period', $periods)
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($invoiceRows as $inv) {
            $key = (string) ($inv->account_id ?? '') . '|' . (string) ($inv->period ?? '');
            if ($key === '|') {
                continue;
            }

            if (!isset($indexed[$key])) {
                $indexed[$key] = $inv;
            }
        }

        $collection->transform(function ($r) use ($indexed, $invHas) {
            $key = (string) ($r->account_id ?? '') . '|' . (string) ($r->period ?? ($r->periodo ?? ''));
            $inv = $indexed[$key] ?? null;

            $r->invoice_id           = $inv->id ?? null;
            $r->invoice_uuid         = $inv->cfdi_uuid ?? ($r->cfdi_uuid ?? null);
            $r->invoice_notes        = $inv->notes ?? null;
            $r->invoice_emailed_to   = $invHas('emailed_to') ? ($inv->emailed_to ?? null) : null;
            $r->invoice_sent_at      = $invHas('sent_at') ? ($inv->sent_at ?? null) : null;
            $r->invoice_pdf_path     = $inv->pdf_path ?? null;
            $r->invoice_pdf_name     = $inv->pdf_name ?? null;
            $r->invoice_xml_path     = $inv->xml_path ?? null;
            $r->invoice_xml_name     = $inv->xml_name ?? null;
            $r->invoice_disk         = $inv->disk ?? 'local';

            $pdfPath = trim((string) ($r->invoice_pdf_path ?? ''));
            $xmlPath = trim((string) ($r->invoice_xml_path ?? ''));
            $disk    = (string) ($r->invoice_disk ?? 'local');

            $hasPdf = false;
            $hasXml = false;

            try {
                $hasPdf = $pdfPath !== '' && Storage::disk($disk)->exists($pdfPath);
            } catch (Throwable $e) {
                $hasPdf = $pdfPath !== '';
            }

            try {
                $hasXml = $xmlPath !== '' && Storage::disk($disk)->exists($xmlPath);
            } catch (Throwable $e) {
                $hasXml = $xmlPath !== '';
            }

            $r->invoice_has_pdf      = $hasPdf;
            $r->invoice_has_xml      = $hasXml;
            $r->invoice_has_files    = $hasPdf || $hasXml;
            $r->invoice_is_saved     = !empty($r->invoice_id) || !empty($r->invoice_uuid) || $r->invoice_has_files;
            $r->invoice_can_re_send  = $r->invoice_is_saved;
            $r->invoice_primary_mail = '';

            $emailed = [];
            $raw = $r->invoice_emailed_to ?? null;

            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $mail) {
                        $mail = strtolower(trim((string) $mail));
                        if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                            $emailed[] = $mail;
                        }
                    }
                } elseif (filter_var(trim($raw), FILTER_VALIDATE_EMAIL)) {
                    $emailed[] = strtolower(trim($raw));
                }
            }

            $r->invoice_primary_mail = $emailed[0] ?? (string) ($r->account_email ?? ($r->email ?? ''));

            return $r;
        });

        if (method_exists($rows, 'setCollection')) {
            $rows->setCollection($collection);
        }
    }

    /**
     * Preferencia de tabla.
     *
     * @return array{0:?string,1:string,2:?string}
     */
    private function resolveInvoiceRequestsTableSmart(Request $req): array
    {
        $hasHub    = Schema::connection($this->adm)->hasTable('billing_invoice_requests');
        $hasLegacy = Schema::connection($this->adm)->hasTable('invoice_requests');

        if (!$hasHub && !$hasLegacy) {
            return [null, 'missing', 'No existe billing_invoice_requests (ni invoice_requests) en ' . $this->adm . '.'];
        }

        $forced = strtolower(trim((string) $req->query('src', '')));
        if ($forced === 'hub') {
            if (!$hasHub) {
                return [null, 'missing', 'Forzaste src=hub pero no existe billing_invoice_requests.'];
            }
            return ['billing_invoice_requests', 'hub', 'Forzaste HUB (src=hub).'];
        }

        if ($forced === 'legacy') {
            if (!$hasLegacy) {
                return [null, 'missing', 'Forzaste src=legacy pero no existe invoice_requests.'];
            }
            return ['invoice_requests', 'legacy', 'Forzaste LEGACY (src=legacy).'];
        }

        if ($hasHub && $hasLegacy) {
            $hubCount    = (int) DB::connection($this->adm)->table('billing_invoice_requests')->count();
            $legacyCount = (int) DB::connection($this->adm)->table('invoice_requests')->count();

            if ($legacyCount > $hubCount) {
                return ['invoice_requests', 'legacy', 'Mostrando LEGACY porque tiene más solicitudes que HUB (migración en curso). Usa ?src=hub para forzar HUB.'];
            }

            return ['billing_invoice_requests', 'hub', null];
        }

        if ($hasHub) {
            return ['billing_invoice_requests', 'hub', null];
        }

        return ['invoice_requests', 'legacy', null];
    }

    private function normalizeStatusForDb(string $raw, string $mode): string
    {
        $s = strtolower(trim($raw));

        $map = [
            'solicitada' => 'requested',
            'solicitado' => 'requested',
            'requested'  => 'requested',
            'request'    => 'requested',
            'pending'    => 'requested',

            'en_proceso'  => 'in_progress',
            'en proceso'  => 'in_progress',
            'proceso'     => 'in_progress',
            'processing'  => 'in_progress',
            'in_progress' => 'in_progress',

            'emitida'   => 'done',
            'emitido'   => 'done',
            'done'      => 'done',
            'completed' => 'done',
            'facturada' => 'done',
            'invoiced'  => 'done',
            'issued'    => 'done',

            'error'           => 'error',
            'failed'          => 'error',
            'timbrado_error'  => 'error',

            'rechazada' => 'rejected',
            'rechazado' => 'rejected',
            'rejected'  => 'rejected',
            'canceled'  => 'rejected',
            'cancelled' => 'rejected',
        ];

        $canonical = $map[$s] ?? $s;

        if ($mode === 'hub') {
            if ($canonical === 'done') {
                return 'issued';
            }
        } else {
            if ($canonical === 'issued') {
                return 'done';
            }
        }

        if (!in_array($canonical, ['requested', 'in_progress', 'done', 'issued', 'rejected', 'error'], true)) {
            $canonical = 'requested';
        }

        return $canonical;
    }

    /**
     * billing_invoice_requests.statement_id es NOT NULL.
     * Resuelve billing_statements.id por account_id + period.
     */
    private function resolveStatementIdForInvoice(string $accountId, string $period): int
    {
        $accountId = trim((string) $accountId);
        $period    = trim((string) $period);

        if ($accountId === '' || $period === '') {
            return 0;
        }
        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            return 0;
        }

        $canon = $this->resolveCanonicalAdminAccountId($accountId);
        if ($canon <= 0) {
            return 0;
        }

        $canonStr = (string) $canon;

        $st = DB::connection($this->adm)->table('billing_statements')
            ->where('account_id', $canonStr)
            ->where('period', $period)
            ->first(['id']);

        if ($st && isset($st->id) && (int) $st->id > 0) {
            return (int) $st->id;
        }

        $now = now();

        try {
            DB::connection($this->adm)->table('billing_statements')->insert([
                'account_id'   => $canonStr,
                'period'       => $period,
                'total_cargo'  => 0.00,
                'total_abono'  => 0.00,
                'saldo'        => 0.00,
                'status'       => 'pending',
                'due_date'     => null,
                'sent_at'      => null,
                'paid_at'      => null,
                'snapshot'     => null,
                'meta'         => null,
                'is_locked'    => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $id = (int) DB::connection($this->adm)->getPdo()->lastInsertId();
            if ($id > 0) {
                return $id;
            }
        } catch (Throwable $e) {
            $again = DB::connection($this->adm)->table('billing_statements')
                ->where('account_id', $canonStr)
                ->where('period', $period)
                ->first(['id']);

            if ($again && isset($again->id) && (int) $again->id > 0) {
                return (int) $again->id;
            }
        }

        return 0;
    }

    /**
     * Dado un accountId que puede ser:
     * - "15" (admin accounts.id)
     * - UUID de mysql_clientes.cuentas_cliente.id
     * regresa admin_account_id (int) o 0 si no se puede.
     */
    private function resolveCanonicalAdminAccountId(string $accountId): int
    {
        $raw = trim((string) $accountId);
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^\d+$/', $raw)) {
            $n = (int) $raw;
            return $n > 0 ? $n : 0;
        }

        $isUuid = (bool) preg_match(
            '/^[0-9a-f]{8}\-[0-9a-f]{4}\-[1-5][0-9a-f]{3}\-[89ab][0-9a-f]{3}\-[0-9a-f]{12}$/i',
            $raw
        );

        if (!$isUuid) {
            return 0;
        }

        try {
            if (!Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
                return 0;
            }

            $cols = Schema::connection($this->cli)->getColumnListing('cuentas_cliente');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

            $row = DB::connection($this->cli)->table('cuentas_cliente')->where('id', $raw)->first();
            if (!$row) {
                return 0;
            }

            $cand = null;

            if ($has('admin_account_id')) {
                $cand = $row->admin_account_id ?? null;
            }
            if (($cand === null || (int) $cand <= 0) && $has('account_id')) {
                $cand = $row->account_id ?? null;
            }

            if (($cand === null || (int) $cand <= 0) && $has('meta')) {
                $meta = $row->meta ?? null;
                if (is_string($meta) && $meta !== '') {
                    $j = json_decode($meta, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                        $cand = $j['admin_account_id'] ?? $j['account_id'] ?? null;
                    }
                } elseif (is_array($meta)) {
                    $cand = $meta['admin_account_id'] ?? $meta['account_id'] ?? null;
                }
            }

            $n = (int) $cand;
            return $n > 0 ? $n : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Normaliza a admin_account_id canónico.
     *
     * @return array{0:string,1:?string,2:string}
     */
    private function resolveCanonicalAdminAccountForStatements(string $accountId): array
    {
        $raw = trim((string) $accountId);
        if ($raw === '') {
            return ['', null, 'empty'];
        }

        if (preg_match('/^\d+$/', $raw)) {
            return [(string) ((int) $raw), null, 'numeric'];
        }

        if (preg_match('/^[0-9a-f\-]{36}$/i', $raw)) {
            try {
                if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
                    $cc = DB::connection($this->cli)->table('cuentas_cliente')
                        ->where('id', $raw)
                        ->first(['id', 'admin_account_id', 'account_id', 'meta']);

                    if ($cc) {
                        $aid = (int) ($cc->admin_account_id ?? 0);
                        if ($aid <= 0) {
                            $aid = (int) ($cc->account_id ?? 0);
                        }

                        if ($aid <= 0) {
                            $meta = $cc->meta ?? null;
                            $arr  = [];

                            if (is_array($meta)) {
                                $arr = $meta;
                            } elseif (is_object($meta)) {
                                $arr = (array) $meta;
                            } elseif (is_string($meta) && $meta !== '') {
                                $j = json_decode($meta, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                                    $arr = $j;
                                }
                            }

                            $aid = (int) data_get($arr, 'admin_account_id', 0);
                            if ($aid <= 0) {
                                $aid = (int) data_get($arr, 'account_id', 0);
                            }
                        }

                        if ($aid > 0) {
                            return [(string) $aid, (string) $cc->id, 'clientes.cuentas_cliente'];
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('[BILLING][INVOICE_REQ] resolveCanonicalAdminAccountForStatements failed', [
                    'account_id_in' => $raw,
                    'err'           => $e->getMessage(),
                ]);
            }

            return ['', $raw, 'uuid.unresolved'];
        }

        return ['', null, 'unknown.format'];
    }

    private function syncToHubFromLegacy(int $legacyId): void
    {
        if (!Schema::connection($this->adm)->hasTable('invoice_requests')) {
            return;
        }
        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            return;
        }

        $legacy = DB::connection($this->adm)->table('invoice_requests')->where('id', $legacyId)->first();
        if (!$legacy) {
            return;
        }

        $accountId = (string) ($legacy->account_id ?? '');
        $period    = (string) ($legacy->period ?? '');
        if ($accountId === '' || $period === '') {
            return;
        }

        $hub = DB::connection($this->adm)->table('billing_invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $payload = [];

        if ($has('account_id')) {
            $payload['account_id'] = $accountId;
        }
        if ($has('period')) {
            $payload['period'] = $period;
        }

        $statusHub = $this->normalizeStatusForDb((string) ($legacy->status ?? 'requested'), 'hub');
        if ($has('status')) {
            $payload['status'] = $statusHub;
        }

        foreach ([
            'notes', 'cfdi_uuid', 'zip_disk', 'zip_path', 'zip_name', 'zip_size', 'zip_sha1',
            'zip_ready_at', 'zip_sent_at', 'public_token', 'public_expires_at',
        ] as $c) {
            if ($has($c) && property_exists($legacy, $c)) {
                $payload[$c] = $legacy->{$c};
            }
        }

        if ($has('statement_id')) {
            $stmtId = $this->resolveStatementIdForInvoice($accountId, $period);

            if ($hub && isset($hub->statement_id) && (int) $hub->statement_id > 0) {
                $payload['statement_id'] = (int) $hub->statement_id;
            } else {
                if ($stmtId > 0) {
                    $payload['statement_id'] = $stmtId;
                } else {
                    Log::error('[BILLING][INVOICE_REQ] HUB requires statement_id but could not be resolved', [
                        'legacy_id'     => (int) $legacyId,
                        'account_id_in' => (string) $accountId,
                        'period'        => (string) $period,
                    ]);
                    return;
                }
            }
        }

        if ($has('updated_at')) {
            $payload['updated_at'] = now();
        }

        if ($hub) {
            DB::connection($this->adm)->table('billing_invoice_requests')
                ->where('id', (int) $hub->id)
                ->update($payload);
        } else {
            if ($has('created_at')) {
                $payload['created_at'] = now();
            }
            DB::connection($this->adm)->table('billing_invoice_requests')->insert($payload);
        }
    }

    private function syncToLegacyFromHub(int $hubId): void
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            return;
        }
        if (!Schema::connection($this->adm)->hasTable('invoice_requests')) {
            return;
        }

        $hub = DB::connection($this->adm)->table('billing_invoice_requests')->where('id', $hubId)->first();
        if (!$hub) {
            return;
        }

        $accountId = (string) ($hub->account_id ?? '');
        $period    = (string) ($hub->period ?? '');
        if ($accountId === '' || $period === '') {
            return;
        }

        $legacy = DB::connection($this->adm)->table('invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $statusLegacy = strtolower((string) ($hub->status ?? 'requested'));
        if ($statusLegacy === 'issued') {
            $statusLegacy = 'done';
        }
        if (!in_array($statusLegacy, ['requested', 'in_progress', 'done', 'rejected', 'error'], true)) {
            $statusLegacy = 'requested';
        }

        $cols = Schema::connection($this->adm)->getColumnListing('invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $payload = [];
        if ($has('status')) {
            $payload['status'] = $statusLegacy;
        }
        if ($has('notes')) {
            $payload['notes'] = $hub->notes ?? null;
        }
        if ($has('cfdi_uuid')) {
            $payload['cfdi_uuid'] = $hub->cfdi_uuid ?? null;
        }
        if ($has('zip_disk')) {
            $payload['zip_disk'] = $hub->zip_disk ?? null;
        }
        if ($has('zip_path')) {
            $payload['zip_path'] = $hub->zip_path ?? null;
        }
        if ($has('zip_name')) {
            $payload['zip_name'] = $hub->zip_name ?? null;
        }
        if ($has('zip_size')) {
            $payload['zip_size'] = $hub->zip_size ?? null;
        }
        if ($has('zip_sha1')) {
            $payload['zip_sha1'] = $hub->zip_sha1 ?? null;
        }
        if ($has('zip_ready_at')) {
            $payload['zip_ready_at'] = $hub->zip_ready_at ?? null;
        }
        if ($has('zip_sent_at')) {
            $payload['zip_sent_at'] = $hub->zip_sent_at ?? null;
        }
        if ($has('updated_at')) {
            $payload['updated_at'] = now();
        }

        if (empty($payload)) {
            return;
        }

        if ($legacy) {
            DB::connection($this->adm)->table('invoice_requests')
                ->where('id', (int) $legacy->id)
                ->update($payload);
        } else {
            $ins = [
                'account_id' => $accountId,
                'period'     => $period,
            ] + $payload;

            if ($has('created_at')) {
                $ins['created_at'] = now();
            }

            DB::connection($this->adm)->table('invoice_requests')->insert($ins);
        }
    }

        private function facturotopiaConfigured(): bool
    {
        if ($this->ftBase === '' || $this->ftToken === '') {
            return false;
        }

        if ($this->ftFlow === 'api_comprobantes' && $this->ftEmisorId === '') {
            return false;
        }

        return true;
    }

    private function facturotopiaClient(): PendingRequest
    {
        return Http::withHeaders([
                'Authorization' => 'ApiKey ' . $this->ftToken,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->asJson()
            ->timeout(90)
            ->connectTimeout(20);
    }

    private function facturotopiaPost(string $uri, array $payload): array
    {
        $url = $this->ftBase . '/' . ltrim($uri, '/');

        try {
            $res = $this->facturotopiaClient()->post($url, $payload);

            $json = [];
            try {
                $json = $res->json() ?: [];
            } catch (Throwable $e) {
                $json = [];
            }

            if ($res->successful()) {
                return [
                    'ok'      => true,
                    'status'  => $res->status(),
                    'json'    => $json,
                    'message' => '',
                ];
            }

            $msg = (string) (
                data_get($json, 'message')
                ?: data_get($json, 'error')
                ?: data_get($json, 'errors.0.message')
                ?: $res->body()
            );

            Log::warning('[FACTUROTOPIA] POST failed', [
                'url'     => $url,
                'status'  => $res->status(),
                'payload' => $payload,
                'body'    => $json,
            ]);

            return [
                'ok'      => false,
                'status'  => $res->status(),
                'json'    => $json,
                'message' => trim($msg) !== '' ? trim($msg) : 'Error no especificado de Facturotopia.',
            ];
        } catch (Throwable $e) {
            Log::error('[FACTUROTOPIA] POST exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok'      => false,
                'status'  => 0,
                'json'    => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    private function facturotopiaGetBinary(string $uri): array
    {
        $url = $this->ftBase . '/' . ltrim($uri, '/');

        try {
            $res = $this->facturotopiaClient()->get($url);

            if ($res->successful()) {
                return [
                    'ok'      => true,
                    'status'  => $res->status(),
                    'body'    => $res->body(),
                    'headers' => $res->headers(),
                    'message' => '',
                ];
            }

            return [
                'ok'      => false,
                'status'  => $res->status(),
                'body'    => '',
                'headers' => [],
                'message' => trim($res->body()) !== '' ? trim($res->body()) : 'No se pudo descargar archivo de Facturotopia.',
            ];
        } catch (Throwable $e) {
            return [
                'ok'      => false,
                'status'  => 0,
                'body'    => '',
                'headers' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    private function loadAccountForInvoice(string $accountId): ?object
    {
        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return null;
        }

        return DB::connection($this->adm)->table('accounts')
            ->where('id', $accountId)
            ->first();
    }

    private function resolveBillingDataForAccount(object $account, string $accountId): array
    {
        $meta = [];
        try {
            $meta = is_string($account->meta ?? null)
                ? (json_decode((string) $account->meta, true) ?: [])
                : (array) ($account->meta ?? []);
        } catch (Throwable $e) {
            $meta = [];
        }

        $billing      = (array) ($meta['billing'] ?? []);
        $company      = (array) ($meta['company'] ?? []);
        $invoicePrefs = (array) ($meta['invoicing'] ?? []);

        $clientRow = null;
        try {
            if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
                $query = DB::connection($this->cli)->table('cuentas_cliente');

                $query->where(function ($q) use ($accountId) {
                    if (preg_match('/^\d+$/', (string) $accountId)) {
                        $aid = (int) $accountId;

                        $q->orWhere('admin_account_id', $aid);

                        if (Schema::connection($this->cli)->hasColumn('cuentas_cliente', 'account_id')) {
                            $q->orWhere('account_id', $aid);
                        }
                    }

                    if (Schema::connection($this->cli)->hasColumn('cuentas_cliente', 'meta')) {
                        $q->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.admin_account_id')) = ?",
                            [(string) $accountId]
                        )->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.account_id')) = ?",
                            [(string) $accountId]
                        );
                    }
                });

                $clientRow = $query->orderByDesc('updated_at')->orderByDesc('id')->first();
            }
        } catch (Throwable $e) {
            $clientRow = null;
        }

        $pick = function (...$values): string {
            foreach ($values as $v) {
                if (is_string($v) && trim($v) !== '') {
                    return trim($v);
                }
                if (is_numeric($v)) {
                    return trim((string) $v);
                }
            }
            return '';
        };

        $emailsFromValue = function ($value): array {
            $out = [];

            if (is_array($value)) {
                foreach ($value as $mail) {
                    $mail = strtolower(trim((string) $mail));
                    if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                        $out[] = $mail;
                    }
                }
            } elseif (is_string($value) && trim($value) !== '') {
                foreach (preg_split('/[;,]+/', $value) ?: [] as $mail) {
                    $mail = strtolower(trim((string) $mail));
                    if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                        $out[] = $mail;
                    }
                }
            }

            return $out;
        };

        $recipients = [];

        // 1) prioridad máxima: preferencias guardadas en cliente
        foreach ([
            $clientRow->email ?? null,
            $clientRow->correo ?? null,
            $clientRow->email_facturacion ?? null,
            $clientRow->correo_facturacion ?? null,
        ] as $mail) {
            $mail = strtolower(trim((string) $mail));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $mail;
            }
        }

        // 2) listas configuradas en meta
        foreach ([
            $invoicePrefs['emails'] ?? null,
            $billing['emails'] ?? null,
            $billing['recipients'] ?? null,
        ] as $v) {
            $recipients = array_merge($recipients, $emailsFromValue($v));
        }

        // 3) fallback final
        foreach ([
            $billing['email'] ?? null,
            $account->email ?? null,
        ] as $mail) {
            $mail = strtolower(trim((string) $mail));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $mail;
            }
        }

        return [
            // ✅ PRIORIDAD: cuentas_cliente -> meta.billing -> meta.company -> accounts
            'rfc' => strtoupper($pick(
                $clientRow->rfc ?? null,
                $clientRow->rfc_fiscal ?? null,
                $billing['rfc'] ?? null,
                $company['rfc'] ?? null,
                $account->rfc ?? null
            )),

            'razon_social' => $pick(
                $clientRow->razon_social ?? null,
                $clientRow->nombre_comercial ?? null,
                $billing['razon_social'] ?? null,
                $billing['nombre_comercial'] ?? null,
                $company['razon_social'] ?? null,
                $account->razon_social ?? null,
                $account->name ?? null
            ),

            'email' => strtolower($pick(
                $clientRow->email ?? null,
                $clientRow->correo ?? null,
                $clientRow->email_facturacion ?? null,
                $clientRow->correo_facturacion ?? null,
                $billing['email'] ?? null,
                $account->email ?? null
            )),

            'telefono' => $pick(
                $clientRow->telefono_facturacion ?? null,
                $clientRow->telefono ?? null,
                $clientRow->tel ?? null,
                $clientRow->phone ?? null,
                $billing['telefono'] ?? null,
                $billing['phone'] ?? null
            ),

            'codigo_postal' => $pick(
                $clientRow->cp ?? null,
                $clientRow->codigo_postal ?? null,
                $billing['cp'] ?? null,
                $billing['codigo_postal'] ?? null
            ),

            'regimen_fiscal' => $pick(
                $clientRow->regimen_fiscal ?? null,
                $billing['regimen_fiscal'] ?? null
            ),

            'uso_cfdi' => $pick(
                $clientRow->uso_cfdi ?? null,
                $billing['uso_cfdi'] ?? null,
                'G03'
            ),

            'forma_pago' => $pick(
                $clientRow->forma_pago ?? null,
                $billing['forma_pago'] ?? null,
                '03'
            ),

            'metodo_pago' => $pick(
                $clientRow->metodo_pago ?? null,
                $billing['metodo_pago'] ?? null,
                'PUE'
            ),

            'calle' => $pick(
                $clientRow->calle ?? null,
                $billing['calle'] ?? null
            ),

            'no_ext' => $pick(
                $clientRow->no_ext ?? null,
                $billing['no_ext'] ?? null
            ),

            'no_int' => $pick(
                $clientRow->no_int ?? null,
                $billing['no_int'] ?? null
            ),

            'colonia' => $pick(
                $clientRow->colonia ?? null,
                $billing['colonia'] ?? null
            ),

            'municipio' => $pick(
                $clientRow->municipio ?? null,
                $billing['municipio'] ?? null
            ),

            'estado' => $pick(
                $clientRow->estado ?? null,
                $billing['estado'] ?? null
            ),

            'pais' => $pick(
                $clientRow->pais ?? null,
                $billing['pais'] ?? null,
                'MEX'
            ),

            'leyenda_pdf' => $pick(
                $clientRow->leyenda_pdf ?? null,
                $billing['leyenda_pdf'] ?? null
            ),

            'emails' => array_values(array_unique(array_filter($recipients))),
        ];
    }

    private function validateBillingData(array $billing): array
    {
        $missing = [];

        foreach ([
            'rfc'            => 'RFC',
            'razon_social'   => 'Razón social',
            'codigo_postal'  => 'Código postal',
            'regimen_fiscal' => 'Régimen fiscal',
            'uso_cfdi'       => 'Uso CFDI',
            'forma_pago'     => 'Forma de pago',
            'metodo_pago'    => 'Método de pago',
        ] as $k => $label) {
            if (!isset($billing[$k]) || trim((string) $billing[$k]) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    private function loadStatementForInvoice(string $accountId, string $period): ?object
    {
        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            return null;
        }

        [$canon] = $this->resolveCanonicalAdminAccountForStatements($accountId);
        if ($canon === '') {
            return null;
        }

        return DB::connection($this->adm)->table('billing_statements')
            ->where('account_id', $canon)
            ->where('period', $period)
            ->first();
    }

    private function loadStatementItemsForInvoice(int $statementId, string $period, object $statement): array
    {
        $items = [];

        if (Schema::connection($this->adm)->hasTable('billing_statement_items')) {
            $items = DB::connection($this->adm)->table('billing_statement_items')
                ->where('statement_id', $statementId)
                ->orderBy('id')
                ->get()
                ->map(function ($r) {
                    return [
                        'description' => trim((string) ($r->description ?? 'Servicio Pactopia360')),
                        'qty'         => (float) ($r->qty ?? 1),
                        'unit_price'  => round((float) ($r->unit_price ?? 0), 2),
                        'amount'      => round((float) ($r->amount ?? 0), 2),
                        'code'        => (string) ($r->code ?? ''),
                        'type'        => (string) ($r->type ?? 'service'),
                    ];
                })
                ->filter(fn($x) => $x['amount'] > 0)
                ->values()
                ->all();
        }

        if (!empty($items)) {
            return $items;
        }

        $amount = round((float) ($statement->total_cargo ?? 0), 2);
        if ($amount <= 0) {
            return [];
        }

        return [[
            'description' => 'Suscripción Pactopia360 · ' . $period,
            'qty'         => 1.0,
            'unit_price'  => $amount,
            'amount'      => $amount,
            'code'        => '',
            'type'        => 'service',
        ]];
    }

    private function buildFacturotopiaPayload(object $requestRow, object $account, object $statement, array $billing, array $items): array
    {
        $conceptos = [];

        foreach ($items as $it) {
            $qty       = max(0.000001, (float) ($it['qty'] ?? 1));
            $unitPrice = round((float) ($it['unit_price'] ?? 0), 2);
            $amount    = round((float) ($it['amount'] ?? ($qty * $unitPrice)), 2);
            $iva       = round($amount * 0.16, 2);

            $conceptos[] = [
                'clave_producto_servicio' => '81112100',
                'cantidad'                => round($qty, 2),
                'clave_unidad'            => 'E48',
                'unidad'                  => 'Servicio',
                'descripcion'             => Str::limit((string) ($it['description'] ?? 'Servicio Pactopia360'), 1000, ''),
                'valor_unitario'          => $unitPrice,
                'importe'                 => $amount,
                'objeto_impuesto'         => '02',
                'impuestos'               => [
                    'traslados' => [[
                        'base'        => $amount,
                        'impuesto'    => '002',
                        'tipo_factor' => 'Tasa',
                        'tasa_cuota'  => '0.160000',
                        'importe'     => $iva,
                    ]],
                ],
            ];
        }

        $subtotal = 0.0;
        foreach ($items as $it) {
            $subtotal += round((float) ($it['amount'] ?? 0), 2);
        }

        $subtotal = round($subtotal, 2);
        $iva      = round($subtotal * 0.16, 2);
        $total    = round($subtotal + $iva, 2);

        if ($this->ftFlow === 'xml_timbrado') {
            throw new \RuntimeException('El flujo xml_timbrado todavía no está implementado en este controlador. Usa api_comprobantes.');
        }

        return [
            'emisor_id'           => $this->ftEmisorId,
            'serie'               => 'A',
            'folio'               => 'REQ-' . (int) ($requestRow->id ?? 0),
            'fecha'               => now()->format('Y-m-d\TH:i:s'),
            'moneda'              => 'MXN',
            'tipo_comprobante'    => 'I',
            'metodo_pago'         => strtoupper((string) $billing['metodo_pago']),
            'forma_pago'          => strtoupper((string) $billing['forma_pago']),
            'lugar_expedicion'    => (string) $billing['codigo_postal'],
            'exportacion'         => '01',

            'receptor' => [
                'rfc'                       => strtoupper((string) $billing['rfc']),
                'nombre'                    => (string) $billing['razon_social'],
                'uso_cfdi'                  => strtoupper((string) $billing['uso_cfdi']),
                'domicilio_fiscal_receptor' => (string) $billing['codigo_postal'],
                'regimen_fiscal_receptor'   => (string) $billing['regimen_fiscal'],
                'email'                     => (string) ($billing['email'] ?? ''),
            ],

            'conceptos' => $conceptos,

            'subtotal' => $subtotal,
            'impuestos' => [
                'traslados' => [[
                    'impuesto'    => '002',
                    'tipo_factor' => 'Tasa',
                    'tasa_cuota'  => '0.160000',
                    'importe'     => $iva,
                ]],
            ],
            'total'    => $total,

            'metadata' => [
                'source'       => 'erp_admin_api',
                'request_id'   => (string) ($requestRow->id ?? ''),
                'account_id'   => (string) ($requestRow->account_id ?? ''),
                'statement_id' => (string) ($statement->id ?? ''),
                'period'       => (string) ($requestRow->period ?? ($requestRow->periodo ?? '')),
                'environment'  => $this->ftMode,
                'flow'         => $this->ftFlow,
            ],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function facturotopiaRequestAttempts(): array
    {
        if ($this->ftFlow === 'xml_timbrado') {
            return [
                '/api/timbrado',
            ];
        }

        return [
            '/api/comprobantes',
        ];
    }

     private function extractFacturotopiaInvoiceData(array $json): array
    {
        $uuid = (string) (
            data_get($json, 'uuid')
            ?: data_get($json, 'data.uuid')
            ?: data_get($json, 'data.attributes.uuid')
            ?: data_get($json, 'cfdi_uuid')
            ?: data_get($json, 'data.cfdi_uuid')
            ?: ''
        );

        $pdfUrl = (string) (
            data_get($json, 'pdf_url')
            ?: data_get($json, 'data.pdf_url')
            ?: data_get($json, 'data.attributes.pdf_url')
            ?: data_get($json, 'links.pdf')
            ?: ''
        );

        $xmlUrl = (string) (
            data_get($json, 'xml_url')
            ?: data_get($json, 'data.xml_url')
            ?: data_get($json, 'data.attributes.xml_url')
            ?: data_get($json, 'links.xml')
            ?: ''
        );

        $invoiceId = (string) (
            data_get($json, 'id')
            ?: data_get($json, 'data.id')
            ?: data_get($json, 'data.attributes.id')
            ?: ''
        );

        return [
            'uuid'      => trim($uuid),
            'pdf_url'   => trim($pdfUrl),
            'xml_url'   => trim($xmlUrl),
            'remote_id' => trim($invoiceId),
            'raw'       => $json,
        ];
    }
    private function persistGeneratedInvoice(
        int $requestId,
        object $requestRow,
        object $account,
        object $statement,
        array $billingData,
        array $generated,
        array $sourcePayload
    ): array {
        $accountId = (string) ($requestRow->account_id ?? '');
        $period    = (string) ($requestRow->period ?? ($requestRow->periodo ?? ''));
        $uuid      = trim((string) ($generated['uuid'] ?? ''));

        $disk = 'local';
        $dir  = "billing/invoices/{$accountId}/{$period}";
        $tag  = $uuid !== '' ? preg_replace('/[^A-Za-z0-9\-]/', '', $uuid) : ('req' . $requestId);

        $pdfPath = null;
        $xmlPath = null;
        $pdfName = null;
        $xmlName = null;
        $pdfSize = null;
        $xmlSize = null;
        $pdfSha1 = null;
        $xmlSha1 = null;

        $xmlContent = $this->downloadGeneratedFile((string) ($generated['xml_url'] ?? ''), $uuid, 'xml');
        if ($xmlContent !== null) {
            $xmlName = "CFDI_{$period}_{$tag}.xml";
            $xmlPath = $dir . '/' . $xmlName;
            Storage::disk($disk)->put($xmlPath, $xmlContent);

            $xmlFull = Storage::disk($disk)->path($xmlPath);
            $xmlSize = @filesize($xmlFull) ?: null;
            $xmlSha1 = @sha1_file($xmlFull) ?: null;
        }

        $pdfContent = $this->downloadGeneratedFile((string) ($generated['pdf_url'] ?? ''), $uuid, 'pdf');
        if ($pdfContent !== null) {
            $pdfName = "CFDI_{$period}_{$tag}.pdf";
            $pdfPath = $dir . '/' . $pdfName;
            Storage::disk($disk)->put($pdfPath, $pdfContent);

            $pdfFull = Storage::disk($disk)->path($pdfPath);
            $pdfSize = @filesize($pdfFull) ?: null;
            $pdfSha1 = @sha1_file($pdfFull) ?: null;
        }

        $payload = [
            'account_id'   => $accountId,
            'period'       => $period,
            'request_id'   => $requestId,
            'source'       => 'facturotopia_' . $this->ftMode,
            'cfdi_uuid'    => $uuid !== '' ? $uuid : null,
            'rfc'          => (string) ($billingData['rfc'] ?? ($account->rfc ?? '')),
            'razon_social' => (string) ($billingData['razon_social'] ?? ($account->razon_social ?? ($account->name ?? ''))),
            'status'       => 'active',
            'issued_at'    => now(),
            'disk'         => $disk,
            'pdf_path'     => $pdfPath,
            'pdf_name'     => $pdfName,
            'pdf_size'     => $pdfSize,
            'pdf_sha1'     => $pdfSha1,
            'xml_path'     => $xmlPath,
            'xml_name'     => $xmlName,
            'xml_size'     => $xmlSize,
            'xml_sha1'     => $xmlSha1,
            'notes'        => 'Generada por integración Facturotopia (' . $this->ftMode . ').',
            'updated_at'   => now(),
            'created_at'   => now(),
        ];

        if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'meta')) {
            $payload['meta'] = json_encode([
                'remote_id'       => (string) ($generated['remote_id'] ?? ''),
                'facturotopia'    => $generated['raw'] ?? [],
                'statement_id'    => (int) ($statement->id ?? 0),
                'request_payload' => $sourcePayload,
                'emails'          => $billingData['emails'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'emailed_to')) {
            $payload['emailed_to'] = json_encode($billingData['emails'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        $saved = $this->upsertBillingInvoice($payload, $requestRow, $uuid !== '' ? $uuid : null);

        return (array) $saved;
    }

    private function downloadGeneratedFile(string $url, string $uuid, string $kind): ?string
    {
        $kind = strtolower($kind);
        if (!in_array($kind, ['pdf', 'xml'], true)) {
            return null;
        }

        if ($url !== '') {
            try {
                $res = Http::timeout(90)->connectTimeout(20)->get($url);
                if ($res->successful() && trim($res->body()) !== '') {
                    return $res->body();
                }
            } catch (Throwable $e) {
                Log::warning('[FACTUROTOPIA] direct file download failed', [
                    'kind'  => $kind,
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($uuid === '') {
            return null;
        }

        $endpoints = $kind === 'pdf'
            ? [
                '/api/comprobantes/' . $uuid . '/pdf',
                '/api/comprobantes/' . $uuid . '/archivo/pdf',
            ]
            : [
                '/api/comprobantes/' . $uuid . '/xml',
                '/api/comprobantes/' . $uuid . '/archivo/xml',
            ];

        foreach ($endpoints as $uri) {
            $file = $this->facturotopiaGetBinary($uri);
            if (($file['ok'] ?? false) === true && trim((string) ($file['body'] ?? '')) !== '') {
                return (string) $file['body'];
            }
        }

        return null;
    }

    private function markRequestAsIssued(string $table, int $id, string $mode, string $uuid, ?string $notes = null): void
    {
        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $statusTo = ($mode === 'hub') ? 'issued' : 'done';
        $upd = [];

        if ($has('status')) {
            $upd['status'] = $statusTo;
        }
        if ($has('estatus')) {
            $upd['estatus'] = $statusTo;
        }
        if ($has('cfdi_uuid')) {
            $upd['cfdi_uuid'] = $uuid;
        }
        if ($has('notes') && $notes !== null) {
            $upd['notes'] = $notes;
        }
        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (!empty($upd)) {
            DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);
        }

        if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $this->syncToHubFromLegacy($id);
        }

        if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
            $this->syncToLegacyFromHub($id);
        }
    }

    private function markRequestAsError(string $table, int $id, string $mode, string $message): void
    {
        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $upd = [];

        if ($has('status')) {
            $upd['status'] = 'error';
        }
        if ($has('estatus')) {
            $upd['estatus'] = 'error';
        }
        if ($has('notes')) {
            $upd['notes'] = Str::limit($message, 5000, '');
        }
        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (!empty($upd)) {
            DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);
        }

        if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $this->syncToHubFromLegacy($id);
        }

        if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
            $this->syncToLegacyFromHub($id);
        }
    }

    private function markInvoiceAsSent(string $table, int $id, string $mode): void
    {
        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $upd = [];
        if ($has('zip_sent_at')) {
            $upd['zip_sent_at'] = now();
        }
        if ($has('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (!empty($upd)) {
            DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);
        }

        if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $this->syncToHubFromLegacy($id);
        }

        if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
            $this->syncToLegacyFromHub($id);
        }
    }

    private function resolveRecipientsForInvoice(Request $req, object $row, ?object $acc): array
    {
        $all = [];

        $manual = trim((string) $req->get('to', ''));
        if ($manual !== '') {
            foreach (preg_split('/[;,]+/', $manual) ?: [] as $mail) {
                $mail = strtolower(trim((string) $mail));
                if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $all[] = $mail;
                }
            }
        }

        foreach ([
            (string) ($acc->email ?? ''),
            (string) ($row->email ?? ''),
        ] as $mail) {
            $mail = strtolower(trim($mail));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $all[] = $mail;
            }
        }

        $invoice = $this->findInvoiceForRequest($row);

        if ($invoice && Schema::connection($this->adm)->hasColumn('billing_invoices', 'emailed_to')) {
            $metaEmails = $invoice->emailed_to ?? null;
            if (is_string($metaEmails) && trim($metaEmails) !== '') {
                $decoded = json_decode($metaEmails, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $mail) {
                        if (is_string($mail) && filter_var(trim($mail), FILTER_VALIDATE_EMAIL)) {
                            $all[] = strtolower(trim($mail));
                        }
                    }
                }
            }
        }

        try {
            if ($acc && isset($acc->meta)) {
                $meta = is_string($acc->meta)
                    ? (json_decode((string) $acc->meta, true) ?: [])
                    : (array) $acc->meta;

                foreach ([
                    data_get($meta, 'billing.emails'),
                    data_get($meta, 'billing.recipients'),
                    data_get($meta, 'invoicing.emails'),
                ] as $value) {
                    if (is_array($value)) {
                        foreach ($value as $mail) {
                            if (is_string($mail) && filter_var(trim($mail), FILTER_VALIDATE_EMAIL)) {
                                $all[] = strtolower(trim($mail));
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
        }

        return array_values(array_unique(array_filter($all)));
    }
}