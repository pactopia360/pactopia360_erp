<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InvoiceRequestsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
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
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        if ($period !== '') {
            if ($has('period'))  $qb->where('period', $period);
            if ($has('periodo')) $qb->where('periodo', $period);
        }

        if ($status !== '') {
            $dbStatus = $this->normalizeStatusForDb($status, $mode);
            if ($has('status'))  $qb->where('status', $dbStatus);
            if ($has('estatus')) $qb->where('estatus', $dbStatus);
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $has) {
                if ($has('id'))         $w->orWhere('id', 'like', "%{$q}%");
                if ($has('account_id')) $w->orWhere('account_id', 'like', "%{$q}%");

                if ($has('period'))     $w->orWhere('period', 'like', "%{$q}%");
                if ($has('periodo'))    $w->orWhere('periodo', 'like', "%{$q}%");

                if ($has('status'))     $w->orWhere('status', 'like', "%{$q}%");
                if ($has('estatus'))    $w->orWhere('estatus', 'like', "%{$q}%");

                if ($has('cfdi_uuid'))  $w->orWhere('cfdi_uuid', 'like', "%{$q}%");
                if ($has('notes'))      $w->orWhere('notes', 'like', "%{$q}%");

                if ($has('email'))      $w->orWhere('email', 'like', "%{$q}%");
                if ($has('rfc'))        $w->orWhere('rfc', 'like', "%{$q}%");
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        // Attach account info si existe
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();

            if (!empty($ids)) {
                $acc = DB::connection($this->adm)->table('accounts')
                    ->select(['id','email','rfc','razon_social','name'])
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

                $rows->getCollection()->transform(function ($r) use ($acc) {
                    $a = $acc[$r->account_id] ?? null;
                    $r->account_rfc   = $a->rfc ?? ($r->rfc ?? null);
                    $r->account_email = $a->email ?? ($r->email ?? null);
                    $r->account_name  = $a->razon_social ?? ($a->name ?? null);
                    return $r;
                });
            }
        }

        return view('admin.billing.invoices.requests', [
            'rows'  => $rows,
            'error' => $error,
            'mode'  => $mode,
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
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        if (!$row) return back()->withErrors(['status' => 'Solicitud no encontrada.']);

        $statusDb = $this->normalizeStatusForDb(trim((string)$data['status']), $mode);

        $upd = [];

        if ($has('status'))  $upd['status']  = $statusDb;
        if ($has('estatus')) $upd['estatus'] = $statusDb;

        if ($has('cfdi_uuid')) $upd['cfdi_uuid'] = ($data['cfdi_uuid'] ?? null) ?: null;
        if ($has('notes'))     $upd['notes']     = ($data['notes'] ?? null) ?: null;

        // ZIP (si viene)
        if ($req->hasFile('zip')) {
            $accId  = (string)($row->account_id ?? '');
            $period = (string)($row->period ?? ($row->periodo ?? ''));

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

            if ($has('zip_disk'))     $upd['zip_disk']     = $disk;
            if ($has('zip_path'))     $upd['zip_path']     = $path;
            if ($has('zip_name'))     $upd['zip_name']     = $name;
            if ($has('zip_size'))     $upd['zip_size']     = $size;
            if ($has('zip_sha1'))     $upd['zip_sha1']     = $sha1;
            if ($has('zip_ready_at')) $upd['zip_ready_at'] = now();
        }

        if ($has('updated_at')) $upd['updated_at'] = now();

        if (empty($upd)) {
            return back()->withErrors(['status' => 'La tabla no tiene columnas editables.']);
        }

        DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);

        // Sync bidireccional según modo
        if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $this->syncToHubFromLegacy($id);
        }

        if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
            $this->syncToLegacyFromHub($id);
        }

        return back()->with('ok', 'Estatus actualizado.');
    }

    /**
     * ✅ NUEVO: Adjuntar factura real (PDF/XML) a una solicitud (y dejarla lista para descarga).
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
            'pdf'       => 'nullable|file|mimes:pdf|max:20480', // 20MB
            'xml'       => 'nullable|file|mimes:xml,txt|max:20480', // XML suele venir como text/xml o application/xml
        ]);

        if (!$req->hasFile('pdf') && !$req->hasFile('xml')) {
            return back()->withErrors(['invoice' => 'Sube al menos PDF o XML.']);
        }

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        if (!$row) return back()->withErrors(['invoice' => 'Solicitud no encontrada.']);

        $accountId = (string)($row->account_id ?? '');
        $period    = (string)($row->period ?? ($row->periodo ?? ''));

        if ($accountId === '' || $period === '') {
            return back()->withErrors(['invoice' => 'No se puede adjuntar: falta account_id/period en la solicitud.']);
        }

        // Account info (opcional)
        $acc = null;
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $acc = DB::connection($this->adm)->table('accounts')
                ->select(['id','rfc','razon_social','name','email'])
                ->where('id', $accountId)
                ->first();
        }

        $uuid = trim((string)($data['cfdi_uuid'] ?? ''));
        if ($uuid === '' && !empty($row->cfdi_uuid)) $uuid = trim((string)$row->cfdi_uuid);

        $disk = 'local';
        $dir  = "billing/invoices/{$accountId}/{$period}";
        $tag  = $uuid !== '' ? preg_replace('/[^A-Za-z0-9\-]/', '', $uuid) : ('req'.$id);

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

        // Guardar archivos
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

        // Upsert: si ya existe invoice para account+period+uuid (cuando uuid viene)
        if (($payload['cfdi_uuid'] ?? null) !== null) {
            $existing = DB::connection($this->adm)->table('billing_invoices')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->where('cfdi_uuid', $payload['cfdi_uuid'])
                ->first();

            if ($existing) {
                unset($payload['created_at']);
                DB::connection($this->adm)->table('billing_invoices')->where('id', (int)$existing->id)->update($payload);
            } else {
                DB::connection($this->adm)->table('billing_invoices')->insert($payload);
            }
        } else {
            DB::connection($this->adm)->table('billing_invoices')->insert($payload);
        }

        // Opcional: marcar solicitud como emitida (issued/done)
        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $statusTo = ($mode === 'hub') ? 'issued' : 'done';
        $updReq   = [];

        if ($has('status'))  $updReq['status'] = $statusTo;
        if ($has('estatus')) $updReq['estatus'] = $statusTo;
        if ($has('cfdi_uuid') && $uuid !== '') $updReq['cfdi_uuid'] = $uuid;
        if ($has('updated_at')) $updReq['updated_at'] = now();

        if (!empty($updReq)) {
            DB::connection($this->adm)->table($table)->where('id', $id)->update($updReq);

            // sync cruzado
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
     * ✅ NUEVO: Descargar PDF/XML de una factura (Admin).
     */
    public function downloadInvoice(int $invoiceId, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['pdf','xml'], true), 404);

        abort_unless(Schema::connection($this->adm)->hasTable('billing_invoices'), 404);

        $inv = DB::connection($this->adm)->table('billing_invoices')->where('id', $invoiceId)->first();
        abort_unless($inv, 404);

        $disk = (string)($inv->disk ?? 'local');
        $path = $kind === 'pdf' ? (string)($inv->pdf_path ?? '') : (string)($inv->xml_path ?? '');
        $name = $kind === 'pdf' ? (string)($inv->pdf_name ?? '') : (string)($inv->xml_name ?? '');

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404);

        $as   = $name !== '' ? $name : basename($path);
        $mime = $kind === 'pdf' ? 'application/pdf' : 'application/xml';

        return Storage::disk($disk)->download($path, $as, ['Content-Type' => $mime]);
    }

    /**
     * Email "Factura lista":
     * - Si subes ZIP aquí, lo guarda primero (misma metadata zip_*)
     * - Adjunta ZIP si existe
     */
    public function emailReady(Request $req, int $id): RedirectResponse
    {
        [$table, $mode] = $this->resolveInvoiceRequestsTableSmart($req);
        abort_unless($table !== null, 404);

        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
        abort_unless($row, 404);

        // Si viene ZIP aquí, guardarlo igual que setStatus
        if ($req->hasFile('zip')) {
            $accId  = (string)($row->account_id ?? '');
            $period = (string)($row->period ?? ($row->periodo ?? ''));

            if ($accId === '' || $period === '') {
                return back()->withErrors(['zip' => 'No se puede adjuntar ZIP: falta account_id/period.']);
            }

            $file = $req->file('zip');
            $disk = 'local';

            $dir  = "billing/invoices/{$accId}/{$period}";
            $name = "factura_{$period}_req{$id}_" . date('Ymd_His') . ".zip";

            $path = $file->storeAs($dir, $name, $disk);

            $full = Storage::disk($disk)->path($path);
            $size = @filesize($full) ?: null;
            $sha1 = @sha1_file($full) ?: null;

            $upd = [];
            if ($has('zip_disk'))     $upd['zip_disk']     = $disk;
            if ($has('zip_path'))     $upd['zip_path']     = $path;
            if ($has('zip_name'))     $upd['zip_name']     = $name;
            if ($has('zip_size'))     $upd['zip_size']     = $size;
            if ($has('zip_sha1'))     $upd['zip_sha1']     = $sha1;
            if ($has('zip_ready_at')) $upd['zip_ready_at'] = now();
            if ($has('updated_at'))   $upd['updated_at']   = now();

            if (!empty($upd)) {
                DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);
                $row = DB::connection($this->adm)->table($table)->where('id', $id)->first();
            }

            if ($mode === 'legacy' && Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                $this->syncToHubFromLegacy($id);
            }
            if ($mode === 'hub' && Schema::connection($this->adm)->hasTable('invoice_requests')) {
                $this->syncToLegacyFromHub($id);
            }
        }

        $acc = null;
        if (Schema::connection($this->adm)->hasTable('accounts') && !empty($row->account_id)) {
            $acc = DB::connection($this->adm)->table('accounts')->where('id', (string)$row->account_id)->first();
        }

        $to = trim((string)$req->get('to', ''));
        if ($to === '') $to = (string)($acc->email ?? '');
        if ($to === '') $to = (string)($row->email ?? '');
        $to = trim($to);

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['to' => 'No hay correo destino válido.']);
        }

        $period    = (string)($row->period ?? ($row->periodo ?? ''));
        $portalUrl = url('/cliente/mi-cuenta');
        $subject   = 'Pactopia360 · Factura lista' . ($period !== '' ? (' · ' . $period) : '');

        $zipDisk = (string)($row->zip_disk ?? 'local');
        $zipPath = (string)($row->zip_path ?? '');
        $zipName = (string)($row->zip_name ?? '');
        $hasZip  = $zipPath !== '' && Storage::disk($zipDisk)->exists($zipPath);

        $bodyHtml = view('admin.mail.invoice_ready_simple', [
            'account'   => $acc,
            'req'       => $row,
            'period'    => $period,
            'portalUrl' => $portalUrl,
            'hasZip'    => $hasZip,
        ])->render();

        try {
            Mail::send([], [], function ($m) use ($to, $subject, $bodyHtml, $hasZip, $zipDisk, $zipPath, $zipName) {
                $m->to($to)->subject($subject);

                // ✅ Laravel 12 / Symfony Mailer: NO usar setBody(string)
                $m->html($bodyHtml);

                if ($hasZip) {
                    $stream = Storage::disk($zipDisk)->readStream($zipPath);
                    if (is_resource($stream)) {
                        $name = $zipName !== '' ? $zipName : basename($zipPath);

                        // Adjuntar por stream (sin tmpfile)
                        $m->attachData(stream_get_contents($stream), $name, [
                            'mime' => 'application/zip',
                        ]);

                        @fclose($stream);
                    }
                }
            });

            $upd = [];
            if ($has('zip_sent_at')) $upd['zip_sent_at'] = now();
            if ($has('updated_at'))  $upd['updated_at']  = now();
            if (!empty($upd)) {
                DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);
            }

            return back()->with('ok', 'Correo enviado (“Factura lista”).');
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Falló el envío: ' . $e->getMessage()]);
        }
    }

    /**
     * Preferencia de tabla:
     * @return array{0:?string,1:string,2:?string}
     */
    private function resolveInvoiceRequestsTableSmart(Request $req): array
    {
        $hasHub    = Schema::connection($this->adm)->hasTable('billing_invoice_requests');
        $hasLegacy = Schema::connection($this->adm)->hasTable('invoice_requests');

        if (!$hasHub && !$hasLegacy) {
            return [null, 'missing', 'No existe billing_invoice_requests (ni invoice_requests) en '.$this->adm.'.'];
        }

        $forced = strtolower(trim((string)$req->query('src', ''))); // hub|legacy
        if ($forced === 'hub') {
            if (!$hasHub) return [null, 'missing', 'Forzaste src=hub pero no existe billing_invoice_requests.'];
            return ['billing_invoice_requests', 'hub', 'Forzaste HUB (src=hub).'];
        }
        if ($forced === 'legacy') {
            if (!$hasLegacy) return [null, 'missing', 'Forzaste src=legacy pero no existe invoice_requests.'];
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

        if ($hasHub) return ['billing_invoice_requests', 'hub', null];
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

            'en_proceso'   => 'in_progress',
            'en proceso'   => 'in_progress',
            'proceso'      => 'in_progress',
            'in_progress'  => 'in_progress',
            'processing'   => 'in_progress',

            'emitida'   => 'done',
            'emitido'   => 'done',
            'done'      => 'done',
            'completed' => 'done',
            'facturada' => 'done',
            'invoiced'  => 'done',
            'issued'    => 'done',

            'rechazada' => 'rejected',
            'rechazado' => 'rejected',
            'rejected'  => 'rejected',
            'canceled'  => 'rejected',
            'cancelled' => 'rejected',
        ];

        $canonical = $map[$s] ?? $s;

        if ($mode === 'hub') {
            if ($canonical === 'done') return 'issued';
        } else {
            if ($canonical === 'issued') return 'done';
        }

        if (!in_array($canonical, ['requested','in_progress','done','issued','rejected'], true)) {
            $canonical = 'requested';
        }

        return $canonical;
    }

        /**
     * ✅ billing_invoice_requests.statement_id es NOT NULL.
     * Resuelve billing_statements.id por account_id + period.
     * Si no existe, lo crea mínimo para evitar 500.
     */
    private function resolveStatementIdForInvoice(string $accountId, string $period): int
    {
        if ($accountId === '' || $period === '') return 0;

        // billing_statements.account_id es varchar(36) y en tu sistema a veces llega "6" (string),
        // por eso usamos candidatos.
        $acctCandidates = array_values(array_unique([
            $accountId,
            is_numeric($accountId) ? (string)((int)$accountId) : $accountId,
        ]));

        $st = DB::connection($this->adm)->table('billing_statements')
            ->whereIn('account_id', $acctCandidates)
            ->where('period', $period)
            ->first(['id']);

        if ($st && isset($st->id) && (int)$st->id > 0) {
            return (int) $st->id;
        }

        // ✅ fallback seguro: crear statement mínimo para ese periodo
        $now = now();

        DB::connection($this->adm)->table('billing_statements')->insert([
            'account_id'   => (string) ($acctCandidates[0] ?? $accountId),
            'period'       => (string) $period,
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
        return $id > 0 ? $id : 0;
    }

    private function syncToHubFromLegacy(int $legacyId): void
    {
        if (!Schema::connection($this->adm)->hasTable('invoice_requests')) return;
        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) return;

        $legacy = DB::connection($this->adm)->table('invoice_requests')->where('id', $legacyId)->first();
        if (!$legacy) return;

        $accountId = (string)($legacy->account_id ?? '');
        $period    = (string)($legacy->period ?? '');
        if ($accountId === '' || $period === '') return;

        $hub = DB::connection($this->adm)->table('billing_invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $cols = Schema::connection($this->adm)->getColumnListing('billing_invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $payload = [];

        if ($has('account_id')) $payload['account_id'] = $accountId;
        if ($has('period'))     $payload['period']     = $period;

        $statusHub = $this->normalizeStatusForDb((string)($legacy->status ?? 'requested'), 'hub');
        if ($has('status'))     $payload['status']     = $statusHub;

        // Copia campos comunes (legacy NO trae statement_id)
        foreach (['notes','cfdi_uuid','zip_disk','zip_path','zip_name','zip_size','zip_sha1','zip_ready_at','zip_sent_at','public_token','public_expires_at'] as $c) {
            if ($has($c) && property_exists($legacy, $c)) {
                $payload[$c] = $legacy->{$c};
            }
        }

        // ✅ statement_id SIEMPRE (HUB lo exige NOT NULL)
        if ($has('statement_id')) {
            $stmtId = $this->resolveStatementIdForInvoice($accountId, $period);

            // Si ya existía en HUB y ya tiene statement_id válido, respétalo.
            if ($hub && isset($hub->statement_id) && (int)$hub->statement_id > 0) {
                $payload['statement_id'] = (int) $hub->statement_id;
            } else {
                $payload['statement_id'] = $stmtId > 0 ? $stmtId : 1; // paracaídas extremo
            }
        }

        if ($has('updated_at')) $payload['updated_at'] = now();

        if ($hub) {
            DB::connection($this->adm)->table('billing_invoice_requests')->where('id', (int)$hub->id)->update($payload);
        } else {
            if ($has('created_at')) $payload['created_at'] = now();
            DB::connection($this->adm)->table('billing_invoice_requests')->insert($payload);
        }
    }

    private function syncToLegacyFromHub(int $hubId): void
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) return;
        if (!Schema::connection($this->adm)->hasTable('invoice_requests')) return;

        $hub = DB::connection($this->adm)->table('billing_invoice_requests')->where('id', $hubId)->first();
        if (!$hub) return;

        $accountId = (string)($hub->account_id ?? '');
        $period    = (string)($hub->period ?? '');
        if ($accountId === '' || $period === '') return;

        $legacy = DB::connection($this->adm)->table('invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $statusLegacy = strtolower((string)($hub->status ?? 'requested'));
        if ($statusLegacy === 'issued') $statusLegacy = 'done';
        if (!in_array($statusLegacy, ['requested','in_progress','done','rejected'], true)) {
            $statusLegacy = 'requested';
        }

        $cols = Schema::connection($this->adm)->getColumnListing('invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $payload = [];
        if ($has('status'))       $payload['status']       = $statusLegacy;
        if ($has('notes'))        $payload['notes']        = $hub->notes ?? null;
        if ($has('cfdi_uuid'))    $payload['cfdi_uuid']    = $hub->cfdi_uuid ?? null;

        if ($has('zip_disk'))     $payload['zip_disk']     = $hub->zip_disk ?? null;
        if ($has('zip_path'))     $payload['zip_path']     = $hub->zip_path ?? null;
        if ($has('zip_name'))     $payload['zip_name']     = $hub->zip_name ?? null;
        if ($has('zip_size'))     $payload['zip_size']     = $hub->zip_size ?? null;
        if ($has('zip_sha1'))     $payload['zip_sha1']     = $hub->zip_sha1 ?? null;
        if ($has('zip_ready_at')) $payload['zip_ready_at'] = $hub->zip_ready_at ?? null;
        if ($has('zip_sent_at'))  $payload['zip_sent_at']  = $hub->zip_sent_at ?? null;

        if ($has('updated_at'))   $payload['updated_at']   = now();

        if (empty($payload)) return;

        if ($legacy) {
            DB::connection($this->adm)->table('invoice_requests')->where('id', (int)$legacy->id)->update($payload);
        } else {
            $ins = [
                'account_id' => $accountId,
                'period'     => $period,
            ] + $payload;

            if ($has('created_at')) $ins['created_at'] = now();

            DB::connection($this->adm)->table('invoice_requests')->insert($ins);
        }
    }
}