<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Admin\Billing\BillingStatementsHubController;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

final class ProcessScheduledBillingEmails extends Command
{
    protected $signature = 'p360:billing:process-scheduled-emails {--limit=40}';
    protected $description = 'Procesa billing_email_logs queued cuyo queued_at <= now() y envía correos de estados de cuenta.';

    private string $adm;

    public function __construct()
    {
        parent::__construct();
        $this->adm = (string)(config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function handle(BillingStatementsHubController $hub): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            $this->error('No existe billing_email_logs');
            return self::FAILURE;
        }

        $limit = max(1, (int)$this->option('limit'));

        $rows = DB::connection($this->adm)->table('billing_email_logs')
            ->where('status', 'queued')
            ->whereNotNull('queued_at')
            ->where('queued_at', '<=', now())
            ->orderBy('queued_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nada que procesar.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($rows as $r) {
            try {
                $accId  = (string)($r->account_id ?? '');
                $period = (string)($r->period ?? '');

                if ($accId === '' || $period === '') {
                    DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$r->id)->update([
                        'status'=>'failed','failed_at'=>now(),'updated_at'=>now(),
                        'meta'=>json_encode(['error'=>'account_id/period vacío'], JSON_UNESCAPED_UNICODE),
                    ]);
                    continue;
                }

                $acc = DB::connection($this->adm)->table('accounts')->where('id',$accId)->first();
                if (!$acc) {
                    DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$r->id)->update([
                        'status'=>'failed','failed_at'=>now(),'updated_at'=>now(),
                        'meta'=>json_encode(['error'=>'Cuenta no encontrada'], JSON_UNESCAPED_UNICODE),
                    ]);
                    continue;
                }

                $emailId = (string)($r->email_id ?? '');
                if ($emailId === '') $emailId = (string)($r->id); // fallback

                $payload = $hub->buildStatementEmailPayloadPublic($acc, $accId, $period, $emailId);

                $toList = (string)($r->to_list ?? $r->email ?? '');
                $tos = $hub->parseToList($toList);
                if (empty($tos)) $tos = $hub->parseToList((string)($acc->email ?? ''));

                if (empty($tos)) {
                    DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$r->id)->update([
                        'status'=>'failed','failed_at'=>now(),'updated_at'=>now(),
                        'meta'=>json_encode(['error'=>'Sin destinatarios'], JSON_UNESCAPED_UNICODE),
                    ]);
                    continue;
                }

                Mail::to($tos)->send(new StatementAccountPeriodMail($accId, $period, $payload));

                DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$r->id)->update([
                    'status'=>'sent','sent_at'=>now(),'updated_at'=>now(),
                    'subject'=>(string)($payload['subject'] ?? null),
                    'payload'=>json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                $sent++;
            } catch (\Throwable $e) {
                Log::error('[p360:billing:process-scheduled-emails] error', ['e'=>$e->getMessage()]);
                DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$r->id)->update([
                    'status'=>'failed','failed_at'=>now(),'updated_at'=>now(),
                    'meta'=>json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $this->info("Procesados: {$sent} enviados / {$rows->count()} leídos.");
        return self::SUCCESS;
    }
}
