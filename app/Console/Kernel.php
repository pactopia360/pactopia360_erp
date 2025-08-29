<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registra comandos Artisan de la app.
     */
    protected $commands = [
        \App\Console\Commands\NovaBotFullMaintenance::class,
        \App\Console\Commands\P360InventoryCommand::class,
    ];

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('p360:estado-cuenta')->monthlyOn(1, '09:00');
        $schedule->command('p360:bloquear-morosos')->monthlyOn(5, '09:30');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
