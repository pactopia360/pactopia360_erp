<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Jobs\SatAutoDownloadJob;

// ✅ SAT backfill
use App\Console\Commands\SatBackfillCfdisMeta;

// ✅ Billing HUB scheduled emails
use App\Console\Commands\BillingProcessScheduledEmails;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\NovaBotFullMaintenance::class,
        \App\Console\Commands\P360InventoryCommand::class,
        \App\Console\Commands\ClienteQaNormalize::class,
        \App\Console\Commands\CleanExpiredOtps::class,

        // SAT (Commands)
        \App\Console\Commands\SatAutoDownloadCommand::class,
        \App\Console\Commands\SatCleanupFreeCommand::class,
        \App\Console\Commands\SatMonitorAlertsCommand::class,

        // (Tu comando previo, si aún lo usas)
        \App\Console\Commands\GenerateMonthlyStatements::class,

        // ✅ Nuevos comandos de estados de cuenta (billing_statements)
        \App\Console\Commands\P360SyncStatements::class,
        \App\Console\Commands\P360SendStatements::class,
        \App\Console\Commands\P360SyncAdminAccountIds::class,
        \App\Console\Commands\ProcessScheduledBillingEmails::class,
        \App\Console\Commands\P360\SyncModulesCommand::class,

        // ✅ SAT (Backfill meta CFDI desde ZIP/XML)
        SatBackfillCfdisMeta::class,

        // ✅ Billing HUB scheduled emails
        BillingProcessScheduledEmails::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->timezone('America/Mexico_City');

        // --- Mantenimientos existentes ---
        $schedule->command('p360:estado-cuenta')->monthlyOn(1, '09:00');
        $schedule->command('p360:bloquear-morosos')->monthlyOn(5, '09:30');
        $schedule->command('p360:reset-mass-invoices')->hourly();
        $schedule->command('otp:clean')->hourly();

        // --- SAT commands (manuales/operativos) ---
        $schedule->command('sat:auto-download')->cron('0 6,14,22 * * *');
        $schedule->command('sat:cleanup-free')->dailyAt('23:50');
        $schedule->command('sat:monitor-alerts')->hourly();

        // --- SAT job (auto-download PRO, sin solapes) ---
        $schedule->job(new SatAutoDownloadJob(60))
            ->cron('0 6,12,18 * * *')
            ->onQueue('sat')
            ->withoutOverlapping()
            ->runInBackground();

        // --- Job de proceso posterior ---
        $schedule->job(new \App\Jobs\SatAutoProcessJob())
            ->cron('0 7,15,23 * * *')
            ->withoutOverlapping()
            ->runInBackground();

        // ============================
        // ✅ Estados de cuenta (nuevo)
        // ============================

        // 1) Generar / sincronizar el periodo actual (1ro del mes)
        $schedule->command('p360:statements:sync --actor=system')
            ->monthlyOn(1, '08:00')
            ->withoutOverlapping()
            ->runInBackground();

        // 2) Enviar por correo el estado del mes anterior (1ro del mes)
        $schedule->command('p360:statements:send --actor=system')
            ->monthlyOn(1, '08:10')
            ->withoutOverlapping()
            ->runInBackground();

        // ============================
        // ✅ Billing HUB (scheduled emails)
        // ============================
        $schedule->command('p360:billing:process-scheduled-emails --limit=50')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        /**
         * ✅ OPCIONAL (solo si quieres que se ejecute automático):
         * Backfill de meta CFDI (RFC/Razón/Subtotal/IVA) desde ZIP/XML.
         *
         * RECOMENDACIÓN: NO lo actives automático aún.
         * Primero ejecútalo manual con --cuenta_id para validar.
         */
        // $schedule->command('sat:backfill-cfdis-meta --limit=50 --disk=vault')
        //     ->dailyAt('02:30')
        //     ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
