<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registra comandos Artisan de la app.
     */
    protected $commands = [
        \App\Console\Commands\NovaBotFullMaintenance::class,
        \App\Console\Commands\P360InventoryCommand::class,
        \App\Console\Commands\ClienteQaNormalize::class, // ← comando para normalizar QA (owners/usuarios/cuentas)
    ];

    /**
     * Definición de tareas programadas (cron).
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('p360:estado-cuenta')->monthlyOn(1, '09:00');
        $schedule->command('p360:bloquear-morosos')->monthlyOn(5, '09:30');
        $schedule->command('p360:reset-mass-invoices')->hourly();
    }

    /**
     * Carga de comandos y rutas de consola.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
