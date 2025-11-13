<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\SatAutoDownloadJob; // 👈 añade este use

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

        // --- Tu Job de proceso posterior ---
        $schedule->job(new \App\Jobs\SatAutoProcessJob())
            ->cron('0 7,15,23 * * *')
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
