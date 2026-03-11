<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\MiCuenta\FacturasController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\MiCuenta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class FacturasController extends Controller
{
    /**
     * Lista solicitudes de facturas.
     * SOT preferido:
     * - billing_invoice_requests
     * Fallback:
     * - invoice_requests
     */
    public function index(Request $request)
    {
        [$adminAccountId, $src] = $this->resolveAdminAccountId($request);
        $adminAccountId = is_numeric($adminAccountId) ? (int) $adminAccountId : 0;

        $q       = trim((string) $request->get('q', ''));
        $status  = trim((string) $request->get('status', ''));
        $perPage = (int) $request->get('per_page', 10);
        if ($perPage <= 0) $perPage = 10;
        if ($perPage > 50) $perPage = 50;

        if ($adminAccountId <= 0) {
            Log::warning('[FACTURAS] adminAccountId unresolved', ['src' => $src]);

            $paginator = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->get('page', 1),
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return $this->render(
                $request,
                $paginator,
                $q,
                $status,
                $perPage,
                $adminAccountId,
                $src,
                'Cuenta no encontrada (admin_account_id).'
            );
        }

        [$table, $mode, $softError] = $this->resolveInvoiceRequestsTableForAccount((string) $adminAccountId);
        if ($table === null) {
            $paginator = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->get('page', 1),
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return $this->render(
                $request,
                $paginator,
                $q,
                $status,
                $perPage,
                $adminAccountId,
                $src,
                $softError ?: 'No existe tabla de solicitudes de factura en admin.'
            );
        }

        $adm  = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $fk = $this->adminFkColumn($table);
        if (!$fk) {
            $msg = "Configuración incompleta: {$table} sin columna FK de cuenta.";
            return $this->renderHardError($request, $msg, $adminAccountId, $src);
        }

        $periodCol = $has('period') ? 'period' : ($has('periodo') ? 'periodo' : null);
        if (!$periodCol) {
            $msg = "Configuración incompleta: {$table} sin columna period/periodo.";
            return $this->renderHardError($request, $msg, $adminAccountId, $src);
        }

        $statusCol = $has('status') ? 'status' : ($has('estatus') ? 'estatus' : null);
        $notesCol  = $has('notes') ? 'notes' : null;
        $metaCol   = $has('meta') ? 'meta' : null;

        $orderCol = $has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]);

        $zipPathCol = null;
        foreach (['zip_path', 'file_path', 'factura_path', 'path', 'ruta_zip', 'zip'] as $c) {
            if ($has($c)) {
                $zipPathCol = $c;
                break;
            }
        }

        $select = [];
        foreach (array_filter([
            $has('id') ? 'id' : null,
            $fk,
            $periodCol,
            $statusCol,
            $zipPathCol,
            $notesCol,
            $metaCol,
            $has('cfdi_uuid') ? 'cfdi_uuid' : null,
            $has('requested_at') ? 'requested_at' : null,
            $has('zip_ready_at') ? 'zip_ready_at' : null,
            $has('zip_sent_at') ? 'zip_sent_at' : null,
            $has('created_at') ? 'created_at' : null,
            $has('updated_at') ? 'updated_at' : null,
        ]) as $c) {
            $select[] = $c;
        }

        $query = DB::connection($adm)->table($table)
            ->where($fk, (string) $adminAccountId);

        if ($q !== '') {
            $query->where(function ($w) use ($q, $periodCol, $statusCol, $notesCol, $has) {
                $w->where($periodCol, 'like', "%{$q}%");

                if ($has('id')) {
                    $w->orWhere('id', 'like', "%{$q}%");
                }

                if ($statusCol) {
                    $w->orWhere($statusCol, 'like', "%{$q}%");
                }

                if ($notesCol) {
                    $w->orWhere($notesCol, 'like', "%{$q}%");
                }

                if ($has('cfdi_uuid')) {
                    $w->orWhere('cfdi_uuid', 'like', "%{$q}%");
                }
            });
        }

        if ($status !== '' && $status !== 'all' && $statusCol) {
            $wanted = $this->normalizeStatusForDb($status, $mode);
            $query->where($statusCol, $wanted);
        }

        $page = (int) $request->get('page', 1);
        if ($page <= 0) $page = 1;

        $total = (clone $query)->count();

        $items = $query
            ->orderByDesc($orderCol)
            ->forPage($page, $perPage)
            ->get($select);

        $rows = $items->map(function ($it) use ($statusCol, $zipPathCol, $notesCol, $metaCol, $periodCol, $mode) {
            $rawStatus = $statusCol ? strtolower(trim((string) ($it->{$statusCol} ?? 'requested'))) : 'requested';
            $uiStatus  = $this->normalizeStatusForUi($rawStatus, $mode);

            $zipPath = $zipPathCol ? trim((string) ($it->{$zipPathCol} ?? '')) : '';
            $hasZip  = $zipPath !== '';

            $notes = $notesCol ? (string) ($it->{$notesCol} ?? '') : '';

            if ($notes === '' && $metaCol) {
                try {
                    $meta = is_string($it->{$metaCol} ?? null)
                        ? (json_decode((string) $it->{$metaCol}, true) ?: [])
                        : (array) ($it->{$metaCol} ?? []);

                    $notes = (string) (data_get($meta, 'notes', '') ?: '');
                } catch (\Throwable $e) {
                    $notes = '';
                }
            }

            return (object) [
                'id'           => (int) ($it->id ?? 0),
                'period'       => (string) ($it->{$periodCol} ?? ''),
                'status'       => $uiStatus,
                'notes'        => $notes,
                'cfdi_uuid'    => (string) ($it->cfdi_uuid ?? ''),
                'has_zip'      => $hasZip,
                'zip_path'     => $zipPath,
                'created_at'   => $it->created_at ?? ($it->requested_at ?? null),
                'updated_at'   => $it->updated_at ?? null,
                'zip_ready_at' => $it->zip_ready_at ?? null,
                'zip_sent_at'  => $it->zip_sent_at ?? null,
            ];
        });

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->render($request, $paginator, $q, $status, $perPage, $adminAccountId, $src, $softError);
    }

    public function show(Request $request, int $id)
    {
        [$adminAccountId, $src] = $this->resolveAdminAccountId($request);
        $adminAccountId = is_numeric($adminAccountId) ? (int) $adminAccountId : 0;

        if ($adminAccountId <= 0) {
            return $this->renderHardError($request, 'Cuenta no encontrada (admin_account_id).', $adminAccountId, $src);
        }

        [$table, $mode, $softError] = $this->resolveInvoiceRequestsTableForAccount((string) $adminAccountId);
        if ($table === null) {
            return $this->renderHardError(
                $request,
                $softError ?: 'No existe tabla de solicitudes de factura en admin.',
                $adminAccountId,
                $src
            );
        }

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $fk  = $this->adminFkColumn($table);

        if (!$fk) {
            return $this->renderHardError($request, "{$table} sin FK de cuenta.", $adminAccountId, $src);
        }

        $row = DB::connection($adm)->table($table)
            ->where($fk, (string) $adminAccountId)
            ->where('id', $id)
            ->first();

        if (!$row) {
            $other = $table === 'billing_invoice_requests' ? 'invoice_requests' : 'billing_invoice_requests';
            if (Schema::connection($adm)->hasTable($other)) {
                $otherFk = $this->adminFkColumn($other);
                if ($otherFk) {
                    $row = DB::connection($adm)->table($other)
                        ->where($otherFk, (string) $adminAccountId)
                        ->where('id', $id)
                        ->first();

                    if ($row) {
                        $table = $other;
                        $mode  = $table === 'billing_invoice_requests' ? 'hub' : 'legacy';
                    }
                }
            }
        }

        if (!$row) {
            abort(404, 'Solicitud no encontrada.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok'    => true,
                'row'   => $row,
                'mode'  => $mode,
                'table' => $table,
            ]);
        }

        $view = 'cliente.mi_cuenta.facturas.show';
        if (view()->exists($view)) {
            return view($view, [
                'row'       => $row,
                'accountId' => $adminAccountId,
                'source'    => $src,
                'mode'      => $mode,
                'table'     => $table,
            ]);
        }

        return response(
            '<pre style="white-space:pre-wrap;word-break:break-word;padding:16px;font:13px/1.45 ui-monospace,Menlo,Consolas;">'
            . e(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            . '</pre>'
        );
    }

    /**
     * Crea solicitud en tabla canónica:
     * - billing_invoice_requests
     * fallback:
     * - invoice_requests
     * y sincroniza espejo si existe.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period'   => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'notes'    => ['nullable', 'string', 'max:5000'],
            'embed'    => ['nullable'],
            'q'        => ['nullable'],
            'status'   => ['nullable'],
            'per_page' => ['nullable'],
        ]);

        $period  = (string) $validated['period'];
        $notesIn = trim((string) ($validated['notes'] ?? ''));

        [$adminAccountId, $src] = $this->resolveAdminAccountId($request);
        $adminAccountId = is_numeric($adminAccountId) ? (int) $adminAccountId : 0;

        if ($adminAccountId <= 0) {
            return back()->withErrors(['period' => 'No se pudo resolver la cuenta (admin_account_id).'])->withInput();
        }

        [$table, $mode, $softError] = $this->resolveInvoiceRequestsTableSmart(true);
        if ($table === null) {
            return back()->withErrors([
                'period' => $softError ?: 'No existe tabla de solicitudes de factura en admin.'
            ])->withInput();
        }

        $adm  = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $fk = $this->adminFkColumn($table);
        if (!$fk) {
            return back()->withErrors(['period' => "{$table} no tiene FK de cuenta."])->withInput();
        }

        $periodCol = $has('period') ? 'period' : ($has('periodo') ? 'periodo' : null);
        if (!$periodCol) {
            return back()->withErrors(['period' => "{$table} no tiene columna period/periodo."])->withInput();
        }

        $statusCol = $has('status') ? 'status' : ($has('estatus') ? 'estatus' : null);
        $notesCol  = $has('notes') ? 'notes' : null;
        $metaCol   = $has('meta') ? 'meta' : null;

        $queryExisting = DB::connection($adm)->table($table)
            ->where($fk, (string) $adminAccountId)
            ->where($periodCol, $period);

        $existing = $queryExisting
            ->orderByDesc($has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]))
            ->first($has('id') ? ['id', $periodCol] : [$periodCol]);

        if ($existing) {
            $id = (int) ($existing->id ?? 0);

            return redirect()->route('cliente.mi_cuenta.facturas.index', $this->keepQueryForEmbed($request))
                ->with('ok', $id > 0
                    ? "Solicitud ya existente (#{$id}) para {$period}."
                    : "Solicitud ya existente para {$period}."
                );
        }

        $payload = [
            $fk        => (string) $adminAccountId,
            $periodCol => $period,
        ];

        if ($statusCol) {
            $payload[$statusCol] = 'requested';
        }

        if ($notesCol) {
            $payload[$notesCol] = $notesIn !== '' ? $notesIn : null;
        }

        if (!$notesCol && $metaCol && $notesIn !== '') {
            $payload[$metaCol] = json_encode(['notes' => $notesIn], JSON_UNESCAPED_UNICODE);
        }

        if ($table === 'billing_invoice_requests' && $has('statement_id')) {
            $statementId = $this->resolveStatementIdForInvoice($adminAccountId, $period);
            if ($statementId <= 0) {
                return back()->withErrors([
                    'period' => 'No se pudo resolver billing_statements.id para esta solicitud.'
                ])->withInput();
            }
            $payload['statement_id'] = $statementId;
        }

        if ($has('requested_at')) $payload['requested_at'] = now();
        if ($has('created_at'))   $payload['created_at']   = now();
        if ($has('updated_at'))   $payload['updated_at']   = now();

        $newId = (int) DB::connection($adm)->table($table)->insertGetId($payload);

        Log::info('[FACTURAS][STORE] created invoice request', [
            'table'      => $table,
            'mode'       => $mode,
            'id'         => $newId,
            'account_id' => $adminAccountId,
            'src'        => $src,
            'period'     => $period,
        ]);

        $this->syncMirrorTableAfterStore(
            table: $table,
            adminAccountId: (string) $adminAccountId,
            period: $period,
            notes: $notesIn,
            requestId: $newId
        );

        return redirect()->route('cliente.mi_cuenta.facturas.index', $this->keepQueryForEmbed($request))
            ->with('ok', $newId > 0
                ? "Solicitud creada (#{$newId}) para {$period}."
                : "Solicitud creada para {$period}."
            );
    }

    public function downloadZip(Request $request, int $id)
    {
        [$adminAccountId, $src] = $this->resolveAdminAccountId($request);
        $adminAccountId = is_numeric($adminAccountId) ? (int) $adminAccountId : 0;

        if ($adminAccountId <= 0) {
            return $this->renderHardError($request, 'Cuenta no encontrada (admin_account_id).', $adminAccountId, $src);
        }

        [$table, $mode, $softError] = $this->resolveInvoiceRequestsTableForAccount((string) $adminAccountId);
        if ($table === null) {
            return $this->renderHardError(
                $request,
                $softError ?: 'No existe tabla de solicitudes de factura en admin.',
                $adminAccountId,
                $src
            );
        }

        $resolved = $this->resolveZipSourceRow((string) $adminAccountId, $id, $table);

        if (!$resolved) {
            $other = $table === 'billing_invoice_requests' ? 'invoice_requests' : 'billing_invoice_requests';
            $resolved = $this->resolveZipSourceRow((string) $adminAccountId, $id, $other);
        }

        if (!$resolved) {
            abort(404, 'Solicitud no encontrada.');
        }

        $table     = $resolved['table'];
        $mode      = $table === 'billing_invoice_requests' ? 'hub' : 'legacy';
        $row       = $resolved['row'];
        $periodCol = $resolved['period_col'];
        $zipPathCol= $resolved['zip_path_col'];
        $diskCol   = $resolved['disk_col'];

        $rawPath = trim((string) ($row->{$zipPathCol} ?? ''));
        if ($rawPath === '') {
            return $this->renderHardError($request, 'Aún no hay ZIP generado para esta solicitud.', $adminAccountId, $src);
        }

        $disk = $diskCol ? trim((string) ($row->{$diskCol} ?? '')) : '';
        if ($disk === '') $disk = 'public';

        if (preg_match('/^([a-zA-Z0-9_\-]+)\s*:\s*(.+)$/', $rawPath, $m)) {
            $disk    = trim($m[1]) !== '' ? trim($m[1]) : $disk;
            $rawPath = trim($m[2]);
        }

        $path = ltrim($rawPath, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = ltrim(substr($path, strlen('storage/')), '/');
        }

        if (str_starts_with($path, 'public/')) {
            $path = ltrim(substr($path, strlen('public/')), '/');
            if ($disk === '') $disk = 'public';
        }

        if (str_starts_with($path, 'app/public/')) {
            $path = ltrim(substr($path, strlen('app/public/')), '/');
            if ($disk === '') $disk = 'public';
        }

        Log::info('[FACTURAS][ZIP] download attempt', [
            'table'              => $table,
            'mode'               => $mode,
            'invoice_request_id' => $id,
            'account_id'         => $adminAccountId,
            'src'                => $src,
            'disk'               => $disk,
            'col'                => $zipPathCol,
            'raw'                => $rawPath,
            'path'               => $path,
            'period'             => (string) ($row->{$periodCol} ?? ''),
        ]);

        try {
            if (!Storage::disk($disk)->exists($path)) {
                Log::warning('[FACTURAS][ZIP] not found on disk', [
                    'table'              => $table,
                    'invoice_request_id' => $id,
                    'account_id'         => $adminAccountId,
                    'disk'               => $disk,
                    'path'               => $path,
                ]);
                abort(404, 'ZIP no encontrado (storage).');
            }

            $period = (string) ($row->{$periodCol} ?? '');
            $filename = $period !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)
                ? 'Factura_' . $period . '.zip'
                : 'Factura.zip';

            return Storage::disk($disk)->download($path, $filename);
        } catch (\Throwable $e) {
            Log::error('[FACTURAS][ZIP] download failed', [
                'table'              => $table,
                'invoice_request_id' => $id,
                'account_id'         => $adminAccountId,
                'disk'               => $disk,
                'path'               => $path,
                'err'                => $e->getMessage(),
            ]);
            abort(500, 'No se pudo descargar el ZIP.');
        }
    }

    private function render(
        Request $request,
        LengthAwarePaginator $rows,
        string $q,
        string $status,
        int $perPage,
        int $adminAccountId,
        string $src,
        ?string $softError = null
    ) {
        $embed = ((string) $request->get('embed', '') === '1');

        $candidatesEmbed = [
            'cliente.mi_cuenta.facturas.modal',
            'cliente.mi_cuenta.facturas_modal',
        ];

        $candidatesFull = [
            'cliente.mi_cuenta.facturas.index',
            'cliente.mi_cuenta.facturas_index',
        ];

        $view = null;

        if ($embed) {
            foreach ($candidatesEmbed as $v) {
                if (view()->exists($v)) {
                    $view = $v;
                    break;
                }
            }
        }

        if (!$view) {
            foreach ($candidatesFull as $v) {
                if (view()->exists($v)) {
                    $view = $v;
                    break;
                }
            }
        }

        $theme = (string) ($request->get('theme', 'light') ?: 'light');

        if ($view) {
            return view($view, [
                'rows'      => $rows,
                'q'         => $q,
                'status'    => $status,
                'perPage'   => $perPage,
                'accountId' => $adminAccountId,
                'source'    => $src,
                'error'     => $softError,
                'embed'     => $embed,
                'theme'     => $theme,
            ]);
        }

        $html = '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;padding:16px">';
        $html .= '<h3 style="margin:0 0 6px">Facturas</h3>';
        $html .= '<div style="color:#64748b;margin:0 0 12px">Solicitudes de factura y archivos generados.</div>';

        if ($softError) {
            $html .= '<div style="padding:10px 12px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.08);border-radius:12px;font-weight:700;margin-bottom:12px">'
                . e($softError) . '</div>';
        }

        $count = (int) $rows->total();
        $html .= '<div style="padding:10px 12px;border:1px solid rgba(15,23,42,.10);background:#fff;border-radius:12px">';
        $html .= '<div style="font-weight:800;margin-bottom:8px">Registros: ' . $count . '</div>';

        /** @var Collection $items */
        $items = collect($rows->items());

        if ($items->isEmpty()) {
            $html .= '<div style="color:#64748b">Aún no hay solicitudes.</div>';
        } else {
            $html .= '<ul style="margin:0;padding-left:18px">';
            foreach ($items as $r) {
                $html .= '<li><strong>' . e((string) ($r->period ?? '')) . '</strong> — ' . e((string) ($r->status ?? '')) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div></div>';

        return response($html);
    }

    private function renderHardError(Request $request, string $message, int $adminAccountId, string $src)
    {
        $embed = ((string) $request->get('embed', '') === '1');

        Log::warning('[FACTURAS] hard error', [
            'msg'        => $message,
            'account_id' => $adminAccountId,
            'src'        => $src,
        ]);

        if ($embed) {
            $paginator = new LengthAwarePaginator(
                collect(),
                0,
                10,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return $this->render($request, $paginator, '', '', 10, $adminAccountId, $src, $message);
        }

        abort(500, $message);
    }

    /**
     * Tabla canónica:
     * - billing_invoice_requests
     * fallback:
     * - invoice_requests
     *
     * @return array{0:?string,1:string,2:?string}
     */
    private function resolveInvoiceRequestsTableSmart(bool $preferHub = true): array
    {
        $adm       = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $hasHub    = Schema::connection($adm)->hasTable('billing_invoice_requests');
        $hasLegacy = Schema::connection($adm)->hasTable('invoice_requests');

        if (!$hasHub && !$hasLegacy) {
            return [null, 'missing', 'No existe billing_invoice_requests ni invoice_requests en la base admin.'];
        }

        if ($preferHub && $hasHub) {
            return ['billing_invoice_requests', 'hub', null];
        }

        if ($hasLegacy) {
            return ['invoice_requests', 'legacy', $hasHub ? 'Mostrando tabla legacy por compatibilidad.' : null];
        }

        if ($hasHub) {
            return ['billing_invoice_requests', 'hub', null];
        }

        return [null, 'missing', 'No existe tabla de solicitudes de factura.'];
    }

    /**
     * Elige tabla por cuenta:
     * - prioriza la que tenga registros para esa cuenta
     * - si ambas tienen, prioriza hub
     *
     * @return array{0:?string,1:string,2:?string}
     */
    private function resolveInvoiceRequestsTableForAccount(string $adminAccountId): array
    {
        [$table, $mode, $softError] = $this->resolveInvoiceRequestsTableSmart(true);
        if ($table === null) {
            return [$table, $mode, $softError];
        }

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $hasHub    = Schema::connection($adm)->hasTable('billing_invoice_requests');
        $hasLegacy = Schema::connection($adm)->hasTable('invoice_requests');

        $hubCount = 0;
        $legacyCount = 0;

        if ($hasHub) {
            $fkHub = $this->adminFkColumn('billing_invoice_requests');
            if ($fkHub) {
                $hubCount = (int) DB::connection($adm)->table('billing_invoice_requests')
                    ->where($fkHub, $adminAccountId)
                    ->count();
            }
        }

        if ($hasLegacy) {
            $fkLegacy = $this->adminFkColumn('invoice_requests');
            if ($fkLegacy) {
                $legacyCount = (int) DB::connection($adm)->table('invoice_requests')
                    ->where($fkLegacy, $adminAccountId)
                    ->count();
            }
        }

        if ($hubCount > 0) {
            return ['billing_invoice_requests', 'hub', null];
        }

        if ($legacyCount > 0) {
            return ['invoice_requests', 'legacy', $hasHub ? 'Mostrando tabla legacy por compatibilidad.' : null];
        }

        return [$table, $mode, $softError];
    }

    private function normalizeStatusForDb(string $status, string $mode): string
    {
        $s = strtolower(trim($status));

        $map = [
            'requested'   => 'requested',
            'solicitada'  => 'requested',
            'solicitado'  => 'requested',
            'pending'     => 'requested',

            'in_progress' => 'in_progress',
            'processing'  => 'in_progress',
            'proceso'     => 'in_progress',
            'en_proceso'  => 'in_progress',
            'en proceso'  => 'in_progress',

            'done'        => $mode === 'hub' ? 'issued' : 'done',
            'issued'      => $mode === 'hub' ? 'issued' : 'done',
            'emitida'     => $mode === 'hub' ? 'issued' : 'done',
            'emitido'     => $mode === 'hub' ? 'issued' : 'done',
            'facturada'   => $mode === 'hub' ? 'issued' : 'done',
            'invoiced'    => $mode === 'hub' ? 'issued' : 'done',

            'rejected'    => 'rejected',
            'rechazada'   => 'rejected',
            'rechazado'   => 'rejected',

            'error'       => 'error',
            'failed'      => 'error',
        ];

        return $map[$s] ?? 'requested';
    }

    private function normalizeStatusForUi(string $status, string $mode): string
    {
        $s = strtolower(trim($status));

        if (in_array($s, ['pending', 'solicitada', 'solicitado'], true)) return 'requested';
        if (in_array($s, ['processing', 'facturando', 'en_proceso', 'en proceso', 'proceso'], true)) return 'in_progress';
        if (in_array($s, ['done', 'completed', 'facturada', 'invoiced', 'issued', 'emitido', 'emitida'], true)) return 'done';
        if (in_array($s, ['rejected', 'rechazada', 'rechazado'], true)) return 'rejected';
        if (in_array($s, ['error', 'failed'], true)) return 'error';

        if ($mode === 'hub' && $s === 'issued') return 'done';

        return $s !== '' ? $s : 'requested';
    }

    private function resolveAdminAccountId(Request $req): array
    {
        $u = Auth::guard('web')->user();

        $toInt = static function ($v): int {
            if ($v === null) return 0;
            if (is_int($v)) return $v > 0 ? $v : 0;
            if (is_numeric($v)) {
                $i = (int) $v;
                return $i > 0 ? $i : 0;
            }
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '' && is_numeric($v)) {
                    $i = (int) $v;
                    return $i > 0 ? $i : 0;
                }
            }
            return 0;
        };

        $toStr = static function ($v): string {
            if ($v === null) return '';
            if (is_string($v)) return trim($v);
            return trim((string) $v);
        };

        $pickSessionId = function (Request $req, array $keys) use ($toInt): array {
            foreach ($keys as $k) {
                $v = $req->session()->get($k);
                $id = $toInt($v);
                if ($id > 0) return [$id, 'session.' . $k];
            }
            return [0, ''];
        };

        $routeAccountId = null;
        try {
            $routeAccountId = $req->route('account_id');
        } catch (\Throwable $e) {
            $routeAccountId = null;
        }

        $accountIdFromParam =
            $toInt($routeAccountId)
            ?: $toInt($req->query('account_id'))
            ?: $toInt($req->input('account_id'))
            ?: $toInt($req->query('aid'))
            ?: $toInt($req->input('aid'));

        if ($accountIdFromParam > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $accountIdFromParam);
                $req->session()->put('billing.admin_account_src', 'param.account_id');
            } catch (\Throwable $e) {
            }

            return [(string) $accountIdFromParam, 'param.account_id'];
        }

        $clientCuentaIdRaw =
            $req->session()->get('client.cuenta_id')
            ?? $req->session()->get('cuenta_id')
            ?? $req->session()->get('client_cuenta_id')
            ?? null;

        $clientCuentaId = $toStr($clientCuentaIdRaw);

        if ($clientCuentaId === '') {
            try {
                $email = strtolower(trim((string) ($u?->email ?? '')));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cli = (string) config('p360.conn.clients', 'mysql_clientes');

                    if (Schema::connection($cli)->hasTable('usuarios_cuenta')) {
                        $cols = Schema::connection($cli)->getColumnListing('usuarios_cuenta');
                        $lc   = array_map('strtolower', $cols);
                        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                        if ($has('email') && $has('cuenta_id')) {
                            $q = DB::connection($cli)->table('usuarios_cuenta')
                                ->whereRaw('LOWER(TRIM(email)) = ?', [$email]);

                            if ($has('activo')) $q->where('activo', 1);
                            if ($has('rol'))  $q->orderByRaw("CASE WHEN rol='owner' THEN 0 ELSE 1 END");
                            if ($has('tipo')) $q->orderByRaw("CASE WHEN tipo='owner' THEN 0 ELSE 1 END");

                            $orderCol = $has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0]);
                            $q->orderByDesc($orderCol);

                            $row = $q->first(['cuenta_id', 'email']);
                            $cid = $toStr($row?->cuenta_id ?? '');

                            if ($cid !== '') {
                                $clientCuentaId = $cid;
                                try {
                                    $req->session()->put('client.cuenta_id', $clientCuentaId);
                                } catch (\Throwable $e) {
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $adminFromClientCuenta = 0;
        $adminFromClientSrc    = '';

        if ($clientCuentaId !== '') {
            try {
                $cli = (string) config('p360.conn.clients', 'mysql_clientes');

                if (Schema::connection($cli)->hasTable('cuentas_cliente')) {
                    $cols = Schema::connection($cli)->getColumnListing('cuentas_cliente');
                    $lc   = array_map('strtolower', $cols);
                    $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                    $sel = ['id'];
                    foreach (['admin_account_id', 'account_id', 'meta'] as $c) {
                        if ($has($c)) $sel[] = $c;
                    }
                    $sel = array_values(array_unique($sel));

                    $q = DB::connection($cli)->table('cuentas_cliente')->select($sel);
                    $q->where('id', $clientCuentaId);

                    $asInt = $toInt($clientCuentaId);
                    if ($asInt > 0) $q->orWhere('id', $asInt);

                    if ($has('meta')) {
                        foreach (['$.cuenta_uuid', '$.cuenta.id', '$.cuenta_id', '$.uuid', '$.public_id', '$.cliente_uuid'] as $path) {
                            $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, ?)) = ?", [$path, $clientCuentaId]);
                        }
                    }

                    $cc = $q->first();

                    if ($cc) {
                        if ($has('admin_account_id')) {
                            $aid = $toInt($cc->admin_account_id ?? null);
                            if ($aid > 0) {
                                $adminFromClientCuenta = $aid;
                                $adminFromClientSrc = 'cuentas_cliente.admin_account_id';
                            }
                        }

                        if ($adminFromClientCuenta <= 0 && $has('account_id')) {
                            $aid = $toInt($cc->account_id ?? null);
                            if ($aid > 0) {
                                $adminFromClientCuenta = $aid;
                                $adminFromClientSrc = 'cuentas_cliente.account_id';
                            }
                        }

                        if ($adminFromClientCuenta <= 0 && $has('meta') && isset($cc->meta)) {
                            try {
                                $meta = is_string($cc->meta) ? (json_decode((string) $cc->meta, true) ?: []) : (array) $cc->meta;
                                $aid  = $toInt(data_get($meta, 'admin_account_id'));
                                if ($aid > 0) {
                                    $adminFromClientCuenta = $aid;
                                    $adminFromClientSrc = 'cuentas_cliente.meta.admin_account_id';
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $adminFromUserRel = 0;
        try {
            if ($u && method_exists($u, 'relationLoaded') && !$u->relationLoaded('cuenta')) {
                try {
                    $u->load('cuenta');
                } catch (\Throwable $e) {
                }
            }
            $adminFromUserRel = $toInt($u?->cuenta?->admin_account_id ?? null);
        } catch (\Throwable $e) {
            $adminFromUserRel = 0;
        }

        $adminFromUserField = $toInt($u?->admin_account_id ?? null);

        [$adminFromSessionDirect, $sessionDirectSrc] = $pickSessionId($req, [
            'billing.admin_account_id',
            'verify.account_id',
            'paywall.account_id',
            'client.admin_account_id',
            'admin_account_id',
            'account_id',
            'client.account_id',
            'client_account_id',
        ]);

        if ($adminFromClientCuenta > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromClientCuenta);
                $req->session()->put('billing.admin_account_src', (string) ($adminFromClientSrc ?: 'cuentas_cliente'));
            } catch (\Throwable $e) {
            }
            return [(string) $adminFromClientCuenta, $adminFromClientSrc ?: 'cuentas_cliente'];
        }

        if ($adminFromUserRel > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromUserRel);
                $req->session()->put('billing.admin_account_src', 'user.cuenta.admin_account_id');
            } catch (\Throwable $e) {
            }
            return [(string) $adminFromUserRel, 'user.cuenta.admin_account_id'];
        }

        if ($adminFromUserField > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromUserField);
                $req->session()->put('billing.admin_account_src', 'user.admin_account_id');
            } catch (\Throwable $e) {
            }
            return [(string) $adminFromUserField, 'user.admin_account_id'];
        }

        if ($adminFromSessionDirect > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromSessionDirect);
                $req->session()->put('billing.admin_account_src', (string) ($sessionDirectSrc ?: 'session.direct'));
            } catch (\Throwable $e) {
            }
            return [(string) $adminFromSessionDirect, $sessionDirectSrc ?: 'session.direct'];
        }

        return [null, 'unresolved'];
    }

    private function adminFkColumn(string $table): ?string
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        if (!Schema::connection($adm)->hasTable($table)) return null;

        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);

        foreach (['account_id', 'cuenta_id', 'admin_account_id', 'accountid', 'id_account', 'idcuenta'] as $c) {
            if (in_array(strtolower($c), $lc, true)) {
                foreach ($cols as $real) {
                    if (strtolower($real) === strtolower($c)) return $real;
                }
                return $c;
            }
        }

        return null;
    }

    private function keepQueryForEmbed(Request $request): array
    {
        return [
            'embed'    => '1',
            'q'        => (string) $request->input('q', $request->query('q', '')),
            'status'   => (string) $request->input('status', $request->query('status', '')),
            'per_page' => (int) $request->input('per_page', $request->query('per_page', 10)),
        ];
    }

    /**
     * Resuelve/crea billing_statements.id para account_id+period.
     * Solo se usa si la tabla hub tiene statement_id.
     */
    private function resolveStatementIdForInvoice(int $accountId, string $period): int
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $period = trim((string) $period);
        if ($accountId <= 0 || $period === '') return 0;
        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) return 0;

        if (!Schema::connection($adm)->hasTable('billing_statements')) {
            return 0;
        }

        $canonStr = (string) $accountId;

        $st = DB::connection($adm)->table('billing_statements')
            ->where('account_id', $canonStr)
            ->where('period', $period)
            ->first(['id']);

        if ($st && isset($st->id) && (int) $st->id > 0) {
            return (int) $st->id;
        }

        $now = now();

        try {
            DB::connection($adm)->table('billing_statements')->insert([
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

            $id = (int) DB::connection($adm)->getPdo()->lastInsertId();
            if ($id > 0) return $id;
        } catch (\Throwable $e) {
            $again = DB::connection($adm)->table('billing_statements')
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
     * Sincroniza la otra tabla si existe.
     */
    private function syncMirrorTableAfterStore(string $table, string $adminAccountId, string $period, string $notes, int $requestId): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $mirror = $table === 'billing_invoice_requests'
            ? 'invoice_requests'
            : 'billing_invoice_requests';

        if (!Schema::connection($adm)->hasTable($mirror)) {
            return;
        }

        $fk = $this->adminFkColumn($mirror);
        if (!$fk) {
            return;
        }

        $cols = Schema::connection($adm)->getColumnListing($mirror);
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $periodCol = $has('period') ? 'period' : ($has('periodo') ? 'periodo' : null);
        if (!$periodCol) {
            return;
        }

        $exists = DB::connection($adm)->table($mirror)
            ->where($fk, $adminAccountId)
            ->where($periodCol, $period)
            ->exists();

        if ($exists) {
            return;
        }

        $payload = [
            $fk        => $adminAccountId,
            $periodCol => $period,
        ];

        if ($has('status'))  $payload['status'] = $mirror === 'billing_invoice_requests' ? 'requested' : 'requested';
        if ($has('estatus')) $payload['estatus'] = 'requested';
        if ($has('notes'))   $payload['notes'] = $notes !== '' ? $notes : null;

        if ($mirror === 'billing_invoice_requests' && $has('statement_id')) {
            $statementId = $this->resolveStatementIdForInvoice((int) $adminAccountId, $period);
            if ($statementId > 0) {
                $payload['statement_id'] = $statementId;
            } else {
                return;
            }
        }

        if ($has('requested_at')) $payload['requested_at'] = now();
        if ($has('created_at'))   $payload['created_at']   = now();
        if ($has('updated_at'))   $payload['updated_at']   = now();

        try {
            DB::connection($adm)->table($mirror)->insert($payload);

            Log::info('[FACTURAS][STORE] mirrored invoice request', [
                'source_table' => $table,
                'mirror_table' => $mirror,
                'source_id'    => $requestId,
                'account_id'   => $adminAccountId,
                'period'       => $period,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[FACTURAS][STORE] mirror insert failed', [
                'source_table' => $table,
                'mirror_table' => $mirror,
                'source_id'    => $requestId,
                'account_id'   => $adminAccountId,
                'period'       => $period,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Busca la fila con ZIP en una tabla dada.
     *
     * @return array{table:string,row:object,period_col:string,zip_path_col:string,disk_col:?string}|null
     */
    private function resolveZipSourceRow(string $adminAccountId, int $id, string $table): ?array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($adm)->hasTable($table)) {
            return null;
        }

        $fk = $this->adminFkColumn($table);
        if (!$fk) {
            return null;
        }

        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $periodCol = $has('period') ? 'period' : ($has('periodo') ? 'periodo' : null);
        if (!$periodCol) {
            return null;
        }

        $zipPathCol = null;
        foreach (['zip_path', 'file_path', 'factura_path', 'path', 'ruta_zip', 'zip'] as $c) {
            if ($has($c)) {
                $zipPathCol = $c;
                break;
            }
        }

        if (!$zipPathCol) {
            return null;
        }

        $diskCol = null;
        foreach (['zip_disk', 'pdf_disk', 'disk', 'storage_disk'] as $c) {
            if ($has($c)) {
                $diskCol = $c;
                break;
            }
        }

        $select = ['id', $fk, $periodCol, $zipPathCol];
        if ($diskCol) $select[] = $diskCol;

        $row = DB::connection($adm)->table($table)
            ->where($fk, $adminAccountId)
            ->where('id', $id)
            ->first($select);

        if (!$row) {
            return null;
        }

        return [
            'table'        => $table,
            'row'          => $row,
            'period_col'   => $periodCol,
            'zip_path_col' => $zipPathCol,
            'disk_col'     => $diskCol,
        ];
    }
}