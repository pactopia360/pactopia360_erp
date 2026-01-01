<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class BillingProcessScheduledEmails extends Command
{
    protected $signature = 'p360:billing:process-scheduled-emails {--limit=50}';
    protected $description = 'Procesa y envía correos programados desde billing_email_logs (status=queued)';

    private string $adm;

    public function __construct()
    {
        parent::__construct();
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function handle(): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            $this->info('billing_email_logs no existe. Nada que procesar.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $now   = now();

        $rows = DB::connection($this->adm)->table('billing_email_logs')
            ->where('status', 'queued')
            ->where(function ($q) use ($now) {
                $q->whereNull('queued_at')->orWhere('queued_at', '<=', $now);
            })
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->count() === 0) {
            $this->info('No hay correos queued para enviar.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($rows as $r) {
            $id      = (int) ($r->id ?? 0);
            $emailId = (string) ($r->email_id ?? '');
            $to      = (string) ($r->to ?? '');
            $period  = (string) ($r->period ?? '');
            $accountId = (string) ($r->account_id ?? '');

            // payload puede venir como json o array
            $payload = [];
            try {
                if (isset($r->payload)) {
                    if (is_string($r->payload)) $payload = json_decode($r->payload, true) ?: [];
                    elseif (is_array($r->payload)) $payload = $r->payload;
                }
            } catch (\Throwable $e) {
                $payload = [];
            }

            // fallback por si account_id no está en columna
            if ($accountId === '') $accountId = (string) ($payload['account_id'] ?? '');
            if ($period === '')    $period    = (string) ($payload['period'] ?? '');

            // Validación mínima
            if ($id <= 0 || $emailId === '' || $to === '' || $accountId === '' || !preg_match('/^\d{4}\-\d{2}$/', $period)) {
                $failed++;
                $this->markFailed($id, 'Payload inválido (email_id/to/account_id/period).', [
                    'email_id' => $emailId,
                    'to' => $to,
                    'account_id' => $accountId,
                    'period' => $period,
                    'payload' => $payload,
                ]);
                continue;
            }

            // Construir dataset para el correo
            try {
                $data = $this->buildStatementData($accountId, $period);

                // tracking (usa email_id como identificador público)
                if (Route::has('track.billing.open')) {
                    $data['open_pixel_url'] = route('track.billing.open', ['emailId' => $emailId]);
                }

                if (Route::has('track.billing.click')) {
                    $data['pdf_track_url'] = !empty($data['pdf_url'])
                        ? route('track.billing.click', ['emailId' => $emailId]) . '?u=' . urlencode((string) $data['pdf_url'])
                        : '';

                    $data['pay_track_url'] = !empty($data['pay_url'])
                        ? route('track.billing.click', ['emailId' => $emailId]) . '?u=' . urlencode((string) $data['pay_url'])
                        : '';

                    $data['portal_track_url'] = !empty($data['portal_url'])
                        ? route('track.billing.click', ['emailId' => $emailId]) . '?u=' . urlencode((string) $data['portal_url'])
                        : '';
                }

                // Subject: si ya existe en tabla, respétalo; si no, el Mailable lo arma.
                $subject = (string) ($r->subject ?? '');
                if ($subject !== '') $data['subject_override'] = $subject;

                // Envío
                Mail::to($to)->send(new StatementAccountPeriodMail($accountId, $period, $data));

                $sent++;

                DB::connection($this->adm)->table('billing_email_logs')->where('id', $id)->update([
                    'status'   => 'sent',
                    'sent_at'  => now(),
                    'error'    => null,
                    'updated_at' => now(),
                ]);

            } catch (\Throwable $e) {
                $failed++;

                Log::error('[P360][BILLING][SCHEDULED_EMAIL] fallo envío', [
                    'id' => $id,
                    'email_id' => $emailId,
                    'to' => $to,
                    'account_id' => $accountId,
                    'period' => $period,
                    'e' => $e->getMessage(),
                ]);

                $this->markFailed($id, $e->getMessage(), [
                    'id' => $id,
                    'email_id' => $emailId,
                    'to' => $to,
                    'account_id' => $accountId,
                    'period' => $period,
                ]);
            }
        }

        $this->info("Procesados: {$rows->count()} | sent={$sent} | failed={$failed}");
        return self::SUCCESS;
    }

    private function markFailed(int $id, string $message, array $context = []): void
    {
        try {
            DB::connection($this->adm)->table('billing_email_logs')->where('id', $id)->update([
                'status'    => 'failed',
                'failed_at' => now(),
                'error'     => mb_substr($message, 0, 65000),
                'updated_at'=> now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[P360][BILLING][SCHEDULED_EMAIL] no se pudo marcar failed', [
                'id' => $id,
                'msg' => $message,
                'ctx' => $context,
                'e' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dataset para el correo, compatible con resources/views/admin/mail/statement.blade.php
     * @return array<string,mixed>
     */
    private function buildStatementData(string $accountId, string $period): array
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();

        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', '=', $period)
            ->orderBy('id')
            ->get();

        $cargoReal = (float) $items->sum('cargo');
        $abono     = (float) $items->sum('abono');

        // meta: seguro
        $meta = [];
        try {
            if ($acc && isset($acc->meta) && is_string($acc->meta) && trim($acc->meta) !== '') {
                $meta = json_decode($acc->meta, true) ?: [];
            }
        } catch (\Throwable $e) {
            $meta = [];
        }

        // expected_total + tarifa_label
        $expectedTotal = 0.0;
        $tarifaLabel   = '—';

        try {
            $lic = [];
            if (isset($meta['license']) && is_array($meta['license'])) $lic = $meta['license'];
            if (!$lic && isset($meta['billing']) && is_array($meta['billing'])) $lic = $meta['billing'];

            $base =
                $lic['amount_mxn'] ?? $lic['amount'] ?? $lic['base_amount'] ??
                ($meta['billing']['amount_mxn'] ?? null) ??
                ($meta['billing']['amount'] ?? null) ??
                0;

            $baseAmt = is_numeric($base) ? (float) $base : 0.0;

            $ov = $meta['billing']['override']['amount_mxn'] ?? ($meta['billing']['override_amount_mxn'] ?? null);
            $override = is_numeric($ov) ? (float) $ov : null;

            $expectedTotal = $override !== null ? $override : $baseAmt;

            $cycle = strtoupper((string) ($lic['cycle'] ?? $lic['billing_cycle'] ?? ($meta['billing']['billing_cycle'] ?? 'MENSUAL')));
            if ($cycle === 'MONTHLY') $cycle = 'MENSUAL';
            if ($cycle === 'YEARLY')  $cycle = 'ANUAL';
            if ($cycle === '') $cycle = 'MENSUAL';

            $pk   = (string) ($lic['pk'] ?? $lic['price_key'] ?? $lic['price_id'] ?? '');
            $plan = strtoupper((string) ($lic['plan'] ?? ($meta['plan'] ?? 'PRO')));

            $tarifaLabel = $override !== null
                ? 'PERSONALIZADO'
                : ($pk !== '' ? strtoupper($pk) . ' · ' . $cycle : $plan . ' · ' . $cycle);

        } catch (\Throwable $e) {
            $expectedTotal = 0.0;
            $tarifaLabel   = '—';
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expectedTotal;
        $saldo = max(0, $totalShown - $abono);

        // URLs (si no existen rutas, no revienta)
        $pdfUrl = '';
        $portalUrl = '';

        if (Route::has('cliente.billing.publicPdfInline')) {
            $pdfUrl = route('cliente.billing.publicPdfInline', ['accountId' => $accountId, 'period' => $period]);
        }

        if (Route::has('cliente.estado_cuenta')) {
            $portalUrl = route('cliente.estado_cuenta') . '?period=' . urlencode($period);
        }

        return [
            'account'        => $acc,
            'account_id'     => $accountId,
            'period'         => $period,
            'period_label'   => Carbon::parse($period . '-01')->translatedFormat('F Y'),
            'items'          => $items,

            'cargo_real'     => round($cargoReal, 2),
            'expected_total' => round($expectedTotal, 2),
            'tarifa_label'   => $tarifaLabel,

            'cargo'          => round($totalShown, 2),
            'abono'          => round($abono, 2),
            'total'          => round($saldo, 2),

            'pdf_url'        => $pdfUrl,
            'portal_url'     => $portalUrl,

            // pay_url (si lo llenas desde hub, se respetará; si no, queda vacío)
            'pay_url'        => '',

            'generated_at'   => now(),
        ];
    }
}
