<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class BillingStatementEmailsController extends Controller
{
    private string $adm;

    private const BCC_MONITOR = 'notificaciones@pactopia.com';

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));

        // =========================================================
        // Filtros NUEVOS por fecha real (UI calendario)
        // =========================================================
        $dateFrom = $this->normalizeDateInput((string) $req->get('date_from', ''));
        $dateTo   = $this->normalizeDateInput((string) $req->get('date_to', ''));

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // =========================================================
        // Compat con filtros viejos por periodo
        // =========================================================
        $periodFrom = trim((string) $req->get('period_from', ''));
        $periodTo   = trim((string) $req->get('period_to', ''));

        if (!$this->isValidPeriod($periodFrom)) {
            $periodFrom = '';
        }

        if (!$this->isValidPeriod($periodTo)) {
            $periodTo = '';
        }

        if ($periodFrom !== '' && $periodTo !== '' && strcmp($periodFrom, $periodTo) > 0) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $accountId = trim((string) $req->get('accountId', ''));
        $status    = strtolower(trim((string) $req->get('status', 'all')));

        $allowedStatus = ['all', 'queued', 'sent', 'failed', 'opened', 'clicked'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
        }

        $perPage = (int) $req->get('perPage', 25);
        if (!in_array($perPage, [10, 25, 50, 100, 200], true)) {
            $perPage = 25;
        }

        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return view('admin.billing.statement_emails.index', [
                'rows'        => collect(),
                'q'           => $q,
                'dateFrom'    => null,
                'dateTo'      => null,
                'periodFrom'  => $periodFrom,
                'periodTo'    => $periodTo,
                'accountId'   => $accountId,
                'status'      => $status,
                'perPage'     => $perPage,
                'kpis'        => $this->emptyKpis(),
                'error'       => 'No existe la tabla billing_email_logs en la conexión admin.',
                'bccMonitor'  => self::BCC_MONITOR,
            ]);
        }

        $cols = $this->tableColumns('billing_email_logs');
        $has  = static fn (string $c): bool => in_array(strtolower($c), $cols, true);

        $recipientExpr = $has('email')
            ? 'l.email'
            : ($has('to') ? 'l.to' : "''");

        $errorExpr = $has('error')
            ? 'l.error'
            : "NULL";

        $payProviderExpr = $has('pay_provider')
            ? 'l.pay_provider'
            : "NULL";

        $paySessionExpr = $has('pay_session_id')
            ? 'l.pay_session_id'
            : "NULL";

        $statementIdExpr = $has('statement_id')
            ? 'l.statement_id'
            : "NULL";

        $firstOpenExpr = $has('first_open_at')
            ? 'l.first_open_at'
            : ($has('first_opened_at') ? 'l.first_opened_at' : "NULL");

        $lastOpenExpr = $has('last_open_at')
            ? 'l.last_open_at'
            : ($has('last_opened_at') ? 'l.last_opened_at' : "NULL");

        $firstClickExpr = $has('first_click_at')
            ? 'l.first_click_at'
            : ($has('first_clicked_at') ? 'l.first_clicked_at' : "NULL");

        $lastClickExpr = $has('last_click_at')
            ? 'l.last_click_at'
            : ($has('last_clicked_at') ? 'l.last_clicked_at' : "NULL");

        $activityExpr = $this->resolveActivityExpr($has);

        $applyFilters = function ($query) use (
            $q,
            $dateFrom,
            $dateTo,
            $periodFrom,
            $periodTo,
            $accountId,
            $status,
            $has,
            $recipientExpr,
            $activityExpr
        ) {
            if ($q !== '') {
                $query->where(function ($w) use ($q, $has, $recipientExpr) {
                    if ($has('subject')) {
                        $w->orWhere('l.subject', 'like', "%{$q}%");
                    }

                    if ($has('to_list')) {
                        $w->orWhere('l.to_list', 'like', "%{$q}%");
                    }

                    if ($has('email')) {
                        $w->orWhere('l.email', 'like', "%{$q}%");
                    }

                    if ($has('to')) {
                        $w->orWhere('l.to', 'like', "%{$q}%");
                    }

                    if ($has('email_id')) {
                        $w->orWhere('l.email_id', 'like', "%{$q}%");
                    }

                    if ($has('account_id')) {
                        $w->orWhere('l.account_id', 'like', "%{$q}%");
                    }

                    if ($has('provider_message_id')) {
                        $w->orWhere('l.provider_message_id', 'like', "%{$q}%");
                    }

                    $w->orWhereRaw("{$recipientExpr} like ?", ["%{$q}%"]);
                });
            }

            if ($periodFrom !== '' && $has('period')) {
                $query->where('l.period', '>=', $periodFrom);
            }

            if ($periodTo !== '' && $has('period')) {
                $query->where('l.period', '<=', $periodTo);
            }

            if ($dateFrom !== null) {
                $query->whereRaw("DATE({$activityExpr}) >= ?", [$dateFrom->toDateString()]);
            }

            if ($dateTo !== null) {
                $query->whereRaw("DATE({$activityExpr}) <= ?", [$dateTo->toDateString()]);
            }

            if ($accountId !== '' && $has('account_id')) {
                $query->where('l.account_id', $accountId);
            }

            if ($status === 'queued' && $has('status')) {
                $query->where('l.status', 'queued');
            } elseif ($status === 'sent' && $has('status')) {
                $query->where('l.status', 'sent');
            } elseif ($status === 'failed' && $has('status')) {
                $query->where('l.status', 'failed');
            } elseif ($status === 'opened' && $has('open_count')) {
                $query->where('l.open_count', '>', 0);
            } elseif ($status === 'clicked' && $has('click_count')) {
                $query->where('l.click_count', '>', 0);
            }

            return $query;
        };

        $listQuery = DB::connection($this->adm)->table('billing_email_logs as l')
            ->selectRaw("
                l.id,
                " . ($has('email_id') ? "l.email_id" : "NULL as email_id") . ",
                " . ($has('account_id') ? "l.account_id" : "NULL as account_id") . ",
                " . ($has('period') ? "l.period" : "NULL as period") . ",
                {$recipientExpr} as recipient_email,
                " . ($has('to_list') ? "l.to_list" : "NULL as to_list") . ",
                " . ($has('subject') ? "l.subject" : "NULL as subject") . ",
                " . ($has('template') ? "l.template" : "NULL as template") . ",
                " . ($has('status') ? "l.status" : "NULL as status") . ",
                {$errorExpr} as error_text,
                " . ($has('sent_at') ? "l.sent_at" : "NULL as sent_at") . ",
                " . ($has('queued_at') ? "l.queued_at" : "NULL as queued_at") . ",
                " . ($has('failed_at') ? "l.failed_at" : "NULL as failed_at") . ",
                " . ($has('open_count') ? "l.open_count" : "0 as open_count") . ",
                " . ($has('click_count') ? "l.click_count" : "0 as click_count") . ",
                {$payProviderExpr} as pay_provider,
                {$paySessionExpr} as pay_session_id,
                {$statementIdExpr} as statement_id,
                " . ($has('email') ? "l.email" : "NULL as email") . ",
                " . ($has('provider') ? "l.provider" : "NULL as provider") . ",
                " . ($has('provider_message_id') ? "l.provider_message_id" : "NULL as provider_message_id") . ",
                " . ($has('payload') ? "l.payload" : "NULL as payload") . ",
                " . ($has('meta') ? "l.meta" : "NULL as meta") . ",
                " . ($has('created_at') ? "l.created_at" : "NULL as created_at") . ",
                " . ($has('updated_at') ? "l.updated_at" : "NULL as updated_at") . ",
                {$firstOpenExpr} as first_open_any,
                {$lastOpenExpr} as last_open_any,
                {$firstClickExpr} as first_click_any,
                {$lastClickExpr} as last_click_any,
                {$activityExpr} as activity_at
            ");

        $applyFilters($listQuery);

        $summaryQuery = DB::connection($this->adm)->table('billing_email_logs as l')
            ->selectRaw("
                COUNT(*) as total_rows,
                SUM(CASE WHEN " . ($has('status') ? "l.status = 'sent'" : "0=1") . " THEN 1 ELSE 0 END) as sent_rows,
                SUM(CASE WHEN " . ($has('status') ? "l.status = 'queued'" : "0=1") . " THEN 1 ELSE 0 END) as queued_rows,
                SUM(CASE WHEN " . ($has('status') ? "l.status = 'failed'" : "0=1") . " THEN 1 ELSE 0 END) as failed_rows,
                SUM(CASE WHEN " . ($has('open_count') ? "COALESCE(l.open_count,0) > 0" : "0=1") . " THEN 1 ELSE 0 END) as opened_rows,
                SUM(CASE WHEN " . ($has('click_count') ? "COALESCE(l.click_count,0) > 0" : "0=1") . " THEN 1 ELSE 0 END) as clicked_rows,
                MAX({$activityExpr}) as last_activity_at
            ");

        $applyFilters($summaryQuery);
        $summary = $summaryQuery->first();

        $currentMonth = now()->format('Y-m');

        $firstDayStart = now()->startOfMonth()->copy()->startOfDay();
        $firstDayEnd   = now()->startOfMonth()->copy()->endOfDay();

        $firstDaySentCount = 0;
        $currentMonthSentCount = 0;

        if ($has('period') && $has('status') && $has('sent_at')) {
            $firstDaySentCount = DB::connection($this->adm)->table('billing_email_logs')
                ->where('period', $currentMonth)
                ->where('status', 'sent')
                ->whereBetween('sent_at', [$firstDayStart, $firstDayEnd])
                ->count();

            $currentMonthSentCount = DB::connection($this->adm)->table('billing_email_logs')
                ->where('period', $currentMonth)
                ->where('status', 'sent')
                ->count();
        }

        $rows = $listQuery
            ->orderByDesc(DB::raw($activityExpr))
            ->orderByDesc('l.id')
            ->paginate($perPage)
            ->withQueryString();

        $rows->getCollection()->transform(function (object $row): object {
            $meta = $this->decodeJson($row->meta ?? null);

            $row->ui_to = (string) ($row->recipient_email ?? '');
            $row->ui_error = $this->extractUiError($row, $meta);

            return $row;
        });

        $kpis = [
            'total'               => (int) ($summary->total_rows ?? 0),
            'sent'                => (int) ($summary->sent_rows ?? 0),
            'queued'              => (int) ($summary->queued_rows ?? 0),
            'failed'              => (int) ($summary->failed_rows ?? 0),
            'opened'              => (int) ($summary->opened_rows ?? 0),
            'clicked'             => (int) ($summary->clicked_rows ?? 0),
            'last_activity_at'    => $summary->last_activity_at ?? null,
            'first_day_sent'      => (int) $firstDaySentCount,
            'current_month_sent'  => (int) $currentMonthSentCount,
            'current_month'       => $currentMonth,
        ];

        return view('admin.billing.statement_emails.index', [
            'rows'        => $rows,
            'q'           => $q,
            'dateFrom'    => $dateFrom?->toDateString(),
            'dateTo'      => $dateTo?->toDateString(),
            'periodFrom'  => $periodFrom,
            'periodTo'    => $periodTo,
            'accountId'   => $accountId,
            'status'      => $status,
            'perPage'     => $perPage,
            'kpis'        => $kpis,
            'error'       => null,
            'bccMonitor'  => self::BCC_MONITOR,
        ]);
    }

    public function show(int $id): View
    {
        $row = $this->findLogOrFail($id);

        $payload = $this->decodeJson($row->payload ?? null);
        $meta    = $this->decodeJson($row->meta ?? null);

        $row->ui_to = (string) ($row->email ?? '');
        $row->ui_error = $this->extractUiError($row, $meta);

        return view('admin.billing.statement_emails.show', [
            'row'        => $row,
            'payload'    => $payload,
            'meta'       => $meta,
            'bccMonitor' => self::BCC_MONITOR,
        ]);
    }

    public function preview(int $id): Response
    {
        $row = $this->findLogOrFail($id);

        $payload  = $this->decodeJson($row->payload ?? null);
        $template = $this->resolveTemplateView((string) ($row->template ?? ''));

        if (empty($payload)) {
            $payload = $this->buildFallbackPreviewPayload($row);
        } else {
            $payload = $this->normalizePreviewPayload($row, $payload);
        }

        if (!view()->exists($template)) {
            $template = 'emails.admin.billing.statement_account_period';
        }

        $html = view($template, $payload)->render();

        return response($html, 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function resend(Request $req, int $id): RedirectResponse
    {
        $row = $this->findLogOrFail($id);

        $payload = $this->decodeJson($row->payload ?? null);
        if (empty($payload)) {
            return back()->withErrors(['email' => 'Este log no tiene payload para reenvío.']);
        }

        $accountId = trim((string) ($row->account_id ?? ''));
        $period    = trim((string) ($row->period ?? ''));

        if ($accountId === '' || $period === '') {
            return back()->withErrors(['email' => 'El log no tiene account_id/period válidos.']);
        }

        $manualRecipients = trim((string) $req->input('recipients', ''));
        $tos = $this->parseToList($manualRecipients);

        if (empty($tos)) {
            $tos = $this->parseToList((string) ($row->to_list ?? $row->email ?? ''));
        }

        if (empty($tos)) {
            return back()->withErrors(['email' => 'Debes indicar al menos un correo válido para reenviar.']);
        }

        $newEmailId = (string) Str::ulid();
        $oldEmailId = trim((string) ($row->email_id ?? ''));

        $payload = $this->refreshPayloadForResend($payload, $newEmailId, $oldEmailId);
        $payload['subject_override'] = (string) ($row->subject ?? '');
        $payload['email_id'] = $newEmailId;

        $meta = $this->decodeJson($row->meta ?? null);
        $newMeta = array_merge($meta, [
            'source'            => 'statement_emails_resend',
            'resent_from_id'    => (int) $row->id,
            'bcc_monitor'       => self::BCC_MONITOR,
            'manual_recipients' => implode(',', $tos),
        ]);

        $newLogId = 0;

        try {
            $newLogId = $this->insertEmailLog([
                'email_id'            => $newEmailId,
                'account_id'          => $accountId,
                'period'              => $period,
                'email'               => $tos[0] ?? null,
                'to_list'             => implode(',', $tos),
                'subject'             => (string) ($row->subject ?? 'Pactopia360 · Estado de cuenta'),
                'template'            => (string) ($row->template ?? 'emails.admin.billing.statement_account_period'),
                'status'              => 'queued',
                'payload'             => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'provider'            => (string) ($row->provider ?? (config('mail.default') ?: 'smtp')),
                'provider_message_id' => null,
                'meta'                => json_encode($newMeta, JSON_UNESCAPED_UNICODE),
                'queued_at'           => now(),
                'statement_id'        => $row->statement_id ?? null,
            ]);

            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')
                ->where('id', $newLogId)
                ->update([
                    'status'     => 'sent',
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ]);

            return back()->with('ok', 'Correo reenviado correctamente a: ' . implode(', ', $tos));
        } catch (\Throwable $e) {
            if ($newLogId > 0) {
                $failedMeta = $this->mergeMetaJson(
                    $newMeta,
                    [
                        'error'         => $e->getMessage(),
                        'error_at'      => now()->toDateTimeString(),
                        'error_context' => 'statement_emails_resend',
                    ]
                );

                DB::connection($this->adm)->table('billing_email_logs')
                    ->where('id', $newLogId)
                    ->update([
                        'status'     => 'failed',
                        'failed_at'  => now(),
                        'meta'       => $failedMeta,
                        'updated_at' => now(),
                    ]);
            }

            Log::error('[BILLING_STATEMENT_EMAILS][resend] fallo', [
                'log_id'      => $id,
                'new_log_id'  => $newLogId,
                'account_id'  => $accountId,
                'period'      => $period,
                'recipients'  => $tos,
                'error'       => $e->getMessage(),
            ]);

            return back()->withErrors(['email' => 'Falló el reenvío: ' . $e->getMessage()]);
        }
    }

    private function findLogOrFail(int $id): object
    {
        abort_unless(Schema::connection($this->adm)->hasTable('billing_email_logs'), 404);

        $row = DB::connection($this->adm)
            ->table('billing_email_logs')
            ->where('id', $id)
            ->first();

        abort_unless($row, 404);

        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int,string>
     */
    private function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $raw = str_replace([';', "\n", "\r", "\t"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn ($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $p = trim((string) $m[1]);
            }

            if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($p);
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 10);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function refreshPayloadForResend(array $payload, string $newEmailId, string $oldEmailId = ''): array
    {
        $payload['email_id'] = $newEmailId;
        $payload['generated_at'] = now();

        foreach (['open_pixel_url', 'pdf_track_url', 'pay_track_url', 'portal_track_url'] as $key) {
            $current = (string) ($payload[$key] ?? '');
            if ($current === '') {
                continue;
            }

            if ($oldEmailId !== '' && str_contains($current, $oldEmailId)) {
                $payload[$key] = str_replace($oldEmailId, $newEmailId, $current);
            }
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function insertEmailLog(array $row): int
    {
        $cols = $this->tableColumns('billing_email_logs');
        $has  = static fn (string $c): bool => in_array(strtolower($c), $cols, true);

        $insert = [];

        if ($has('email_id')) {
            $insert['email_id'] = (string) ($row['email_id'] ?? Str::ulid());
        }

        if ($has('account_id')) {
            $insert['account_id'] = $row['account_id'] ?? null;
        }

        if ($has('period')) {
            $insert['period'] = (string) ($row['period'] ?? '');
        }

        if ($has('statement_id')) {
            $insert['statement_id'] = $row['statement_id'] ?? null;
        }

        if ($has('email')) {
            $insert['email'] = $row['email'] ?? null;
        }

        if ($has('to_list')) {
            $insert['to_list'] = $row['to_list'] ?? null;
        }

        if ($has('subject')) {
            $insert['subject'] = (string) ($row['subject'] ?? 'Pactopia360 · Estado de cuenta');
        }

        if ($has('template')) {
            $insert['template'] = $this->resolveTemplateView((string) ($row['template'] ?? 'emails.admin.billing.statement_account_period'));
        }

        if ($has('status')) {
            $insert['status'] = (string) ($row['status'] ?? 'queued');
        }

        if ($has('provider')) {
            $insert['provider'] = $row['provider'] ?? null;
        }

        if ($has('provider_message_id')) {
            $insert['provider_message_id'] = $row['provider_message_id'] ?? null;
        }

        if ($has('payload')) {
            $insert['payload'] = $row['payload'] ?? null;
        }

        if ($has('meta')) {
            $insert['meta'] = $row['meta'] ?? null;
        }

        if ($has('queued_at')) {
            $insert['queued_at'] = $row['queued_at'] ?? now();
        }

        if ($has('sent_at')) {
            $insert['sent_at'] = $row['sent_at'] ?? null;
        }

        if ($has('failed_at')) {
            $insert['failed_at'] = $row['failed_at'] ?? null;
        }

        if ($has('open_count')) {
            $insert['open_count'] = (int) ($row['open_count'] ?? 0);
        }

        if ($has('click_count')) {
            $insert['click_count'] = (int) ($row['click_count'] ?? 0);
        }

        if ($has('created_at')) {
            $insert['created_at'] = now();
        }

        if ($has('updated_at')) {
            $insert['updated_at'] = now();
        }

        return (int) DB::connection($this->adm)
            ->table('billing_email_logs')
            ->insertGetId($insert);
    }

    private function resolveTemplateView(string $template): string
    {
        $template = trim($template);

        if ($template === '') {
            return 'emails.admin.billing.statement_account_period';
        }

        $map = [
            'statement'                => 'emails.admin.billing.statement_account_period',
            'admin.mail.statement'     => 'emails.admin.billing.statement_account_period',
            'statement_account'        => 'emails.admin.billing.statement_account_period',
            'statement_account_period' => 'emails.admin.billing.statement_account_period',
        ];

        $key = strtolower($template);

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (str_starts_with($template, 'emails.')) {
            return $template;
        }

        if (view()->exists($template)) {
            return $template;
        }

        return 'emails.admin.billing.statement_account_period';
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizePreviewPayload(object $row, array $payload): array
    {
        $period  = trim((string) ($payload['period'] ?? $row->period ?? ''));
        $subject = trim((string) ($payload['subject'] ?? $row->subject ?? 'Pactopia360 · Estado de cuenta'));

        $payload['subject'] = $subject;
        $payload['period'] = $period;
        $payload['period_label'] = (string) ($payload['period_label'] ?? $period);
        $payload['email_id'] = (string) ($payload['email_id'] ?? $row->email_id ?? '');
        $payload['generated_at'] = $payload['generated_at'] ?? ($row->created_at ?? now());

        if (empty($payload['account']) || (!is_array($payload['account']) && !is_object($payload['account']))) {
            $payload['account'] = (object) [
                'id'           => (string) ($row->account_id ?? ''),
                'razon_social' => 'Cliente',
                'name'         => 'Cliente',
                'rfc'          => '',
                'email'        => (string) ($row->email ?? ''),
            ];
        } elseif (is_array($payload['account'])) {
            $payload['account'] = (object) $payload['account'];
        }

        $payload['items'] = (isset($payload['items']) && is_iterable($payload['items']))
            ? $payload['items']
            : collect();

        $payload['open_pixel_url']   = (string) ($payload['open_pixel_url'] ?? '');
        $payload['pdf_track_url']    = (string) ($payload['pdf_track_url'] ?? '');
        $payload['pay_track_url']    = (string) ($payload['pay_track_url'] ?? '');
        $payload['portal_track_url'] = (string) ($payload['portal_track_url'] ?? '');
        $payload['pay_url']          = (string) ($payload['pay_url'] ?? '');
        $payload['pdf_url']          = (string) ($payload['pdf_url'] ?? '');
        $payload['portal_url']       = (string) ($payload['portal_url'] ?? '');

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildFallbackPreviewPayload(object $row): array
    {
        $period  = trim((string) ($row->period ?? ''));
        $to      = trim((string) ($row->email ?? ''));
        $subject = trim((string) ($row->subject ?? 'Pactopia360 · Estado de cuenta'));

        return [
            'subject'          => $subject,
            'template'         => 'emails.admin.billing.statement_account_period',
            'account'          => (object) [
                'id'           => (string) ($row->account_id ?? ''),
                'razon_social' => 'Cliente',
                'name'         => 'Cliente',
                'rfc'          => '',
                'email'        => $to,
            ],
            'period'           => $period,
            'period_label'     => $period,
            'items'            => collect(),
            'tarifa_label'     => 'Estado de cuenta',
            'total'            => 0.0,
            'saldo'            => 0.0,
            'total_cargo'      => 0.0,
            'total_abono'      => 0.0,
            'cargo'            => 0.0,
            'abono'            => 0.0,
            'abono_edo'        => 0.0,
            'abono_pay'        => 0.0,
            'prev_balance'     => 0.0,
            'total_due'        => 0.0,
            'status_pago'      => strtolower((string) ($row->status ?? 'queued')) === 'sent' ? 'pagado' : 'pendiente',
            'email_id'         => (string) ($row->email_id ?? ''),
            'generated_at'     => $row->created_at ?? now(),
            'open_pixel_url'   => '',
            'pdf_track_url'    => '',
            'pay_track_url'    => '',
            'portal_track_url' => '',
            'pay_url'          => '',
            'pdf_url'          => '',
            'portal_url'       => '',
        ];
    }

    /**
     * @return array<string,int|string|null>
     */
    private function emptyKpis(): array
    {
        return [
            'total'              => 0,
            'sent'               => 0,
            'queued'             => 0,
            'failed'             => 0,
            'opened'             => 0,
            'clicked'            => 0,
            'last_activity_at'   => null,
            'first_day_sent'     => 0,
            'current_month_sent' => 0,
            'current_month'      => now()->format('Y-m'),
        ];
    }

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($period));
    }

    private function normalizeDateInput(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        try {
            return array_map(
                static fn ($c) => strtolower((string) $c),
                Schema::connection($this->adm)->getColumnListing($table)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param callable(string):bool $has
     */
    private function resolveActivityExpr(callable $has): string
    {
        $parts = [];

        if ($has('sent_at')) {
            $parts[] = 'l.sent_at';
        }

        if ($has('queued_at')) {
            $parts[] = 'l.queued_at';
        }

        if ($has('failed_at')) {
            $parts[] = 'l.failed_at';
        }

        if ($has('created_at')) {
            $parts[] = 'l.created_at';
        }

        if (empty($parts)) {
            return 'CURRENT_TIMESTAMP';
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function extractUiError(object $row, array $meta): ?string
    {
        $candidates = [
            $row->error_text ?? null,
            $meta['error'] ?? null,
            $meta['message'] ?? null,
            $meta['last_error'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $text = trim((string) $candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $base
     */
    private function mergeMetaJson(array $base, array $extra): string
    {
        return json_encode(array_merge($base, $extra), JSON_UNESCAPED_UNICODE);
    }
}