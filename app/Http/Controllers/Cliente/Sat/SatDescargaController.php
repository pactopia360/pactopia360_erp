<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\Sat\SatDescargaController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;

use App\Services\Sat\SatDownloadService;
use App\Services\Sat\SatDownloadZipHelper;
use App\Services\Sat\VaultAccountSummaryService;

use App\Services\Sat\Client\SatClientContext;
use App\Services\Sat\Client\SatVaultStorage;
use App\Services\Sat\Client\SatCartService;
use App\Services\Sat\Client\SatDownloadsPresenter;
use App\Services\Sat\Client\SatRfcOptionsService;
use App\Services\Sat\Client\SatRfcAliasService;
use App\Services\Sat\Client\SatQuoteService;

use App\Services\Sat\External\ExternalStoreService;
use App\Services\Sat\External\SatExternalZipService;
use App\Services\Sat\Stats\DashboardStatsService;

use App\Models\Cliente\SatUserMetadataUpload;
use App\Models\Cliente\SatUserXmlUpload;
use App\Models\Cliente\SatUserReportUpload;
use App\Models\Cliente\VaultFile;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SatDescargaController extends Controller
{
    public function __construct(
        private readonly SatDownloadService         $service,
        private readonly SatDownloadZipHelper       $zipHelper,
        private readonly VaultAccountSummaryService $vaultSummaryService,

        private readonly SatClientContext           $ctx,
        private readonly SatVaultStorage            $vaultStorage,
        private readonly SatCartService             $cart,
        private readonly SatDownloadsPresenter      $presenter,

        private readonly SatRfcOptionsService       $rfcOptionsSvc,
        private readonly SatRfcAliasService         $rfcAliasSvc,
        private readonly SatQuoteService            $quoteSvc,

        private readonly ExternalStoreService       $externalStoreSvc,
        private readonly SatExternalZipService      $externalZipSvc,
        private readonly DashboardStatsService      $dashboardStatsSvc,
    ) {}

    private function cu(): ?object { return $this->ctx->user(); }
    private function cuId(): string { return $this->ctx->cuentaId(); }
    private function trace(): string { return $this->ctx->trace(); }
    private function isAjax(Request $r): bool { return $this->ctx->isAjax($r); }
    private function resolveCuentaIdFromUser($user): ?string { return $this->ctx->resolveCuentaIdFromUser($user); }

    private function resolveAdminAccountIdFromPortal(): ?int
    {
        $uuid = trim((string) $this->cuId());
        if ($uuid === '') {
            return null;
        }

        try {
            $cc = $this->vaultStorage->fetchCuentaCliente($uuid);
            if (!$cc) {
                return null;
            }

            $val = trim((string) ($cc->admin_account_id ?? ''));
            if ($val !== '' && ctype_digit($val) && (int) $val > 0) {
                return (int) $val;
            }
        } catch (\Throwable $e) {
            Log::warning('[SAT] resolveAdminAccountIdFromPortal failed', [
                'trace_id'    => $this->trace(),
                'cuenta_uuid' => $uuid,
                'err'         => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function injectExternalAccountContext(Request $request): void
    {
        $uuid = trim((string) $this->cuId());
        $aid  = $this->resolveAdminAccountIdFromPortal();

        if ($uuid !== '') {
            $request->merge(['cuenta_id' => $uuid]);
        }

        if ($aid && $aid > 0) {
            $request->merge([
                'admin_account_id' => (string) $aid,
                'account_id'       => (string) $aid,
            ]);
        }
    }

    public function index(Request $request)
    {
        $user = $this->cu();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        $cuentaId = trim((string) ($this->resolveCuentaIdFromUser($user) ?? ''));
        $cuentaCliente = $cuentaId !== '' ? $this->vaultStorage->fetchCuentaCliente($cuentaId) : null;

        if (!$cuentaCliente) {
            $tmp = $user?->cuenta ?? null;
            if (is_array($tmp)) {
                $tmp = (object) $tmp;
            }

            if (is_object($tmp)) {
                if ($cuentaId === '') {
                    $cuentaId = trim((string) ($tmp->id ?? $tmp->cuenta_id ?? ''));
                }
                $cuentaCliente = $tmp;
            }
        }

        $planRaw   = (string) (($cuentaCliente->plan_actual ?? $cuentaCliente->plan ?? 'FREE'));
        $plan      = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        [$vaultSummary, $vaultForJs] = $this->presenter->buildVaultSummaries($cuentaId, $cuentaCliente);

        $credList             = collect();
        $credMap              = [];
        $rfcOptions           = [];
        $initialRows          = [];
        $cartIds              = [];
        $downloadsPage        = null;
        $downloadsTotalAll    = 0;
        $cotizaciones         = collect();

        $selectedRfc          = strtoupper(trim((string) $request->query('rfc', '')));
        $unifiedDownloadItems = collect();
        $downloadSources      = [
            'centro_sat' => 0,
            'boveda_v1'  => 0,
            'boveda_v2'  => 0,
        ];
        $storageBreakdown     = [
            'used_bytes'      => 0,
            'available_bytes' => 0,
            'quota_bytes'     => 0,
            'used_gb'         => 0.0,
            'available_gb'    => 0.0,
            'quota_gb'        => 0.0,
            'used_pct'        => 0.0,
            'available_pct'   => 0.0,
            'chart'           => [
                'series' => [0, 0],
                'labels' => ['Usado', 'Disponible'],
            ],
        ];

        if ($cuentaId !== '') {
            try {
                $cartIds = $this->cart->getCartIds($cuentaId);

                $conn = 'mysql_clientes';

                $credList   = $this->rfcOptionsSvc->loadCredentials($cuentaId, $conn);
                $credMap    = $this->rfcOptionsSvc->buildCredMap($credList);
                $rfcOptions = $this->rfcOptionsSvc->buildRfcOptionsSmart($credList, $conn);

                if ($selectedRfc === '') {
                    $activeCred = $credList->first(function ($item) {
                        $meta = $item->meta ?? [];

                        if (is_string($meta)) {
                            $decoded = json_decode($meta, true);
                            $meta = is_array($decoded) ? $decoded : [];
                        }

                        if (!is_array($meta)) {
                            $meta = [];
                        }

                        return (bool) ($meta['is_active'] ?? true) === true
                            && trim((string) ($item->rfc ?? '')) !== '';
                    });

                    if (!$activeCred) {
                        $activeCred = $credList->first(function ($item) {
                            return trim((string) ($item->rfc ?? '')) !== '';
                        });
                    }

                    $selectedRfc = strtoupper(trim((string) ($activeCred->rfc ?? '')));
                }

                $perPage = (int) $request->query('per', 20);
                $perPage = max(5, min(100, $perPage));

                $baseQuery = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                    ->orderByDesc('created_at');

                $downloadsTotalAll = (int) $baseQuery->count();
                $downloadsPage     = $baseQuery->paginate($perPage);

                $now = Carbon::now();

                $pageCollection = $downloadsPage->getCollection()->values();

                $downloadRows = $pageCollection
                    ->filter(function (SatDownload $d) {
                        return !$this->isCotizacionLikeDownload($d);
                    })
                    ->values();

                $rowsH = $downloadRows
                    ->map(fn (SatDownload $d) => $this->presenter->transformDownloadRow($d, $credMap, $cartIds, $now))
                    ->values();

                $downloadsPage->setCollection($rowsH);
                $initialRows = $rowsH->all();

                $usuarioId = (string) ($user->id ?? '');

                $v2Items = $this->buildSatV2LikeItems(
                    cuentaId: $cuentaId,
                    usuarioId: $usuarioId,
                    selectedRfc: $selectedRfc
                );

                $unifiedDownloadItems = $this->buildUnifiedDownloadItemsForPortal(
                    cuentaId: $cuentaId,
                    usuarioId: $usuarioId,
                    selectedRfc: $selectedRfc,
                    v2Items: $v2Items
                );

                $downloadSources = [
                    'centro_sat' => (int) $unifiedDownloadItems->where('origin', 'centro_sat')->count(),
                    'boveda_v1'  => (int) $unifiedDownloadItems->where('origin', 'boveda_v1')->count(),
                    'boveda_v2'  => (int) $unifiedDownloadItems->where('origin', 'boveda_v2')->count(),
                ];

                $storageBreakdown = $this->buildUnifiedStorageBreakdownForPortal(
                    cuentaId: $cuentaId,
                    unifiedItems: $unifiedDownloadItems
                );
            } catch (\Throwable $e) {
                Log::error('[SAT:index] Error cargando sat', [
                    'cuenta_id' => $cuentaId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return view('cliente.sat.index', [
            'plan'               => $plan,
            'isProPlan'          => $isProPlan,
            'credList'           => $credList,
            'rfcs'               => $credList,
            'initialRows'        => $initialRows,
            'downloads'          => $initialRows,
            'downloadsPaginator' => $downloadsPage,
            'downloadsTotalAll'  => $downloadsTotalAll,
            'cuenta'             => $cuentaCliente,
            'vaultSummary'       => $vaultSummary,
            'storage'            => $vaultSummary,
            'vault'              => $vaultForJs,
            'vault_quota_gb'     => (float) ($vaultSummary['quota_gb'] ?? 0.0),
            'vault_used_gb'      => (float) ($vaultSummary['used_gb'] ?? 0.0),
            'vault_used_pct'     => (float) ($vaultSummary['used_pct'] ?? 0.0),
            'cartIds'            => $cartIds,
            'rfcOptions'         => $rfcOptions,
            'cotizaciones'       => $cotizaciones,
            'admin_account_id'   => $this->resolveAdminAccountIdFromPortal(),
            'selectedRfc'         => $selectedRfc,
            'unifiedDownloadItems'=> $unifiedDownloadItems,
            'downloadSources'     => $downloadSources,
            'storageBreakdown'    => $storageBreakdown,
        ]);
    }

    public function saveAlias(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim((string) $this->cuId());
        $isAjax   = $this->isAjax($request);

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = (string) ($data['rfc'] ?? '');
        $alias = array_key_exists('alias', $data) ? (string) $data['alias'] : null;

        $res = $this->rfcAliasSvc->saveAlias($cuentaId, $rfc, $alias, $request);

        if (!$res['ok']) {
            return $isAjax
                ? response()->json(['ok' => false, 'msg' => $res['msg'], 'trace_id' => $trace], 422)
                : redirect()->route('cliente.sat.index')->with('error', $res['msg']);
        }

        if ($isAjax) {
            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'rfc'      => $res['rfc'],
                'alias'    => $res['alias'],
                'msg'      => $res['msg'],
            ], 200);
        }

        return redirect()->route('cliente.sat.index')->with('ok', $res['msg']);
    }

    public function cartAdd(Request $request): JsonResponse { return $this->cart->cartAdd($request, $this->ctx); }
    public function cartRemove(Request $request): JsonResponse { return $this->cart->cartRemove($request, $this->ctx); }
    public function cartBulkAdd(Request $request): JsonResponse { return $this->cart->cartBulkAdd($request, $this->ctx); }
    public function cartClear(Request $request): JsonResponse { return $this->cart->cartClear($request, $this->ctx); }
    public function cartGet(Request $request): JsonResponse { return $this->cart->cartGet($request, $this->ctx); }

    public function cancelDownload(Request $request): JsonResponse
    {
        if (method_exists($this->cart, 'cancelDownload')) {
            return $this->cart->cancelDownload($request, $this->ctx);
        }

        return response()->json([
            'ok'       => false,
            'trace_id' => $this->trace(),
            'msg'      => 'cancelDownload aún no está delegado a servicio (SatCartService).',
        ], 501);
    }

    public function quoteCalc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
            'mode'          => ['nullable', 'string', 'max:20'],
            'draft_id'      => ['nullable', 'string', 'max:60'],
            'rfc'           => ['required', 'string', 'min:12', 'max:13'],
            'tipo'          => ['nullable', 'string', 'max:30'],
            'date_from'     => ['required', 'date'],
            'date_to'       => ['required', 'date', 'after_or_equal:date_from'],
            'xml_count'     => ['required', 'integer', 'min:1', 'max:50000000'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            'notes'         => ['nullable', 'string', 'max:3000'],
            'iva'           => ['nullable'],
            'iva_rate'      => ['nullable'],
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
        $adminAccountId = $this->resolveAdminAccountIdFromPortal();

        /*
        |--------------------------------------------------------------------------
        | FIX: cliente siempre calcula contra lista de precios admin
        |--------------------------------------------------------------------------
        | Antes:
        |   - quickCalc()  -> useAdminPrice: true
        |   - quoteCalc()  -> useAdminPrice: false
        |
        | Eso provocaba importes distintos entre simulación y cotización real.
        | Ahora ambos usan la misma fuente de precio.
        */
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
                $dl = null;

                if ($draftId !== '') {
                    $dl = SatDownload::on('mysql_clientes')
                        ->where('cuenta_id', $cuentaId)
                        ->where('id', $draftId)
                        ->first();
                }

                if (!$dl) {
                    $dl = new SatDownload();
                    $dl->setConnection('mysql_clientes');
                }

                $statusDb   = $mode === 'draft' ? 'pending' : 'requested';
                $statusUi   = $mode === 'draft' ? 'borrador' : 'en_proceso';
                $progressUi = $mode === 'draft' ? 10 : 35;
                $folio      = (string) $payload['folio'];
                $concepto   = $this->buildQuoteConcepto($tipo, $dateFrom, $dateTo);

                $existingMeta = is_array($dl->meta ?? null) ? $dl->meta : [];

                $dl->cuenta_id  = $cuentaId;
                $dl->rfc        = $rfc;
                $dl->tipo       = 'quote';
                $dl->date_from  = $dateFrom;
                $dl->date_to    = $dateTo;
                $dl->status     = $statusDb;
                $dl->xml_count  = $xmlCount;
                $dl->cfdi_count = $xmlCount;
                $dl->subtotal   = (float) $payload['subtotal'];
                $dl->iva        = (float) $payload['ivaAmount'];
                $dl->total      = (float) $payload['total'];
                $dl->costo      = (float) $payload['base'];

                $dl->meta = array_merge($existingMeta, [
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

                    'quote' => [
                        'mode'                  => $mode,
                        'draft_id'              => $draftId !== '' ? $draftId : null,
                        'folio'                 => $folio,
                        'quote_no'              => $folio,
                        'rfc'                   => $rfc,
                        'razon_social'          => $razonSocial,
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
                        'price_source'          => (string) ($payload['priceSource'] ?? 'admin'),
                    ],
                ]);

                $dl->save();

                return $dl->fresh();
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
                'data'     => [
                    'id'                    => (string) ($download->id ?? ''),
                    'draft_id'              => (string) ($download->id ?? ''),
                    'mode'                  => $mode,
                    'quote_mode'            => $mode === 'draft' ? 'simulation' : 'formal',
                    'is_formal_quote'       => $mode === 'quote',
                    'is_simulation'         => $mode === 'draft',
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
                    'status'                => $mode === 'draft' ? 'borrador' : 'en_proceso',
                    'status_label'          => $mode === 'draft' ? 'Borrador' : 'En proceso de cotización',
                    'progress'              => $mode === 'draft' ? 10 : 35,
                    'rfc'                   => $rfc,
                    'razon_social'          => $razonSocial,
                    'tipo'                  => $tipo,
                    'date_from'             => $dateFrom->toDateString(),
                    'date_to'               => $dateTo->toDateString(),
                    'concepto'              => $this->buildQuoteConcepto($tipo, $dateFrom, $dateTo),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[SAT:quoteCalc] Error procesando cotización', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'mode'      => $mode,
                'draft_id'  => $draftId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => $mode === 'draft'
                    ? 'No se pudo guardar el borrador.'
                    : 'No se pudo registrar la solicitud de cotización.',
                'trace_id' => $trace,
            ], 500);
        }
    }

    public function quickCalc(Request $request): JsonResponse
    {
        $trace = $this->trace();
        $user  = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Sesión expirada o cuenta inválida.', 'trace_id' => $trace], 401);
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
            return response()->json(['ok' => false, 'msg' => 'xml_count requerido.', 'trace_id' => $trace], 422);
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));
        $ivaRate = $this->quoteSvc->normalizeIvaRate($data['iva'] ?? $data['iva_rate'] ?? 16);

        $p = $this->quoteSvc->buildSatQuotePayload(
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
            'data'     => [
                'mode'                  => 'quick',
                'folio'                 => $p['folio'],
                'generated_at'          => $p['generated']->toIso8601String(),
                'valid_until'           => $p['validUntil']->toDateString(),
                'plan'                  => $p['plan'],
                'cuenta_id'             => $cuentaId,
                'empresa'               => $p['empresa'],
                'xml_count'             => $p['xmlCount'],
                'base'                  => $p['base'],
                'discount_code'         => $p['discountCode'],
                'discount_code_applied' => $p['discountCodeApplied'],
                'discount_label'        => $p['discountLabel'],
                'discount_reason'       => $p['discountReason'],
                'discount_type'         => $p['discountType'],
                'discount_value'        => $p['discountValue'],
                'discount_pct'          => (float) $p['discountPct'],
                'discount_amount'       => $p['discountAmount'],
                'subtotal'              => $p['subtotal'],
                'iva_rate'              => $p['ivaRate'],
                'iva_amount'            => $p['ivaAmount'],
                'total'                 => $p['total'],
                'note'                  => $p['note'],
            ],
        ], 200);
    }

        public function quickPdf(Request $request)
    {
        $trace = $this->trace();
        $user = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Sesión expirada o cuenta inválida.');
        }

        $data = $request->validate([
            'quote_mode'          => ['nullable', 'string', 'max:20'],
            'rfc'                 => ['nullable', 'string', 'min:12', 'max:13'],
            'tipo'                => ['nullable', 'string', 'max:30'],
            'date_from'           => ['nullable', 'date'],
            'date_to'             => ['nullable', 'date', 'after_or_equal:date_from'],
            'notes'               => ['nullable', 'string', 'max:3000'],
            'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'discount_code'       => ['nullable', 'string', 'max:64'],
            'iva'                 => ['nullable'],
            'iva_rate'            => ['nullable'],
        ]);

        $pdfMode = $this->resolveQuotePdfMode($request);

        $rfc = strtoupper(trim((string) ($data['rfc'] ?? '')));
        $tipo = strtolower(trim((string) ($data['tipo'] ?? 'emitidos')));
        $notes = trim((string) ($data['notes'] ?? ''));

        if (!in_array($tipo, ['emitidos', 'recibidos', 'ambos'], true)) {
            $tipo = 'emitidos';
        }

        $dateFrom = !empty($data['date_from'])
            ? Carbon::parse((string) $data['date_from'])->startOfDay()
            : null;

        $dateTo = !empty($data['date_to'])
            ? Carbon::parse((string) $data['date_to'])->endOfDay()
            : null;

        $xmlCount = (int) ($data['xml_count'] ?? $data['xml_count_estimated'] ?? 0);
        if ($xmlCount <= 0) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'xml_count requerido.');
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));
        $ivaRate = $this->quoteSvc->normalizeIvaRate($data['iva'] ?? $data['iva_rate'] ?? 16);

        $useAdminPrice = !in_array($pdfMode, ['formal', 'quote'], true);

        $p = $this->quoteSvc->buildSatQuotePayload(
            user: $user,
            cuentaId: $cuentaId,
            xmlCount: $xmlCount,
            discountCode: $discountCode,
            ivaRate: $ivaRate,
            useAdminPrice: $useAdminPrice
        );

        $razonSocial = '';
        if ($rfc !== '') {
            $cred = $this->rfcOptionsSvc
                ->loadCredentials($cuentaId, 'mysql_clientes')
                ->first(function ($item) use ($rfc) {
                    return strtoupper(trim((string) ($item->rfc ?? ''))) === $rfc;
                });

            $razonSocial = trim((string) ($cred->razon_social ?? ''));
        }

        $protection = $this->buildQuotePdfProtectionText($pdfMode);

        $payload = [
            'trace_id'              => $trace,
            'mode'                  => $pdfMode,
            'is_simulation'         => $pdfMode === 'simulation',
            'is_formal_quote'       => in_array($pdfMode, ['formal', 'quote'], true),
            'folio'                 => $p['folio'],
            'generated_at'          => $p['generated'],
            'valid_until'           => $p['validUntil'],
            'plan'                  => $p['plan'],
            'cuenta_id'             => $cuentaId,
            'empresa'               => $p['empresa'],
            'rfc'                   => $rfc,
            'razon_social'          => $razonSocial,
            'tipo'                  => $tipo,
            'tipo_label'            => match ($tipo) {
                'recibidos' => 'Recibidos',
                'ambos'     => 'Ambos',
                default     => 'Emitidos',
            },
            'date_from'             => $dateFrom,
            'date_to'               => $dateTo,
            'periodo_label'         => ($dateFrom && $dateTo)
                ? $dateFrom->format('d/m/Y') . ' al ' . $dateTo->format('d/m/Y')
                : 'Periodo no definido',
            'concepto'              => ($dateFrom && $dateTo)
                ? $this->buildQuoteConcepto($tipo, $dateFrom, $dateTo)
                : 'Cotización SAT',
            'notes'                 => $notes,
            'xml_count'             => $p['xmlCount'],
            'base'                  => $p['base'],
            'discount_code'         => $p['discountCode'],
            'discount_code_applied' => $p['discountCodeApplied'],
            'discount_label'        => $p['discountLabel'],
            'discount_reason'       => $p['discountReason'],
            'discount_type'         => $p['discountType'],
            'discount_value'        => $p['discountValue'],
            'discount_pct'          => (float) $p['discountPct'],
            'discount_amount'       => $p['discountAmount'],
            'subtotal'              => $p['subtotal'],
            'iva_rate'              => $p['ivaRate'],
            'iva_amount'            => $p['ivaAmount'],
            'total'                 => $p['total'],
            'note'                  => $p['note'],
            'protection'            => $protection,
            'issuer'                => [
                'name'             => (string) (config('app.name') ?: 'Pactopia360'),
                'brand'            => 'Pactopia',
                'website'          => (string) (config('app.url') ?: ''),
                'email'            => (string) (config('mail.from.address') ?: 'notificaciones@pactopia360.com'),
                'phone'            => (string) (config('services.pactopia.phone') ?: ''),
                'logo_public_path' => public_path('assets/client/logp360ligjt.png'),
            ],
        ];

        if (!View::exists('cliente.sat.pdf.quote')) {
            return response(
                'No existe la vista PDF: resources/views/cliente/sat/pdf/quote.blade.php (cliente.sat.pdf.quote).',
                Response::HTTP_NOT_IMPLEMENTED
            );
        }

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response(
                'No está disponible el generador PDF (DomPDF). Instala/activa barryvdh/laravel-dompdf.',
                Response::HTTP_NOT_IMPLEMENTED
            );
        }

        $payload['quote_hash'] = $this->quoteSvc->quotePdfHash($payload);

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cliente.sat.pdf.quote', $payload)
                ->setPaper('letter', 'portrait')
                ->setOptions([
                    'defaultFont'          => 'DejaVu Sans',
                    'isRemoteEnabled'      => false,
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled'         => false,
                    'dpi'                  => 96,
                    'fontHeightRatio'      => 1.0,
                ]);

            $prefix = $pdfMode === 'simulation'
                ? 'simulacion_sat'
                : 'cotizacion_sat';

            $file = $prefix . '_' . $cuentaId . '_' . $p['generated']->format('Ymd_His') . '.pdf';

            return $pdf->download($file);
        } catch (\Throwable $e) {
            Log::error('[SAT:quickPdf] DomPDF error', [
                'trace_id'   => $trace,
                'pdf_mode'   => $pdfMode,
                'cuenta_id'  => $cuentaId,
                'rfc'        => $rfc,
                'xml_count'  => $xmlCount,
                'err'        => $e->getMessage(),
            ]);

            return response('Error generando PDF.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function externalInvite(Request $request): JsonResponse
    {
        $this->injectExternalAccountContext($request);

        $trace = $this->trace();
        $res = $this->externalZipSvc->externalZipInvite($request, $trace);

        return response()->json(
            array_merge(['trace_id' => $trace], $res),
            (int) ($res['code'] ?? ($res['ok'] ? 200 : 500))
        );
    }

    public function externalRegister(Request $request)
    {
        return response('externalRegister no fue movido aquí en este refactor.', 501);
    }

    public function externalStore(Request $request)
    {
        return $this->externalStoreSvc->handle($request);
    }

    public function externalZipRegister(Request $request): JsonResponse
    {
        $this->injectExternalAccountContext($request);

        $trace = $this->trace();
        $res = $this->externalZipSvc->externalZipRegister($request, $trace);

        return response()->json(
            array_merge(['trace_id' => $trace], $res),
            (int) ($res['code'] ?? ($res['ok'] ? 200 : 500))
        );
    }

    public function externalZipList(Request $request): JsonResponse
    {
        $this->injectExternalAccountContext($request);

        $trace = $this->trace();
        $res = $this->externalZipSvc->externalZipList($request, $trace);

        return response()->json(
            array_merge(['trace_id' => $trace], $res),
            (int) ($res['code'] ?? ($res['ok'] ? 200 : 500))
        );
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        return $this->dashboardStatsSvc->handle($request, $this->ctx);
    }

        private function resolveQuotePdfMode(Request $request): string
    {
        $mode = strtolower(trim((string) $request->input('quote_mode', 'simulation')));

        return in_array($mode, ['simulation', 'formal', 'quote'], true)
            ? $mode
            : 'simulation';
    }

    private function buildQuotePdfProtectionText(string $pdfMode): array
    {
        if (in_array($pdfMode, ['formal', 'quote'], true)) {
            return [
                'watermark'            => 'COTIZACIÓN PACTOPIA',
                'document_title'       => 'Cotización de servicio SAT',
                'document_subtitle'    => 'Propuesta técnica y económica sujeta a vigencia, alcance y condiciones operativas.',
                'legal_notice'         => 'Esta cotización describe un alcance técnico de descarga y entrega de información. No incluye validación fiscal, contable, conciliación, interpretación ni reprocesos posteriores no contratados.',
                'pricing_notice'       => 'Los precios están sujetos a vigencia comercial, alcance final autorizado, confirmación operativa y condiciones del portal del SAT.',
                'sat_dependency_notice'=> 'Los tiempos de ejecución y entrega dependen del portal del SAT, sus límites operativos, intermitencias, mantenimientos y tiempos de respuesta.',
                'footer_notice'        => 'Documento comercial de uso controlado. Pactopia se reserva ajustes por cambios de alcance, volumen final o condiciones externas de operación.',
                'show_no_validity_badge' => false,
            ];
        }

        return [
            'watermark'            => 'SIMULACIÓN / SIN VALIDEZ COMERCIAL',
            'document_title'       => 'Simulación de cotización SAT',
            'document_subtitle'    => 'Estimación preliminar generada por el portal cliente.',
            'legal_notice'         => 'Este documento es una simulación informativa. No constituye una oferta definitiva ni obligación comercial para Pactopia.',
            'pricing_notice'       => 'El costo final puede variar según validación del periodo solicitado, volumen real de CFDI, alcance definitivo, reglas comerciales vigentes y condiciones operativas del SAT.',
            'sat_dependency_notice'=> 'La ejecución real del servicio está sujeta a disponibilidad, límites, intermitencias y tiempos de respuesta del portal del SAT.',
            'footer_notice'        => 'Simulación generada automáticamente. Sin validez comercial ni contractual.',
            'show_no_validity_badge' => true,
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
            'Importante: Este correo es únicamente operativo para soporte.',
            'Los importes y condiciones comerciales son administrados por el área comercial.',
            '',
            'Este correo fue generado automáticamente por el portal cliente.',
        ];

        Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    } catch (\Throwable $e) {
        Log::warning('[SAT:quoteCalc] No se pudo enviar correo a soporte', [
            'trace_id' => $trace,
            'to'       => $to,
            'err'      => $e->getMessage(),
        ]);
    }
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

    private function isCotizacionLikeDownload(SatDownload $download): bool
    {
        $tipo = strtolower(trim((string) ($download->tipo ?? '')));
        $status = $download->statusNormalized();

        $meta = is_array($download->meta ?? null) ? $download->meta : [];
        $payload = [];
        $response = [];

        $modeMeta = strtolower(trim((string) data_get($meta, 'mode', '')));
        $modePayload = strtolower(trim((string) data_get($payload, 'mode', '')));
        $modeResponse = strtolower(trim((string) data_get($response, 'mode', '')));

        $folioMeta = trim((string) (
            data_get($meta, 'folio')
            ?: data_get($payload, 'folio')
            ?: data_get($response, 'folio')
            ?: data_get($meta, 'quote_no')
            ?: data_get($payload, 'quote_no')
            ?: data_get($response, 'quote_no')
            ?: ''
        ));

        $isRequest = (bool) data_get($download, 'is_request', false);
        $esSolicitud = (bool) data_get($download, 'es_solicitud', false);
        $isDraft = (bool) data_get($download, 'is_draft', false);

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

        if (in_array($modePayload, ['quote', 'draft', 'quote_draft', 'quick', 'quick_quote', 'simulation', 'simulada'], true)) {
            return true;
        }

        if (in_array($modeResponse, ['quote', 'draft', 'quote_draft', 'quick', 'quick_quote', 'simulation', 'simulada'], true)) {
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

    private function transformCotizacionRow(SatDownload $download, $credList): array
    {
        $meta = is_array($download->meta ?? null) ? $download->meta : [];
        $payload = [];
        $response = [];

        $rfc = strtoupper(trim((string) (
            $download->rfc
            ?: data_get($meta, 'rfc')
            ?: data_get($payload, 'rfc')
            ?: data_get($response, 'rfc')
            ?: ''
        )));

        $razonSocial = trim((string) (
            data_get($meta, 'razon_social')
            ?: data_get($payload, 'razon_social')
            ?: data_get($response, 'razon_social')
            ?: data_get($meta, 'empresa')
            ?: data_get($payload, 'empresa')
            ?: data_get($response, 'empresa')
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
            ?: data_get($payload, 'folio')
            ?: data_get($response, 'folio')
            ?: data_get($meta, 'quote_no')
            ?: data_get($payload, 'quote_no')
            ?: data_get($response, 'quote_no')
            ?: ''
        ));

        if ($folio === '') {
            $rawId = (string) ($download->id ?? '');
            $folio = 'COT-' . str_pad(substr(preg_replace('/[^A-Za-z0-9]/', '', $rawId) ?: '0', -6), 6, '0', STR_PAD_LEFT);
        }

        $tipo = strtolower(trim((string) ($download->tipo ?? '')));
        $dateFrom = $download->date_from ? $download->date_from->format('d/m/Y') : null;
        $dateTo   = $download->date_to ? $download->date_to->format('d/m/Y') : null;

        $concepto = trim((string) (
            data_get($meta, 'concepto')
            ?: data_get($payload, 'concepto')
            ?: data_get($response, 'concepto')
            ?: data_get($meta, 'note')
            ?: data_get($payload, 'note')
            ?: data_get($response, 'note')
            ?: ''
        ));

        if ($concepto === '') {
            $labelTipo = match ($tipo) {
                'emitidos'   => 'Descarga SAT emitidos',
                'recibidos'  => 'Descarga SAT recibidos',
                'ambos'      => 'Descarga SAT ambos',
                'quote',
                'cotizacion',
                'cotización' => 'Cotización SAT',
                default      => 'Cotización SAT',
            };

            if ($dateFrom && $dateTo) {
                $concepto = $labelTipo . ' · ' . $dateFrom . ' al ' . $dateTo;
            } else {
                $concepto = $labelTipo;
            }
        }

        $statusUi = $this->normalizeCotizacionStatusForUi($download);
        $progress = $this->resolveCotizacionProgress($download, $statusUi);

        $importe = null;
        foreach ([
            $download->total ?? null,
            data_get($meta, 'total'),
            data_get($payload, 'total'),
            data_get($response, 'total'),
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
            'concepto'         => $concepto,
            'status'           => $statusUi,
            'importe_estimado' => $importe,
            'progress'         => $progress,
            'updated_at'       => $download->updated_at ?? $download->created_at,
            'meta'             => $meta,
        ];
    }

    private function normalizeCotizacionStatusForUi(SatDownload $download): string
    {
        $status = $download->statusNormalized();
        $meta = is_array($download->meta ?? null) ? $download->meta : [];
        $statusUiMeta = strtolower(trim((string) data_get($meta, 'status_ui', '')));

        if ($statusUiMeta === 'borrador') {
            return 'borrador';
        }

        if ($download->isPaid()) {
            return 'pagada';
        }

        if (in_array($status, ['downloaded', 'done'], true)) {
            return 'completada';
        }

        if (in_array($status, ['ready'], true)) {
            return 'cotizada';
        }

        if (in_array($status, ['processing', 'requested'], true)) {
            return 'en_proceso';
        }

        if (in_array($status, ['canceled', 'expired', 'error'], true)) {
            return 'cancelada';
        }

        if (in_array($status, ['pending', 'created'], true)) {
            return 'borrador';
        }

        return 'borrador';
    }

    private function resolveCotizacionProgress(SatDownload $download, string $statusUi): int
    {
        $meta = is_array($download->meta ?? null) ? $download->meta : [];

        $raw = data_get($meta, 'progress', data_get($meta, 'avance', data_get($meta, 'porcentaje')));
        if (is_numeric($raw)) {
            return max(0, min(100, (int) $raw));
        }

        return match ($statusUi) {
            'borrador'   => 10,
            'en_proceso' => 35,
            'cotizada'   => 65,
            'pagada'     => 82,
            'completada' => 100,
            'cancelada'  => 0,
            default      => 0,
        };
    }

        public function submitTransferProof(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
            'sat_download_id'      => ['required', 'integer', 'min:1'],
            'bank_name'            => ['required', 'string', 'max:120'],
            'account_holder'       => ['nullable', 'string', 'max:190'],
            'reference'            => ['required', 'string', 'max:120'],
            'transfer_date'        => ['required', 'date'],
            'transfer_amount'      => ['required', 'numeric', 'min:0.01'],
            'payer_name'           => ['nullable', 'string', 'max:190'],
            'payer_bank'           => ['nullable', 'string', 'max:120'],
            'notes'                => ['nullable', 'string', 'max:3000'],
            'proof_file'           => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $satDownloadId = (int) $data['sat_download_id'];

        /** @var SatDownload|null $quote */
        $quote = SatDownload::on('mysql_clientes')
            ->where('id', $satDownloadId)
            ->where('cuenta_id', $cuentaId)
            ->first();

        if (!$quote) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No se encontró la cotización seleccionada.',
                'trace_id' => $trace,
            ], 404);
        }

        $status = strtolower(trim((string) $quote->status));
        $meta   = is_array($quote->meta ?? null) ? $quote->meta : [];

        if (!in_array($status, ['ready', 'pending'], true)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La cotización no está disponible para registrar pago por transferencia.',
                'trace_id' => $trace,
            ], 422);
        }

        $canPay = (bool) ($meta['can_pay'] ?? ($status === 'ready'));
        if (!$canPay && $status !== 'pending') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La cotización todavía no está habilitada para pago.',
                'trace_id' => $trace,
            ], 422);
        }

        $file = $request->file('proof_file');
        if (!$file || !$file->isValid()) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo procesar el comprobante.',
                'trace_id' => $trace,
            ], 422);
        }

        $realTotal = round((float) ($quote->total ?? 0), 2);
        $sentTotal = round((float) $data['transfer_amount'], 2);
        $transferDate = Carbon::parse((string) $data['transfer_date'])->startOfDay();

        $stored = DB::connection('mysql_clientes')->transaction(function () use (
            $quote,
            $meta,
            $file,
            $data,
            $trace,
            $realTotal,
            $sentTotal,
            $transferDate
        ) {
            $disk = 'public';
            $folder = 'sat/transfers/' . date('Y/m');
            $extension = strtolower((string) $file->getClientOriginalExtension());
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
                'submitted_by'      => (string) (auth('web')->id() ?? ''),
                'bank_name'         => trim((string) $data['bank_name']),
                'account_holder'    => trim((string) ($data['account_holder'] ?? '')),
                'reference'         => trim((string) $data['reference']),
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
            ];

            $quote->status = 'pending';
            $quote->meta   = $meta;
            $quote->save();

            return [
                'quote'       => $quote->fresh(),
                'proof_path'  => $storedPath,
                'risk'        => $risk,
            ];
        });

        try {
            $this->sendTransferProofEmail(
                to: 'soporte@pactopia.com',
                quote: $stored['quote'],
                trace: $trace
            );
        } catch (\Throwable $mailError) {
            Log::warning('[SAT:transferProof] No se pudo enviar correo a soporte', [
                'trace_id'       => $trace,
                'sat_download_id'=> $satDownloadId,
                'err'            => $mailError->getMessage(),
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

    private function sendTransferProofEmail(string $to, SatDownload $quote, string $trace): void
    {
        $meta = is_array($quote->meta ?? null) ? $quote->meta : [];
        $transfer = is_array($meta['transfer_review'] ?? null) ? $meta['transfer_review'] : [];

        $folio = (string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote.folio')
            ?: ('SAT-' . $quote->id)
        );

        $subject = 'Comprobante de transferencia SAT por validar · ' . $folio;

        $proofPath = trim((string) ($transfer['proof_path'] ?? ''));
        $proofDisk = trim((string) ($transfer['proof_disk'] ?? 'public'));

        $lines = [
            'Se recibió un comprobante de pago por transferencia para revisión.',
            '',
            'Folio: ' . $folio,
            'Cotización ID: ' . (string) $quote->id,
            'RFC: ' . (string) ($quote->rfc ?? ''),
            'Monto esperado: $' . number_format((float) ($transfer['expected_amount'] ?? $quote->total ?? 0), 2) . ' MXN',
            'Monto reportado: $' . number_format((float) ($transfer['transfer_amount'] ?? 0), 2) . ' MXN',
            'Banco: ' . (string) ($transfer['bank_name'] ?? ''),
            'Referencia: ' . (string) ($transfer['reference'] ?? ''),
            'Fecha transferencia: ' . (string) ($transfer['transfer_date'] ?? ''),
            'Riesgo inicial: ' . strtoupper((string) ($transfer['risk_level'] ?? 'medium')),
            'Banderas: ' . implode(', ', (array) ($transfer['risk_flags'] ?? [])),
            'Trace ID: ' . $trace,
            '',
            'Este pago quedó pendiente de validación manual/IA.',
        ];

        Mail::raw(implode("\n", $lines), function ($message) use ($to, $subject, $proofPath, $proofDisk) {
            $message->to($to)->subject($subject);

            try {
                if ($proofPath !== '' && Storage::disk($proofDisk)->exists($proofPath)) {
                    $message->attach(Storage::disk($proofDisk)->path($proofPath));
                }
            } catch (\Throwable $e) {
                Log::warning('[SAT:transferProof] No se pudo adjuntar comprobante', [
                    'proof_disk' => $proofDisk,
                    'proof_path' => $proofPath,
                    'err'        => $e->getMessage(),
                ]);
            }
        });
    }

        private function buildSatV2LikeItems(
        string $cuentaId,
        string $usuarioId,
        string $selectedRfc
    ): Collection {
        $metadataUploads = SatUserMetadataUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->when($selectedRfc !== '', fn ($q) => $q->where('rfc_owner', $selectedRfc))
            ->latest('id')
            ->limit(100)
            ->get();

        $xmlUploads = SatUserXmlUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->when($selectedRfc !== '', fn ($q) => $q->where('rfc_owner', $selectedRfc))
            ->latest('id')
            ->limit(100)
            ->get();

        $reportUploads = SatUserReportUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->when($selectedRfc !== '', fn ($q) => $q->where('rfc_owner', $selectedRfc))
            ->latest('id')
            ->limit(100)
            ->get();

        return collect()
            ->merge(
                $metadataUploads->map(function ($upload) {
                    return [
                        'kind'          => 'metadata',
                        'id'            => (int) $upload->id,
                        'rfc_owner'     => (string) $upload->rfc_owner,
                        'direction'     => (string) ($upload->direction_detected ?? ''),
                        'source_type'   => (string) ($upload->source_type ?? 'metadata'),
                        'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('metadata_' . $upload->id)),
                        'stored_name'   => (string) ($upload->stored_name ?? ''),
                        'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                        'bytes'         => (int) ($upload->bytes ?? 0),
                        'rows_count'    => (int) ($upload->rows_count ?? 0),
                        'files_count'   => 0,
                        'status'        => (string) ($upload->status ?? ''),
                        'created_at'    => $upload->created_at,
                    ];
                })
            )
            ->merge(
                $xmlUploads->map(function ($upload) {
                    return [
                        'kind'          => 'xml',
                        'id'            => (int) $upload->id,
                        'rfc_owner'     => (string) $upload->rfc_owner,
                        'direction'     => (string) ($upload->direction_detected ?? ''),
                        'source_type'   => (string) ($upload->source_type ?? 'xml'),
                        'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('xml_' . $upload->id)),
                        'stored_name'   => (string) ($upload->stored_name ?? ''),
                        'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                        'bytes'         => (int) ($upload->bytes ?? 0),
                        'rows_count'    => 0,
                        'files_count'   => (int) ($upload->files_count ?? 0),
                        'status'        => (string) ($upload->status ?? ''),
                        'created_at'    => $upload->created_at,
                    ];
                })
            )
            ->merge(
                $reportUploads->map(function ($upload) {
                    return [
                        'kind'          => 'report',
                        'id'            => (int) $upload->id,
                        'rfc_owner'     => (string) $upload->rfc_owner,
                        'direction'     => (string) data_get($upload->meta, 'report_direction', ''),
                        'source_type'   => (string) ($upload->report_type ?? 'report'),
                        'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('reporte_' . $upload->id)),
                        'stored_name'   => (string) ($upload->stored_name ?? ''),
                        'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                        'bytes'         => (int) ($upload->bytes ?? 0),
                        'rows_count'    => (int) ($upload->rows_count ?? 0),
                        'files_count'   => 0,
                        'status'        => (string) ($upload->status ?? ''),
                        'created_at'    => $upload->created_at,
                    ];
                })
            )
            ->sortByDesc(function (array $row) {
                return optional($row['created_at'] ?? null)?->timestamp ?? 0;
            })
            ->values();
    }

    private function buildUnifiedDownloadItemsForPortal(
        string $cuentaId,
        string $usuarioId,
        string $selectedRfc,
        Collection $v2Items
    ): Collection {
        $items = collect();

        $items = $items->merge(
            $v2Items->map(function (array $item) use ($selectedRfc) {
                $bytes = (int) ($item['bytes'] ?? 0);

                return [
                    'origin'         => 'boveda_v2',
                    'origin_label'   => 'Bóveda v2',
                    'origin_badge'   => 'V2',
                    'kind'           => (string) ($item['kind'] ?? 'archivo'),
                    'id'             => (string) ($item['id'] ?? ''),
                    'rfc_owner'      => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                    'direction'      => (string) ($item['direction'] ?? ''),
                    'original_name'  => (string) ($item['original_name'] ?? 'Archivo'),
                    'stored_name'    => (string) ($item['stored_name'] ?? ''),
                    'mime'           => (string) ($item['mime'] ?? 'application/octet-stream'),
                    'bytes'          => $bytes,
                    'bytes_human'    => $this->formatBytesHumanForPortal($bytes),
                    'detail'         => $this->resolveV2DetailLabelForPortal($item),
                    'status'         => (string) ($item['status'] ?? ''),
                    'created_at'     => $item['created_at'] ?? null,
                    'download_url'   => route('cliente.sat.v2.download', [
                        'type' => (string) ($item['kind'] ?? 'metadata'),
                        'id'   => (int) ($item['id'] ?? 0),
                        'rfc'  => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                    ]),
                    'view_url'       => route('cliente.sat.v2.download', [
                        'type' => (string) ($item['kind'] ?? 'metadata'),
                        'id'   => (int) ($item['id'] ?? 0),
                        'rfc'  => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                        'view' => 1,
                    ]),
                ];
            })
        );

        if (Schema::connection('mysql_clientes')->hasTable('sat_vault_files')) {
            $vaultFilesQuery = DB::connection('mysql_clientes')
                ->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->orderByDesc('id');

            if ($selectedRfc !== '') {
                $vaultFilesQuery->where('rfc', $selectedRfc);
            }

            $vaultFiles = $vaultFilesQuery->limit(300)->get();

            $items = $items->merge(
                collect($vaultFiles)->map(function ($row) {
                    $bytes = (int) ($row->bytes ?? $row->size_bytes ?? 0);
                    $filename = trim((string) ($row->original_name ?? $row->filename ?? ''));

                    if ($filename === '') {
                        $filename = basename((string) ($row->path ?? 'archivo'));
                    }

                    return [
                        'origin'         => 'boveda_v1',
                        'origin_label'   => 'Bóveda v1',
                        'origin_badge'   => 'V1',
                        'kind'           => $this->resolveKindFromFilenameForPortal($filename, (string) ($row->mime ?? '')),
                        'id'             => (string) ($row->id ?? ''),
                        'rfc_owner'      => strtoupper((string) ($row->rfc ?? '')),
                        'direction'      => '',
                        'original_name'  => $filename,
                        'stored_name'    => (string) ($row->filename ?? ''),
                        'mime'           => (string) ($row->mime ?? 'application/octet-stream'),
                        'bytes'          => $bytes,
                        'bytes_human'    => $this->formatBytesHumanForPortal($bytes),
                        'detail'         => 'Archivo de bóveda',
                        'status'         => 'disponible',
                        'created_at'     => $row->created_at ?? null,
                        'download_url'   => route('cliente.sat.vault.file', ['id' => (string) ($row->id ?? '')]),
                        'view_url'       => route('cliente.sat.vault.file', ['id' => (string) ($row->id ?? '')]),
                    ];
                })
            );
        }

        if (Schema::connection('mysql_clientes')->hasTable('sat_downloads')) {
            $satDownloadsQuery = DB::connection('mysql_clientes')
                ->table('sat_downloads')
                ->where('cuenta_id', $cuentaId)
                ->whereNotNull('zip_path')
                ->where('zip_path', '<>', '')
                ->orderByDesc('created_at');

            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'usuario_id')) {
                $satDownloadsQuery->where('usuario_id', $usuarioId);
            }

            if ($selectedRfc !== '' && Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'rfc')) {
                $satDownloadsQuery->where('rfc', $selectedRfc);
            }

            $satDownloads = $satDownloadsQuery->limit(300)->get();

            $items = $items->merge(
                collect($satDownloads)->map(function ($row) {
                    $bytes = 0;

                    if (isset($row->bytes) && is_numeric($row->bytes)) {
                        $bytes = (int) $row->bytes;
                    } elseif (isset($row->size_bytes) && is_numeric($row->size_bytes)) {
                        $bytes = (int) $row->size_bytes;
                    }

                    $filename = trim((string) ($row->zip_filename ?? $row->filename ?? ''));
                    if ($filename === '') {
                        $filename = basename((string) ($row->zip_path ?? 'descarga.zip'));
                    }

                    return [
                        'origin'         => 'centro_sat',
                        'origin_label'   => 'Centro SAT',
                        'origin_badge'   => 'SAT',
                        'kind'           => 'zip',
                        'id'             => (string) ($row->id ?? ''),
                        'rfc_owner'      => strtoupper((string) ($row->rfc ?? '')),
                        'direction'      => strtolower((string) ($row->tipo ?? '')),
                        'original_name'  => $filename,
                        'stored_name'    => (string) ($row->zip_path ?? ''),
                        'mime'           => 'application/zip',
                        'bytes'          => $bytes,
                        'bytes_human'    => $this->formatBytesHumanForPortal($bytes),
                        'detail'         => 'Paquete SAT',
                        'status'         => (string) ($row->status ?? 'disponible'),
                        'created_at'     => $row->created_at ?? null,
                        'download_url'   => null,
                        'view_url'       => null,
                    ];
                })
            );
        }

        return $items
            ->filter(function (array $item) {
                return trim((string) ($item['original_name'] ?? '')) !== '';
            })
            ->sortByDesc(function (array $item) {
                return optional($item['created_at'] ?? null)?->timestamp ?? 0;
            })
            ->values();
    }

    private function buildUnifiedStorageBreakdownForPortal(
        string $cuentaId,
        Collection $unifiedItems
    ): array {
        $usedBytes = (int) $unifiedItems->sum(function (array $item) {
            return (int) ($item['bytes'] ?? 0);
        });

        $quotaBytes = 0;

        if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            $cuenta = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->first();

            if ($cuenta) {
                if (property_exists($cuenta, 'vault_quota_bytes') && is_numeric($cuenta->vault_quota_bytes)) {
                    $quotaBytes = (int) $cuenta->vault_quota_bytes;
                } elseif (property_exists($cuenta, 'espacio_asignado_mb') && is_numeric($cuenta->espacio_asignado_mb)) {
                    $quotaBytes = (int) round(((float) $cuenta->espacio_asignado_mb) * 1024 * 1024);
                }
            }
        }

        if ($quotaBytes < $usedBytes) {
            $quotaBytes = $usedBytes;
        }

        $availableBytes = max(0, $quotaBytes - $usedBytes);

        $usedGb = $usedBytes / 1073741824;
        $availableGb = $availableBytes / 1073741824;
        $quotaGb = $quotaBytes / 1073741824;

        $usedPct = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $availablePct = $quotaBytes > 0 ? round(($availableBytes / $quotaBytes) * 100, 2) : 0.0;

        return [
            'used_bytes'      => $usedBytes,
            'available_bytes' => $availableBytes,
            'quota_bytes'     => $quotaBytes,
            'used_gb'         => round($usedGb, 2),
            'available_gb'    => round($availableGb, 2),
            'quota_gb'        => round($quotaGb, 2),
            'used_pct'        => $usedPct,
            'available_pct'   => $availablePct,
            'chart'           => [
                'series' => [
                    round($usedGb, 2),
                    round($availableGb, 2),
                ],
                'labels' => ['Usado', 'Disponible'],
            ],
        ];
    }

    private function resolveV2DetailLabelForPortal(array $item): string
    {
        $kind = strtolower((string) ($item['kind'] ?? ''));

        return match ($kind) {
            'metadata' => number_format((int) ($item['rows_count'] ?? 0)) . ' registros',
            'xml'      => number_format((int) ($item['files_count'] ?? 0)) . ' archivo(s)',
            'report'   => number_format((int) ($item['rows_count'] ?? 0)) . ' filas',
            default    => 'Archivo',
        };
    }

    private function resolveKindFromFilenameForPortal(string $filename, string $mime = ''): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext !== '') {
            return $ext;
        }

        $mime = strtolower(trim($mime));

        return match ($mime) {
            'application/zip' => 'zip',
            'application/xml', 'text/xml' => 'xml',
            'text/csv', 'application/csv' => 'csv',
            'application/pdf' => 'pdf',
            default => 'archivo',
        };
    }

    private function formatBytesHumanForPortal(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }
}