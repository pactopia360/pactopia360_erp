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
            'account_id'     => ['nullable','string','max:64'],
            'period'         => ['required','string','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],

            'sale_id'        => ['nullable','integer','min:0'],
            'is_projection'  => ['nullable','boolean'],

            'vendor_id'      => ['nullable','string'],
            'ec_status'      => ['nullable','string','max:20'],
            'invoice_status' => ['nullable','string','max:30'],
            'cfdi_uuid'      => ['nullable','string','max:60'],
            'rfc_receptor'   => ['nullable','string','max:20'],
            'forma_pago'     => ['nullable','string','max:40'],
            'notes'          => ['nullable','string','max:5000'],

            // Montos (opcional)
            'subtotal'       => ['nullable','numeric'],
            'iva'            => ['nullable','numeric'],
            'total'          => ['nullable','numeric'],

            // solo para ventas (si decides exponerlo)
            'include_in_statement'      => ['nullable','integer','in:0,1'],
            'statement_period_target'   => ['nullable','string','max:7'],
        ]);

        $saleId = (int) ($data['sale_id'] ?? 0);
        $period = (string) $data['period'];

        // Normaliza vendor_id
        $vendorId = $data['vendor_id'] ?? null;
        $vendorId = is_string($vendorId) && $vendorId !== '' ? (int) $vendorId : null;

        $ecStatus = $data['ec_status'] ?? null;
        $ecStatus = $ecStatus ? strtolower(trim((string)$ecStatus)) : null;

        $invStatus = $data['invoice_status'] ?? null;
        $invStatus = $invStatus ? strtolower(trim((string)$invStatus)) : null;

        $cfdiUuid = $data['cfdi_uuid'] ?? null;
        $cfdiUuid = $cfdiUuid ? trim((string)$cfdiUuid) : null;

        $rfcRec = $data['rfc_receptor'] ?? null;
        $rfcRec = $rfcRec ? strtoupper(trim((string)$rfcRec)) : null;

        $formaPago = $data['forma_pago'] ?? null;
        $formaPago = $formaPago ? trim((string)$formaPago) : null;

        $notes = $data['notes'] ?? null;
        $notes = $notes ? trim((string)$notes) : null;

        // -------------------------
        // A) Si es venta única -> actualizar finance_sales
        // -------------------------
        if ($saleId > 0 && Schema::connection($this->adm)->hasTable('finance_sales')) {

            $upd = [];

            if ($vendorId !== null) $upd['vendor_id'] = $vendorId;
            if ($ecStatus !== null) $upd['statement_status'] = $ecStatus;

            if ($invStatus !== null) $upd['invoice_status'] = $invStatus;
            if ($cfdiUuid !== null) $upd['cfdi_uuid'] = $cfdiUuid;
            if ($rfcRec !== null) $upd['receiver_rfc'] = $rfcRec;
            if ($formaPago !== null) $upd['pay_method'] = $formaPago;

            if (array_key_exists('subtotal', $data)) $upd['subtotal'] = $data['subtotal'] !== null ? round((float)$data['subtotal'], 2) : null;
            if (array_key_exists('iva', $data))      $upd['iva']      = $data['iva'] !== null ? round((float)$data['iva'], 2) : null;
            if (array_key_exists('total', $data))    $upd['total']    = $data['total'] !== null ? round((float)$data['total'], 2) : null;

            if ($notes !== null) $upd['notes'] = $notes;

            if (array_key_exists('include_in_statement', $data)) {
                $upd['include_in_statement'] = (int) ($data['include_in_statement'] ?? 0);
            }

            if (!empty($data['statement_period_target'])) {
                $spt = (string) $data['statement_period_target'];
                if (preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $spt)) $upd['statement_period_target'] = $spt;
            }

            if (!empty($upd)) {
                $upd['updated_at'] = now();

                DB::connection($this->adm)->table('finance_sales')
                    ->where('id', '=', $saleId)
                    ->update($upd);
            }

            return response()->json([
                'ok' => true,
                'mode' => 'finance_sales',
                'sale_id' => $saleId,
            ]);
        }

        // -------------------------
        // B) Proyección / Statement -> override row
        // -------------------------
        if (!Schema::connection($this->adm)->hasTable('finance_income_overrides')) {
            return response()->json([
                'ok' => false,
                'message' => 'Tabla finance_income_overrides no existe.',
            ], 422);
        }

        $accountId = (string) ($data['account_id'] ?? '');
        if ($accountId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'account_id requerido cuando no hay sale_id.',
            ], 422);
        }

        $rowType = !empty($data['is_projection']) ? 'projection' : 'statement';

        $payload = [
            'row_type'       => $rowType,
            'account_id'     => $accountId,
            'period'         => $period,
            'sale_id'        => null,

            'vendor_id'      => $vendorId,
            'ec_status'      => $ecStatus,
            'invoice_status' => $invStatus,
            'cfdi_uuid'      => $cfdiUuid,
            'rfc_receptor'   => $rfcRec,
            'forma_pago'     => $formaPago,

            'subtotal'       => array_key_exists('subtotal', $data) && $data['subtotal'] !== null ? round((float)$data['subtotal'],2) : null,
            'iva'            => array_key_exists('iva', $data) && $data['iva'] !== null ? round((float)$data['iva'],2) : null,
            'total'          => array_key_exists('total', $data) && $data['total'] !== null ? round((float)$data['total'],2) : null,

            'notes'          => $notes,
            'updated_by'     => (int) (auth('admin')->id() ?: 0) ?: null,
            'updated_at'     => now(),
        ];

        // Upsert por unique(row_type, account_id, period)
        DB::connection($this->adm)->table('finance_income_overrides')->updateOrInsert(
            ['row_type' => $rowType, 'account_id' => $accountId, 'period' => $period],
            $payload + ['created_at' => now()]
        );

        return response()->json([
            'ok' => true,
            'mode' => 'override',
            'row_type' => $rowType,
            'account_id' => $accountId,
            'period' => $period,
        ]);
    }

    /**
     * DELETE:
     * - Si $id > 0: elimina una venta (finance_sales.id)
     * - Si $id == 0: elimina un override por (row_type + account_id + period)
     */
    public function destroy(Request $req, int $id): JsonResponse
    {
        // -------------------------
        // A) Eliminar venta (finance_sales)
        // -------------------------
        if ($id > 0) {
            if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Tabla finance_sales no existe.',
                ], 422);
            }

            $q = DB::connection($this->adm)->table('finance_sales')->where('id', '=', $id);

            $exists = (bool) $q->exists();
            if (!$exists) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No existe la venta (sale_id=' . $id . ').',
                ], 404);
            }

            // Soft delete si existe deleted_at, si no, hard delete.
            $hasDeletedAt = Schema::connection($this->adm)->hasColumn('finance_sales', 'deleted_at');

            if ($hasDeletedAt) {
                $q->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
                return response()->json([
                    'ok' => true,
                    'mode' => 'finance_sales_soft_delete',
                    'sale_id' => $id,
                ]);
            }

            $q->delete();

            return response()->json([
                'ok' => true,
                'mode' => 'finance_sales_delete',
                'sale_id' => $id,
            ]);
        }

        // -------------------------
        // B) Eliminar override (finance_income_overrides) por key
        // -------------------------
        if (!Schema::connection($this->adm)->hasTable('finance_income_overrides')) {
            return response()->json([
                'ok' => false,
                'message' => 'Tabla finance_income_overrides no existe.',
            ], 422);
        }

        // Permitimos mandar por querystring o body (FormData) en DELETE:
        // row_type: 'projection'|'statement'  OR is_projection=1
        $period = (string) ($req->input('period') ?? $req->query('period') ?? '');
        $accountId = (string) ($req->input('account_id') ?? $req->query('account_id') ?? '');
        $rowType = (string) ($req->input('row_type') ?? $req->query('row_type') ?? '');

        $isProjection = $req->input('is_projection', $req->query('is_projection', null));
        if ($rowType === '') {
            $rowType = !empty($isProjection) ? 'projection' : 'statement';
        }
        $rowType = strtolower(trim($rowType));

        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            return response()->json([
                'ok' => false,
                'message' => 'period inválido (se requiere YYYY-MM).',
            ], 422);
        }

        if ($accountId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'account_id requerido para eliminar override.',
            ], 422);
        }

        if (!in_array($rowType, ['projection','statement'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'row_type inválido (projection|statement).',
            ], 422);
        }

        $q = DB::connection($this->adm)->table('finance_income_overrides')
            ->where('row_type', '=', $rowType)
            ->where('account_id', '=', $accountId)
            ->where('period', '=', $period);

        $exists = (bool) $q->exists();
        if (!$exists) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe override para esa key.',
            ], 404);
        }

        $q->delete();

        return response()->json([
            'ok' => true,
            'mode' => 'override_delete',
            'row_type' => $rowType,
            'account_id' => $accountId,
            'period' => $period,
        ]);
    }
}