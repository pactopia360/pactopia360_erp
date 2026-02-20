<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\MiCuenta\FacturasController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\MiCuenta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

final class FacturasController extends Controller
{
    /**
     * Lista solicitudes de facturas (SOT: mysql_admin.invoice_requests)
     * Vista para iframe/modal.
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

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('invoice_requests')) {
            Log::warning('[FACTURAS] invoice_requests missing in admin', [
                'account_id' => $adminAccountId,
                'src'        => $src,
            ]);

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
                'No existe la tabla invoice_requests en la base admin.'
            );
        }

        $cols = Schema::connection($adm)->getColumnListing('invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $fk = $this->adminFkColumn('invoice_requests');
        if (!$fk) {
            $msg = 'Configuración incompleta: invoice_requests sin columna FK de cuenta.';
            return $this->renderHardError($request, $msg, $adminAccountId, $src);
        }

        if (!$has('period')) {
            $msg = 'Configuración incompleta: invoice_requests sin columna period.';
            return $this->renderHardError($request, $msg, $adminAccountId, $src);
        }

        $orderCol = $has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]);

        $notesCol = $has('notes') ? 'notes' : null;
        $metaCol  = $has('meta') ? 'meta' : null;

        $zipPathCol = null;
        foreach (['zip_path', 'file_path', 'factura_path', 'path', 'ruta_zip'] as $c) {
            if ($has($c)) { $zipPathCol = $c; break; }
        }

        $statusCol = $has('status') ? 'status' : null;

        $select = [];
        foreach (array_filter([
            $has('id') ? 'id' : null,
            $fk,
            'period',
            $statusCol,
            $zipPathCol,
            $notesCol,
            $metaCol,
            $has('requested_at') ? 'requested_at' : null,
            $has('created_at') ? 'created_at' : null,
            $has('updated_at') ? 'updated_at' : null,
        ]) as $c) $select[] = $c;

        $query = DB::connection($adm)->table('invoice_requests')
            ->where($fk, $adminAccountId);

        if ($q !== '') {
            $query->where(function ($w) use ($q, $has) {
                $w->where('period', 'like', "%{$q}%");
                if ($has('id')) $w->orWhere('id', 'like', "%{$q}%");
            });
        }

        if ($status !== '' && $status !== 'all' && $statusCol) {
            $query->where($statusCol, $status);
        }

        $page = (int) $request->get('page', 1);
        if ($page <= 0) $page = 1;

        $total = (clone $query)->count();

        $items = $query
            ->orderByDesc($orderCol)
            ->forPage($page, $perPage)
            ->get($select);

        $rows = $items->map(function ($it) use ($statusCol, $zipPathCol, $notesCol, $metaCol) {
            $st = $statusCol ? strtolower((string) ($it->{$statusCol} ?? 'requested')) : 'requested';

            if (in_array($st, ['pending', 'solicitada'], true)) $st = 'requested';
            if (in_array($st, ['processing', 'facturando', 'en_proceso', 'proceso'], true)) $st = 'in_progress';
            if (in_array($st, ['done', 'completed', 'facturada', 'invoiced', 'issued', 'emitido', 'emitida'], true)) $st = 'done';

            $zipPath = $zipPathCol ? (string) ($it->{$zipPathCol} ?? '') : '';
            $hasZip  = trim($zipPath) !== '';

            if ($hasZip && in_array($st, ['requested', 'in_progress'], true)) {
                $st = 'done';
            }

            $notes = $notesCol ? (string) ($it->{$notesCol} ?? '') : '';
            if ($notes === '' && $metaCol) {
                $notes = '';
            }

            return (object) [
                'id'         => (int) ($it->id ?? 0),
                'period'     => (string) ($it->period ?? ''),
                'status'     => $st,
                'notes'      => $notes,
                'has_zip'    => $hasZip,
                'zip_path'   => $zipPath,
                'created_at' => $it->created_at ?? ($it->requested_at ?? null),
                'updated_at' => $it->updated_at ?? null,
            ];
        });

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->render($request, $paginator, $q, $status, $perPage, $adminAccountId, $src);
    }

    public function show(Request $request, int $id)
    {
        [$adminAccountId, $src] = $this->resolveAdminAccountId($request);
        $adminAccountId = is_numeric($adminAccountId) ? (int) $adminAccountId : 0;

        if ($adminAccountId <= 0) {
            return $this->renderHardError($request, 'Cuenta no encontrada (admin_account_id).', $adminAccountId, $src);
        }

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('invoice_requests')) {
            return $this->renderHardError($request, 'No existe invoice_requests en base admin.', $adminAccountId, $src);
        }

        $fk = $this->adminFkColumn('invoice_requests');
        if (!$fk) {
            return $this->renderHardError($request, 'invoice_requests sin FK de cuenta.', $adminAccountId, $src);
        }

        $row = DB::connection($adm)->table('invoice_requests')
            ->where($fk, $adminAccountId)
            ->where('id', $id)
            ->first();

        if (!$row) abort(404, 'Solicitud no encontrada.');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'row' => $row]);
        }

        $view = 'cliente.mi_cuenta.facturas.show';
        if (view()->exists($view)) {
            return view($view, ['row' => $row, 'accountId' => $adminAccountId, 'source' => $src]);
        }

        return response(
            '<pre style="white-space:pre-wrap;word-break:break-word;padding:16px;font:13px/1.45 ui-monospace,Menlo,Consolas;">'
            . e(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            . '</pre>'
        );
    }

    /**
     * ✅ FIX: crear la solicitud en mysql_admin.invoice_requests (lo que index() lista)
     * y regresar al modal con embed=1 para que se vea la línea inmediatamente.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period'   => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'notes'    => ['nullable', 'string', 'max:500'],
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

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('invoice_requests')) {
            Log::warning('[FACTURAS][STORE] invoice_requests missing', [
                'account_id' => $adminAccountId,
                'src'        => $src,
                'period'     => $period,
            ]);

            return back()->withErrors(['period' => 'No existe invoice_requests en la base admin.'])->withInput();
        }

        $cols = Schema::connection($adm)->getColumnListing('invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $fk = $this->adminFkColumn('invoice_requests');
        if (!$fk) {
            return back()->withErrors(['period' => 'invoice_requests no tiene FK de cuenta.'])->withInput();
        }
        if (!$has('period')) {
            return back()->withErrors(['period' => 'invoice_requests no tiene columna period.'])->withInput();
        }

        $statusCol = $has('status') ? 'status' : null;
        $notesCol  = $has('notes') ? 'notes' : null;
        $metaCol   = $has('meta') ? 'meta' : null;

        // 🔒 Evitar duplicados por account+period (si ya existe, solo regresamos ok)
        $existing = DB::connection($adm)->table('invoice_requests')
            ->where($fk, $adminAccountId)
            ->where('period', $period)
            ->orderByDesc($has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]))
            ->first($has('id') ? ['id', 'period', $statusCol] : ['period', $statusCol]);

        if ($existing) {
            $id = (int) ($existing->id ?? 0);
            return redirect()->route('cliente.mi_cuenta.facturas.index', $this->keepQueryForEmbed($request))
                ->with('ok', $id > 0
                    ? "Solicitud ya existente (#{$id}) para {$period}."
                    : "Solicitud ya existente para {$period}."
                );
        }

        $payload = [
            $fk     => $adminAccountId,
            'period'=> $period,
        ];

        if ($statusCol) $payload[$statusCol] = 'requested';
        if ($notesCol)  $payload[$notesCol]  = $notesIn;

        // Si no hay notes pero sí meta, guardamos notas en meta básico
        if (!$notesCol && $metaCol && $notesIn !== '') {
            $payload[$metaCol] = json_encode(['notes' => $notesIn], JSON_UNESCAPED_UNICODE);
        }

        if ($has('requested_at')) $payload['requested_at'] = now();
        if ($has('created_at'))   $payload['created_at']   = now();
        if ($has('updated_at'))   $payload['updated_at']   = now();

        $newId = (int) DB::connection($adm)->table('invoice_requests')->insertGetId($payload);

        Log::info('[FACTURAS][STORE] created invoice_request', [
            'id'         => $newId,
            'account_id' => $adminAccountId,
            'src'        => $src,
            'period'     => $period,
        ]);

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

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('invoice_requests')) {
            return $this->renderHardError($request, 'No existe invoice_requests en base admin.', $adminAccountId, $src);
        }

        $fk = $this->adminFkColumn('invoice_requests');
        if (!$fk) {
            return $this->renderHardError($request, 'invoice_requests sin FK de cuenta.', $adminAccountId, $src);
        }

        // Columnas dinámicas
        $cols = Schema::connection($adm)->getColumnListing('invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c) => in_array(strtolower($c), $lc, true);

        $zipPathCol = null;
        foreach (['zip_path', 'file_path', 'factura_path', 'path', 'ruta_zip', 'zip'] as $c) {
            if ($has($c)) { $zipPathCol = $c; break; }
        }

        if (!$zipPathCol) {
            return $this->renderHardError($request, 'invoice_requests no tiene columna de ZIP (zip_path/file_path/factura_path/path/ruta_zip).', $adminAccountId, $src);
        }

        $diskCol = null;
        foreach (['zip_disk', 'pdf_disk', 'disk', 'storage_disk'] as $c) {
            if ($has($c)) { $diskCol = $c; break; }
        }

        $select = ['id', $fk, 'period', $zipPathCol];
        if ($diskCol) $select[] = $diskCol;

        $row = DB::connection($adm)->table('invoice_requests')
            ->where($fk, $adminAccountId)
            ->where('id', $id)
            ->first($select);

        if (!$row) {
            abort(404, 'Solicitud no encontrada.');
        }

        $rawPath = trim((string) ($row->{$zipPathCol} ?? ''));
        if ($rawPath === '') {
            return $this->renderHardError($request, 'Aún no hay ZIP generado para esta solicitud.', $adminAccountId, $src);
        }

        $disk = $diskCol ? trim((string) ($row->{$diskCol} ?? '')) : '';
        if ($disk === '') $disk = 'public';

        // Normaliza "disk:path" si viene así
        if (preg_match('/^([a-zA-Z0-9_\-]+)\s*:\s*(.+)$/', $rawPath, $m)) {
            $disk    = trim($m[1]) !== '' ? trim($m[1]) : $disk;
            $rawPath = trim($m[2]);
        }

        // Normaliza rutas típicas
        $path = ltrim($rawPath, '/');

        // Si guardaron URL /storage/...
        if (str_starts_with($path, 'storage/')) {
            $path = ltrim(substr($path, strlen('storage/')), '/');
        }

        // Si guardaron "public/..."
        if (str_starts_with($path, 'public/')) {
            $path = ltrim(substr($path, strlen('public/')), '/');
            if ($disk === '') $disk = 'public';
        }

        // Si guardaron "app/public/..."
        if (str_starts_with($path, 'app/public/')) {
            $path = ltrim(substr($path, strlen('app/public/')), '/');
            if ($disk === '') $disk = 'public';
        }

        // Logging de diagnóstico (solo server)
        Log::info('[FACTURAS][ZIP] download attempt', [
            'invoice_request_id' => $id,
            'account_id'         => $adminAccountId,
            'src'                => $src,
            'disk'               => $disk,
            'col'                => $zipPathCol,
            'raw'                => $rawPath,
            'path'               => $path,
            'period'             => (string) ($row->period ?? ''),
        ]);

        try {
            if (!\Storage::disk($disk)->exists($path)) {
                Log::warning('[FACTURAS][ZIP] not found on disk', [
                    'invoice_request_id' => $id,
                    'account_id'         => $adminAccountId,
                    'disk'               => $disk,
                    'path'               => $path,
                ]);
                abort(404, 'ZIP no encontrado (storage).');
            }

            $period = (string) ($row->period ?? '');
            $name = 'Factura';
            if ($period !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                $name = 'Factura_' . $period;
            }
            $filename = $name . '.zip';

            return \Storage::disk($disk)->download($path, $filename);
        } catch (\Throwable $e) {
            Log::error('[FACTURAS][ZIP] download failed', [
                'invoice_request_id' => $id,
                'account_id'         => $adminAccountId,
                'disk'               => $disk,
                'path'               => $path,
                'err'                => $e->getMessage(),
            ]);
            abort(500, 'No se pudo descargar el ZIP.');
        }
    }
    // =========================
    // Helpers
    // =========================

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

        // ✅ Soporta ambos nombres (tú tienes facturas_modal.blade.php)
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
                if (view()->exists($v)) { $view = $v; break; }
            }
        }

        if (!$view) {
            foreach ($candidatesFull as $v) {
                if (view()->exists($v)) { $view = $v; break; }
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

        // Fallback HTML simple
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

        // 0) Param explícito si llegara (por compat)
        $routeAccountId = null;
        try { $routeAccountId = $req->route('account_id'); } catch (\Throwable $e) { $routeAccountId = null; }
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
            } catch (\Throwable $e) {}
            return [(string) $accountIdFromParam, 'param.account_id'];
        }

        // 1) cuenta cliente (UUID) desde sesión o desde usuarios_cuenta por email
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

                            $row = $q->first(['cuenta_id','email']);
                            $cid = $toStr($row?->cuenta_id ?? '');
                            if ($cid !== '') {
                                $clientCuentaId = $cid;
                                try { $req->session()->put('client.cuenta_id', $clientCuentaId); } catch (\Throwable $e) {}
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2) resolver admin_account_id desde cuentas_cliente
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
                        foreach (['$.cuenta_uuid','$.cuenta.id','$.cuenta_id','$.uuid','$.public_id','$.cliente_uuid'] as $path) {
                            $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, ?)) = ?", [$path, $clientCuentaId]);
                        }
                    }

                    $cc = $q->first();
                    if ($cc) {
                        if ($has('admin_account_id')) {
                            $aid = $toInt($cc->admin_account_id ?? null);
                            if ($aid > 0) { $adminFromClientCuenta = $aid; $adminFromClientSrc = 'cuentas_cliente.admin_account_id'; }
                        }
                        if ($adminFromClientCuenta <= 0 && $has('account_id')) {
                            $aid = $toInt($cc->account_id ?? null);
                            if ($aid > 0) { $adminFromClientCuenta = $aid; $adminFromClientSrc = 'cuentas_cliente.account_id'; }
                        }
                        if ($adminFromClientCuenta <= 0 && $has('meta') && isset($cc->meta)) {
                            try {
                                $meta = is_string($cc->meta) ? (json_decode((string)$cc->meta, true) ?: []) : (array)$cc->meta;
                                $aid  = $toInt(data_get($meta, 'admin_account_id'));
                                if ($aid > 0) { $adminFromClientCuenta = $aid; $adminFromClientSrc = 'cuentas_cliente.meta.admin_account_id'; }
                            } catch (\Throwable $e) {}
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 3) fallbacks por relación/field/session (namespaced primero)
        $adminFromUserRel = 0;
        try {
            if ($u && method_exists($u, 'relationLoaded') && !$u->relationLoaded('cuenta')) {
                try { $u->load('cuenta'); } catch (\Throwable $e) {}
            }
            $adminFromUserRel = $toInt($u?->cuenta?->admin_account_id ?? null);
        } catch (\Throwable $e) { $adminFromUserRel = 0; }

        $adminFromUserField = $toInt($u?->admin_account_id ?? null);

        [$adminFromSessionDirect, $sessionDirectSrc] = $pickSessionId($req, [
            'billing.admin_account_id',
            'verify.account_id',
            'paywall.account_id',
            'client.admin_account_id',
            // genéricas al final
            'admin_account_id',
            'account_id',
            'client.account_id',
            'client_account_id',
        ]);

        // 4) selección final
        if ($adminFromClientCuenta > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromClientCuenta);
                $req->session()->put('billing.admin_account_src', (string) ($adminFromClientSrc ?: 'cuentas_cliente'));
            } catch (\Throwable $e) {}
            return [(string) $adminFromClientCuenta, $adminFromClientSrc ?: 'cuentas_cliente'];
        }

        if ($adminFromUserRel > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromUserRel);
                $req->session()->put('billing.admin_account_src', 'user.cuenta.admin_account_id');
            } catch (\Throwable $e) {}
            return [(string) $adminFromUserRel, 'user.cuenta.admin_account_id'];
        }

        if ($adminFromUserField > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromUserField);
                $req->session()->put('billing.admin_account_src', 'user.admin_account_id');
            } catch (\Throwable $e) {}
            return [(string) $adminFromUserField, 'user.admin_account_id'];
        }

        if ($adminFromSessionDirect > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromSessionDirect);
                $req->session()->put('billing.admin_account_src', (string) ($sessionDirectSrc ?: 'session.direct'));
            } catch (\Throwable $e) {}
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
        // ✅ Asegura que el modal se recargue en embed y conserve filtros
        return [
            'embed'    => '1',
            'q'        => (string) $request->input('q', $request->query('q', '')),
            'status'   => (string) $request->input('status', $request->query('status', '')),
            'per_page' => (int) $request->input('per_page', $request->query('per_page', 10)),
        ];
    }

        /**
     * ✅ Resuelve/crea el billing_statements.id para account_id+period (admin).
     * billing_invoice_requests.statement_id es NOT NULL -> SIEMPRE se debe mandar.
     */
    private function resolveStatementIdForInvoice(int $accountId, string $period): int
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        // billing_statements.account_id es varchar(36) pero en tu sistema llega como int (6,21,...)
        $acctCandidates = array_values(array_unique([
            (string) $accountId,
            (int) $accountId,
        ]));

        $st = DB::connection($adm)->table('billing_statements')
            ->whereIn('account_id', $acctCandidates)
            ->where('period', $period)
            ->first(['id']);

        if ($st && isset($st->id) && (int)$st->id > 0) {
            return (int) $st->id;
        }

        // ✅ fallback seguro: si NO existe el statement para ese periodo, lo creamos mínimo.
        // (esto evita que truene la solicitud de factura por el NOT NULL)
        $now = now();

        DB::connection($adm)->table('billing_statements')->insert([
            'account_id'   => (string) $accountId,
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

        return $id > 0 ? $id : 0;
    }
}