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
use Illuminate\View\View;

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
                $row = DB::connection($this->adm)->table($table)->where('id', $id)->first(); // refrescar
            }

            // Sync cruzado para cliente
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
                $m->setBody($bodyHtml, 'text/html');

                if ($hasZip) {
                    $stream = Storage::disk($zipDisk)->readStream($zipPath);
                    $name = $zipName !== '' ? $zipName : basename($zipPath);

                    $tmp = tmpfile();
                    if ($tmp) {
                        stream_copy_to_stream($stream, $tmp);
                        $meta = stream_get_meta_data($tmp);
                        $tmpPath = $meta['uri'] ?? null;
                        if ($tmpPath) {
                            $m->attach($tmpPath, ['as' => $name, 'mime' => 'application/zip']);
                        }
                    }
                }
            });

            // marca zip_sent_at si existe
            $upd = [];
            if ($has('zip_sent_at')) $upd['zip_sent_at'] = now();
            if ($has('updated_at'))  $upd['updated_at']  = now();
            if (!empty($upd)) {
                DB::connection($this->adm)->table($table)->where('id', $id)->update($upd);
            }

            return back()->with('ok', 'Correo enviado (“Factura lista”).');
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Falló el envío: '.$e->getMessage()]);
        }
    }

    /**
     * Preferencia de tabla:
     * - Si forzas src=hub|legacy, respeta.
     * - Si existen ambas: mientras hub no esté completo, preferir legacy si tiene más filas.
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

            // Mientras migra: si legacy tiene más, mostrar legacy para no “perder” solicitudes
            if ($legacyCount > $hubCount) {
                return ['invoice_requests', 'legacy', 'Mostrando LEGACY porque tiene más solicitudes que HUB (migración en curso). Usa ?src=hub para forzar HUB.'];
            }

            return ['billing_invoice_requests', 'hub', null];
        }

        if ($hasHub) return ['billing_invoice_requests', 'hub', null];
        return ['invoice_requests', 'legacy', null];
    }

    /**
     * Normaliza ES/EN/UI -> DB.
     * legacy: requested|in_progress|done|rejected
     * hub:    requested|in_progress|issued|rejected
     */
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

            // finales
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

        // Ajuste por modo DB:
        if ($mode === 'hub') {
            // hub usa issued (no done)
            if ($canonical === 'done') return 'issued';
        } else {
            // legacy usa done (no issued)
            if ($canonical === 'issued') return 'done';
        }

        // Sanitiza por seguridad
        if (!in_array($canonical, ['requested','in_progress','done','issued','rejected'], true)) {
            $canonical = 'requested';
        }

        return $canonical;
    }


    /**
     * Legacy -> Hub (por account_id + period). Escribe solo columnas que existan.
     */
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

        foreach (['notes','cfdi_uuid','zip_disk','zip_path','zip_name','zip_size','zip_sha1','zip_ready_at','zip_sent_at','public_token','public_expires_at','statement_id'] as $c) {
            if ($has($c) && property_exists($legacy, $c)) {
                $payload[$c] = $legacy->{$c};
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

    /**
     * Sync mínimo desde hub -> legacy si existe.
     * Copia status + zip_path + cfdi_uuid + notes por (account_id, period).
     */
    private function syncToLegacyFromHub(int $hubId): void
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) return;
        if (!Schema::connection($this->adm)->hasTable('invoice_requests')) return;

        $hub = DB::connection($this->adm)->table('billing_invoice_requests')->where('id', $hubId)->first();
        if (!$hub) return;

        $accountId = (string)($hub->account_id ?? '');
        $period    = (string)($hub->period ?? '');
        if ($accountId === '' || $period === '') return;

        // legacy match por account_id + period
        $legacy = DB::connection($this->adm)->table('invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        // status: hub issued -> legacy done
        $statusLegacy = strtolower((string)($hub->status ?? 'requested'));
        if ($statusLegacy === 'issued') $statusLegacy = 'done';
        if (!in_array($statusLegacy, ['requested','in_progress','done','rejected'], true)) {
            $statusLegacy = 'requested';
        }

        // Columnas reales en legacy (por si no existen en alguna BD)
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
            // Crea registro legacy si no existe
            $ins = [
                'account_id' => $accountId,
                'period'     => $period,
            ] + $payload;

            if ($has('created_at')) $ins['created_at'] = now();

            DB::connection($this->adm)->table('invoice_requests')->insert($ins);
        }
    }



    
}
