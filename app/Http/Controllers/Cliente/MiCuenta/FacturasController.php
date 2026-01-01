<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\MiCuenta\FacturasController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\MiCuenta;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Cliente\AccountBillingController;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

final class FacturasController extends Controller
{
    /**
     * Lista solicitudes de facturas (SOT: mysql_admin.invoice_requests)
     * Vista para iframe/modal: resources/views/cliente/mi_cuenta/facturas/modal.blade.php
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

        // ✅ En embed NO abortar (para que el iframe no quede vacío o redirigido)
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

        // Si no existe tabla, se muestra vacío (pero sin romper).
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

        // Columnas “nota/meta”
        $notesCol = $has('notes') ? 'notes' : null;
        $metaCol  = $has('meta') ? 'meta' : null;

        // Detecta zip_path/archivo
        $zipPathCol = null;
        foreach (['zip_path', 'file_path', 'factura_path', 'path', 'ruta_zip'] as $c) {
            if ($has($c)) { $zipPathCol = $c; break; }
        }

        // Status
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

            // Normaliza estados “raros”
            if (in_array($st, ['pending', 'solicitada'], true)) $st = 'requested';
            if (in_array($st, ['processing', 'facturando', 'en_proceso', 'proceso'], true)) $st = 'in_progress';
            if (in_array($st, ['done', 'completed', 'facturada', 'invoiced', 'issued', 'emitido', 'emitida'], true)) $st = 'done';

            $zipPath = $zipPathCol ? (string) ($it->{$zipPathCol} ?? '') : '';
            $hasZip  = trim($zipPath) !== '';

            // ✅ Regla UI: si ya existe ZIP, está “lista” aunque status diga requested/in_progress
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

    /**
     * Ver detalle de una solicitud (por id) — útil si quieres abrir una vista/JSON.
     */
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

        if (!$row) {
            abort(404, 'Solicitud no encontrada.');
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'row' => $row]);
        }

        $view = 'cliente.mi_cuenta.facturas.show';
        if (view()->exists($view)) {
            return view($view, [
                'row'       => $row,
                'accountId' => $adminAccountId,
                'source'    => $src,
            ]);
        }

        return response(
            '<pre style="white-space:pre-wrap;word-break:break-word;padding:16px;font:13px/1.45 ui-monospace,Menlo,Consolas;">'
            . e(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            . '</pre>'
        );
    }

    /**
     * Solicitar factura (reusa exactamente la lógica del flujo de Estado de cuenta).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $period = (string) $validated['period'];

        /** @var AccountBillingController $billing */
        $billing = App::make(AccountBillingController::class);

        return $billing->requestInvoice($request, $period);
    }

    /**
     * Descarga ZIP por ID de solicitud.
     * Ruta: /cliente/mi-cuenta/facturas/{id}/download
     */
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

        $row = DB::connection($adm)->table('invoice_requests')
            ->where($fk, $adminAccountId)
            ->where('id', $id)
            ->first(['id', 'period']);

        if (!$row || empty($row->period)) {
            abort(404, 'Solicitud no encontrada o sin periodo.');
        }

        $period = (string) $row->period;

        /** @var AccountBillingController $billing */
        $billing = App::make(AccountBillingController::class);

        return $billing->downloadInvoiceZip($request, $period);
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

        // ✅ VISTA CORRECTA PARA IFRAME/MODAL
        $viewEmbed = 'cliente.mi_cuenta.facturas.modal';

        $viewFull  = 'cliente.mi_cuenta.facturas.index';

        $view = $embed ? $viewEmbed : $viewFull;

        // ✅ fallback si no existen las vistas
        if (!view()->exists($view)) {
            $view = view()->exists($viewFull) ? $viewFull : null;
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

        // Último fallback: HTML simple (para no dejar el iframe en blanco)
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

        if ($u && method_exists($u, 'relationLoaded') && !$u->relationLoaded('cuenta')) {
            try { $u->load('cuenta'); } catch (\Throwable $e) {}
        }

        $adminId = $u?->cuenta?->admin_account_id ?? null;
        if ($adminId) return [(string) $adminId, 'user.cuenta.admin_account_id'];

        if (!empty($u?->admin_account_id) && is_numeric($u->admin_account_id)) {
            return [(string) $u->admin_account_id, 'user.admin_account_id'];
        }

        $fallback = $req->session()->get('client.account_id');
        if ($fallback) return [(string) $fallback, 'session.client.account_id'];

        $fallback2 = $req->session()->get('account_id');
        if ($fallback2) return [(string) $fallback2, 'session.account_id'];

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
}
