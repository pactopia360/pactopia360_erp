<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\BillingStatementsV2Controller.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Admin\Billing\Concerns\HandlesStatementOverridesAndPeriods;
use App\Http\Controllers\Controller;
use App\Models\Admin\Billing\BillingStatement;
use App\Models\Cliente\CuentaCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Models\Admin\Billing\BillingStatementItem;


final class BillingStatementsV2Controller extends Controller
{
    use HandlesStatementOverridesAndPeriods;

    protected string $adm = 'mysql_admin';

    protected BillingStatementsController $legacyStatements;

    /**
     * @var array<string, string|null>
     */
    protected array $cacheLastPaid = [];

    public function __construct(BillingStatementsController $legacyStatements)
    {
        $this->legacyStatements = $legacyStatements;
    }

    public function index(Request $request): View
    {
        $period = trim((string) $request->query('period', now()->format('Y-m')));

        if (!$this->isValidPeriod($period)) {
            $period = now()->format('Y-m');
        }

        $search = trim((string) $request->query('search', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $statusFilter = strtolower(trim((string) $request->query('status', '')));
        $scope = strtolower(trim((string) $request->query('scope', '')));
        $selectedIds = collect((array) $request->query('selected_ids', []))
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $allowedPerPage = [25, 50, 100, 250, 500, 1000];
        $perPage = (int) $request->query('per_page', 25);

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodDate->copy()->endOfMonth();
        $previousPeriod = $periodDate->copy()->subMonth()->format('Y-m');

        $clientes = CuentaCliente::query()
            ->with(['owner'])
            ->select($this->resolveCuentaClienteSelectColumns())
            ->where(function ($query) use ($periodEnd) {
                if ($this->cuentaClienteHasColumn('created_at')) {
                    $query->whereNull('created_at')
                        ->orWhere('created_at', '<=', $periodEnd);
                }
            })
            ->orderByRaw('COALESCE(NULLIF(razon_social, ""), NULLIF(nombre_comercial, ""), id) asc')
            ->get()
            ->filter(function (CuentaCliente $cliente) use ($periodDate, $periodEnd) {
                return $this->shouldIncludeClienteInStatementsV2($cliente, $periodDate, $periodEnd);
            })
            ->values();

        if ($clientes->isEmpty()) {
            return view('admin.billing.statements_v2.index', [
                'currentPeriod' => $period,
                'expandAll'     => true,
                'statements'    => collect(),
            ]);
        }

        $clientesByStatementAccountId = $this->mapClientesByStatementAccountId($clientes);
        $statementAccountIds = array_keys($clientesByStatementAccountId);

        if (empty($statementAccountIds)) {
            return view('admin.billing.statements_v2.index', [
                'currentPeriod' => $period,
                'expandAll'     => true,
                'statements'    => collect(),
            ]);
        }

        $statementRows = BillingStatement::query()
            ->whereIn('account_id', $statementAccountIds)
            ->where('period', '<=', $period)
            ->orderBy('account_id')
            ->orderByDesc('period')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'account_id',
                'period',
                'total_cargo',
                'total_abono',
                'saldo',
                'status',
                'due_date',
                'sent_at',
                'paid_at',
                'snapshot',
                'meta',
                'is_locked',
                'created_at',
                'updated_at',
            ]);

        if ($statementRows->isEmpty()) {
            return view('admin.billing.statements_v2.index', [
                'currentPeriod' => $period,
                'expandAll'     => true,
                'statements'    => collect(),
            ]);
        }

                $rangeStartPeriod = $period;
        $rangeEndPeriod = $period;

        if ($dateFrom !== '') {
            try {
                $rangeStartPeriod = Carbon::parse($dateFrom)->startOfMonth()->format('Y-m');
            } catch (\Throwable $e) {
                $rangeStartPeriod = $period;
            }
        }

        if ($dateTo !== '') {
            try {
                $rangeEndPeriod = Carbon::parse($dateTo)->startOfMonth()->format('Y-m');
            } catch (\Throwable $e) {
                $rangeEndPeriod = $period;
            }
        }

        if ($rangeStartPeriod > $rangeEndPeriod) {
            [$rangeStartPeriod, $rangeEndPeriod] = [$rangeEndPeriod, $rangeStartPeriod];
        }

        $baseStatementRows = $statementRows
            ->filter(function (BillingStatement $row) use ($rangeStartPeriod, $rangeEndPeriod, $period, $dateFrom, $dateTo) {
                $rowPeriod = (string) ($row->period ?? '');

                if (!$this->isValidPeriod($rowPeriod)) {
                    return false;
                }

                if ($dateFrom === '' && $dateTo === '') {
                    return $rowPeriod === $period;
                }

                return $rowPeriod >= $rangeStartPeriod && $rowPeriod <= $rangeEndPeriod;
            })
            ->groupBy(fn (BillingStatement $row) => (string) $row->account_id . '|' . (string) $row->period)
            ->map(function (Collection $rows) {
                return $rows
                    ->sortByDesc(fn (BillingStatement $row) => sprintf(
                        '%s|%s|%s',
                        (string) $row->period,
                        (string) optional($row->updated_at)->timestamp,
                        str_pad((string) $row->id, 12, '0', STR_PAD_LEFT)
                    ))
                    ->first();
            })
            ->filter()
            ->values();

        $visibleAccountIds = $baseStatementRows
            ->map(fn (BillingStatement $row) => (string) $row->account_id)
            ->unique()
            ->values()
            ->all();

        $visiblePeriods = $baseStatementRows
            ->map(fn (BillingStatement $row) => (string) $row->period)
            ->unique()
            ->values()
            ->all();

        $overridesByAccountAndPeriod = [];

        foreach ($visiblePeriods as $visiblePeriod) {
            $periodOverrides = empty($visibleAccountIds)
                ? []
                : $this->fetchStatusOverridesForAccountsPeriod($visibleAccountIds, $visiblePeriod);

            foreach ($periodOverrides as $accountId => $overrideRow) {
                $overridesByAccountAndPeriod[(string) $accountId . '|' . (string) $visiblePeriod] = $overrideRow;
            }
        }

        $commercialAgreementsByAccountId = $this->fetchCommercialAgreementsForAccounts($visibleAccountIds);

        $statements = $baseStatementRows
            ->map(function (BillingStatement $currentStatement) use (
                $clientesByStatementAccountId,
                $statementRows,
                $period,
                $previousPeriod,
                $overridesByAccountAndPeriod,
                $commercialAgreementsByAccountId
            ) {
                $statementAccountId = (string) $currentStatement->account_id;
                $cliente = $clientesByStatementAccountId[(string) $statementAccountId] ?? null;

                if (!$cliente instanceof CuentaCliente) {
                    return null;
                }

                /** @var \Illuminate\Support\Collection<int, \App\Models\Admin\Billing\BillingStatement> $history */
                $history = $statementRows
                    ->where('account_id', (string) $statementAccountId)
                    ->values();

                $statementPeriod = (string) ($currentStatement->period ?? $period);

                /** @var \App\Models\Admin\Billing\BillingStatement|null $previousStatement */
                $previousStatement = $history
                    ->filter(fn (BillingStatement $row) => (string) $row->period < $statementPeriod)
                    ->sortByDesc('period')
                    ->first();

                $totalPeriodo = round((float) ($currentStatement->total_cargo ?? 0), 2);
                $abonoPeriodo = round((float) ($currentStatement->total_abono ?? 0), 2);
                $saldoPeriodo = round(max(0.0, (float) ($currentStatement->saldo ?? 0)), 2);

                $saldoAnterior = 0.00;

                if ($previousStatement) {
                    $previousStatus = strtolower(trim((string) ($previousStatement->status ?? '')));
                    $previousSaldo = round(max(0.0, (float) ($previousStatement->saldo ?? 0)), 2);

                    if (!in_array($previousStatus, ['paid', 'pagado'], true) && $previousSaldo > 0.00001) {
                        $saldoAnterior = $previousSaldo;
                    }
                }

                $statementMeta = $currentStatement->meta;
                if ($statementMeta instanceof \stdClass) {
                    $statementMeta = (array) $statementMeta;
                }
                if (!is_array($statementMeta)) {
                    $statementMeta = [];
                }

                $commercialAgreement = $commercialAgreementsByAccountId[(string) $statementAccountId] ?? null;

                if (!is_array($commercialAgreement)) {
                    $commercialAgreement = $this->resolveCommercialAgreementFromStatementMeta($statementMeta);
                }

                $lastPaidPeriod = $this->resolveLastPaidPeriodForAccount(
                    (string) $statementAccountId,
                    $statementMeta
                );

                if ($lastPaidPeriod !== null && $lastPaidPeriod >= $previousPeriod) {
                    $saldoAnterior = 0.00;
                }

                $statusBase = (object) [
                    'status_pago'                => $this->normalizeStatementStatus($currentStatement),
                    'status_auto'                => $this->normalizeStatementStatus($currentStatement),
                    'status_override'            => null,
                    'status_override_reason'     => null,
                    'status_override_updated_at' => null,
                    'status_override_updated_by' => null,
                    'pay_method'                 => null,
                    'pay_provider'               => null,
                    'pay_status'                 => null,
                    'pay_last_paid_at'           => $currentStatement->paid_at,
                ];

                $statusResolved = $this->applyStatusOverride(
                    $statusBase,
                    $overridesByAccountAndPeriod[(string) $statementAccountId . '|' . $statementPeriod] ?? null
                );

                $statusFinal = strtolower((string) ($statusResolved->status_pago ?? 'pendiente'));

                $lastPaymentDate = $currentStatement->paid_at
                    ?? ($statusResolved->ov_paid_at ?? null)
                    ?? $this->resolveLastPaidDateFromHistory($history);

                $owner = $cliente->owner;
                $displayName = $this->resolveClientDisplayName($cliente, $owner);
                $clientRfc = strtoupper(trim((string) ($cliente->rfc_padre ?? '')));
                $clientEmail = $this->resolveClientEmail($cliente, $owner);

                if ($this->shouldSkipIncompleteStatementRow($cliente, $displayName, $clientRfc, $clientEmail)) {
                    return null;
                }

                $licenseType = $this->resolveLicenseLabel($cliente);
                $billingMode = $this->resolveBillingMode($cliente);
                $clientSequence = $this->resolveClientSequence($cliente);

                return (object) [
                    'id'                   => $currentStatement->id,
                    'period'               => $statementPeriod,
                    'period_label'         => $this->formatPeriodLabel($statementPeriod),
                    'statement_account_id' => (string) $statementAccountId,
                    'account_id'           => (string) $statementAccountId,
                    'client_id'            => (string) $cliente->id,
                    'admin_account_id'     => $cliente->admin_account_id !== null ? (string) $cliente->admin_account_id : null,
                    'client_uuid'          => (string) $cliente->id,
                    'client_sequence'      => $clientSequence,
                    'customer_no'          => $cliente->customer_no ?? null,
                    'codigo_cliente'       => $cliente->codigo_cliente ?? null,
                    'client_name'          => $displayName,
                    'client_rfc'           => $clientRfc !== '' ? $clientRfc : null,
                    'client_email'         => $clientEmail !== '' ? $clientEmail : null,
                    'license_type'         => $licenseType,
                    'billing_mode'         => $billingMode,
                    'estado_cuenta'        => (string) ($cliente->estado_cuenta ?? ''),
                    'is_blocked'           => (bool) ($cliente->is_blocked ?? false),
                    'registered_at'        => $cliente->created_at ?? null,
                    'saldo_anterior'       => $saldoAnterior,
                    'total_periodo'        => $totalPeriodo,
                    'total_abono'          => $abonoPeriodo,
                    'saldo_periodo'        => $saldoPeriodo,
                    'saldo_total'          => round($saldoAnterior + $saldoPeriodo, 2),
                    'status'               => $statusFinal,
                    'status_auto'          => $statusResolved->status_auto ?? null,
                    'status_override'      => $statusResolved->status_override ?? null,
                    'status_reason'        => $statusResolved->status_override_reason ?? null,
                    'last_payment_date'    => $lastPaymentDate,
                    'due_date'             => $currentStatement->due_date,
                    'commercial_agreement' => $commercialAgreement,
                    'sent_at'              => $currentStatement->sent_at,
                    'paid_at'              => $currentStatement->paid_at,
                    'statement_id'         => $currentStatement->id,
                    'view_url'             => $this->buildStatementPreviewUrl((string) $statementAccountId, $statementPeriod),
                    'pdf_url'              => $this->buildStatementDownloadUrl((string) $statementAccountId, $statementPeriod),
                    'send_url'             => $this->buildStatementEmailSendUrl((string) $statementAccountId, $statementPeriod),
                    'status_url'           => $this->buildStatementStatusUpdateUrl((string) $statementAccountId, $statementPeriod),
                    'email_preview_url'    => $this->buildStatementEmailPreviewUrl((string) $statementAccountId, $statementPeriod),
                    'source_statement'     => $currentStatement,
                    'source_previous'      => $previousStatement,
                ];
            })
            ->filter()
            ->sortBy([
                fn ($row) => is_numeric((string) ($row->client_sequence ?? null)) ? (int) $row->client_sequence : PHP_INT_MAX,
                fn ($row) => mb_strtolower((string) ($row->client_name ?? '')),
                fn ($row) => (string) ($row->period ?? ''),
            ])
            ->values();

                    $statements = $statements
            ->filter(function (object $statement) use ($search, $statusFilter, $scope, $selectedIds, $dateFrom, $dateTo, $period) {
                if ($search !== '') {
                    $haystack = collect([
                        (string) ($statement->client_name ?? ''),
                        (string) ($statement->client_rfc ?? ''),
                        (string) ($statement->client_email ?? ''),
                        (string) ($statement->client_sequence ?? ''),
                        (string) ($statement->customer_no ?? ''),
                        (string) ($statement->codigo_cliente ?? ''),
                        (string) ($statement->account_id ?? ''),
                        (string) ($statement->statement_id ?? ''),
                        (string) ($statement->license_type ?? ''),
                        (string) ($statement->billing_mode ?? ''),
                    ])->implode(' | ');

                    if (!Str::contains(mb_strtolower($haystack), mb_strtolower($search))) {
                        return false;
                    }
                }

                if ($statusFilter !== '') {
                    $normalizedStatus = strtolower(trim((string) ($statement->status ?? '')));
                    $normalizedStatus = match ($normalizedStatus) {
                        'paid' => 'pagado',
                        'pending' => 'pendiente',
                        'partial' => 'parcial',
                        'overdue', 'late' => 'vencido',
                        default => $normalizedStatus,
                    };

                    if ($normalizedStatus !== $statusFilter) {
                        return false;
                    }
                }

                $statementId = trim((string) ($statement->statement_id ?? $statement->id ?? ''));

                if ($scope === 'selected' && !$selectedIds->contains($statementId)) {
                    return false;
                }

                if ($scope === 'unselected' && $selectedIds->contains($statementId)) {
                    return false;
                }

                if ($dateFrom !== '' || $dateTo !== '') {
                    $comparableDate = $this->resolveStatementComparableDate($statement, $period);

                    if (!$comparableDate instanceof Carbon) {
                        return false;
                    }

                    if ($dateFrom !== '') {
                        try {
                            $from = Carbon::parse($dateFrom)->startOfDay();
                            if ($comparableDate->lt($from)) {
                                return false;
                            }
                        } catch (\Throwable $e) {
                        }
                    }

                    if ($dateTo !== '') {
                        try {
                            $to = Carbon::parse($dateTo)->endOfDay();
                            if ($comparableDate->gt($to)) {
                                return false;
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                }

                return true;
            })
            ->values();

        $totalFiltered = $statements->count();

        $statements = $statements
            ->take($perPage)
            ->values();

        return view('admin.billing.statements_v2.index', [
            'currentPeriod' => $period,
            'expandAll'     => true,
            'statements'    => $statements,
            'totalFiltered' => $totalFiltered,
            'perPage'       => $perPage,
        ]);
    }

    public function generateCutoff(Request $request): RedirectResponse
    {
        $period = trim((string) $request->input('period', now()->format('Y-m')));

        if (!$this->isValidPeriod($period)) {
            return back()->withErrors([
                'period' => 'Periodo inválido.',
            ]);
        }

        $stats = $this->generateCutoffRowsForPeriod($period, $request->boolean('force'));

        return redirect()
            ->route('admin.billing.statements_v2.index', ['period' => $period])
            ->with('ok', "Corte generado. Creados: {$stats['created']}. Actualizados: {$stats['updated']}. Omitidos: {$stats['skipped']}.");
    }

    public function preview(Request $request, string $accountId, string $period)
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $query = $request->query();
        $query['inline'] = 1;
        $query['preview'] = 1;

        $proxyRequest = Request::create(
            $request->url(),
            'GET',
            $query,
            $request->cookies->all(),
            [],
            $request->server->all()
        );

        return $this->legacyStatements->pdf($proxyRequest, $accountId, $period);
    }

    public function download(Request $request, string $accountId, string $period)
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $proxyRequest = Request::create(
            $request->url(),
            'GET',
            $request->query(),
            $request->cookies->all(),
            [],
            $request->server->all()
        );

        return $this->legacyStatements->pdf($proxyRequest, $accountId, $period);
    }

    public function emailPreview(Request $request, string $accountId, string $period): JsonResponse
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $statement = $this->findStatementRow($accountId, $period);
        if (!$statement) {
            return response()->json([
                'ok'      => false,
                'message' => 'Estado de cuenta no encontrado.',
            ], 404);
        }

        $subject = $this->resolveEmailSubject($statement, $period);
        $message = $this->resolveEmailBody($statement, $period);
        $to = $this->resolveDefaultRecipientsForStatement($statement);

        $downloadUrl = $this->buildStatementDownloadUrl($accountId, $period);
        $previewUrl = $this->buildStatementPreviewUrl($accountId, $period);

        $html = $this->renderEmailHtml(
            $statement,
            $subject,
            $message,
            $downloadUrl !== '#' ? $downloadUrl : null,
            $previewUrl !== '#' ? $previewUrl : null
        );

        return response()->json([
            'ok'          => true,
            'account_id'  => $accountId,
            'period'      => $period,
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'html'        => $html,
            'downloadUrl' => $downloadUrl !== '#' ? $downloadUrl : null,
            'previewUrl'  => $previewUrl !== '#' ? $previewUrl : null,
        ]);
    }

    public function updateStatus(Request $request, string $accountId, string $period)
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $data = $request->validate([
            'status'            => 'required|string|in:pendiente,pagado,sin_mov,vencido',
            'pay_method'        => 'nullable|string|max:30',
            'paid_at'           => 'nullable|string',
            'payment_reference' => 'nullable|string|max:120',
            'payment_notes'     => 'nullable|string|max:4000',
        ]);

        $status = strtolower(trim((string) ($data['status'] ?? 'pendiente')));
        $payMethod = trim((string) ($data['pay_method'] ?? 'manual'));
        $paidAt = trim((string) ($data['paid_at'] ?? ''));
        $paymentReference = trim((string) ($data['payment_reference'] ?? ''));
        $paymentNotes = trim((string) ($data['payment_notes'] ?? ''));

        $payload = [
            'account_id' => $accountId,
            'period'     => $period,
            'status'     => $status,
            'pay_method' => $payMethod !== '' ? $payMethod : 'manual',
            'paid_at'    => $paidAt,
        ];

        $proxyRequest = Request::create(
            $request->url(),
            'POST',
            $payload,
            $request->cookies->all(),
            [],
            $request->server->all()
        );

        $jsonResponse = $this->legacyStatements->statusAjax($proxyRequest);

        $responseData = $jsonResponse->getData(true);

        if (($responseData['ok'] ?? false) !== true) {
            if ($request->expectsJson()) {
                return $jsonResponse;
            }

            return back()->withErrors([
                'status' => (string) ($responseData['message'] ?? 'No se pudo actualizar el estado de cuenta.'),
            ])->withInput();
        }

        $this->upsertV2PaymentExtras($accountId, $period, [
            'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
            'payment_notes'     => $paymentNotes !== '' ? $paymentNotes : null,
            'edited_from'       => 'statements_v2',
            'edited_at'         => now()->toDateTimeString(),
            'edited_by'         => auth('admin')->id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($responseData);
        }

        return back()->with('ok', 'Estado de cuenta actualizado correctamente.');
    }

    public function saveCommercialAgreement(Request $request, string $accountId): JsonResponse
{
    $accountId = trim($accountId);

    if ($accountId === '') {
        return response()->json([
            'ok' => false,
            'message' => 'La cuenta es inválida.',
        ], 422);
    }

    $validated = $request->validate([
        'agreed_due_day'             => ['nullable', 'integer', 'min:1', 'max:31'],
        'reminders_enabled'          => ['nullable', 'boolean'],
        'grace_days'                 => ['nullable', 'integer', 'min:0', 'max:31'],
        'effective_from'             => ['nullable', 'date'],
        'effective_until'            => ['nullable', 'date', 'after_or_equal:effective_from'],
        'apply_forward_indefinitely' => ['nullable', 'boolean'],
        'status'                     => ['nullable', 'in:active,inactive'],
        'notes'                      => ['nullable', 'string', 'max:5000'],
    ]);

    $agreedDueDay = isset($validated['agreed_due_day']) && $validated['agreed_due_day'] !== null
        ? (int) $validated['agreed_due_day']
        : null;

    $remindersEnabled = array_key_exists('reminders_enabled', $validated)
        ? (bool) $validated['reminders_enabled']
        : true;

    $graceDays = isset($validated['grace_days']) ? (int) $validated['grace_days'] : 0;
    $status = (string) ($validated['status'] ?? 'active');

    $applyForwardIndefinitely = array_key_exists('apply_forward_indefinitely', $validated)
        ? (bool) $validated['apply_forward_indefinitely']
        : false;

    $effectiveFrom = !empty($validated['effective_from']) ? (string) $validated['effective_from'] : null;
    $effectiveUntil = !empty($validated['effective_until']) ? (string) $validated['effective_until'] : null;

    if ($applyForwardIndefinitely) {
        $effectiveFrom = now()->toDateString();
        $effectiveUntil = null;
    }

    $notes = isset($validated['notes']) ? trim((string) $validated['notes']) : null;

    $cliente = CuentaCliente::query()
        ->select($this->resolveCuentaClienteSelectColumns())
        ->where(function ($query) use ($accountId) {
            $query->where('id', $accountId);

            if ($this->cuentaClienteHasColumn('admin_account_id')) {
                $query->orWhere('admin_account_id', $accountId);
            }
        })
        ->first();

    if (!$cliente instanceof CuentaCliente) {
        return response()->json([
            'ok' => false,
            'message' => 'No se encontró la cuenta del cliente.',
        ], 404);
    }

    $resolvedAccountId = (string) $cliente->id;
    $resolvedAdminAccountId = $cliente->admin_account_id !== null
        ? (int) $cliente->admin_account_id
        : null;

    $adminId = auth('admin')->id();
    $now = now();

    DB::connection('mysql_admin')->transaction(function () use (
        $resolvedAccountId,
        $resolvedAdminAccountId,
        $agreedDueDay,
        $remindersEnabled,
        $graceDays,
        $effectiveFrom,
        $effectiveUntil,
        $status,
        $notes,
        $adminId,
        $now
    ) {
        $existingAgreement = DB::connection('mysql_admin')
            ->table('billing_commercial_agreements')
            ->where('account_id', $resolvedAccountId)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'account_id'           => $resolvedAccountId,
            'admin_account_id'     => $resolvedAdminAccountId,
            'agreed_due_day'       => $agreedDueDay,
            'reminders_enabled'    => $remindersEnabled ? 1 : 0,
            'grace_days'           => $graceDays,
            'effective_from'       => $effectiveFrom,
            'effective_until'      => $effectiveUntil,
            'status'               => $status,
            'notes'                => $notes !== '' ? $notes : null,
            'meta'                 => json_encode([
                'source' => 'admin_statements_v2',
                'apply_forward_indefinitely' => $applyForwardIndefinitely,
            ], JSON_UNESCAPED_UNICODE),
            'updated_by_admin_id'  => $adminId,
            'updated_at'           => $now,
        ];

        if ($existingAgreement) {
            DB::connection('mysql_admin')
                ->table('billing_commercial_agreements')
                ->where('id', $existingAgreement->id)
                ->update($payload);
        } else {
            $payload['created_by_admin_id'] = $adminId;
            $payload['created_at'] = $now;

            DB::connection('mysql_admin')
                ->table('billing_commercial_agreements')
                ->insert($payload);
        }

        $this->syncCommercialAgreementDueDates(
            accountId: $resolvedAccountId,
            agreedDueDay: $agreedDueDay,
            graceDays: $graceDays,
            effectiveFrom: $effectiveFrom,
            effectiveUntil: $effectiveUntil,
            status: $status
        );
    });

    return response()->json([
        'ok' => true,
        'message' => 'Acuerdo comercial guardado correctamente.',
        'data' => [
            'account_id' => $resolvedAccountId,
            'agreed_due_day' => $agreedDueDay,
            'reminders_enabled' => $remindersEnabled,
            'grace_days' => $graceDays,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'apply_forward_indefinitely' => $applyForwardIndefinitely,
            'status' => $status,
            'notes' => $notes,
        ],
    ]);
}

    public function sendEmail(Request $request, string $accountId, string $period): RedirectResponse
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $statement = $this->findStatementRow($accountId, $period);
        if (!$statement) {
            return back()->withErrors([
                'email' => 'Estado de cuenta no encontrado.',
            ])->withInput();
        }

        $data = $request->validate([
            'to'      => 'required|string|max:4000',
            'subject' => 'required|string|max:180',
            'message' => 'required|string|max:12000',
        ]);

        $recipients = $this->normalizeRecipientList((string) $data['to']);
        if (empty($recipients)) {
            return back()->withErrors([
                'to' => 'Debes capturar al menos un correo válido.',
            ])->withInput();
        }

        $subject = trim((string) $data['subject']);
        $message = trim((string) $data['message']);

        $downloadUrl = $this->buildStatementDownloadUrl($accountId, $period);
        $previewUrl = $this->buildStatementPreviewUrl($accountId, $period);

        $html = $this->renderEmailHtml(
            $statement,
            $subject,
            $message,
            $downloadUrl !== '#' ? $downloadUrl : null,
            $previewUrl !== '#' ? $previewUrl : null
        );

        foreach ($recipients as $recipient) {
            Mail::html($html, function ($mailMessage) use ($recipient, $subject) {
                $mailMessage->to($recipient)->subject($subject);

                $bcc = $this->billingBccEmail();
                if ($bcc !== null) {
                    $mailMessage->bcc($bcc);
                }
            });
        }

        $this->markStatementAsSentIfPossible($accountId, $period);

        return back()->with('ok', 'Estado de cuenta enviado correctamente.');
    }

    public function sendBulk(Request $request): JsonResponse
    {
        $period = trim((string) $request->input('period', now()->format('Y-m')));

        if (!$this->isValidPeriod($period)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Periodo inválido.',
            ], 422);
        }

        $this->generateCutoffRowsForPeriod($period, false);

        $mode = strtolower(trim((string) $request->input('mode', 'visible')));

        $selectedIds = collect((array) $request->input('selected_ids', []))
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        [$statements, $totalFiltered] = $this->resolveStatementsForFilters($request, false);

        if ($mode === 'selected') {
            $statements = $statements
                ->filter(function (object $statement) use ($selectedIds) {
                    $statementId = trim((string) ($statement->statement_id ?? $statement->id ?? ''));
                    return $selectedIds->contains($statementId);
                })
                ->values();
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($statements as $statement) {
            $accountId = trim((string) ($statement->account_id ?? ''));
            $statementPeriod = trim((string) ($statement->period ?? ''));

            if ($accountId === '' || !$this->isValidPeriod($statementPeriod)) {
                $skipped++;
                continue;
            }

            $statementRow = $this->findStatementRow($accountId, $statementPeriod);

            if (!$statementRow) {
                $skipped++;
                continue;
            }

            $recipients = $this->resolveDefaultRecipientsForStatement($statementRow);

            if (empty($recipients)) {
                $skipped++;
                continue;
            }

            $subject = $this->resolveEmailSubject($statementRow, $statementPeriod);
            $message = $this->resolveEmailBody($statementRow, $statementPeriod);
            $downloadUrl = $this->buildStatementDownloadUrl($accountId, $statementPeriod);
            $previewUrl = $this->buildStatementPreviewUrl($accountId, $statementPeriod);

            $html = $this->renderEmailHtml(
                $statementRow,
                $subject,
                $message,
                $downloadUrl !== '#' ? $downloadUrl : null,
                $previewUrl !== '#' ? $previewUrl : null
            );

            try {
                foreach ($recipients as $recipient) {
                    Mail::html($html, function ($mailMessage) use ($recipient, $subject) {
                        $mailMessage->to($recipient)->subject($subject);

                        $bcc = $this->billingBccEmail();
                        if ($bcc !== null) {
                            $mailMessage->bcc($bcc);
                        }
                    });
                }

                $this->markStatementAsSentIfPossible($accountId, $statementPeriod);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return response()->json([
            'ok'            => $failed === 0,
            'mode'          => $mode,
            'totalFiltered' => $totalFiltered,
            'sent'          => $sent,
            'skipped'       => $skipped,
            'failed'        => $failed,
            'message'       => "Envío terminado. Enviados: {$sent}. Omitidos: {$skipped}. Fallidos: {$failed}.",
        ]);
    }
    public function registerAdvancePayments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id'              => 'required|string|max:80',
            'payment_date'            => 'nullable|date',
            'payment_method'          => 'nullable|string|max:40',
            'payment_reference'       => 'nullable|string|max:120',
            'lines'                   => 'required|array|min:1',
            'lines.*.period'          => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'lines.*.type'            => 'required|string|in:full,partial',
            'lines.*.amount'          => 'required|numeric|min:0.01|max:99999999',
            'lines.*.notes'           => 'nullable|string|max:500',
        ]);

        $accountId = trim((string) $data['account_id']);
        $paymentMethod = trim((string) ($data['payment_method'] ?? 'transferencia'));
        $paymentReference = trim((string) ($data['payment_reference'] ?? ''));
        $paymentDate = !empty($data['payment_date'])
            ? Carbon::parse((string) $data['payment_date'])->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');

        $lines = collect((array) $data['lines'])
            ->map(function (array $line) {
                return [
                    'period' => trim((string) ($line['period'] ?? '')),
                    'type'   => strtolower(trim((string) ($line['type'] ?? 'full'))),
                    'amount' => round((float) ($line['amount'] ?? 0), 2),
                    'notes'  => trim((string) ($line['notes'] ?? '')),
                ];
            })
            ->filter(fn (array $line) => $line['period'] !== '' && $line['amount'] > 0.00001)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Debes capturar al menos un periodo válido.',
            ]);
        }

        $periodFrom = (string) $lines->min('period');
        $periodTo = (string) $lines->max('period');
        $totalAmount = round((float) $lines->sum('amount'), 2);

        $paymentsController = app(PaymentsController::class);

        try {
            $subRequest = Request::create(
                route('admin.billing.payments.manual'),
                'POST',
                [
                    'account_id'           => $accountId,
                    'amount_pesos'         => $totalAmount,
                    'currency'             => 'MXN',
                    'concept'              => 'Adelanto de pagos desde estados de cuenta V2',
                    'period'               => $periodFrom,
                    'period_to'            => $periodTo,
                    'also_apply_statement' => 1,
                ]
            );

            $paymentsController->manual($subRequest);
        } catch (Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo registrar el adelanto de pagos.',
                'error'   => $e->getMessage(),
            ], 422);
        }

        foreach ($lines as $line) {
            $this->upsertAdvancePaymentMeta($accountId, (string) $line['period'], [
                'registered_from'   => 'statements_v2.advance_modal',
                'payment_method'    => $paymentMethod,
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'payment_date'      => $paymentDate,
                'payment_type'      => (string) $line['type'],
                'payment_amount'    => (float) $line['amount'],
                'payment_notes'     => (string) $line['notes'],
                'registered_at'     => now()->toDateTimeString(),
                'registered_by'     => auth('admin')->id(),
            ]);
        }

        return response()->json([
            'ok'          => true,
            'account_id'  => $accountId,
            'period_from' => $periodFrom,
            'period_to'   => $periodTo,
            'total'       => $totalAmount,
            'message'     => 'Adelanto de pagos registrado correctamente.',
        ]);
    }

    public function registerBulkPayments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payments'                   => 'required|array|min:1',
            'payments.*.account_id'      => 'required|string|max:80',
            'payments.*.period'          => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payments.*.amount'          => 'required|numeric|min:0.01|max:99999999',
            'payments.*.method'          => 'nullable|string|max:40',
            'payments.*.reference'       => 'nullable|string|max:120',
            'payments.*.concept'         => 'nullable|string|max:255',
            'payments.*.also_apply'      => 'nullable|boolean',
        ]);

        $paymentsController = app(PaymentsController::class);
        $processed = 0;
        $errors = [];

        foreach ((array) $data['payments'] as $index => $payment) {
            try {
                $subRequest = Request::create(
                    route('admin.billing.payments.manual'),
                    'POST',
                    [
                        'account_id'           => trim((string) ($payment['account_id'] ?? '')),
                        'amount_pesos'         => round((float) ($payment['amount'] ?? 0), 2),
                        'currency'             => 'MXN',
                        'concept'              => trim((string) ($payment['concept'] ?? 'Pago masivo manual desde estados de cuenta V2')),
                        'period'               => trim((string) ($payment['period'] ?? '')),
                        'also_apply_statement' => (bool) ($payment['also_apply'] ?? true),
                    ]
                );

                $paymentsController->manual($subRequest);
                $processed++;
            } catch (Throwable $e) {
                $errors[] = [
                    'row'     => $index + 1,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok'        => empty($errors),
            'processed' => $processed,
            'errors'    => $errors,
            'message'   => empty($errors)
                ? "Se registraron {$processed} pagos correctamente."
                : "Se registraron {$processed} pagos, pero hubo errores en algunas filas.",
        ], empty($errors) ? 200 : 207);
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Cliente\CuentaCliente> $clientes
     * @return array<string, \App\Models\Cliente\CuentaCliente>
     */
    private function mapClientesByStatementAccountId(Collection $clientes): array
    {
        $mapped = [];

        foreach ($clientes as $cliente) {
            $statementAccountIds = $this->resolveStatementAccountIdsForCliente($cliente);

            foreach ($statementAccountIds as $statementAccountId) {
                if (!isset($mapped[$statementAccountId])) {
                    $mapped[$statementAccountId] = $cliente;
                }
            }
        }

        return $mapped;
    }

    /**
     * @return array<int, string>
     */
    private function resolveStatementAccountIdsForCliente(CuentaCliente $cliente): array
    {
        $ids = [];

        $adminAccountId = trim((string) ($cliente->admin_account_id ?? ''));

        if ($adminAccountId !== '' && ctype_digit($adminAccountId)) {
            $ids[] = $adminAccountId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int,string>
     */
    private function resolveCuentaClienteSelectColumns(): array
    {
        $baseColumns = [
            'id',
            'admin_account_id',
            'rfc_padre',
            'razon_social',
            'nombre_comercial',
            'email',
            'telefono',
            'plan',
            'plan_actual',
            'modo_cobro',
            'estado_cuenta',
            'activo',
            'is_blocked',
            'customer_no',
            'codigo_cliente',
            'next_invoice_date',
            'created_at',
            'updated_at',
        ];

        $existing = Schema::connection('mysql_clientes')->getColumnListing('cuentas_cliente');
        $existingMap = array_fill_keys($existing, true);

        $select = [];
        foreach ($baseColumns as $column) {
            if (isset($existingMap[$column])) {
                $select[] = $column;
            }
        }

        if (!in_array('id', $select, true)) {
            $select[] = 'id';
        }

        return $select;
    }

    private function cuentaClienteHasColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = array_fill_keys(
                Schema::connection('mysql_clientes')->getColumnListing('cuentas_cliente'),
                true
            );
        }

        return isset($columns[$column]);
    }

    private function shouldIncludeClienteInStatementsV2(
        CuentaCliente $cliente,
        Carbon $periodDate,
        Carbon $periodEnd
    ): bool {
        if ($this->cuentaClienteHasColumn('activo')) {
            $activo = $cliente->activo ?? null;

            if ($activo !== null && (string) $activo === '0') {
                return false;
            }
        }

        $estadoCuenta = strtolower(trim((string) ($cliente->estado_cuenta ?? '')));

        if (in_array($estadoCuenta, ['cancelada', 'cancelled', 'deleted', 'eliminada'], true)) {
            return false;
        }

        $billingMode = strtolower(trim((string) ($cliente->modo_cobro ?? '')));
        $isAnnual = in_array($billingMode, ['anual', 'annual', 'yearly'], true);

        if ($isAnnual) {
            $nextInvoiceDate = $cliente->next_invoice_date ?? null;

            if ($nextInvoiceDate instanceof Carbon) {
                $nextInvoiceMonth = $nextInvoiceDate->copy()->startOfMonth();
            } elseif (!empty($nextInvoiceDate)) {
                try {
                    $nextInvoiceMonth = Carbon::parse((string) $nextInvoiceDate)->startOfMonth();
                } catch (\Throwable $e) {
                    $nextInvoiceMonth = null;
                }
            } else {
                $nextInvoiceMonth = null;
            }

            if ($nextInvoiceMonth && $nextInvoiceMonth->greaterThan($periodEnd->copy()->startOfMonth())) {
                return false;
            }
        }

        return true;
    }

    private function resolveClientSequence(CuentaCliente $cliente): string
    {
        $customerNo = $cliente->customer_no ?? null;
        if ($customerNo !== null && $customerNo !== '') {
            return (string) $customerNo;
        }

        $codigoCliente = trim((string) ($cliente->codigo_cliente ?? ''));
        if ($codigoCliente !== '') {
            return $codigoCliente;
        }

        if ($cliente->admin_account_id !== null && $cliente->admin_account_id !== '') {
            return (string) $cliente->admin_account_id;
        }

        return (string) $cliente->id;
    }

    protected function resolveClientDisplayName(CuentaCliente $cliente, ?\App\Models\Cliente\UsuarioCuenta $owner = null): string
    {
        $razonSocial = trim((string) ($cliente->razon_social ?? ''));
        if ($razonSocial !== '') {
            return $razonSocial;
        }

        $nombreComercial = trim((string) ($cliente->nombre_comercial ?? ''));
        if ($nombreComercial !== '') {
            return $nombreComercial;
        }

        $ownerNombre = trim((string) ($owner->nombre ?? ''));
        if ($ownerNombre !== '') {
            return $ownerNombre;
        }

        $codigoCliente = trim((string) ($cliente->codigo_cliente ?? ''));
        if ($codigoCliente !== '') {
            return 'Cliente ' . $codigoCliente;
        }

        $customerNo = trim((string) ($cliente->customer_no ?? ''));
        if ($customerNo !== '') {
            return 'Cliente ' . $customerNo;
        }

        return 'Cliente sin nombre';
    }

    protected function syncCommercialAgreementDueDates(
    string $accountId,
    ?int $agreedDueDay,
    int $graceDays,
    ?string $effectiveFrom,
    ?string $effectiveUntil,
    string $status
): void {
    $statements = BillingStatement::query()
        ->where('account_id', $accountId)
        ->orderBy('period')
        ->get();

    foreach ($statements as $statement) {
        $period = trim((string) ($statement->period ?? ''));

        if (!$this->isValidStatementPeriod($period)) {
            continue;
        }

        if ($effectiveFrom !== null && $period < substr($effectiveFrom, 0, 7)) {
            continue;
        }

        if ($effectiveUntil !== null && $period > substr($effectiveUntil, 0, 7)) {
            continue;
        }

        $newDueDate = $this->resolveCommercialAgreementDueDate(
            period: $period,
            agreedDueDay: $agreedDueDay,
            graceDays: $graceDays,
            status: $status
        );

        $meta = $statement->meta;
        if (!is_array($meta)) {
            $meta = [];
        }

        $existingAgreementRow = DB::connection($this->adm)
            ->table('billing_commercial_agreements')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->first();

        $meta['commercial_agreement'] = [
            'applied'            => $status === 'active' && $agreedDueDay !== null,
            'agreed_due_day'     => $agreedDueDay,
            'reminders_enabled'  => $existingAgreementRow ? (bool) ($existingAgreementRow->reminders_enabled ?? true) : true,
            'grace_days'         => $graceDays,
            'effective_from'     => $effectiveFrom,
            'effective_until'    => $effectiveUntil,
            'status'             => $status,
            'notes'              => $existingAgreementRow ? (string) ($existingAgreementRow->notes ?? '') : '',
        ];

        $statement->due_date = $newDueDate;
        $statement->meta = $meta;
        $statement->save();
    }
}

protected function resolveCommercialAgreementDueDate(
    string $period,
    ?int $agreedDueDay,
    int $graceDays,
    string $status
): ?string {
    if ($status !== 'active' || $agreedDueDay === null) {
        return $this->resolveDefaultStatementDueDate($period);
    }

    try {
        $periodStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
    } catch (\Throwable $e) {
        return null;
    }

    $targetDay = min($agreedDueDay, (int) $periodStart->copy()->endOfMonth()->day);

    return $periodStart
        ->copy()
        ->day($targetDay)
        ->addDays(max(0, $graceDays))
        ->toDateString();
}

protected function resolveDefaultStatementDueDate(string $period): ?string
{
    if (!$this->isValidStatementPeriod($period)) {
        return null;
    }

    try {
        return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $period . '-01')
            ->startOfMonth()
            ->addMonthNoOverflow()
            ->day(5)
            ->toDateString();
    } catch (\Throwable $e) {
        return null;
    }
}

protected function isValidStatementPeriod(string $period): bool
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1;
}

    protected function resolveClientEmail(CuentaCliente $cliente, ?\App\Models\Cliente\UsuarioCuenta $owner = null): string
    {
        $emailCuenta = strtolower(trim((string) ($cliente->email ?? '')));
        if ($emailCuenta !== '') {
            return $emailCuenta;
        }

        $emailOwner = strtolower(trim((string) ($owner->email ?? '')));
        if ($emailOwner !== '') {
            return $emailOwner;
        }

        return '';
    }

    /**
     * @param array<int,string> $accountIds
     * @return array<string,array<string,mixed>>
     */
    private function fetchCommercialAgreementsForAccounts(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $accountIds
        ))));

        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_commercial_agreements')) {
            return [];
        }

        $rows = DB::connection($this->adm)
            ->table('billing_commercial_agreements')
            ->whereIn('account_id', $accountIds)
            ->orderByDesc('id')
            ->get();

        $mapped = [];

        foreach ($rows as $row) {
            $rowAccountId = trim((string) ($row->account_id ?? ''));
            if ($rowAccountId === '' || isset($mapped[$rowAccountId])) {
                continue;
            }

            $rowMeta = [];
            if (!empty($row->meta)) {
                try {
                    $rowMeta = json_decode((string) $row->meta, true);
                    if (!is_array($rowMeta)) {
                        $rowMeta = [];
                    }
                } catch (\Throwable $e) {
                    $rowMeta = [];
                }
            }

            $mapped[$rowAccountId] = [
                'agreed_due_day'             => isset($row->agreed_due_day) ? (int) $row->agreed_due_day : null,
                'reminders_enabled'          => isset($row->reminders_enabled) ? (bool) $row->reminders_enabled : true,
                'grace_days'                 => isset($row->grace_days) ? (int) $row->grace_days : 0,
                'effective_from'             => !empty($row->effective_from) ? (string) $row->effective_from : null,
                'effective_until'            => !empty($row->effective_until) ? (string) $row->effective_until : null,
                'apply_forward_indefinitely' => !empty($rowMeta['apply_forward_indefinitely']) || (!empty($row->effective_from) && empty($row->effective_until)),
                'status'                     => !empty($row->status) ? (string) $row->status : 'active',
                'notes'                      => !empty($row->notes) ? (string) $row->notes : '',
            ];
        }

        return $mapped;
    }

    private function resolveCommercialAgreementFromStatementMeta(array $statementMeta): ?array
    {
        $agreement = $statementMeta['commercial_agreement'] ?? null;

        if ($agreement instanceof \stdClass) {
            $agreement = (array) $agreement;
        }

        if (!is_array($agreement) || empty($agreement)) {
            return null;
        }

        $effectiveFrom = !empty($agreement['effective_from']) ? (string) $agreement['effective_from'] : null;
        $effectiveUntil = !empty($agreement['effective_until']) ? (string) $agreement['effective_until'] : null;

        return [
            'agreed_due_day'             => isset($agreement['agreed_due_day']) && $agreement['agreed_due_day'] !== null ? (int) $agreement['agreed_due_day'] : null,
            'reminders_enabled'          => array_key_exists('reminders_enabled', $agreement) ? (bool) $agreement['reminders_enabled'] : true,
            'grace_days'                 => isset($agreement['grace_days']) ? (int) $agreement['grace_days'] : 0,
            'effective_from'             => $effectiveFrom,
            'effective_until'            => $effectiveUntil,
            'apply_forward_indefinitely' => !empty($agreement['apply_forward_indefinitely']) || ($effectiveFrom !== null && $effectiveUntil === null),
            'status'                     => !empty($agreement['status']) ? (string) $agreement['status'] : 'active',
            'notes'                      => !empty($agreement['notes']) ? (string) $agreement['notes'] : '',
        ];      
    }

    protected function shouldSkipIncompleteStatementRow(
        CuentaCliente $cliente,
        string $clientName,
        string $clientRfc,
        string $clientEmail
    ): bool {
        $normalizedName = strtolower(trim($clientName));

        $hasGenericName = preg_match('/^cuenta\s+\d+$/i', $clientName) === 1
            || $normalizedName === 'cliente sin nombre'
            || $normalizedName === '';

        if (!$hasGenericName) {
            return false;
        }

        if (trim($clientRfc) !== '' || trim($clientEmail) !== '') {
            return false;
        }

        return true;
    }

    private function resolveLicenseLabel(CuentaCliente $cliente): string
    {
        $planActual = trim((string) ($cliente->plan_actual ?? ''));
        $plan = trim((string) ($cliente->plan ?? ''));

        return $planActual !== ''
            ? mb_strtoupper($planActual)
            : ($plan !== '' ? mb_strtoupper($plan) : 'SIN LICENCIA');
    }

    private function resolveBillingMode(CuentaCliente $cliente): string
    {
        $modo = strtolower(trim((string) ($cliente->modo_cobro ?? '')));

        return match ($modo) {
            'anual', 'annual', 'yearly'   => 'Anual',
            'mensual', 'monthly', 'month' => 'Mensual',
            default                       => 'Sin definir',
        };
    }

    private function normalizeStatementStatus(?BillingStatement $statement): string
    {
        if (!$statement) {
            return 'pendiente';
        }

        $status = strtolower(trim((string) $statement->status));
        $saldo = (float) $statement->saldo;

        if (in_array($status, ['paid', 'pagado'], true)) {
            return 'pagado';
        }

        if (in_array($status, ['partial', 'parcial'], true)) {
            return 'parcial';
        }

        if (in_array($status, ['sin_mov', 'sin movimiento', 'no_movement'], true)) {
            return 'sin_mov';
        }

        if (in_array($status, ['overdue', 'vencido', 'late'], true)) {
            return 'vencido';
        }

        if ($saldo <= 0.00001) {
            return 'pagado';
        }

        if ($statement->due_date) {
            try {
                if (Carbon::parse($statement->due_date)->isPast()) {
                    return 'vencido';
                }
            } catch (\Throwable $e) {
            }
        }

        $statementPeriod = trim((string) ($statement->period ?? ''));
        if ($this->isValidPeriod($statementPeriod)) {
            try {
                $cutoff = Carbon::createFromFormat('Y-m', $statementPeriod)
                    ->startOfMonth()
                    ->addDays(4)
                    ->endOfDay();

                if (now()->gt($cutoff)) {
                    return 'vencido';
                }
            } catch (\Throwable $e) {
            }
        }

        return 'pendiente';
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Admin\Billing\BillingStatement> $history
     */
    private function resolveLastPaidDateFromHistory(Collection $history): ?string
    {
        /** @var \App\Models\Admin\Billing\BillingStatement|null $lastPaid */
        $lastPaid = $history
            ->filter(function (BillingStatement $statement): bool {
                $status = strtolower(trim((string) $statement->status));

                return in_array($status, ['paid', 'pagado'], true) || $statement->paid_at !== null;
            })
            ->sortByDesc(function (BillingStatement $statement) {
                return $statement->paid_at
                    ? Carbon::parse($statement->paid_at)->timestamp
                    : 0;
            })
            ->first();

        if (!$lastPaid) {
            return null;
        }

        return $lastPaid->paid_at
            ? Carbon::parse($lastPaid->paid_at)->toDateString()
            : null;
    }

    private function formatPeriodLabel(?string $period): string
    {
        $period = trim((string) $period);

        if (!$this->isValidPeriod($period)) {
            return $period;
        }

        try {
            return Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y');
        } catch (\Throwable $e) {
            return $period;
        }
    }

    private function isValidPeriod(?string $period): bool
    {
        return is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1;
    }

    private function buildStatementPreviewUrl(string $accountId, string $period): string
    {
        if (Route::has('admin.billing.statements_v2.preview')) {
            return route('admin.billing.statements_v2.preview', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        }

        return '#';
    }

    private function buildStatementDownloadUrl(string $accountId, string $period): string
    {
        if (Route::has('admin.billing.statements_v2.download')) {
            return route('admin.billing.statements_v2.download', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        }

        return '#';
    }

    private function buildStatementEmailPreviewUrl(string $accountId, string $period): string
    {
        if (Route::has('admin.billing.statements_v2.email.preview')) {
            return route('admin.billing.statements_v2.email.preview', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        }

        return '#';
    }

    private function buildStatementEmailSendUrl(string $accountId, string $period): string
    {
        if (Route::has('admin.billing.statements_v2.email.send')) {
            return route('admin.billing.statements_v2.email.send', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        }

        return '#';
    }

    private function buildStatementStatusUpdateUrl(string $accountId, string $period): string
    {
        if (Route::has('admin.billing.statements_v2.status.update')) {
            return route('admin.billing.statements_v2.status.update', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        }

        return '#';
    }

    private function resolveStatementComparableDate(object $statement, string $period): ?Carbon
    {
        $statementPeriod = trim((string) ($statement->period ?? $period));

        if ($this->isValidPeriod($statementPeriod)) {
            try {
                return Carbon::createFromFormat('Y-m', $statementPeriod)->startOfMonth();
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    private function findStatementRow(string $accountId, string $period): ?object
    {
        $period = trim($period);
        $accountId = trim($accountId);

        if ($accountId === '' || !$this->isValidPeriod($period)) {
            return null;
        }

        /** @var BillingStatement|null $statement */
        $statement = BillingStatement::query()
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (!$statement) {
            return null;
        }

        $cliente = CuentaCliente::query()
            ->with(['owner'])
            ->select($this->resolveCuentaClienteSelectColumns())
            ->where(function ($query) use ($accountId) {
                $query->where('id', $accountId);

                if ($this->cuentaClienteHasColumn('admin_account_id')) {
                    $query->orWhere('admin_account_id', $accountId);
                }
            })
            ->first();

        $owner = $cliente instanceof CuentaCliente ? $cliente->owner : null;

        $clientName = $cliente instanceof CuentaCliente
            ? $this->resolveClientDisplayName($cliente, $owner)
            : 'Cliente sin nombre';

        $clientEmail = $cliente instanceof CuentaCliente
            ? $this->resolveClientEmail($cliente, $owner)
            : '';

        if ($clientEmail === '' && ctype_digit($accountId) && Schema::connection($this->adm)->hasTable('accounts')) {
            try {
                $fallbackEmail = DB::connection($this->adm)
                    ->table('accounts')
                    ->where('id', (int) $accountId)
                    ->value('email');

                $fallbackEmail = strtolower(trim((string) $fallbackEmail));

                if ($fallbackEmail !== '' && filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
                    $clientEmail = $fallbackEmail;
                }
            } catch (\Throwable $e) {
                //
            }
        }
        return (object) [
            'account_id'    => $accountId,
            'period'        => $period,
            'period_label'  => $this->formatPeriodLabel($period),
            'client_name'   => $clientName,
            'client_email'  => $clientEmail,
            'statement_id'  => $statement->id,
            'total_cargo'   => round((float) ($statement->total_cargo ?? 0), 2),
            'total_abono'   => round((float) ($statement->total_abono ?? 0), 2),
            'saldo'         => round((float) ($statement->saldo ?? 0), 2),
            'status'        => strtolower(trim((string) ($statement->status ?? 'pending'))),
            'paid_at'       => $statement->paid_at,
            'sent_at'       => $statement->sent_at,

            'commercial_agreement' => $this->resolveCommercialAgreementFromStatementMeta(
                is_array($statement->meta) ? $statement->meta : []
            ),
        ];
        
    }

    /**
     * @return array<int,string>
     */
    private function normalizeRecipientList(?string $to): array
    {
        $to = trim((string) $to);
        if ($to === '') {
            return [];
        }

        $to = str_replace([';', "\n", "\r", "\t"], [',', ',', ',', ' '], $to);
        $parts = array_filter(array_map('trim', explode(',', $to)));

        $out = [];
        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    private function resolveEmailSubject(object $statement, string $period): string
    {
        $clientName = trim((string) ($statement->client_name ?? 'Cliente'));
        $periodLabel = trim((string) ($statement->period_label ?? $this->formatPeriodLabel($period)));

        return 'Pactopia360 · Estado de cuenta ' . $periodLabel . ' · ' . $clientName;
    }

    private function resolveEmailBody(object $statement, string $period): string
    {
        $clientName = trim((string) ($statement->client_name ?? 'Cliente'));
        $periodLabel = trim((string) ($statement->period_label ?? $this->formatPeriodLabel($period)));

        return "Hola {$clientName},\n\nTu estado de cuenta correspondiente a {$periodLabel} ya está listo para revisión y pago.\n\nPuedes revisar la información y descargar el documento desde los enlaces incluidos en este correo.\n\nSaludos,\nEquipo Pactopia360";
    }

    /**
     * @return array<int,string>
     */
    private function resolveDefaultRecipientsForStatement(object $statement): array
    {
        $emails = [];

        $clientEmail = strtolower(trim((string) ($statement->client_email ?? '')));
        if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $clientEmail;
        }

        $accountId = trim((string) ($statement->account_id ?? ''));

        if ($accountId !== '' && ctype_digit($accountId) && Schema::connection($this->adm)->hasTable('accounts')) {
            try {
                $accountEmail = DB::connection($this->adm)
                    ->table('accounts')
                    ->where('id', (int) $accountId)
                    ->value('email');

                $accountEmail = strtolower(trim((string) $accountEmail));

                if ($accountEmail !== '' && filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $accountEmail;
                }
            } catch (\Throwable $e) {
                //
            }
        }

        return array_values(array_unique($emails));
    }

    private function renderEmailHtml(
        object $statement,
        string $subject,
        string $message,
        ?string $downloadUrl,
        ?string $previewUrl
    ): string {
        $clientName = e((string) ($statement->client_name ?? 'Cliente'));
        $periodLabel = e((string) ($statement->period_label ?? $statement->period ?? ''));
        $saldo = number_format((float) ($statement->saldo ?? 0), 2);
        $total = number_format((float) ($statement->total_cargo ?? 0), 2);
        $messageHtml = nl2br(e($message));

        $downloadButton = '';
        if ($downloadUrl) {
            $downloadButton = '<a href="' . e($downloadUrl) . '" style="display:inline-block;padding:12px 18px;border-radius:12px;background:#2f6df6;color:#ffffff;text-decoration:none;font-weight:700;">Descargar estado de cuenta</a>';
        }

        $previewButton = '';
        if ($previewUrl) {
            $previewButton = '<a href="' . e($previewUrl) . '" style="display:inline-block;padding:12px 18px;border-radius:12px;background:#eaf1ff;color:#2f6df6;text-decoration:none;font-weight:700;border:1px solid #d7e4ff;">Ver vista previa</a>';
        }

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#132238;">
    <div style="max-width:720px;margin:0 auto;padding:28px 18px;">
        <div style="background:linear-gradient(135deg,#15356c 0%,#234b92 55%,#5f90f6 100%);border-radius:20px;padding:26px 24px;color:#ffffff;">
            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;opacity:.85;">PACTOPIA360</div>
            <h1 style="margin:10px 0 0;font-size:30px;line-height:1.05;">Estado de cuenta</h1>
            <p style="margin:10px 0 0;font-size:14px;line-height:1.6;opacity:.92;">{$periodLabel}</p>
        </div>

        <div style="background:#ffffff;border:1px solid #e3ebf5;border-radius:20px;padding:24px;margin-top:18px;">
            <p style="margin:0 0 12px;font-size:14px;color:#6d7f96;">Cliente</p>
            <p style="margin:0 0 18px;font-size:22px;font-weight:800;color:#132238;">{$clientName}</p>

            <div style="margin:0 0 18px;font-size:15px;line-height:1.75;color:#132238;">{$messageHtml}</div>

            <div style="display:flex;flex-wrap:wrap;gap:12px;margin:20px 0 22px;">
                {$downloadButton}
                {$previewButton}
            </div>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:10px;">
                <div style="border:1px solid #e3ebf5;border-radius:14px;padding:14px 16px;background:#fbfdff;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#91a0b5;font-weight:800;margin-bottom:6px;">Total del período</div>
                    <div style="font-size:20px;font-weight:900;color:#132238;">$ {$total}</div>
                </div>
                <div style="border:1px solid #e3ebf5;border-radius:14px;padding:14px 16px;background:#fbfdff;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#91a0b5;font-weight:800;margin-bottom:6px;">Saldo actual</div>
                    <div style="font-size:20px;font-weight:900;color:#2f6df6;">$ {$saldo}</div>
                </div>
            </div>
        </div>

        <div style="padding:14px 6px 0;color:#7b8ca4;font-size:12px;line-height:1.7;">
            Este correo fue generado desde el módulo de estados de cuenta V2 de Pactopia360.
        </div>
    </div>
</body>
</html>
HTML;
    }

        /**
     * @return array{0: \Illuminate\Support\Collection<int, object>, 1:int}
     */
    private function resolveStatementsForFilters(Request $request, bool $applyPerPage = true): array
    {
        $clone = Request::create(
            $request->url(),
            'GET',
            array_merge($request->query(), $request->request->all())
        );

        $view = $this->index($clone);
        $data = $view->getData();

        /** @var \Illuminate\Support\Collection<int, object> $statements */
        $statements = collect($data['statements'] ?? []);
        $totalFiltered = (int) ($data['totalFiltered'] ?? $statements->count());

        if (!$applyPerPage && isset($data['perPage'])) {
            $period = trim((string) $clone->input('period', now()->format('Y-m')));
            $search = trim((string) $clone->input('search', ''));
            $dateFrom = trim((string) $clone->input('date_from', ''));
            $dateTo = trim((string) $clone->input('date_to', ''));
            $status = trim((string) $clone->input('status', ''));
            $scope = trim((string) $clone->input('scope', ''));
            $selected = (array) $clone->input('selected_ids', []);

            $rebuildRequest = Request::create(
                $request->url(),
                'GET',
                [
                    'period'       => $period,
                    'search'       => $search,
                    'date_from'    => $dateFrom,
                    'date_to'      => $dateTo,
                    'status'       => $status,
                    'scope'        => $scope,
                    'selected_ids' => $selected,
                    'per_page'     => 1000,
                ]
            );

            $view = $this->index($rebuildRequest);
            $data = $view->getData();
            $statements = collect($data['statements'] ?? []);
            $totalFiltered = (int) ($data['totalFiltered'] ?? $statements->count());
        }

        return [$statements, $totalFiltered];
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function upsertAdvancePaymentMeta(string $accountId, string $period, array $extra): void
    {
        $statement = BillingStatement::query()
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (!$statement) {
            return;
        }

        $meta = $statement->meta;
        if ($meta instanceof \stdClass) {
            $meta = (array) $meta;
        }
        if (!is_array($meta)) {
            $meta = [];
        }

        $meta['advance_payment'] = array_filter(
            $extra,
            static fn ($value) => $value !== null && $value !== ''
        );

        $statement->meta = $meta;
        $statement->updated_at = now();
        $statement->save();
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function upsertV2PaymentExtras(string $accountId, string $period, array $extra): void
    {
        $table = $this->overrideTable();

        if (!Schema::connection($this->adm)->hasTable($table)) {
            return;
        }

        $columns = $this->getOverrideTableColumns();
        if (!isset($columns['account_id'], $columns['period'], $columns['status_override'])) {
            return;
        }

        $existing = DB::connection($this->adm)->table($table)
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        if (!$existing) {
            return;
        }

        $payload = [
            'updated_at' => now(),
        ];

        $meta = [];
        if (isset($columns['meta'])) {
            try {
                $meta = json_decode((string) ($existing->meta ?? ''), true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            } catch (\Throwable $e) {
                $meta = [];
            }

            $meta['v2_payment_data'] = array_filter($extra, static fn ($value) => $value !== null && $value !== '');
            $payload['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (isset($columns['updated_by'])) {
            $payload['updated_by'] = auth('admin')->id();
        }

        DB::connection($this->adm)->table($table)
            ->where('id', (int) $existing->id)
            ->update($payload);
    }

    /**
     * @return array<string,bool>
     */
    private function getOverrideTableColumns(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $table = $this->overrideTable();
        if (!Schema::connection($this->adm)->hasTable($table)) {
            $cache = [];
            return $cache;
        }

        $columns = Schema::connection($this->adm)->getColumnListing($table);
        $cache = array_fill_keys(array_map('strtolower', $columns), true);

        return $cache;
    }

    private function markStatementAsSentIfPossible(string $accountId, string $period): void
    {
        try {
            BillingStatement::query()
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->update([
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
        }
    }

    private function resolveCutoffAmountForCliente(CuentaCliente $cliente, string $period): float
    {
        $adminAccountId = trim((string) ($cliente->admin_account_id ?? ''));

        if ($adminAccountId !== '' && Schema::connection($this->adm)->hasTable('accounts')) {
            $account = DB::connection($this->adm)
                ->table('accounts')
                ->where('id', $adminAccountId)
                ->first();

            if ($account) {
                $meta = [];

                if (!empty($account->meta)) {
                    try {
                        $decoded = json_decode((string) $account->meta, true);
                        $meta = is_array($decoded) ? $decoded : [];
                    } catch (\Throwable $e) {
                        $meta = [];
                    }
                }

                foreach ([
                    data_get($meta, 'billing.override.amount_mxn'),
                    data_get($meta, 'billing.override_amount_mxn'),
                    data_get($meta, 'billing.amount_mxn'),
                    data_get($meta, 'billing.amount'),
                    data_get($meta, 'billing.custom.amount_mxn'),
                    data_get($meta, 'billing.custom_mxn'),
                ] as $value) {
                    if (is_numeric($value) && (float) $value > 0) {
                        return round((float) $value, 2);
                    }
                }

                foreach ([
                    'override_amount_mxn',
                    'custom_amount_mxn',
                    'billing_amount_mxn',
                    'amount_mxn',
                    'precio_mxn',
                    'monto_mxn',
                    'license_amount_mxn',
                    'billing_amount',
                    'amount',
                    'precio',
                    'monto',
                ] as $column) {
                    if (isset($account->{$column}) && is_numeric($account->{$column}) && (float) $account->{$column} > 0) {
                        return round((float) $account->{$column}, 2);
                    }
                }

                try {
                    $hub = app(BillingStatementsHubController::class);
                    [$amount] = $hub->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);

                    if (is_numeric($amount) && (float) $amount > 0) {
                        return round((float) $amount, 2);
                    }
                } catch (\Throwable $e) {
                    //
                }
            }
        }

        return 0.0;
    }

    private function generateCutoffRowsForPeriod(string $period, bool $force = false): array
{
    $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
    $periodEnd = $periodDate->copy()->endOfMonth();

    $clientes = CuentaCliente::query()
        ->with(['owner'])
        ->select($this->resolveCuentaClienteSelectColumns())
        ->where(function ($query) use ($periodEnd) {
            if ($this->cuentaClienteHasColumn('created_at')) {
                $query->whereNull('created_at')
                    ->orWhere('created_at', '<=', $periodEnd);
            }
        })
        ->orderByRaw('COALESCE(NULLIF(razon_social, ""), NULLIF(nombre_comercial, ""), id) asc')
        ->get()
        ->filter(fn (CuentaCliente $cliente) => $this->shouldIncludeClienteInStatementsV2($cliente, $periodDate, $periodEnd))
        ->values();

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($clientes as $cliente) {
        $accountIds = $this->resolveStatementAccountIdsForCliente($cliente);
        $accountId = (string) ($accountIds[0] ?? '');

        if ($accountId === '' || !ctype_digit($accountId)) {
            $skipped++;
            continue;
        }

        $existing = BillingStatement::query()
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->orderByDesc('id')
            ->first();

        if ($existing && !$force) {
            $skipped++;
            continue;
        }

        if ($existing && !$force && (bool) ($existing->is_locked ?? false)) {
            $skipped++;
            continue;
        }

        $owner = $cliente->owner;
        $clientName = $this->resolveClientDisplayName($cliente, $owner);
        $clientEmail = $this->resolveClientEmail($cliente, $owner);
        $clientRfc = strtoupper(trim((string) ($cliente->rfc_padre ?? '')));

        $amount = round(max(0.0, $this->resolveCutoffAmountForCliente($cliente, $period)), 2);
        $status = $amount > 0.00001 ? 'pending' : 'sin_mov';
        $dueDate = $this->resolveDefaultStatementDueDate($period);

        DB::connection($this->adm)->transaction(function () use (
            $existing,
            $cliente,
            $accountId,
            $period,
            $amount,
            $status,
            $dueDate,
            $clientName,
            $clientEmail,
            $clientRfc,
            &$created,
            &$updated
        ) {
            $payload = [
                'account_id'  => $accountId,
                'period'      => $period,
                'total_cargo' => $amount,
                'total_abono' => 0,
                'saldo'       => $amount,
                'status'      => $status,
                'due_date'    => $dueDate,
                'snapshot'    => [
                    'source'           => 'statements_v2_generate_cutoff',
                    'generated_at'     => now()->toDateTimeString(),
                    'generated_by'     => auth('admin')->id(),
                    'client_id'        => (string) $cliente->id,
                    'admin_account_id' => $cliente->admin_account_id !== null ? (string) $cliente->admin_account_id : null,
                    'client_name'      => $clientName,
                    'client_email'     => $clientEmail,
                    'client_rfc'       => $clientRfc,
                    'billing_mode'     => $this->resolveBillingMode($cliente),
                    'license_type'     => $this->resolveLicenseLabel($cliente),
                    'period'           => $period,
                    'amount'           => $amount,
                ],
                'meta' => [
                    'source'       => 'statements_v2_generate_cutoff',
                    'generated_at' => now()->toDateTimeString(),
                    'generated_by' => auth('admin')->id(),
                ],
                'is_locked' => false,
            ];

            if ($existing) {
                $existing->fill($payload);
                $existing->save();
                $statement = $existing;
                $updated++;
            } else {
                $statement = BillingStatement::query()->create($payload);
                $created++;
            }

            BillingStatementItem::query()
                ->where('statement_id', $statement->id)
                ->where('code', 'BASE_SERVICE')
                ->delete();

            if ($amount > 0.00001) {
                BillingStatementItem::query()->create([
                    'statement_id' => $statement->id,
                    'type'         => 'charge',
                    'code'         => 'BASE_SERVICE',
                    'description'  => $this->resolveBillingMode($cliente) === 'Anual'
                        ? 'Servicio anual Pactopia360'
                        : 'Servicio mensual Pactopia360',
                    'qty'          => 1,
                    'unit_price'   => $amount,
                    'amount'       => $amount,
                    'ref'          => $period,
                    'meta'         => [
                        'source'    => 'statements_v2_generate_cutoff',
                        'client_id' => (string) $cliente->id,
                        'period'    => $period,
                    ],
                ]);
            }
        });
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
    ];
}


private function billingBccEmail(): ?string
{
    $email = strtolower(trim((string) config('p360.billing_bcc_email', 'notificaciones@pactopia.com')));

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}
}