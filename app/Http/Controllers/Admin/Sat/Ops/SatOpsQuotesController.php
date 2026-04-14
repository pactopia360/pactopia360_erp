<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use App\Services\Sat\Client\SatQuoteService;

final class SatOpsQuotesController extends Controller
{
    public function __construct(
        private readonly SatQuoteService $quoteService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q'      => trim((string) $request->input('q', '')),
            'status' => strtolower(trim((string) $request->input('status', ''))),
            'rfc'    => strtoupper(trim((string) $request->input('rfc', ''))),
            'desde'  => trim((string) $request->input('desde', '')),
            'hasta'  => trim((string) $request->input('hasta', '')),
            'per'    => min(100, max(10, (int) $request->input('per', 20))),
        ];

        $query = SatDownload::query()
            ->where(function ($q) {
                $q->where('tipo', 'quote')
                  ->orWhere('status', 'requested')
                  ->orWhere('status', 'pending');
            })
            ->where(function ($q) {
                $q->whereNotNull('meta')
                  ->orWhereNotNull('total')
                  ->orWhereNotNull('subtotal')
                  ->orWhereNotNull('costo');
            });

        if ($filters['q'] !== '') {
            $q = $filters['q'];

            $query->where(function ($sub) use ($q) {
                $sub->where('rfc', 'like', '%' . $q . '%')
                    ->orWhere('tipo', 'like', '%' . $q . '%')
                    ->orWhere('status', 'like', '%' . $q . '%');
            });
        }

        if ($filters['rfc'] !== '') {
            $query->where('rfc', $filters['rfc']);
        }

        if ($filters['status'] !== '' && in_array($filters['status'], ['borrador', 'en_proceso', 'cotizada', 'pagada', 'en_descarga', 'completada', 'cancelada'], true)) {
            $query->getQuery()->wheres[] = []; // noop para mantener estructura limpia
        }

        if ($filters['desde'] !== '') {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }

        if ($filters['hasta'] !== '') {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        $rows = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->paginate($filters['per'])
            ->through(function (SatDownload $row) {
                return $this->mapQuoteRow($row);
            });

        if ($filters['status'] !== '' && in_array($filters['status'], ['borrador', 'en_proceso', 'cotizada', 'pagada', 'en_descarga', 'completada', 'cancelada'], true)) {
            $filtered = collect($rows->items())
                ->filter(fn (array $row) => $row['status_ui'] === $filters['status'])
                ->values();

            $rows->setCollection($filtered);
        }

        return view('admin.sat.ops.quotes.index', [
            'title'   => 'SAT · Operación · Cotizaciones',
            'rows'    => $rows,
            'filters' => $filters,
            'stats'   => [
                'borrador'    => $this->countByUiStatus('borrador'),
                'en_proceso'  => $this->countByUiStatus('en_proceso'),
                'cotizada'    => $this->countByUiStatus('cotizada'),
                'pagada'      => $this->countByUiStatus('pagada'),
                'en_descarga' => $this->countByUiStatus('en_descarga'),
                'completada'  => $this->countByUiStatus('completada'),
                'cancelada'   => $this->countByUiStatus('cancelada'),
            ],
        ]);
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'status_ui' => ['required', 'string', 'in:borrador,en_proceso,cotizada,pagada,en_descarga,completada,cancelada'],
            'notes'     => ['nullable', 'string', 'max:5000'],
        ]);

        $statusUi = (string) $data['status_ui'];
        $notes    = trim((string) ($data['notes'] ?? ''));

        [$dbStatus, $progress] = match ($statusUi) {
            'borrador'    => ['pending', 10],
            'en_proceso'  => ['requested', 35],
            'cotizada'    => ['ready', 65],
            'pagada'      => ['paid', 82],
            'en_descarga' => ['paid', 90],
            'completada'  => ['done', 100],
            'cancelada'   => ['canceled', 0],
            default       => ['pending', 10],
        };

        $meta = is_array($row->meta ?? null) ? $row->meta : [];
        $meta['status_ui'] = $statusUi;
        $meta['progress']  = $progress;

        if ($notes !== '') {
            $meta['admin_notes'] = $notes;
        }

        if ($statusUi === 'cotizada') {
            $meta['can_pay'] = true;
            $meta['customer_action'] = 'pay_pending';
        }

        if ($statusUi === 'pagada') {
            $meta['can_pay'] = false;
            $meta['customer_action'] = 'payment_confirmed';
            if (!$row->paid_at) {
                $row->paid_at = now();
            }
        }

        if ($statusUi === 'en_descarga') {
            $meta['can_pay'] = false;
            $meta['customer_action'] = 'download_in_progress';
            if (!$row->paid_at) {
                $row->paid_at = now();
            }
        }

        if ($statusUi === 'cancelada') {
            $meta['can_pay'] = false;
            if (!$row->canceled_at) {
                $row->canceled_at = now();
            }
        }

        if ($statusUi === 'completada') {
            $meta['can_pay'] = false;
            $meta['customer_action'] = 'completed';
            $meta['download_stage'] = 'completed';
            $meta['completed_at'] = (string) (data_get($meta, 'completed_at') ?: now()->toIso8601String());

            if (!$row->completed_at) {
                $row->completed_at = now();
            }
        }

        $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
        $quote['status_ui'] = $statusUi;
        $quote['progress'] = $progress;
        $quote['admin_notes'] = (string) ($meta['admin_notes'] ?? '');
        $meta['quote'] = $quote;

        $row->status = $dbStatus;
        $row->meta   = $meta;
        $row->save();

        return back()->with('success', 'Estatus de cotización actualizado correctamente.');
    }

        public function updateQuote(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'rfc'              => ['required', 'string', 'min:12', 'max:13'],
            'tipo_solicitud'   => ['required', 'string', 'in:emitidos,recibidos,ambos'],
            'xml_count'        => ['required', 'integer', 'min:1', 'max:50000000'],
            'date_from'        => ['required', 'date'],
            'date_to'          => ['required', 'date', 'after_or_equal:date_from'],
            'discount_code'    => ['nullable', 'string', 'max:64'],
            'iva_rate'         => ['nullable'],
            'concepto'         => ['nullable', 'string', 'max:1000'],
            'admin_notes'      => ['nullable', 'string', 'max:5000'],
            'commercial_notes' => ['nullable', 'string', 'max:5000'],

            /* se aceptan pero ya no se usan como fuente de verdad */
            'subtotal'         => ['nullable'],
            'iva'              => ['nullable'],
            'total'            => ['nullable'],
        ]);

        $meta = is_array($row->meta ?? null) ? $row->meta : [];

        $rfc = strtoupper(trim((string) $data['rfc']));
        $tipoSolicitud = strtolower(trim((string) $data['tipo_solicitud']));
        $xmlCount = (int) $data['xml_count'];
        $discountCode = trim((string) ($data['discount_code'] ?? ''));
        $ivaRate = $this->quoteService->normalizeIvaRate($data['iva_rate'] ?? data_get($meta, 'iva_rate', 16));

        $dateFrom = Carbon::parse((string) $data['date_from'])->startOfDay();
        $dateTo = Carbon::parse((string) $data['date_to'])->endOfDay();

        $cuentaId = trim((string) ($row->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return back()->with('error', 'La cotización no tiene cuenta asociada para recalcular.');
        }

        try {
            $payload = $this->quoteService->buildSatQuotePayload(
                user: auth()->user(),
                cuentaId: $cuentaId,
                xmlCount: $xmlCount,
                discountCode: $discountCode,
                ivaRate: $ivaRate,
                useAdminPrice: true
            );
        } catch (\Throwable $e) {
            Log::error('[SAT:adminQuotes] Error recalculando cotización en edición admin', [
                'quote_id'       => $id,
                'cuenta_id'      => $cuentaId,
                'rfc'            => $rfc,
                'tipo_solicitud' => $tipoSolicitud,
                'xml_count'      => $xmlCount,
                'discount_code'  => $discountCode,
                'iva_rate'       => $ivaRate,
                'err'            => $e->getMessage(),
            ]);

            return back()->with('error', 'No se pudo recalcular la cotización con la lista de precios.');
        }

        $concepto = trim((string) ($data['concepto'] ?? ''));
        if ($concepto === '') {
            $concepto = $this->buildAdminQuoteConcept(
                tipoSolicitud: $tipoSolicitud,
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );
        }

        $adminNotes = trim((string) ($data['admin_notes'] ?? ''));
        $commercialNotes = trim((string) ($data['commercial_notes'] ?? ''));

        $row->rfc = $rfc;
        $row->date_from = $dateFrom;
        $row->date_to = $dateTo;
        $row->xml_count = $xmlCount;
        $row->cfdi_count = $xmlCount;

        /* fuente de verdad: recálculo */
        $row->subtotal = (float) ($payload['subtotal'] ?? 0);
        $row->iva = (float) ($payload['ivaAmount'] ?? 0);
        $row->total = (float) ($payload['total'] ?? 0);
        $row->costo = (float) ($payload['base'] ?? 0);

        $meta['rfc'] = $rfc;
        $meta['tipo'] = $tipoSolicitud;
        $meta['tipo_solicitud'] = $tipoSolicitud;
        $meta['date_from'] = $dateFrom->toDateString();
        $meta['date_to'] = $dateTo->toDateString();
        $meta['concepto'] = $concepto;

        $meta['xml_count'] = $xmlCount;
        $meta['base'] = (float) ($payload['base'] ?? 0);
        $meta['subtotal'] = (float) ($payload['subtotal'] ?? 0);
        $meta['iva_rate'] = (int) ($payload['ivaRate'] ?? $ivaRate);
        $meta['iva_amount'] = (float) ($payload['ivaAmount'] ?? 0);
        $meta['total'] = (float) ($payload['total'] ?? 0);

        $meta['discount_code'] = (string) ($payload['discountCode'] ?? $discountCode);
        $meta['discount_code_applied'] = (string) ($payload['discountCodeApplied'] ?? '');
        $meta['discount_label'] = (string) ($payload['discountLabel'] ?? '');
        $meta['discount_reason'] = (string) ($payload['discountReason'] ?? '');
        $meta['discount_type'] = (string) ($payload['discountType'] ?? '');
        $meta['discount_value'] = $payload['discountValue'] ?? null;
        $meta['discount_pct'] = (float) ($payload['discountPct'] ?? 0);
        $meta['discount_amount'] = (float) ($payload['discountAmount'] ?? 0);
        $meta['price_source'] = (string) ($payload['priceSource'] ?? 'admin');

        $meta['admin_notes'] = $adminNotes;
        $meta['commercial_notes'] = $commercialNotes;
        $meta['updated_from_admin_quote_editor_at'] = now()->toIso8601String();

        $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
        $quote['rfc'] = $rfc;
        $quote['tipo'] = $tipoSolicitud;
        $quote['tipo_solicitud'] = $tipoSolicitud;
        $quote['date_from'] = $dateFrom->toDateString();
        $quote['date_to'] = $dateTo->toDateString();
        $quote['concepto'] = $concepto;

        $quote['xml_count'] = $xmlCount;
        $quote['base'] = (float) ($payload['base'] ?? 0);
        $quote['subtotal'] = (float) ($payload['subtotal'] ?? 0);
        $quote['iva_rate'] = (int) ($payload['ivaRate'] ?? $ivaRate);
        $quote['iva_amount'] = (float) ($payload['ivaAmount'] ?? 0);
        $quote['total'] = (float) ($payload['total'] ?? 0);

        $quote['discount_code'] = (string) ($payload['discountCode'] ?? $discountCode);
        $quote['discount_code_applied'] = (string) ($payload['discountCodeApplied'] ?? '');
        $quote['discount_label'] = (string) ($payload['discountLabel'] ?? '');
        $quote['discount_reason'] = (string) ($payload['discountReason'] ?? '');
        $quote['discount_type'] = (string) ($payload['discountType'] ?? '');
        $quote['discount_value'] = $payload['discountValue'] ?? null;
        $quote['discount_pct'] = (float) ($payload['discountPct'] ?? 0);
        $quote['discount_amount'] = (float) ($payload['discountAmount'] ?? 0);
        $quote['price_source'] = (string) ($payload['priceSource'] ?? 'admin');

        $quote['admin_notes'] = $adminNotes;
        $quote['commercial_notes'] = $commercialNotes;
        $quote['updated_from_admin_quote_editor_at'] = now()->toIso8601String();

        $meta['quote'] = $quote;

        $row->meta = $meta;
        $row->save();

        return back()->with('success', 'Cotización actualizada correctamente y recalculada con la lista de precios.');
    }

    public function confirmQuote(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'subtotal'         => ['required', 'numeric', 'min:0'],
            'iva'              => ['required', 'numeric', 'min:0'],
            'total'            => ['required', 'numeric', 'min:0'],
            'admin_notes'      => ['nullable', 'string', 'max:5000'],
            'commercial_notes' => ['nullable', 'string', 'max:5000'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
        ]);

        DB::transaction(function () use ($row, $data) {
            $meta = is_array($row->meta ?? null) ? $row->meta : [];

            $row->subtotal = (float) $data['subtotal'];
            $row->iva      = (float) $data['iva'];
            $row->total    = (float) $data['total'];
            $row->status   = 'ready';

            $meta['status_ui'] = 'cotizada';
            $meta['progress'] = 65;
            $meta['admin_notes'] = trim((string) ($data['admin_notes'] ?? ''));
            $meta['commercial_notes'] = trim((string) ($data['commercial_notes'] ?? ''));
            $meta['confirmed_at'] = now()->toIso8601String();
            $meta['can_pay'] = true;
            $meta['customer_action'] = 'pay_pending';

            $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
            $quote['subtotal'] = (float) $row->subtotal;
            $quote['iva_amount'] = (float) $row->iva;
            $quote['total'] = (float) $row->total;
            $quote['status_ui'] = 'cotizada';
            $quote['confirmed_at'] = now()->toIso8601String();
            $quote['admin_notes'] = (string) $meta['admin_notes'];
            $quote['commercial_notes'] = (string) $meta['commercial_notes'];
            $meta['quote'] = $quote;

            $row->meta = $meta;
            $row->save();
        });

        $customerEmail = trim((string) ($data['customer_email'] ?? ''));

        if ($customerEmail !== '') {
            $this->sendConfirmedQuoteEmail(
                to: $customerEmail,
                row: $row->fresh()
            );
        }

        return back()->with('success', 'Cotización confirmada correctamente y lista para pago del cliente.');
    }

    public function rejectQuote(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'reject_reason'  => ['required', 'string', 'max:5000'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $reason = trim((string) $data['reject_reason']);
        $meta   = is_array($row->meta ?? null) ? $row->meta : [];

        $row->status = 'canceled';
        $row->canceled_at = now();

        $meta['status_ui'] = 'cancelada';
        $meta['progress'] = 0;
        $meta['reject_reason'] = $reason;
        $meta['rejected_at'] = now()->toIso8601String();

        $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
        $quote['status_ui'] = 'cancelada';
        $quote['reject_reason'] = $reason;
        $quote['rejected_at'] = now()->toIso8601String();
        $meta['quote'] = $quote;

        $row->meta = $meta;
        $row->save();

        $customerEmail = trim((string) ($data['customer_email'] ?? ''));
        if ($customerEmail !== '') {
            $this->sendRejectedQuoteEmail(
                to: $customerEmail,
                row: $row->fresh(),
                reason: $reason
            );
        }

        return back()->with('success', 'Cotización rechazada correctamente.');
    }

        public function approveTransfer(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'approval_notes'    => ['nullable', 'string', 'max:5000'],
            'move_to_download'  => ['nullable', 'in:0,1'],
            'customer_email'    => ['nullable', 'email', 'max:255'],
        ]);

        $approvalNotes = trim((string) ($data['approval_notes'] ?? ''));
        $moveToDownload = (string) ($data['move_to_download'] ?? '1') === '1';

        DB::transaction(function () use ($row, $approvalNotes, $moveToDownload) {
            $meta = is_array($row->meta ?? null) ? $row->meta : [];
            $transfer = is_array(data_get($meta, 'transfer_review')) ? data_get($meta, 'transfer_review') : [];

            $transfer['review_status'] = 'approved';
            $transfer['approved_at'] = now()->toIso8601String();
            $transfer['approved_by'] = (string) (auth()->id() ?? '');
            $transfer['approval_notes'] = $approvalNotes;
            $transfer['ai_status'] = (string) ($transfer['ai_status'] ?? 'manual_pending');
            $transfer['manual_resolution'] = 'approved';

            $meta['transfer_review'] = $transfer;
            $meta['payment_method'] = 'transfer';
            $meta['can_pay'] = false;
            $meta['paid_via'] = 'transfer';
            $meta['paid_confirmed_at'] = now()->toDateTimeString();
            $meta['customer_action'] = $moveToDownload ? 'download_in_progress' : 'payment_confirmed';
            $meta['status_ui'] = $moveToDownload ? 'en_descarga' : 'pagada';
            $meta['progress'] = $moveToDownload ? 90 : 82;

            $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
            $quote['status_ui'] = $meta['status_ui'];
            $quote['progress'] = $meta['progress'];
            $quote['paid_via'] = 'transfer';
            $quote['paid_confirmed_at'] = $meta['paid_confirmed_at'];
            $quote['approval_notes'] = $approvalNotes;
            $meta['quote'] = $quote;

            $row->status = 'paid';
            if (!$row->paid_at) {
                $row->paid_at = now();
            }

            $row->meta = $meta;
            $row->save();
        });

        $fresh = $row->fresh();

        try {
            $this->sendTransferApprovedInternalEmail($fresh);
        } catch (\Throwable $e) {
            Log::warning('[SAT:adminQuotes] No se pudo enviar correo interno de transferencia aprobada', [
                'quote' => (string) $row->getKey(),
                'err'   => $e->getMessage(),
            ]);
        }

        $customerEmail = trim((string) ($data['customer_email'] ?? ''));
        if ($customerEmail !== '') {
            try {
                $this->sendTransferApprovedCustomerEmail($customerEmail, $fresh);
            } catch (\Throwable $e) {
                Log::warning('[SAT:adminQuotes] No se pudo enviar correo al cliente por transferencia aprobada', [
                    'to'    => $customerEmail,
                    'quote' => (string) $row->getKey(),
                    'err'   => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Transferencia aprobada correctamente.');
    }

    public function rejectTransfer(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'reject_reason'   => ['required', 'string', 'max:5000'],
            'customer_email'  => ['nullable', 'email', 'max:255'],
        ]);

        $reason = trim((string) $data['reject_reason']);

        DB::transaction(function () use ($row, $reason) {
            $meta = is_array($row->meta ?? null) ? $row->meta : [];
            $transfer = is_array(data_get($meta, 'transfer_review')) ? data_get($meta, 'transfer_review') : [];

            $transfer['review_status'] = 'rejected';
            $transfer['rejected_at'] = now()->toIso8601String();
            $transfer['rejected_by'] = (string) (auth()->id() ?? '');
            $transfer['reject_reason'] = $reason;
            $transfer['manual_resolution'] = 'rejected';

            $meta['transfer_review'] = $transfer;
            $meta['customer_action'] = 'payment_rejected';
            $meta['status_ui'] = 'cotizada';
            $meta['progress'] = 65;
            $meta['can_pay'] = true;
            $meta['transfer_reject_reason'] = $reason;

            $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
            $quote['status_ui'] = 'cotizada';
            $quote['progress'] = 65;
            $quote['transfer_reject_reason'] = $reason;
            $meta['quote'] = $quote;

            $row->status = 'ready';
            $row->meta = $meta;
            $row->save();
        });

        $fresh = $row->fresh();

        $customerEmail = trim((string) ($data['customer_email'] ?? ''));
        if ($customerEmail !== '') {
            try {
                $this->sendTransferRejectedCustomerEmail($customerEmail, $fresh, $reason);
            } catch (\Throwable $e) {
                Log::warning('[SAT:adminQuotes] No se pudo enviar correo al cliente por transferencia rechazada', [
                    'to'    => $customerEmail,
                    'quote' => (string) $row->getKey(),
                    'err'   => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Transferencia rechazada correctamente.');
    }

    public function markSatRequested(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'sat_request_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $meta = is_array($row->meta ?? null) ? $row->meta : [];
        $meta['sat_request'] = [
            'status'       => 'requested',
            'requested_at' => now()->toIso8601String(),
            'notes'        => trim((string) ($data['sat_request_notes'] ?? '')),
        ];

        $meta['status_ui'] = 'en_proceso';
        $meta['progress']  = max(35, (int) data_get($meta, 'progress', 35));

        $row->status = 'requested';
        $row->meta   = $meta;
        $row->save();

        return back()->with('success', 'Base de solicitud SAT registrada correctamente.');
    }

        public function completeQuote(Request $request, string $id): RedirectResponse
    {
        $row = SatDownload::query()->findOrFail($id);

        $data = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $notes = trim((string) ($data['completion_notes'] ?? ''));

        DB::transaction(function () use ($row, $notes) {
            $meta = is_array($row->meta ?? null) ? $row->meta : [];

            $meta['status_ui'] = 'completada';
            $meta['progress'] = 100;
            $meta['can_pay'] = false;
            $meta['customer_action'] = 'completed';
            $meta['download_stage'] = 'completed';
            $meta['completed_at'] = now()->toIso8601String();

            if ($notes !== '') {
                $meta['completion_notes'] = $notes;
                $meta['admin_notes'] = $notes;
            }

            $quote = is_array(data_get($meta, 'quote')) ? data_get($meta, 'quote') : [];
            $quote['status_ui'] = 'completada';
            $quote['progress'] = 100;
            $quote['completed_at'] = now()->toIso8601String();

            if ($notes !== '') {
                $quote['completion_notes'] = $notes;
                $quote['admin_notes'] = $notes;
            }

            $meta['quote'] = $quote;

            $row->status = 'done';

            if (!$row->paid_at) {
                $row->paid_at = now();
            }

            if (!$row->completed_at) {
                $row->completed_at = now();
            }

            $row->meta = $meta;
            $row->save();
        });

        return back()->with('success', 'Entrega finalizada correctamente. La cotización ya quedó completada.');
    }

    private function mapQuoteRow(SatDownload $row): array
    {
        $meta = is_array($row->meta ?? null) ? $row->meta : [];

        $folio = trim((string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote_no')
            ?: ''
        ));

        if ($folio === '') {
            $rawId = (string) $row->getKey();
            $folio = 'COT-' . str_pad(substr(preg_replace('/[^A-Za-z0-9]/', '', $rawId) ?: '0', -6), 6, '0', STR_PAD_LEFT);
        }

        $rfc = strtoupper(trim((string) (
            $row->rfc
            ?: data_get($meta, 'rfc')
            ?: ''
        )));

        $razonSocial = trim((string) (
            data_get($meta, 'razon_social')
            ?: data_get($meta, 'empresa')
            ?: ''
        ));

        $tipoSolicitud = strtolower(trim((string) (
            data_get($meta, 'tipo_solicitud')
            ?: data_get($meta, 'tipo')
            ?: 'emitidos'
        )));

        $concepto = trim((string) (
            data_get($meta, 'concepto')
            ?: data_get($meta, 'note')
            ?: 'Cotización SAT'
        ));

        $statusUi = $this->normalizeQuoteUiStatus($row);
        $progress = $this->resolveQuoteProgress($row, $statusUi);

        $dateFrom = $row->date_from instanceof Carbon
            ? $row->date_from->format('Y-m-d')
            : (string) data_get($meta, 'date_from', '');

        $dateTo = $row->date_to instanceof Carbon
            ? $row->date_to->format('Y-m-d')
            : (string) data_get($meta, 'date_to', '');

        return [
            'id'                 => (string) $row->getKey(),
            'folio'              => $folio,
            'rfc'                => $rfc,
            'razon_social'       => $razonSocial,
            'tipo_solicitud'     => $tipoSolicitud,
            'concepto'           => $concepto,
            'xml_count'          => (int) ($row->xml_count ?? data_get($meta, 'xml_count', 0)),
            'subtotal'           => (float) ($row->subtotal ?? data_get($meta, 'subtotal', 0)),
            'iva'                => (float) ($row->iva ?? data_get($meta, 'iva_amount', 0)),
            'total'              => (float) ($row->total ?? data_get($meta, 'total', 0)),
            'status_ui'          => $statusUi,
            'progress'           => $progress,
            'date_from'          => $dateFrom,
            'date_to'            => $dateTo,
            'generated_at'       => (string) data_get($meta, 'generated_at', ''),
            'valid_until'        => (string) data_get($meta, 'valid_until', ''),
            'notes'              => (string) data_get($meta, 'notes', ''),
            'admin_notes'        => (string) data_get($meta, 'admin_notes', ''),
            'commercial_notes'   => (string) data_get($meta, 'commercial_notes', ''),
            'reject_reason'      => (string) data_get($meta, 'reject_reason', ''),
            'confirmed_at'       => (string) data_get($meta, 'confirmed_at', ''),
            'rejected_at'        => (string) data_get($meta, 'rejected_at', ''),
            'completed_at'       => (string) data_get($meta, 'completed_at', ''),
            'completion_notes'   => (string) data_get($meta, 'completion_notes', ''),
            'can_pay'            => (bool) data_get($meta, 'can_pay', false),
            'sat_request_status' => (string) data_get($meta, 'sat_request.status', ''),
            'transfer_review'    => is_array(data_get($meta, 'transfer_review')) ? data_get($meta, 'transfer_review') : [],
            'paid_via'           => (string) data_get($meta, 'paid_via', ''),
            'created_at'         => $row->created_at,
            'updated_at'         => $row->updated_at,
            'meta'               => $meta,
        ];
    }

        private function sendConfirmedQuoteEmail(string $to, SatDownload $row): void
    {
        try {
            $mapped = $this->mapQuoteRow($row);

            $subject = 'Cotización SAT confirmada · ' . (string) ($mapped['folio'] ?? 'SIN-FOLIO');

            $lines = [
                'Tu cotización SAT ha sido confirmada.',
                '',
                'Folio: ' . (string) ($mapped['folio'] ?? ''),
                'RFC: ' . (string) ($mapped['rfc'] ?? ''),
                'Razón social: ' . (string) ($mapped['razon_social'] ?? ''),
                'Tipo: ' . (string) ($mapped['tipo_solicitud'] ?? ''),
                'Periodo: ' . (string) ($mapped['date_from'] ?? '') . ' a ' . (string) ($mapped['date_to'] ?? ''),
                'CFDI/XML estimados: ' . number_format((int) ($mapped['xml_count'] ?? 0)),
                'Subtotal: $' . number_format((float) ($mapped['subtotal'] ?? 0), 2, '.', ','),
                'IVA: $' . number_format((float) ($mapped['iva'] ?? 0), 2, '.', ','),
                'Total: $' . number_format((float) ($mapped['total'] ?? 0), 2, '.', ','),
                '',
                'Notas de revisión:',
                (string) ($mapped['commercial_notes'] ?: $mapped['admin_notes'] ?: 'Sin notas adicionales'),
                '',
                'Ya puedes ingresar al portal cliente para proceder con el pago.',
            ];

            Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[SAT:adminQuotes] No se pudo enviar correo de cotización confirmada', [
                'to'    => $to,
                'quote' => (string) $row->getKey(),
                'err'   => $e->getMessage(),
            ]);
        }
    }

    private function sendRejectedQuoteEmail(string $to, SatDownload $row, string $reason): void
    {
        try {
            $mapped = $this->mapQuoteRow($row);

            $subject = 'Cotización SAT rechazada · ' . (string) ($mapped['folio'] ?? 'SIN-FOLIO');

            $lines = [
                'Tu solicitud de cotización SAT no pudo ser confirmada.',
                '',
                'Folio: ' . (string) ($mapped['folio'] ?? ''),
                'RFC: ' . (string) ($mapped['rfc'] ?? ''),
                'Razón social: ' . (string) ($mapped['razon_social'] ?? ''),
                '',
                'Motivo / notas:',
                $reason !== '' ? $reason : 'No se especificó un motivo.',
                '',
                'Por favor revisa los datos y vuelve a generar la solicitud en portal cliente si aplica.',
            ];

            Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[SAT:adminQuotes] No se pudo enviar correo de cotización rechazada', [
                'to'    => $to,
                'quote' => (string) $row->getKey(),
                'err'   => $e->getMessage(),
            ]);
        }
    }

        private function sendTransferApprovedInternalEmail(SatDownload $row): void
    {
        try {
            $mapped = $this->mapQuoteRow($row);
            $transfer = is_array($mapped['transfer_review'] ?? null) ? $mapped['transfer_review'] : [];

            $subject = 'Transferencia aprobada · ' . (string) ($mapped['folio'] ?? 'SIN-FOLIO');

            $lines = [
                'La transferencia fue aprobada correctamente.',
                '',
                'Folio: ' . (string) ($mapped['folio'] ?? ''),
                'RFC: ' . (string) ($mapped['rfc'] ?? ''),
                'Razón social: ' . (string) ($mapped['razon_social'] ?? ''),
                'Monto: $' . number_format((float) ($mapped['total'] ?? 0), 2, '.', ','),
                'Referencia: ' . (string) ($transfer['reference'] ?? ''),
                'Banco: ' . (string) ($transfer['bank_name'] ?? ''),
                'Estado actual: ' . (string) ($mapped['status_ui'] ?? ''),
                '',
                'El flujo puede continuar a descarga.',
            ];

            Mail::raw(implode("\n", $lines), function ($message) use ($subject) {
                $message->to('soporte@pactopia.com')->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[SAT:adminQuotes] Error enviando correo interno transferencia aprobada', [
                'quote' => (string) $row->getKey(),
                'err'   => $e->getMessage(),
            ]);
        }
    }

    private function sendTransferApprovedCustomerEmail(string $to, SatDownload $row): void
    {
        try {
            $mapped = $this->mapQuoteRow($row);

            $subject = 'Pago por transferencia aprobado · ' . (string) ($mapped['folio'] ?? 'SIN-FOLIO');

            $lines = [
                'Tu pago por transferencia fue validado correctamente.',
                '',
                'Folio: ' . (string) ($mapped['folio'] ?? ''),
                'RFC: ' . (string) ($mapped['rfc'] ?? ''),
                'Total: $' . number_format((float) ($mapped['total'] ?? 0), 2, '.', ','),
                '',
                'Tu solicitud ya puede continuar al siguiente paso operativo.',
            ];

            Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[SAT:adminQuotes] Error enviando correo al cliente transferencia aprobada', [
                'to'    => $to,
                'quote' => (string) $row->getKey(),
                'err'   => $e->getMessage(),
            ]);
        }
    }

    private function sendTransferRejectedCustomerEmail(string $to, SatDownload $row, string $reason): void
    {
        try {
            $mapped = $this->mapQuoteRow($row);

            $subject = 'Pago por transferencia rechazado · ' . (string) ($mapped['folio'] ?? 'SIN-FOLIO');

            $lines = [
                'Tu comprobante de pago por transferencia no pudo ser validado.',
                '',
                'Folio: ' . (string) ($mapped['folio'] ?? ''),
                'RFC: ' . (string) ($mapped['rfc'] ?? ''),
                '',
                'Motivo:',
                $reason !== '' ? $reason : 'No se especificó motivo.',
                '',
                'Puedes volver a cargar un comprobante correcto desde el portal.',
            ];

            Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[SAT:adminQuotes] Error enviando correo al cliente transferencia rechazada', [
                'to'    => $to,
                'quote' => (string) $row->getKey(),
                'err'   => $e->getMessage(),
            ]);
        }
    }

        private function normalizeQuoteUiStatus(SatDownload $row): string
    {
        $meta = is_array($row->meta ?? null) ? $row->meta : [];
        $statusUiMeta = strtolower(trim((string) data_get($meta, 'status_ui', '')));
        $customerAction = strtolower(trim((string) data_get($meta, 'customer_action', '')));
        $transferReviewStatus = strtolower(trim((string) data_get($meta, 'transfer_review.review_status', '')));

        if ($transferReviewStatus === 'pending') {
            return 'en_proceso';
        }

        if (
            $row->statusNormalized() === 'paid'
            && in_array($customerAction, ['download_in_progress', 'processing_download', 'download_started'], true)
        ) {
            return 'en_descarga';
        }

        if (in_array($statusUiMeta, ['borrador', 'en_proceso', 'cotizada', 'pagada', 'en_descarga', 'completada', 'cancelada'], true)) {
            return $statusUiMeta;
        }

        if (in_array($row->statusNormalized(), ['downloaded', 'done'], true)) {
            return 'completada';
        }

        if ($row->statusNormalized() === 'paid') {
            return 'pagada';
        }

        return match ($row->statusNormalized()) {
            'pending', 'created'           => 'borrador',
            'requested', 'processing'      => 'en_proceso',
            'ready'                        => 'cotizada',
            'paid'                         => 'pagada',
            'downloaded', 'done'           => 'completada',
            'canceled', 'expired', 'error' => 'cancelada',
            default                        => 'borrador',
        };
    }

    private function resolveQuoteProgress(SatDownload $row, string $statusUi): int
    {
        $meta = is_array($row->meta ?? null) ? $row->meta : [];
        $raw  = data_get($meta, 'progress');

        if (is_numeric($raw)) {
            $value = max(0, min(100, (int) $raw));

            if ($statusUi === 'en_descarga') {
                return max(90, $value);
            }

            if ($statusUi === 'pagada') {
                return max(82, $value);
            }

            return $value;
        }

        return match ($statusUi) {
            'borrador'    => 10,
            'en_proceso'  => 35,
            'cotizada'    => 65,
            'pagada'      => 82,
            'en_descarga' => 90,
            'completada'  => 100,
            'cancelada'   => 0,
            default       => 0,
        };
    }

    private function countByUiStatus(string $statusUi): int
    {
        return SatDownload::query()
            ->where('tipo', 'quote')
            ->get()
            ->filter(fn (SatDownload $row) => $this->normalizeQuoteUiStatus($row) === $statusUi)
            ->count();
    }

    private function buildAdminQuoteConcept(
        string $tipoSolicitud,
        Carbon $dateFrom,
        Carbon $dateTo
    ): string {
        $label = match ($tipoSolicitud) {
            'recibidos' => 'Cotización SAT recibidos',
            'ambos'     => 'Cotización SAT ambos',
            default     => 'Cotización SAT emitidos',
        };

        return $label . ' · ' . $dateFrom->format('d/m/Y') . ' al ' . $dateTo->format('d/m/Y');
    }
}