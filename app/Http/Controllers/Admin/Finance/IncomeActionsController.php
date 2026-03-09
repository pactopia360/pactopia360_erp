<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IncomeActionsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function upsert(Request $req): JsonResponse
    {
        $data = $req->validate([
            'account_id'     => ['nullable', 'string', 'max:64'],
            'period'         => ['required', 'string', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],

            'sale_id'        => ['nullable', 'integer', 'min:0'],
            'is_projection'  => ['nullable', 'boolean'],
            'row_type'       => ['nullable', 'string', 'max:30'],
            'source'         => ['nullable', 'string', 'max:30'],

            'vendor_id'      => ['nullable', 'string'],
            'ec_status'      => ['nullable', 'string', 'max:20'],
            'invoice_status' => ['nullable', 'string', 'max:30'],
            'cfdi_uuid'      => ['nullable', 'string', 'max:60'],
            'rfc_receptor'   => ['nullable', 'string', 'max:20'],
            'forma_pago'     => ['nullable', 'string', 'max:40'],
            'notes'          => ['nullable', 'string', 'max:5000'],

            'subtotal'       => ['nullable', 'numeric'],
            'iva'            => ['nullable', 'numeric'],
            'total'          => ['nullable', 'numeric'],

            'include_in_statement'    => ['nullable', 'integer', 'in:0,1'],
            'statement_period_target' => ['nullable', 'string', 'max:7'],
        ]);

        $saleId = (int) ($data['sale_id'] ?? 0);
        $period = (string) $data['period'];
        $source = strtolower(trim((string) ($data['source'] ?? '')));
        $rowType = strtolower(trim((string) ($data['row_type'] ?? '')));

        // =========================================================================
        // SOT: INGRESOS ES SOLO REPORTE
        // - statements: SOLO lectura
        // - projections: bloqueado
        // - overrides: bloqueado
        // - solo permitimos actualizar finance_sales cuando sale_id > 0
        // =========================================================================
        if ($saleId <= 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ingresos es solo reporte. Los registros de Estados de Cuenta no se editan aquí; edítalos en su módulo origen.',
                'mode'    => 'read_only',
                'source'  => $source !== '' ? $source : ($rowType !== '' ? $rowType : 'statement'),
            ], 422);
        }

        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tabla finance_sales no existe.',
            ], 422);
        }

        $before = DB::connection($this->adm)
            ->table('finance_sales')
            ->where('id', '=', $saleId)
            ->first();

        if (!$before) {
            return response()->json([
                'ok'      => false,
                'message' => 'No existe la venta (sale_id=' . $saleId . ').',
            ], 404);
        }

        $vendorId = $data['vendor_id'] ?? null;
        $vendorId = is_string($vendorId) && trim($vendorId) !== '' ? (int) trim($vendorId) : null;

        $ecStatusInput = $data['ec_status'] ?? null;
        $ecStatusInput = $ecStatusInput !== null && trim((string) $ecStatusInput) !== ''
            ? strtolower(trim((string) $ecStatusInput))
            : null;

        $invStatusInput = $data['invoice_status'] ?? null;
        $invStatusInput = $invStatusInput !== null && trim((string) $invStatusInput) !== ''
            ? strtolower(trim((string) $invStatusInput))
            : null;

        $cfdiUuid = $data['cfdi_uuid'] ?? null;
        $cfdiUuid = $cfdiUuid !== null && trim((string) $cfdiUuid) !== ''
            ? trim((string) $cfdiUuid)
            : null;

        $rfcRec = $data['rfc_receptor'] ?? null;
        $rfcRec = $rfcRec !== null && trim((string) $rfcRec) !== ''
            ? strtoupper(trim((string) $rfcRec))
            : null;

        $formaPago = $data['forma_pago'] ?? null;
        $formaPago = $formaPago !== null && trim((string) $formaPago) !== ''
            ? trim((string) $formaPago)
            : null;

        $notes = $data['notes'] ?? null;
        $notes = $notes !== null && trim((string) $notes) !== ''
            ? trim((string) $notes)
            : null;

        $allowedStatementStatuses = $this->getEnumValues('finance_sales', 'statement_status');
        $allowedInvoiceStatuses   = $this->getEnumValues('finance_sales', 'invoice_status');

        $dbStatementStatus = $this->normalizeFinanceSalesStatementStatus(
            $ecStatusInput,
            $allowedStatementStatuses,
            (string) ($before->statement_status ?? '')
        );

        $dbInvoiceStatus = $this->normalizeFinanceSalesInvoiceStatus(
            $invStatusInput,
            $allowedInvoiceStatuses,
            (string) ($before->invoice_status ?? '')
        );

        $upd = [];

        if ($vendorId !== null) {
            $upd['vendor_id'] = $vendorId;
        }

        if ($dbStatementStatus !== null) {
            $upd['statement_status'] = $dbStatementStatus;
        }

        if ($dbInvoiceStatus !== null) {
            $upd['invoice_status'] = $dbInvoiceStatus;
        }

        if ($cfdiUuid !== null) {
            $upd['cfdi_uuid'] = $cfdiUuid;
        }

        if ($rfcRec !== null) {
            $upd['receiver_rfc'] = $rfcRec;
        }

        if ($formaPago !== null) {
            $upd['pay_method'] = $formaPago;
        }

        if (array_key_exists('subtotal', $data)) {
            $upd['subtotal'] = $data['subtotal'] !== null ? round((float) $data['subtotal'], 2) : null;
        }

        if (array_key_exists('iva', $data)) {
            $upd['iva'] = $data['iva'] !== null ? round((float) $data['iva'], 2) : null;
        }

        if (array_key_exists('total', $data)) {
            $upd['total'] = $data['total'] !== null ? round((float) $data['total'], 2) : null;
        }

        if ($notes !== null) {
            $upd['notes'] = $notes;
        }

        if (array_key_exists('include_in_statement', $data)) {
            $upd['include_in_statement'] = (int) ($data['include_in_statement'] ?? 0);
        }

        if (array_key_exists('statement_period_target', $data)) {
            $spt = trim((string) ($data['statement_period_target'] ?? ''));
            if ($spt === '') {
                $upd['statement_period_target'] = null;
            } elseif (preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $spt)) {
                $upd['statement_period_target'] = $spt;
            }
        }

        if (empty($upd)) {
            return response()->json([
                'ok'      => true,
                'mode'    => 'finance_sales_no_changes',
                'sale_id' => $saleId,
            ]);
        }

        $upd['updated_at'] = now();

        DB::connection($this->adm)->table('finance_sales')
            ->where('id', '=', $saleId)
            ->update($upd);

        $after = DB::connection($this->adm)
            ->table('finance_sales')
            ->where('id', '=', $saleId)
            ->first();

        $changedFields = $this->diffFields(
            $this->normalizeRecordForAudit((array) $before),
            $this->normalizeRecordForAudit((array) $after)
        );

        $this->writeAudit(
            action: 'finance_income.sale.update',
            entityType: 'finance_sale',
            entityId: $saleId,
            meta: [
                'module'         => 'finance_income',
                'action_scope'   => 'sale_update_from_income_readonly_mode',
                'changed_fields' => $changedFields,
                'before'         => $this->normalizeRecordForAudit((array) $before),
                'after'          => $this->normalizeRecordForAudit((array) $after),
                'context'        => [
                    'sale_id'                  => $saleId,
                    'period'                   => $period,
                    'account_id'               => (string) ($after->account_id ?? $before->account_id ?? ''),
                    'ui_ec_status'             => $ecStatusInput,
                    'db_statement_status'      => $dbStatementStatus,
                    'ui_invoice_status'        => $invStatusInput,
                    'db_invoice_status'        => $dbInvoiceStatus,
                    'allowed_statement_status' => $allowedStatementStatuses,
                    'allowed_invoice_status'   => $allowedInvoiceStatuses,
                ],
            ],
            request: $req
        );

        return response()->json([
            'ok'                  => true,
            'mode'                => 'finance_sales',
            'sale_id'             => $saleId,
            'statement_status_db' => $dbStatementStatus,
            'invoice_status_db'   => $dbInvoiceStatus,
        ]);
    }

    /**
     * DELETE:
     * - Bloqueado desde Ingresos para mantener modo espejo / solo lectura
     */
    public function destroy(Request $req, int $id): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'message' => 'Ingresos es solo reporte. Las eliminaciones deben hacerse en el módulo origen.',
            'mode'    => 'read_only',
            'id'      => $id,
        ], 422);
    }

    private function normalizeFinanceSalesStatementStatus(
        ?string $uiStatus,
        array $allowed,
        string $currentDbValue = ''
    ): ?string {
        if ($uiStatus === null || trim($uiStatus) === '') {
            return null;
        }

        $v = strtolower(trim($uiStatus));

        $mapped = match ($v) {
            'paid', 'pagado'         => 'pagado',
            'sent', 'emitido'        => 'emitido',
            'overdue', 'vencido'     => 'vencido',
            'pending', 'pendiente'   => 'pending',
            'parcial', 'partial'     => 'pending',
            default                  => $v,
        };

        if (empty($allowed)) {
            return match ($mapped) {
                'vencido' => 'pending',
                default   => $mapped,
            };
        }

        if (in_array($mapped, $allowed, true)) {
            return $mapped;
        }

        if ($mapped === 'vencido') {
            if (in_array('pending', $allowed, true)) {
                return 'pending';
            }
            if (in_array('emitido', $allowed, true)) {
                return 'emitido';
            }
        }

        if ($mapped === 'pagado' && in_array('paid', $allowed, true)) {
            return 'paid';
        }

        if ($mapped === 'emitido' && in_array('sent', $allowed, true)) {
            return 'sent';
        }

        if ($mapped === 'pending' && in_array('pendiente', $allowed, true)) {
            return 'pendiente';
        }

        $currentDbValue = strtolower(trim($currentDbValue));
        if ($currentDbValue !== '' && in_array($currentDbValue, $allowed, true)) {
            return $currentDbValue;
        }

        if (in_array('pending', $allowed, true)) {
            return 'pending';
        }

        return $allowed[0] ?? null;
    }

    private function normalizeFinanceSalesInvoiceStatus(
        ?string $uiStatus,
        array $allowed,
        string $currentDbValue = ''
    ): ?string {
        if ($uiStatus === null || trim($uiStatus) === '') {
            return null;
        }

        $v = strtolower(trim($uiStatus));
        $v = str_replace([' ', '-'], '_', $v);

        $mapped = match ($v) {
            'requested', 'solicitada', 'solicitado' => 'solicitada',
            'ready', 'en_proceso', 'procesando'     => 'en_proceso',
            'issued', 'facturada', 'facturado'      => 'facturada',
            'cancelled', 'canceled', 'rechazada', 'rechazado', 'cancelada', 'cancelado' => 'rechazada',
            'sin_solicitud', 'no_request', 'none'   => 'sin_solicitud',
            default                                 => $v,
        };

        if (empty($allowed)) {
            return $mapped;
        }

        if (in_array($mapped, $allowed, true)) {
            return $mapped;
        }

        $fallbackMap = [
            'requested'      => ['solicitada'],
            'ready'          => ['en_proceso'],
            'issued'         => ['facturada'],
            'cancelled'      => ['rechazada'],
            'sin_solicitud'  => ['sin_solicitud'],
            'solicitada'     => ['requested'],
            'en_proceso'     => ['ready'],
            'facturada'      => ['issued'],
            'rechazada'      => ['cancelled'],
        ];

        foreach (($fallbackMap[$mapped] ?? []) as $cand) {
            if (in_array($cand, $allowed, true)) {
                return $cand;
            }
        }

        $currentDbValue = strtolower(trim($currentDbValue));
        if ($currentDbValue !== '' && in_array($currentDbValue, $allowed, true)) {
            return $currentDbValue;
        }

        if (in_array('sin_solicitud', $allowed, true)) {
            return 'sin_solicitud';
        }

        return $allowed[0] ?? null;
    }

    private function getEnumValues(string $table, string $column): array
    {
        try {
            $rows = DB::connection($this->adm)->select(
                "SHOW COLUMNS FROM `{$table}` LIKE ?",
                [$column]
            );

            if (empty($rows)) {
                return [];
            }

            $row = (array) $rows[0];
            $type = (string) ($row['Type'] ?? $row['type'] ?? '');

            if (!preg_match("/^enum\\((.*)\\)$/i", $type, $m)) {
                return [];
            }

            $raw = (string) ($m[1] ?? '');
            if ($raw === '') {
                return [];
            }

            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $raw, $matches);

            $vals = array_map(
                static fn (string $v): string => stripcslashes($v),
                $matches[1] ?? []
            );

            return array_values(array_unique(array_filter($vals, static fn ($v) => $v !== '')));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function writeAudit(
        string $action,
        string $entityType,
        ?int $entityId,
        array $meta,
        Request $request
    ): void {
        if (!Schema::connection($this->adm)->hasTable('audit_logs')) {
            return;
        }

        try {
            DB::connection($this->adm)->table('audit_logs')->insert([
                'user_id'     => auth('admin')->id() ? (int) auth('admin')->id() : null,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'meta'        => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip'          => $request->ip(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function diffFields(array $before, array $after): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        $changed = [];

        foreach ($keys as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;

            if ($this->normalizeScalar($b) !== $this->normalizeScalar($a)) {
                $changed[] = $key;
            }
        }

        sort($changed);

        return $changed;
    }

    private function normalizeRecordForAudit(array $row): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            if (is_object($value)) {
                $out[$key] = (array) $value;
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $value;
                continue;
            }

            $out[$key] = $this->normalizeScalar($value);
        }

        ksort($out);

        return $out;
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if ($value === '') {
            return null;
        }

        return $value;
    }
}