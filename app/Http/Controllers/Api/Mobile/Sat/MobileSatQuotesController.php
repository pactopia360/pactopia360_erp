<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\Client\SatQuoteService;
use App\Services\Sat\Client\SatRfcOptionsService;
use App\Services\Sat\Client\SatVaultStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\StripeClient;

final class MobileSatQuotesController extends Controller
{
    private const SAT_TRANSFER_REVIEW_EMAIL = 'facturacion@pactopia.com';
    private const SAT_TRANSFER_BANK_NAME = 'Fondeadora';
    private const SAT_TRANSFER_ACCOUNT_HOLDER = 'PACTOPIA S A P I DE CV';
    private const SAT_TRANSFER_RFC = 'PAC251010CS1';
    private const SAT_TRANSFER_CLABE = '699180600008252099';

    private StripeClient $stripe;

    public function __construct(
        private readonly SatVaultStorage $vaultStorage,
        private readonly SatRfcOptionsService $rfcOptionsSvc,
        private readonly SatQuoteService $quoteSvc,
    ) {
        $secret = (string) config('services.stripe.secret');
        $this->stripe = new StripeClient($secret ?: '');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->jsonError('No autenticado.', 'UNAUTHENTICATED', 401);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return $this->jsonError('Cuenta inválida.', 'ACCOUNT_INVALID', 422);
        }

        $selectedRfc = strtoupper(trim((string) $request->query('rfc', '')));
        $status      = strtolower(trim((string) $request->query('status', '')));
        $perPage     = max(1, min(100, (int) $request->query('per_page', 20)));
        $page        = max(1, (int) $request->query('page', 1));

        $credList = $this->rfcOptionsSvc->loadCredentials($cuentaId, 'mysql_clientes');

        $downloads = SatDownload::on('mysql_clientes')
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        $filtered = $downloads
            ->filter(fn (SatDownload $download) => $this->isCotizacionLikeDownload($download))
            ->filter(function (SatDownload $download) use ($selectedRfc, $status) {
                if ($selectedRfc !== '') {
                    $downloadRfc = strtoupper(trim((string) ($download->rfc ?? data_get($download->meta, 'rfc', ''))));
                    if ($downloadRfc !== $selectedRfc) {
                        return false;
                    }
                }

                if ($status === '') {
                    return true;
                }

                $uiStatus = $this->normalizeCotizacionStatusForUi($download);
                $dbStatus = $download->statusNormalized();

                return $uiStatus === $status || $dbStatus === $status;
            })
            ->values();

        $total = $filtered->count();
        $items = $filtered
            ->slice(($page - 1) * $perPage, $perPage)
            ->values()
            ->map(fn (SatDownload $download) => $this->transformQuoteForMobile($download, $credList))
            ->values();

        return response()->json([
            'ok'   => true,
            'msg'  => 'Cotizaciones obtenidas correctamente.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => $total > 0 ? (int) ceil($total / $perPage) : 1,
                    'has_more'     => ($page * $perPage) < $total,
                ],
                'filters' => [
                    'rfc'    => $selectedRfc,
                    'status' => $status,
                ],
            ],
        ], 200);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->jsonError('No autenticado.', 'UNAUTHENTICATED', 401);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return $this->jsonError('Cuenta inválida.', 'ACCOUNT_INVALID', 422);
        }

        $quote = SatDownload::on('mysql_clientes')
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->first();

        if (!$quote || !$this->isCotizacionLikeDownload($quote)) {
            return $this->jsonError('No se encontró la cotización indicada.', 'QUOTE_NOT_FOUND', 404);
        }

        $credList = $this->rfcOptionsSvc->loadCredentials($cuentaId, 'mysql_clientes');

        return response()->json([
            'ok'   => true,
            'msg'  => 'Cotización obtenida correctamente.',
            'data' => $this->transformQuoteForMobile($quote, $credList),
        ], 200);
    }

    public function quickCalc(Request $request): JsonResponse
    {
        $trace = $this->traceId();
        $user  = $request->user();

        if (!$user) {
            return $this->jsonError('No autenticado.', 'UNAUTHENTICATED', 401, $trace);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return $this->jsonError('Cuenta inválida.', 'ACCOUNT_INVALID', 422, $trace);
        }

        $data = $request->validate([
            'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'discount_code'       => ['nullable', 'string', 'max:64'],
            'iva'                 => ['nullable'],
            'iva_rate'            => ['nullable'],
        ]);

        $xmlCount = (int) ($data['xml_count'] ?? $data['xml_count_estimated'] ?? 0);
        if ($xmlCount <= 0) {
            return $this->jsonError('xml_count requerido.', 'XML_COUNT_REQUIRED', 422, $trace);
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));
        $ivaRate = $this->quoteSvc->normalizeIvaRate($data['iva'] ?? $data['iva_rate'] ?? 16);

        $payload = $this->quoteSvc->buildSatQuotePayload(
            user: $user,
            cuentaId: $cuentaId,
            xmlCount: $xmlCount,
            discountCode: $discountCode,
            ivaRate: $ivaRate,
            useAdminPrice: true
        );

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'msg'      => 'Simulación calculada correctamente.',
            'data'     => [
                'mode'                  => 'quick',
                'folio'                 => $payload['folio'],
                'generated_at'          => $payload['generated']->toIso8601String(),
                'valid_until'           => $payload['validUntil']->toDateString(),
                'plan'                  => $payload['plan'],
                'cuenta_id'             => $cuentaId,
                'empresa'               => $payload['empresa'],
                'xml_count'             => $payload['xmlCount'],
                'base'                  => $payload['base'],
                'discount_code'         => $payload['discountCode'],
                'discount_code_applied' => $payload['discountCodeApplied'],
                'discount_label'        => $payload['discountLabel'],
                'discount_reason'       => $payload['discountReason'],
                'discount_type'         => $payload['discountType'],
                'discount_value'        => $payload['discountValue'],
                'discount_pct'          => (float) $payload['discountPct'],
                'discount_amount'       => $payload['discountAmount'],
                'subtotal'              => $payload['subtotal'],
                'iva_rate'              => $payload['ivaRate'],
                'iva_amount'            => $payload['ivaAmount'],
                'total'                 => $payload['total'],
                'note'                  => $payload['note'],
                'price_source'          => (string) ($payload['priceSource'] ?? 'admin'),
            ],
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $trace = $this->traceId();
        $user  = $request->user();

        if (!$user) {
            return $this->jsonError('No autenticado.', 'UNAUTHENTICATED', 401, $trace);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return $this->jsonError('Cuenta inválida.', 'ACCOUNT_INVALID', 422, $trace);
        }

        $data = $request->validate([
            'mode'                 => ['nullable', 'string', 'max:20'],
            'draft_id'             => ['nullable', 'string', 'max:64'],
            'rfc'                  => ['required', 'string', 'min:12', 'max:13'],
            'tipo'                 => ['required', 'string', 'max:30'],
            'date_from'            => ['required', 'date'],
            'date_to'              => ['required', 'date', 'after_or_equal:date_from'],
            'notes'                => ['nullable', 'string', 'max:3000'],
            'xml_count'            => ['required', 'integer', 'min:1', 'max:50000000'],
            'discount_code'        => ['nullable', 'string', 'max:64'],
            'iva'                  => ['nullable'],
            'iva_rate'             => ['nullable'],
        ]);

        $mode         = strtolower(trim((string) ($data['mode'] ?? 'quote')));
        $draftId      = trim((string) ($data['draft_id'] ?? ''));
        $rfc          = strtoupper(trim((string) $data['rfc']));
        $tipo         = strtolower(trim((string) ($data['tipo'] ?? 'emitidos')));
        $dateFrom     = Carbon::parse((string) $data['date_from'])->startOfDay();
        $dateTo       = Carbon::parse((string) $data['date_to'])->endOfDay();
        $xmlCount     = (int) $data['xml_count'];
        $discountCode = trim((string) ($data['discount_code'] ?? ''));
        $notes        = trim((string) ($data['notes'] ?? ''));
        $ivaRate      = $this->quoteSvc->normalizeIvaRate($data['iva'] ?? $data['iva_rate'] ?? 16);

        if (!in_array($mode, ['draft', 'quote'], true)) {
            $mode = 'quote';
        }

        if (!in_array($tipo, ['emitidos', 'recibidos', 'ambos'], true)) {
            $tipo = 'emitidos';
        }

        $cred = $this->rfcOptionsSvc
            ->loadCredentials($cuentaId, 'mysql_clientes')
            ->first(function ($item) use ($rfc) {
                return strtoupper(trim((string) ($item->rfc ?? ''))) === $rfc;
            });

        $razonSocial    = trim((string) ($cred->razon_social ?? ''));
        $adminAccountId = $this->resolveAdminAccountIdFromMobile($cuentaId);

        $payload = $this->quoteSvc->buildSatQuotePayload(
            user: $user,
            cuentaId: $cuentaId,
            xmlCount: $xmlCount,
            discountCode: $discountCode,
            ivaRate: $ivaRate,
            useAdminPrice: true
        );

        try {
            $download = DB::connection('mysql_clientes')->transaction(function () use (
                $cuentaId,
                $adminAccountId,
                $rfc,
                $razonSocial,
                $tipo,
                $dateFrom,
                $dateTo,
                $xmlCount,
                $discountCode,
                $notes,
                $payload,
                $trace,
                $mode,
                $draftId
            ) {
                $download = null;

                if ($draftId !== '') {
                    $download = SatDownload::on('mysql_clientes')
                        ->where('cuenta_id', $cuentaId)
                        ->where('id', $draftId)
                        ->first();
                }

                if (!$download) {
                    $download = new SatDownload();
                    $download->setConnection('mysql_clientes');
                }

                $statusDb   = $mode === 'draft' ? 'pending' : 'requested';
                $statusUi   = $mode === 'draft' ? 'borrador' : 'en_proceso';
                $progressUi = $mode === 'draft' ? 10 : 35;
                $folio      = (string) $payload['folio'];
                $concepto   = $this->buildQuoteConcepto($tipo, $dateFrom, $dateTo);

                $existingMeta = is_array($download->meta ?? null) ? $download->meta : [];

                $download->cuenta_id  = $cuentaId;
                $download->rfc        = $rfc;
                $download->tipo       = 'quote';
                $download->date_from  = $dateFrom;
                $download->date_to    = $dateTo;
                $download->status     = $statusDb;
                $download->xml_count  = $xmlCount;
                $download->cfdi_count = $xmlCount;
                $download->subtotal   = (float) $payload['subtotal'];
                $download->iva        = (float) $payload['ivaAmount'];
                $download->total      = (float) $payload['total'];
                $download->costo      = (float) $payload['base'];

                $download->meta = array_merge($existingMeta, [
                    'mode'                  => $mode === 'draft' ? 'quote_draft' : 'quote',
                    'draft_id'              => $draftId !== '' ? $draftId : null,
                    'folio'                 => $folio,
                    'quote_no'              => $folio,
                    'rfc'                   => $rfc,
                    'razon_social'          => $razonSocial,
                    'tipo_solicitud'        => $tipo,
                    'tipo'                  => $tipo,
                    'date_from'             => $dateFrom->toDateString(),
                    'date_to'               => $dateTo->toDateString(),
                    'concepto'              => $concepto,
                    'status_ui'             => $statusUi,
                    'progress'              => $progressUi,
                    'empresa'               => (string) ($payload['empresa'] ?? ''),
                    'plan'                  => (string) ($payload['plan'] ?? ''),
                    'xml_count'             => $xmlCount,
                    'base'                  => (float) $payload['base'],
                    'subtotal'              => (float) $payload['subtotal'],
                    'iva_rate'              => (int) $payload['ivaRate'],
                    'iva_amount'            => (float) $payload['ivaAmount'],
                    'total'                 => (float) $payload['total'],
                    'discount_code'         => $payload['discountCode'],
                    'discount_code_applied' => $payload['discountCodeApplied'],
                    'discount_label'        => $payload['discountLabel'],
                    'discount_reason'       => $payload['discountReason'],
                    'discount_type'         => $payload['discountType'],
                    'discount_value'        => $payload['discountValue'],
                    'discount_pct'          => (float) $payload['discountPct'],
                    'discount_amount'       => (float) $payload['discountAmount'],
                    'valid_until'           => $payload['validUntil']->toDateString(),
                    'generated_at'          => $payload['generated']->toIso8601String(),
                    'note'                  => (string) ($payload['note'] ?? ''),
                    'notes'                 => $notes,
                    'trace_id'              => $trace,
                    'is_quote_request'      => $mode === 'quote',
                    'es_solicitud'          => $mode === 'quote',
                    'is_request'            => $mode === 'quote',
                    'is_draft'              => $mode === 'draft',
                    'admin_account_id'      => $adminAccountId,
                    'price_source'          => (string) ($payload['priceSource'] ?? 'admin'),
                ]);

                $download->save();

                return $download->fresh();
            });

            if ($mode === 'quote') {
                $this->sendQuoteRequestEmail(
                    to: 'soporte@pactopia.com',
                    trace: $trace,
                    cuentaId: $cuentaId,
                    adminAccountId: $adminAccountId,
                    rfc: $rfc,
                    razonSocial: $razonSocial,
                    tipo: $tipo,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    xmlCount: $xmlCount,
                    notes: $notes,
                    payload: $payload,
                    downloadId: (string) ($download->id ?? '')
                );
            }

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'msg'      => $mode === 'draft'
                    ? 'Borrador guardado correctamente.'
                    : 'Cotización solicitada correctamente. Se envió a soporte para revisión.',
                'data'     => $this->transformQuoteForMobile(
                    $download,
                    $this->rfcOptionsSvc->loadCredentials($cuentaId, 'mysql_clientes')
                ),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[MOBILE:SAT:quotes.store] Error procesando cotización', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'mode'      => $mode,
                'draft_id'  => $draftId,
                'error'     => $e->getMessage(),
            ]);

            return $this->jsonError(
                $mode === 'draft'
                    ? 'No se pudo guardar el borrador.'
                    : 'No se pudo registrar la solicitud de cotización.',
                'QUOTE_STORE_FAILED',
                500,
                $trace
            );
        }
    }

    public function checkout(Request $request, string $id): JsonResponse
    {
        $trace = $this->traceId();
        $user  = $request->user();

        if (!$user) {
            return $this->jsonError('No autenticado.', 'UNAUTHENTICATED', 401, $trace);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return $this->jsonError('Cuenta inválida.', 'ACCOUNT_INVALID', 422, $trace);
        }

        if (!trim((string) config('services.stripe.secret'))) {
            return $this->jsonError('Stripe no está configurado.', 'STRIPE_NOT_CONFIGURED', 500, $trace);
        }

        /** @var SatDownload|null $quote */
        $quote = SatDownload::on('mysql_clientes')
            ->where('id', $id)
            ->where('cuenta_id', $cuentaId)
            ->first();

        if (!$quote || !$this->isCotizacionLikeDownload($quote)) {
            return $this->jsonError('No se encontró la cotización SAT indicada.', 'QUOTE_NOT_FOUND', 404, $trace);
        }

        $meta = is_array($quote->meta) ? $quote->meta : [];

        $status = strtolower(trim((string) ($quote->status ?? '')));
        $statusUi = strtolower(trim((string) ($meta['status_ui'] ?? '')));
        $customerAction = strtolower(trim((string) ($meta['customer_action'] ?? '')));
        $canPayMeta = $meta['can_pay'] ?? null;

        $statusAllowsPayment = in_array($status, [
            'ready',
            'pending',
            'quoted',
            'cotizada',
            'pendiente_pago',
        ], true)
            || in_array($statusUi, [
                'cotizada',
                'pendiente_pago',
            ], true)
            || in_array($customerAction, [
                'pay_pending',
                'pending_payment',
                'pagar',
            ], true);

        $canPay = filter_var($canPayMeta, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($canPay === null) {
            $canPay = $statusAllowsPayment;
        }

        if (!$statusAllowsPayment || !$canPay) {
            return $this->jsonError(
                'La cotización todavía no está disponible para pago.',
                'QUOTE_NOT_PAYABLE',
                422,
                $trace
            );
        }

        $amountMxn = round((float) (
            $quote->total
            ?? $quote->importe_estimado
            ?? $quote->monto_estimado
            ?? $quote->total_estimado
            ?? $quote->amount
            ?? $meta['total']
            ?? $meta['importe_estimado']
            ?? $meta['monto_estimado']
            ?? $meta['total_estimado']
            ?? $meta['amount']
            ?? 0
        ), 2);

        $amountCents = (int) round($amountMxn * 100);

        if ($amountCents <= 0) {
            return $this->jsonError(
                'La cotización no tiene un monto válido para cobro.',
                'QUOTE_INVALID_AMOUNT',
                422,
                $trace
            );
        }

        $folio = trim((string) (
            $quote->folio
            ?? $quote->codigo
            ?? $quote->quote_no
            ?? $meta['folio']
            ?? ('SAT-' . str_pad((string) $quote->id, 6, '0', STR_PAD_LEFT))
        ));

        $rfc = trim((string) (
            $quote->rfc
            ?? $meta['rfc']
            ?? ''
        ));

        $customerEmail = trim((string) ($user->email ?? ''));
        $idempotencyKey = 'mobile:checkout:sat_quote:' . $quote->id . ':' . md5($folio . '|' . $amountCents);

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode'                 => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'mxn',
                        'unit_amount'  => $amountCents,
                        'product_data' => [
                            'name'        => 'Cotización SAT ' . $folio,
                            'description' => 'Pago de solicitud SAT para RFC ' . $rfc,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'customer_email'      => $customerEmail !== '' ? $customerEmail : null,
                'client_reference_id' => (string) $quote->id,
                'success_url'         => $this->mobileCheckoutReturnUrl('success', (string) $quote->id),
                'cancel_url'          => $this->mobileCheckoutReturnUrl('cancel', (string) $quote->id, 'canceled'),
                'metadata' => [
                    'type'            => 'sat_quote',
                    'sat_download_id' => (string) $quote->id,
                    'cuenta_id'       => (string) $quote->cuenta_id,
                    'rfc'             => $rfc,
                    'folio'           => $folio,
                    'amount_mxn'      => (string) $amountMxn,
                    'amount_cents'    => (string) $amountCents,
                    'channel'         => 'mobile',
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            $meta['last_checkout_started_at'] = now()->toDateTimeString();
            $meta['last_checkout_amount_mxn'] = $amountMxn;
            $meta['last_checkout_folio'] = $folio;
            $meta['last_checkout_status_ui'] = $statusUi;
            $meta['last_checkout_can_pay'] = $canPay;
            $meta['last_checkout_channel'] = 'mobile';

            $quote->stripe_session_id = (string) ($session->id ?? '');
            $quote->meta = $meta;
            $quote->save();

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'msg'      => 'Checkout Stripe generado correctamente.',
                'data'     => [
                    'sat_download_id' => (string) $quote->id,
                    'session_id'      => (string) ($session->id ?? ''),
                    'checkout_url'    => (string) ($session->url ?? ''),
                    'folio'           => $folio,
                    'rfc'             => $rfc,
                    'amount_mxn'      => $amountMxn,
                    'amount_cents'    => $amountCents,
                    'currency'        => 'MXN',
                    'status'          => 'checkout_created',
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[MOBILE:SAT:quotes.checkout] error creando checkout', [
                'trace_id'       => $trace,
                'error'          => $e->getMessage(),
                'sat_download_id'=> $quote->id,
            ]);

            return $this->jsonError(
                'No se pudo iniciar el checkout de la cotización SAT.',
                'STRIPE_CHECKOUT_FAILED',
                500,
                $trace
            );
        }
    }

    public function submitTransferProof(Request $request, string $id): JsonResponse
    {
        $trace = $this->traceId();
        $user  = $request->user();

        if (!$user) {
            return $this->jsonError('No autenticado.', 'UNAUTHENTICATED', 401, $trace);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return $this->jsonError('Cuenta inválida.', 'ACCOUNT_INVALID', 422, $trace);
        }

        $data = $request->validate([
            'reference'       => ['required', 'string', 'max:120'],
            'transfer_date'   => ['required', 'date'],
            'transfer_amount' => ['required', 'numeric', 'min:0.01'],
            'payer_name'      => ['nullable', 'string', 'max:190'],
            'payer_bank'      => ['nullable', 'string', 'max:120'],
            'notes'           => ['nullable', 'string', 'max:3000'],
            'proof_file'      => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        /** @var SatDownload|null $quote */
        $quote = SatDownload::on('mysql_clientes')
            ->where('id', $id)
            ->where('cuenta_id', $cuentaId)
            ->first();

        if (!$quote || !$this->isCotizacionLikeDownload($quote)) {
            return $this->jsonError('No se encontró la cotización seleccionada.', 'QUOTE_NOT_FOUND', 404, $trace);
        }

        $status = strtolower(trim((string) $quote->status));
        $meta   = is_array($quote->meta ?? null) ? $quote->meta : [];

        if (!in_array($status, ['ready', 'pending'], true)) {
            return $this->jsonError(
                'La cotización no está disponible para registrar pago por transferencia.',
                'QUOTE_TRANSFER_NOT_ALLOWED',
                422,
                $trace
            );
        }

        $canPay = (bool) ($meta['can_pay'] ?? ($status === 'ready'));
        if (!$canPay && $status !== 'pending') {
            return $this->jsonError(
                'La cotización todavía no está habilitada para pago.',
                'QUOTE_NOT_PAYABLE',
                422,
                $trace
            );
        }

        $expectedReference  = $this->buildTransferReference($quote);
        $submittedReference = strtoupper(trim((string) ($data['reference'] ?? '')));

        if ($submittedReference === '' || $submittedReference !== $expectedReference) {
            return response()->json([
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => 'La referencia de pago no coincide con la asignada por el sistema.',
                'code'     => 'TRANSFER_REFERENCE_MISMATCH',
                'data'     => [
                    'expected_reference' => $expectedReference,
                ],
            ], 422);
        }

        $file = $request->file('proof_file');
        if (!$file || !$file->isValid()) {
            return $this->jsonError('No se pudo procesar el comprobante.', 'INVALID_PROOF_FILE', 422, $trace);
        }

        $realTotal     = round((float) ($quote->total ?? 0), 2);
        $sentTotal     = round((float) $data['transfer_amount'], 2);
        $transferDate  = Carbon::parse((string) $data['transfer_date'])->startOfDay();

        $stored = DB::connection('mysql_clientes')->transaction(function () use (
            $quote,
            $meta,
            $file,
            $data,
            $trace,
            $realTotal,
            $sentTotal,
            $transferDate,
            $expectedReference,
            $user
        ) {
            $disk          = 'public';
            $folder        = 'sat/transfers/' . date('Y/m');
            $extension     = strtolower((string) $file->getClientOriginalExtension());
            $generatedName = 'sat-transfer-' . $quote->id . '-' . Str::uuid() . '.' . $extension;

            $storedPath = $file->storeAs($folder, $generatedName, $disk);

            $risk = $this->buildInitialTransferRisk(
                expectedAmount: $realTotal,
                sentAmount: $sentTotal,
                transferDate: $transferDate
            );

            $meta['payment_method'] = 'transfer';
            $meta['can_pay'] = false;
            $meta['customer_action'] = 'transfer_under_review';
            $meta['status_ui'] = 'en_revision_pago';
            $meta['progress'] = max((int) ($meta['progress'] ?? 0), 78);

            $meta['transfer_review'] = [
                'submitted_at'      => now()->toDateTimeString(),
                'submitted_by'      => (string) ($user->id ?? ''),
                'bank_name'         => self::SAT_TRANSFER_BANK_NAME,
                'account_holder'    => self::SAT_TRANSFER_ACCOUNT_HOLDER,
                'receiver_rfc'      => self::SAT_TRANSFER_RFC,
                'receiver_clabe'    => self::SAT_TRANSFER_CLABE,
                'reference'         => $expectedReference,
                'transfer_date'     => $transferDate->toDateString(),
                'transfer_amount'   => $sentTotal,
                'expected_amount'   => $realTotal,
                'payer_name'        => trim((string) ($data['payer_name'] ?? '')),
                'payer_bank'        => trim((string) ($data['payer_bank'] ?? '')),
                'notes'             => trim((string) ($data['notes'] ?? '')),
                'proof_disk'        => $disk,
                'proof_path'        => $storedPath,
                'proof_name'        => $file->getClientOriginalName(),
                'proof_extension'   => $extension,
                'proof_mime'        => (string) $file->getMimeType(),
                'proof_size'        => (int) $file->getSize(),
                'ai_status'         => 'pending',
                'ai_summary'        => null,
                'ai_extracted_data' => [],
                'review_status'     => 'pending',
                'risk_level'        => $risk['risk_level'],
                'risk_flags'        => $risk['risk_flags'],
                'trace_id'          => $trace,
                'channel'           => 'mobile',
            ];

            $quote->status = 'pending';
            $quote->meta   = $meta;
            $quote->save();

            return [
                'quote'      => $quote->fresh(),
                'proof_path' => $storedPath,
                'risk'       => $risk,
            ];
        });

        try {
            $this->sendTransferProofEmail(
                to: self::SAT_TRANSFER_REVIEW_EMAIL,
                quote: $stored['quote'],
                trace: $trace
            );
        } catch (\Throwable $mailError) {
            Log::warning('[MOBILE:SAT:transferProof] No se pudo enviar correo a facturación', [
                'trace_id'        => $trace,
                'sat_download_id' => $id,
                'notify_to'       => self::SAT_TRANSFER_REVIEW_EMAIL,
                'err'             => $mailError->getMessage(),
            ]);
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'msg'      => 'Comprobante recibido. El pago por transferencia quedó en revisión.',
            'data'     => [
                'sat_download_id' => (string) ($stored['quote']->id ?? ''),
                'status'          => 'pending_review',
                'status_ui'       => 'en_revision_pago',
                'review_status'   => 'pending',
                'risk_level'      => $stored['risk']['risk_level'] ?? 'medium',
                'risk_flags'      => $stored['risk']['risk_flags'] ?? [],
            ],
        ], 200);
    }

    private function transformQuoteForMobile(SatDownload $download, $credList): array
    {
        $meta = is_array($download->meta ?? null) ? $download->meta : [];

        $rfc = strtoupper(trim((string) (
            $download->rfc
            ?: data_get($meta, 'rfc')
            ?: ''
        )));

        $razonSocial = trim((string) (
            data_get($meta, 'razon_social')
            ?: data_get($meta, 'empresa')
            ?: ''
        ));

        if ($razonSocial === '' && $rfc !== '' && $credList instanceof \Illuminate\Support\Collection) {
            $cred = $credList->first(function ($item) use ($rfc) {
                return strtoupper(trim((string) ($item->rfc ?? ''))) === $rfc;
            });

            if ($cred) {
                $razonSocial = trim((string) ($cred->razon_social ?? ''));
            }
        }

        $folio = trim((string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote_no')
            ?: ''
        ));

        if ($folio === '') {
            $rawId = (string) ($download->id ?? '');
            $folio = 'COT-' . str_pad(substr(preg_replace('/[^A-Za-z0-9]/', '', $rawId) ?: '0', -6), 6, '0', STR_PAD_LEFT);
        }

        $tipo = strtolower(trim((string) (
            data_get($meta, 'tipo')
            ?: data_get($meta, 'tipo_solicitud')
            ?: $download->tipo
            ?: 'emitidos'
        )));

        $dateFrom = $download->date_from?->toDateString() ?: data_get($meta, 'date_from');
        $dateTo   = $download->date_to?->toDateString()   ?: data_get($meta, 'date_to');

        $concepto = trim((string) (
            data_get($meta, 'concepto')
            ?: data_get($meta, 'note')
            ?: ''
        ));

        if ($concepto === '') {
            $labelTipo = match ($tipo) {
                'recibidos' => 'Cotización SAT recibidos',
                'ambos'     => 'Cotización SAT ambos',
                default     => 'Cotización SAT emitidos',
            };

            if ($dateFrom && $dateTo) {
                $concepto = $labelTipo . ' · ' . Carbon::parse($dateFrom)->format('d/m/Y') . ' al ' . Carbon::parse($dateTo)->format('d/m/Y');
            } else {
                $concepto = $labelTipo;
            }
        }

        $statusDb       = $download->statusNormalized();
        $statusUi       = $this->normalizeCotizacionStatusForUi($download);
        $progress       = $this->resolveCotizacionProgress($download, $statusUi);
        $customerAction = strtolower(trim((string) data_get($meta, 'customer_action', '')));
        $canPayMeta     = data_get($meta, 'can_pay');

        $statusAllowsPayment = in_array($statusDb, ['ready', 'pending'], true)
            || in_array($statusUi, ['cotizada', 'pendiente_pago'], true)
            || in_array($customerAction, ['pay_pending', 'pending_payment', 'pagar'], true);

        $canPay = filter_var($canPayMeta, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($canPay === null) {
            $canPay = $statusAllowsPayment;
        }

        $importe = null;
        foreach ([
            $download->total ?? null,
            data_get($meta, 'total'),
            $download->subtotal ?? null,
            $download->costo ?? null,
        ] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                $importe = (float) $candidate;
                break;
            }
        }

        return [
            'id'               => (string) ($download->id ?? ''),
            'folio'            => $folio,
            'rfc'              => $rfc,
            'razon_social'     => $razonSocial,
            'tipo'             => $tipo,
            'concepto'         => $concepto,
            'status_db'        => $statusDb,
            'status_ui'        => $statusUi,
            'status_label'     => $this->statusUiLabel($statusUi),
            'progress'         => $progress,
            'can_pay'          => (bool) $canPay,
            'customer_action'  => $customerAction,
            'importe_estimado' => $importe,
            'subtotal'         => is_numeric($download->subtotal ?? null) ? (float) $download->subtotal : null,
            'iva'              => is_numeric($download->iva ?? null) ? (float) $download->iva : null,
            'total'            => is_numeric($download->total ?? null) ? (float) $download->total : $importe,
            'xml_count'        => is_numeric($download->xml_count ?? null) ? (int) $download->xml_count : (int) data_get($meta, 'xml_count', 0),
            'cfdi_count'       => is_numeric($download->cfdi_count ?? null) ? (int) $download->cfdi_count : (int) data_get($meta, 'cfdi_count', 0),
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'valid_until'      => data_get($meta, 'valid_until'),
            'price_source'     => data_get($meta, 'price_source'),
            'discount_code'    => data_get($meta, 'discount_code'),
            'paid_at'          => $download->paid_at?->toIso8601String(),
            'created_at'       => $download->created_at?->toIso8601String(),
            'updated_at'       => $download->updated_at?->toIso8601String(),
            'transfer_review'  => is_array(data_get($meta, 'transfer_review')) ? data_get($meta, 'transfer_review') : null,
            'meta'             => $meta,
        ];
    }

    private function isCotizacionLikeDownload(SatDownload $download): bool
    {
        $tipo   = strtolower(trim((string) ($download->tipo ?? '')));
        $status = $download->statusNormalized();
        $meta   = is_array($download->meta ?? null) ? $download->meta : [];

        $modeMeta = strtolower(trim((string) data_get($meta, 'mode', '')));
        $folioMeta = trim((string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote_no')
            ?: ''
        ));

        $isRequest   = (bool) data_get($download, 'is_request', false);
        $esSolicitud = (bool) data_get($download, 'es_solicitud', false);
        $isDraft     = (bool) data_get($download, 'is_draft', false);

        if ($isRequest || $esSolicitud || $isDraft) {
            return true;
        }

        if (in_array($tipo, [
            'solicitud',
            'request',
            'peticion',
            'cotizacion',
            'cotización',
            'quote',
            'quick_quote',
            'simulacion',
            'simulación',
            'simulada',
        ], true)) {
            return true;
        }

        if (in_array($modeMeta, ['quote', 'quote_draft', 'quick', 'quick_quote', 'simulation', 'simulada'], true)) {
            return true;
        }

        if ($folioMeta !== '') {
            return true;
        }

        if (
            in_array($status, ['paid', 'ready', 'done', 'processing', 'requested', 'pending'], true)
            && (
                (float) ($download->total ?? 0) > 0
                || (float) ($download->subtotal ?? 0) > 0
                || (float) ($download->costo ?? 0) > 0
            )
        ) {
            return true;
        }

        return false;
    }

    private function normalizeCotizacionStatusForUi(SatDownload $download): string
    {
        $status = $download->statusNormalized();
        $meta   = is_array($download->meta ?? null) ? $download->meta : [];

        $statusUiMeta        = strtolower(trim((string) data_get($meta, 'status_ui', '')));
        $customerAction      = strtolower(trim((string) data_get($meta, 'customer_action', '')));
        $transferReviewState = strtolower(trim((string) data_get($meta, 'transfer_review.review_status', '')));

        if ($transferReviewState === 'pending') {
            return 'en_revision_pago';
        }

        if (
            $status === 'paid'
            && in_array($customerAction, ['download_in_progress', 'processing_download', 'download_started'], true)
        ) {
            return 'en_descarga';
        }

        if (in_array($statusUiMeta, [
            'borrador',
            'en_proceso',
            'cotizada',
            'pagada',
            'en_descarga',
            'completada',
            'cancelada',
            'en_revision_pago',
        ], true)) {
            return $statusUiMeta;
        }

        if (in_array($status, ['downloaded', 'done'], true)) {
            return 'completada';
        }

        if ($status === 'paid') {
            return 'pagada';
        }

        return match ($status) {
            'pending', 'created'         => 'borrador',
            'requested', 'processing'    => 'en_proceso',
            'ready'                      => 'cotizada',
            'canceled', 'expired', 'error' => 'cancelada',
            default                      => 'borrador',
        };
    }

    private function resolveCotizacionProgress(SatDownload $download, string $statusUi): int
    {
        $meta = is_array($download->meta ?? null) ? $download->meta : [];

        $raw = data_get($meta, 'progress', data_get($meta, 'avance', data_get($meta, 'porcentaje')));
        if (is_numeric($raw)) {
            return max(0, min(100, (int) $raw));
        }

        return match ($statusUi) {
            'borrador'         => 10,
            'en_proceso'       => 35,
            'cotizada'         => 65,
            'en_revision_pago' => 78,
            'pagada'           => 82,
            'en_descarga'      => 90,
            'completada'       => 100,
            'cancelada'        => 0,
            default            => 0,
        };
    }

    private function statusUiLabel(string $statusUi): string
    {
        return match ($statusUi) {
            'borrador'         => 'Borrador',
            'en_proceso'       => 'En proceso de cotización',
            'cotizada'         => 'Cotizada',
            'en_revision_pago' => 'Pago en revisión',
            'pagada'           => 'Pagada',
            'en_descarga'      => 'En descarga',
            'completada'       => 'Completada',
            'cancelada'        => 'Cancelada',
            default            => ucfirst(str_replace('_', ' ', $statusUi)),
        };
    }

    private function resolveAdminAccountIdFromMobile(string $cuentaId): ?int
    {
        if ($cuentaId === '') {
            return null;
        }

        try {
            $cuentaCliente = $this->vaultStorage->fetchCuentaCliente($cuentaId);
            if (!$cuentaCliente) {
                return null;
            }

            $value = trim((string) ($cuentaCliente->admin_account_id ?? ''));
            if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
                return (int) $value;
            }
        } catch (\Throwable $e) {
            Log::warning('[MOBILE:SAT] resolveAdminAccountIdFromMobile failed', [
                'cuenta_id' => $cuentaId,
                'err'       => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function buildQuoteConcepto(string $tipo, Carbon $dateFrom, Carbon $dateTo): string
    {
        $label = match ($tipo) {
            'recibidos' => 'Cotización SAT recibidos',
            'ambos'     => 'Cotización SAT ambos',
            default     => 'Cotización SAT emitidos',
        };

        return $label . ' · ' . $dateFrom->format('d/m/Y') . ' al ' . $dateTo->format('d/m/Y');
    }

    private function buildTransferReference(SatDownload $quote): string
    {
        $meta = is_array($quote->meta ?? null) ? $quote->meta : [];

        $folio = strtoupper(trim((string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote.folio')
            ?: data_get($meta, 'quote_no')
            ?: ('SAT-' . $quote->id)
        )));

        $folioClean = preg_replace('/[^A-Z0-9]/', '', $folio) ?: ('SAT' . (string) $quote->id);
        $folioLast4 = substr($folioClean, -4);

        $quoteId = (string) ($quote->id ?? '0');
        $quoteIdClean = preg_replace('/[^A-Z0-9]/', '', strtoupper($quoteId)) ?: '0';

        return 'SAT-' . $folioLast4 . '-' . $quoteIdClean;
    }

    private function buildInitialTransferRisk(float $expectedAmount, float $sentAmount, Carbon $transferDate): array
    {
        $riskFlags = [];
        $riskLevel = 'low';

        if ($expectedAmount > 0 && abs($expectedAmount - $sentAmount) > 1.00) {
            $riskFlags[] = 'amount_mismatch';
        }

        if ($transferDate->greaterThan(now()->addDay())) {
            $riskFlags[] = 'future_date';
        }

        if ($transferDate->lessThan(now()->copy()->subDays(15))) {
            $riskFlags[] = 'old_transfer_date';
        }

        if (count($riskFlags) >= 2) {
            $riskLevel = 'high';
        } elseif (count($riskFlags) === 1) {
            $riskLevel = 'medium';
        }

        return [
            'risk_level' => $riskLevel,
            'risk_flags' => $riskFlags,
        ];
    }

    private function sendQuoteRequestEmail(
        string $to,
        string $trace,
        string $cuentaId,
        ?int $adminAccountId,
        string $rfc,
        string $razonSocial,
        string $tipo,
        Carbon $dateFrom,
        Carbon $dateTo,
        int $xmlCount,
        string $notes,
        array $payload,
        string $downloadId
    ): void {
        try {
            $subject = 'Nueva solicitud de cotización SAT · ' . (string) ($payload['folio'] ?? 'SIN-FOLIO');

            $lines = [
                'Nueva solicitud de cotización SAT',
                '',
                'Folio: ' . (string) ($payload['folio'] ?? ''),
                'Trace ID: ' . $trace,
                'Download ID: ' . $downloadId,
                'Cuenta UUID: ' . $cuentaId,
                'Admin Account ID: ' . (string) ($adminAccountId ?? ''),
                'RFC: ' . $rfc,
                'Razón social: ' . ($razonSocial !== '' ? $razonSocial : 'N/D'),
                'Tipo: ' . $tipo,
                'Periodo: ' . $dateFrom->toDateString() . ' a ' . $dateTo->toDateString(),
                'CFDI/XML estimados: ' . number_format($xmlCount, 0, '.', ','),
                'Notas: ' . ($notes !== '' ? $notes : 'Sin notas'),
                '',
                'Importante: Este correo fue generado automáticamente por la app móvil.',
            ];

            Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[MOBILE:SAT:quotes.store] No se pudo enviar correo a soporte', [
                'trace_id' => $trace,
                'to'       => $to,
                'err'      => $e->getMessage(),
            ]);
        }
    }

    private function sendTransferProofEmail(string $to, SatDownload $quote, string $trace): void
    {
        $meta = is_array($quote->meta ?? null) ? $quote->meta : [];
        $transfer = is_array($meta['transfer_review'] ?? null) ? $meta['transfer_review'] : [];

        $folio = (string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote.folio')
            ?: ('SAT-' . $quote->id)
        );

        $subject = 'Transferencia SAT por validar · ' . $folio;

        $proofPath = trim((string) ($transfer['proof_path'] ?? ''));
        $proofDisk = trim((string) ($transfer['proof_disk'] ?? 'public'));

        $riskFlags = (array) ($transfer['risk_flags'] ?? []);
        $riskFlagsLabel = !empty($riskFlags) ? implode(', ', $riskFlags) : 'Sin banderas detectadas';

        $lines = [
            'Se recibió un comprobante de pago por transferencia para revisión.',
            '',
            'Folio: ' . $folio,
            'Cotización ID: ' . (string) $quote->id,
            'RFC cliente: ' . (string) ($quote->rfc ?? ''),
            'Monto esperado: $' . number_format((float) ($transfer['expected_amount'] ?? $quote->total ?? 0), 2) . ' MXN',
            'Monto reportado: $' . number_format((float) ($transfer['transfer_amount'] ?? 0), 2) . ' MXN',
            'Banco receptor: ' . (string) ($transfer['bank_name'] ?? self::SAT_TRANSFER_BANK_NAME),
            'Titular receptor: ' . (string) ($transfer['account_holder'] ?? self::SAT_TRANSFER_ACCOUNT_HOLDER),
            'CLABE receptora: ' . (string) ($transfer['receiver_clabe'] ?? self::SAT_TRANSFER_CLABE),
            'RFC receptor: ' . (string) ($transfer['receiver_rfc'] ?? self::SAT_TRANSFER_RFC),
            'Banco emisor: ' . (string) ($transfer['payer_bank'] ?? ''),
            'Pagador: ' . (string) ($transfer['payer_name'] ?? ''),
            'Referencia asignada: ' . (string) ($transfer['reference'] ?? ''),
            'Fecha transferencia: ' . (string) ($transfer['transfer_date'] ?? ''),
            'Riesgo inicial: ' . strtoupper((string) ($transfer['risk_level'] ?? 'medium')),
            'Banderas: ' . $riskFlagsLabel,
            'Notas del cliente: ' . (string) ($transfer['notes'] ?? 'Sin notas'),
            'Trace ID: ' . $trace,
        ];

        Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject, $proofPath, $proofDisk) {
            $message->to($to)->subject($subject);

            try {
                if ($proofPath !== '' && Storage::disk($proofDisk)->exists($proofPath)) {
                    $message->attach(Storage::disk($proofDisk)->path($proofPath));
                }
            } catch (\Throwable $e) {
                Log::warning('[MOBILE:SAT:transferProof] No se pudo adjuntar comprobante', [
                    'proof_disk' => $proofDisk,
                    'proof_path' => $proofPath,
                    'err'        => $e->getMessage(),
                ]);
            }
        });
    }

    private function mobileCheckoutReturnUrl(string $result, string $quoteId, string $sessionIdPlaceholder = '{CHECKOUT_SESSION_ID}'): string
    {
        $base = trim((string) config('services.mobile.deep_link_base', ''));

        if ($base === '') {
            $base = trim((string) env('MOBILE_DEEP_LINK_BASE', ''));
        }

        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/') . '/mobile/stripe';
        }

        $base = rtrim($base, '/');

        return $base
            . '/' . $result
            . '?flow=sat_quote'
            . '&session_id=' . urlencode($sessionIdPlaceholder)
            . '&quote_id=' . urlencode($quoteId);
    }

    private function traceId(): string
    {
        return (string) Str::uuid();
    }

    private function jsonError(string $msg, string $code, int $status, ?string $traceId = null): JsonResponse
    {
        $payload = [
            'ok'   => false,
            'msg'  => $msg,
            'code' => $code,
        ];

        if ($traceId) {
            $payload['trace_id'] = $traceId;
        }

        return response()->json($payload, $status);
    }
}