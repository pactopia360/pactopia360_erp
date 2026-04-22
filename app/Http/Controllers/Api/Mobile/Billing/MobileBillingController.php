<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\Billing;

use App\Http\Controllers\Cliente\AccountBillingController;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class MobileBillingController extends Controller
{
    private AccountBillingController $billing;
    private SessionManager $sessionManager;

    public function __construct()
    {
        $this->billing = app(AccountBillingController::class);
        $this->sessionManager = app('session');
    }

    public function statement(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $this->prepareWebContext($request, $user);

            $response = $this->billing->statement($request);
            $viewData = $this->extractViewData($response);

            if ($viewData === null) {
                Log::warning('[MOBILE:BILLING] statement did not return a view payload', [
                    'response_class' => is_object($response) ? get_class($response) : gettype($response),
                    'user_id'        => $user->id ?? null,
                ]);

                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo interpretar el estado de cuenta móvil.',
                ], 500);
            }

            $rows = collect((array) ($viewData['rows'] ?? []))
                ->map(function ($row) {
                    $r = is_array($row) ? $row : (array) $row;

                    $charge = round((float) ($r['charge'] ?? $r['total_cargo'] ?? 0), 2);
                    $paidAmount = round((float) ($r['paid_amount'] ?? $r['total_abono'] ?? 0), 2);
                    $saldo = round((float) ($r['saldo'] ?? 0), 2);
                    $status = (string) ($r['status'] ?? 'pending');

                    return [
                        'period'                 => (string) ($r['period'] ?? ''),
                        'status'                 => $status,
                        'charge'                 => $charge,
                        'paid_amount'            => $paidAmount,
                        'saldo'                  => $saldo,
                        'can_pay'                => (bool) ($r['can_pay'] ?? false),
                        'period_range'           => (string) ($r['period_range'] ?? ''),
                        'rfc'                    => (string) ($r['rfc'] ?? ''),
                        'alias'                  => (string) ($r['alias'] ?? ''),
                        'invoice_request_status' => $r['invoice_request_status'] ?? null,
                        'invoice_has_zip'        => (bool) ($r['invoice_has_zip'] ?? false),
                        'price_source'           => (string) ($r['price_source'] ?? ''),
                        'is_paid'                => in_array($status, ['paid', 'pagado'], true) || $saldo <= 0,
                        'is_pending'             => $saldo > 0,
                    ];
                })
                ->values()
                ->all();

            $saldoPendiente = round((float) ($viewData['saldoPendiente'] ?? 0), 2);
            $periodosPagadosTotal = round((float) ($viewData['periodosPagadosTotal'] ?? 0), 2);
            $mensualidadAdmin = round((float) ($viewData['mensualidadAdmin'] ?? 0), 2);
            $annualTotalMxn = round((float) ($viewData['annualTotalMxn'] ?? 0), 2);

            $rowsCount = count($rows);
            $pendingRows = collect($rows)->where('is_pending', true)->count();
            $paidRows = collect($rows)->where('is_paid', true)->count();

            return response()->json([
                'ok'   => true,
                'data' => [
                    'account_id'             => $viewData['accountId'] ?? null,
                    'contract_start'         => (string) ($viewData['contractStart'] ?? ''),
                    'last_paid'              => (string) ($viewData['lastPaid'] ?? ''),
                    'pay_allowed'            => (string) ($viewData['payAllowed'] ?? ''),
                    'saldo_pendiente'        => $saldoPendiente,
                    'periodos_pagados_total' => $periodosPagadosTotal,
                    'mensualidad_admin'      => $mensualidadAdmin,
                    'annual_total_mxn'       => $annualTotalMxn,
                    'is_annual'              => (bool) ($viewData['isAnnual'] ?? false),
                    'rfc'                    => (string) ($viewData['rfc'] ?? ''),
                    'alias'                  => (string) ($viewData['alias'] ?? ''),

                    'summary' => [
                        'rows_count'      => $rowsCount,
                        'pending_rows'    => $pendingRows,
                        'paid_rows'       => $paidRows,
                        'saldo_pendiente' => $saldoPendiente,
                        'mensualidad'     => $mensualidadAdmin,
                        'annual_total'    => $annualTotalMxn,
                        'is_annual'       => (bool) ($viewData['isAnnual'] ?? false),
                    ],

                    'rows' => $rows,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[MOBILE:BILLING] statement failed', [
                'user_id' => $user->id ?? null,
                'err'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo cargar el estado de cuenta móvil.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function pdfUrl(Request $request, string $period)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->isValidPeriod($period)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Periodo inválido.',
            ], 422);
        }

        try {
            $this->prepareWebContext($request, $user);

            [$accountIdRaw] = $this->billingResolveAdminAccountId($request);
            $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;

            if ($accountId <= 0) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo resolver la cuenta.',
                ], 422);
            }

            $url = URL::temporarySignedRoute(
                'cliente.billing.publicPdfInline',
                now()->addMinutes(30),
                [
                    'accountId' => $accountId,
                    'period'    => $period,
                ]
            );

            return response()->json([
                'ok'   => true,
                'data' => [
                    'period' => $period,
                    'url'    => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[MOBILE:BILLING] pdfUrl failed', [
                'user_id' => $user->id ?? null,
                'period'  => $period,
                'err'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo generar la URL del PDF.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function payUrl(Request $request, string $period)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->isValidPeriod($period)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Periodo inválido.',
            ], 422);
        }

        try {
            $this->prepareWebContext($request, $user);

            [$accountIdRaw] = $this->billingResolveAdminAccountId($request);
            $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;

            if ($accountId <= 0) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo resolver la cuenta.',
                ], 422);
            }

            $url = URL::temporarySignedRoute(
                'cliente.billing.publicPay',
                now()->addMinutes(30),
                [
                    'accountId' => $accountId,
                    'period'    => $period,
                ]
            );

            return response()->json([
                'ok'   => true,
                'data' => [
                    'period' => $period,
                    'url'    => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[MOBILE:BILLING] payUrl failed', [
                'user_id' => $user->id ?? null,
                'period'  => $period,
                'err'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo generar la URL de pago.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function requestInvoice(Request $request, string $period)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->isValidPeriod($period)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Periodo inválido.',
            ], 422);
        }

        try {
            $this->prepareWebContext($request, $user);

            $response = $this->billing->requestInvoice($request, $period);

            $statusCode = $this->extractStatusCode($response);
            if ($statusCode >= 400) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo solicitar la factura.',
                ], $statusCode);
            }

            return response()->json([
                'ok'   => true,
                'data' => [
                    'period'  => $period,
                    'message' => 'Solicitud de factura enviada correctamente.',
                    'status'  => 'requested',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[MOBILE:BILLING] requestInvoice failed', [
                'user_id' => $user->id ?? null,
                'period'  => $period,
                'err'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo solicitar la factura.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function prepareWebContext(Request $request, mixed $user): void
    {
        Auth::shouldUse('web');
        Auth::guard('web')->setUser($user);

        if (!$request->hasSession()) {
            $session = $this->sessionManager->driver();
            $session->start();
            $request->setLaravelSession($session);
        }
    }

    private function extractViewData(mixed $response): ?array
    {
        if ($response instanceof ViewContract) {
            /** @var array<string,mixed> $data */
            $data = $response->getData();
            return $data;
        }

        if ($response instanceof HttpResponse || $response instanceof SymfonyResponse) {
            $original = method_exists($response, 'getOriginalContent')
                ? $response->getOriginalContent()
                : null;

            if ($original instanceof ViewContract) {
                /** @var array<string,mixed> $data */
                $data = $original->getData();
                return $data;
            }
        }

        return null;
    }

    private function extractStatusCode(mixed $response): int
    {
        if ($response instanceof HttpResponse || $response instanceof SymfonyResponse) {
            return (int) $response->getStatusCode();
        }

        if ($response instanceof RedirectResponse) {
            return (int) $response->getStatusCode();
        }

        return 200;
    }

    private function billingResolveAdminAccountId(Request $request): array
    {
        $method = new \ReflectionMethod($this->billing, 'resolveAdminAccountId');
        $method->setAccessible(true);

        /** @var array{0:mixed,1:mixed} $result */
        $result = $method->invoke($this->billing, $request);

        return $result;
    }

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }
}