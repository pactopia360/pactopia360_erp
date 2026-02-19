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

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

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

    /* =========================
     * SOT helpers
     * ========================= */
    private function cu(): ?object { return $this->ctx->user(); }
    private function cuId(): string { return $this->ctx->cuentaId(); }
    private function trace(): string { return $this->ctx->trace(); }
    private function isAjax(Request $r): bool { return $this->ctx->isAjax($r); }
    private function resolveCuentaIdFromUser($user): ?string { return $this->ctx->resolveCuentaIdFromUser($user); }

    /**
     * Resolver admin_account_id (BIGINT) de la cuenta del portal:
     * - toma UUID real (cuentas_cliente.id) desde ctx (lo que ya te funciona en index)
     * - consulta cuentas_cliente.admin_account_id vía SatVaultStorage
     */
    private function resolveAdminAccountIdFromPortal(): ?int
    {
        $uuid = trim((string) $this->cuId());
        if ($uuid === '') return null;

        try {
            $cc = $this->vaultStorage->fetchCuentaCliente($uuid);
            if (!$cc) return null;

            $val = trim((string) ($cc->admin_account_id ?? ''));
            if ($val !== '' && ctype_digit($val) && (int)$val > 0) return (int) $val;
        } catch (\Throwable $e) {
            Log::warning('[SAT] resolveAdminAccountIdFromPortal failed', [
                'trace_id' => $this->trace(),
                'cuenta_uuid' => $uuid,
                'err' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Inyecta admin_account_id/cuenta_id al Request para que el service resuelva SIEMPRE
     * sin depender de session/guard.
     */
    private function injectExternalAccountContext(Request $request): void
    {
        $uuid = trim((string) $this->cuId());
        $aid  = $this->resolveAdminAccountIdFromPortal();

        // Nota: input() lee query+request, así que merge sirve para GET/POST
        if ($uuid !== '') $request->merge(['cuenta_id' => $uuid]);

        if ($aid && $aid > 0) {
            $request->merge([
                'admin_account_id' => (string) $aid,
                'account_id' => (string) $aid,
            ]);
        }
    }

    /* ===================================================
     *  VISTA SAT
     * =================================================== */
    public function index(Request $request)
    {
        $user = $this->cu();
        if (!$user) return redirect()->route('cliente.login');

        $cuentaId = trim((string) ($this->resolveCuentaIdFromUser($user) ?? ''));
        $cuentaCliente = $cuentaId !== '' ? $this->vaultStorage->fetchCuentaCliente($cuentaId) : null;

        // fallback solo si tu auth trae cuenta embebida
        if (!$cuentaCliente) {
            $tmp = $user?->cuenta ?? null;
            if (is_array($tmp)) $tmp = (object) $tmp;
            if (is_object($tmp)) {
                if ($cuentaId === '') $cuentaId = trim((string) ($tmp->id ?? $tmp->cuenta_id ?? ''));
                $cuentaCliente = $tmp;
            }
        }

        $planRaw   = (string) (($cuentaCliente->plan_actual ?? $cuentaCliente->plan ?? 'FREE'));
        $plan      = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        [$vaultSummary, $vaultForJs] = $this->presenter->buildVaultSummaries($cuentaId, $cuentaCliente);

        $credList          = collect();
        $credMap           = [];
        $rfcOptions        = [];
        $initialRows       = [];
        $cartIds           = [];
        $downloadsPage     = null;
        $downloadsTotalAll = 0;

        if ($cuentaId !== '') {
            try {
                $cartIds = $this->cart->getCartIds($cuentaId);

                $conn = 'mysql_clientes';

                $credList   = $this->rfcOptionsSvc->loadCredentials($cuentaId, $conn);
                $credMap    = $this->rfcOptionsSvc->buildCredMap($credList);
                $rfcOptions = $this->rfcOptionsSvc->buildRfcOptionsSmart($credList, $conn);

                $perPage = (int) $request->query('per', 20);
                $perPage = max(5, min(100, $perPage));

                $baseQuery = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                    ->orderByDesc('created_at');

                $downloadsTotalAll = (int) $baseQuery->count();
                $downloadsPage     = $baseQuery->paginate($perPage);

                $now = Carbon::now();

                $collection = $downloadsPage->getCollection()
                    ->filter(function (SatDownload $d) {
                        $tipo        = strtolower((string) data_get($d, 'tipo', ''));
                        $isRequest   = (bool) data_get($d, 'is_request', false);
                        $esSolicitud = (bool) data_get($d, 'es_solicitud', false);
                        if ($isRequest || $esSolicitud) return false;
                        if (in_array($tipo, ['solicitud', 'request', 'peticion'], true)) return false;
                        return true;
                    })
                    ->values();

                $rowsH = $collection->map(fn (SatDownload $d) => $this->presenter->transformDownloadRow($d, $credMap, $cartIds, $now));
                $downloadsPage->setCollection($rowsH);

                $initialRows = $rowsH->values()->all();
            } catch (\Throwable $e) {
                Log::error('[SAT:index] Error cargando sat', ['cuenta_id' => $cuentaId, 'error' => $e->getMessage()]);
            }
        }

        return view('cliente.sat.index', [
            'plan'               => $plan,
            'isProPlan'          => $isProPlan,
            'credList'           => $credList,
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

            // (opcional, si ya lo inyectas en window.P360_SAT)
            'admin_account_id'   => $this->resolveAdminAccountIdFromPortal(),
        ]);
    }

    /* ===================================================
     *  RFC: guardar alias (delegado)
     * =================================================== */
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
                ? response()->json(['ok'=>false,'msg'=>$res['msg'],'trace_id'=>$trace], 422)
                : redirect()->route('cliente.sat.index')->with('error', $res['msg']);
        }

        if ($isAjax) {
            return response()->json([
                'ok' => true,
                'trace_id' => $trace,
                'rfc' => $res['rfc'],
                'alias' => $res['alias'],
                'msg' => $res['msg'],
            ], 200);
        }

        return redirect()->route('cliente.sat.index')->with('ok', $res['msg']);
    }

    /* ===================================================
     *  CARRITO
     * =================================================== */
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
            'ok' => false,
            'trace_id' => $this->trace(),
            'msg' => 'cancelDownload aún no está delegado a servicio (SatCartService).',
        ], 501);
    }

    /* ==========================================================
     *  COTIZADOR / QUICK / PDF
     * ========================================================== */
    public function quoteCalc(Request $request): JsonResponse
    {
        $trace = $this->trace();
        $user  = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json(['ok'=>false,'msg'=>'Sesión expirada o cuenta inválida.','trace_id'=>$trace], 401);
        }

        $data = $request->validate([
            'xml_count'     => ['required', 'integer', 'min:1', 'max:50000000'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            'iva'           => ['nullable'],
            'iva_rate'      => ['nullable'],
        ]);

        $xmlCount = (int) $data['xml_count'];
        $discountCode = trim((string) ($data['discount_code'] ?? ''));

        $ivaRate = $this->quoteSvc->normalizeIvaRate($data['iva'] ?? $data['iva_rate'] ?? 16);

        $p = $this->quoteSvc->buildSatQuotePayload(
            user: $user,
            cuentaId: $cuentaId,
            xmlCount: $xmlCount,
            discountCode: $discountCode,
            ivaRate: $ivaRate,
            useAdminPrice: false
        );

        return response()->json([
            'ok' => true,
            'trace_id' => $trace,
            'data' => [
                'mode' => 'quote',
                'folio' => $p['folio'],
                'generated_at' => $p['generated']->toIso8601String(),
                'valid_until' => $p['validUntil']->toDateString(),
                'plan' => $p['plan'],
                'cuenta_id' => $cuentaId,
                'empresa' => $p['empresa'],
                'xml_count' => $p['xmlCount'],
                'base' => $p['base'],
                'discount_code' => $p['discountCode'],
                'discount_code_applied' => $p['discountCodeApplied'],
                'discount_label' => $p['discountLabel'],
                'discount_reason' => $p['discountReason'],
                'discount_type' => $p['discountType'],
                'discount_value' => $p['discountValue'],
                'discount_pct' => (float) $p['discountPct'],
                'discount_amount' => $p['discountAmount'],
                'subtotal' => $p['subtotal'],
                'iva_rate' => $p['ivaRate'],
                'iva_amount' => $p['ivaAmount'],
                'total' => $p['total'],
                'note' => $p['note'],
            ],
        ], 200);
    }

    public function quickCalc(Request $request): JsonResponse
    {
        $trace = $this->trace();
        $user  = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json(['ok'=>false,'msg'=>'Sesión expirada o cuenta inválida.','trace_id'=>$trace], 401);
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
            return response()->json(['ok'=>false,'msg'=>'xml_count requerido.','trace_id'=>$trace], 422);
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
            'ok' => true,
            'trace_id' => $trace,
            'data' => [
                'mode' => 'quick',
                'folio' => $p['folio'],
                'generated_at' => $p['generated']->toIso8601String(),
                'valid_until' => $p['validUntil']->toDateString(),
                'plan' => $p['plan'],
                'cuenta_id' => $cuentaId,
                'empresa' => $p['empresa'],
                'xml_count' => $p['xmlCount'],
                'base' => $p['base'],
                'discount_code' => $p['discountCode'],
                'discount_code_applied' => $p['discountCodeApplied'],
                'discount_label' => $p['discountLabel'],
                'discount_reason' => $p['discountReason'],
                'discount_type' => $p['discountType'],
                'discount_value' => $p['discountValue'],
                'discount_pct' => (float) $p['discountPct'],
                'discount_amount' => $p['discountAmount'],
                'subtotal' => $p['subtotal'],
                'iva_rate' => $p['ivaRate'],
                'iva_amount' => $p['ivaAmount'],
                'total' => $p['total'],
                'note' => $p['note'],
            ],
        ], 200);
    }

    public function quickPdf(Request $request)
    {
        $trace = $this->trace();
        $user  = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') abort(401, 'Sesión expirada o cuenta inválida.');

        $data = $request->validate([
            'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'discount_code'       => ['nullable', 'string', 'max:64'],
            'iva'                 => ['nullable'],
            'iva_rate'            => ['nullable'],
        ]);

        $xmlCount = (int) ($data['xml_count'] ?? $data['xml_count_estimated'] ?? 0);
        if ($xmlCount <= 0) abort(422, 'xml_count requerido.');

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

        $payload = [
            'trace_id'     => $trace,
            'mode'         => 'quick',
            'folio'        => $p['folio'],
            'generated_at' => $p['generated'],
            'valid_until'  => $p['validUntil'],
            'plan'         => $p['plan'],
            'cuenta_id'    => $cuentaId,
            'empresa'      => $p['empresa'],
            'xml_count'    => $p['xmlCount'],
            'base'         => $p['base'],
            'discount_code'   => $p['discountCode'],
            'discount_code_applied' => $p['discountCodeApplied'],
            'discount_label'        => $p['discountLabel'],
            'discount_reason'       => $p['discountReason'],
            'discount_type'         => $p['discountType'],
            'discount_value'        => $p['discountValue'],
            'discount_pct'    => (float) $p['discountPct'],
            'discount_amount' => $p['discountAmount'],
            'subtotal'     => $p['subtotal'],
            'iva_rate'     => $p['ivaRate'],
            'iva_amount'   => $p['ivaAmount'],
            'total'        => $p['total'],
            'note'         => $p['note'],
            'issuer'       => [
                'name'             => (string) (config('app.name') ?: 'Pactopia360'),
                'brand'            => 'Pactopia',
                'website'          => (string) (config('app.url') ?: ''),
                'email'            => (string) (config('mail.from.address') ?: 'notificaciones@pactopia360.com'),
                'phone'            => (string) (config('services.pactopia.phone') ?: ''),
                'logo_public_path' => public_path('assets/client/logp360ligjt.png'),
            ],

        ];

        if (!View::exists('cliente.sat.pdf.quote')) {
            return response('No existe la vista PDF: resources/views/cliente/sat/pdf/quote.blade.php (cliente.sat.pdf.quote).', 501);
        }

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response('No está disponible el generador PDF (DomPDF). Instala/activa barryvdh/laravel-dompdf.', 501);
        }

        $payload['quote_hash'] = $this->quoteSvc->quotePdfHash($payload);

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cliente.sat.pdf.quote', $payload)
                ->setPaper('letter', 'portrait')
                ->setOptions([
                    'defaultFont'           => 'DejaVu Sans',
                    'isRemoteEnabled'       => false,
                    'isHtml5ParserEnabled'  => true,
                    'isPhpEnabled'          => false,
                    'dpi'                   => 96,
                    'fontHeightRatio'       => 1.0,
                ]);

            $file = 'cotizacion_sat_rapida_' . $cuentaId . '_' . $p['generated']->format('Ymd_His') . '.pdf';
            return $pdf->download($file);
        } catch (\Throwable $e) {
            Log::error('[SAT:quickPdf] DomPDF error', ['trace_id' => $trace, 'err' => $e->getMessage()]);
            return response('Error generando PDF.', 500);
        }
    }

    /* ==========================================================
     *  EXTERNAL INVITE / REGISTER / STORE
     * ========================================================== */

    public function externalInvite(Request $request): JsonResponse
    {
        // ✅ FIX: inyecta contexto (admin_account_id) y delega al service de invite ZIP
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
        // ✅ FIX: inyecta contexto (admin_account_id)
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
        // ✅ FIX: inyecta contexto (admin_account_id)
        $this->injectExternalAccountContext($request);

        $trace = $this->trace();
        $res = $this->externalZipSvc->externalZipList($request, $trace);

        return response()->json(
            array_merge(['trace_id' => $trace], $res),
            (int) ($res['code'] ?? ($res['ok'] ? 200 : 500))
        );
    }

    /* ==========================================================
     *  DASHBOARD STATS
     * ========================================================== */
    public function dashboardStats(Request $request): JsonResponse
    {
        return $this->dashboardStatsSvc->handle($request, $this->ctx);
    }
}
