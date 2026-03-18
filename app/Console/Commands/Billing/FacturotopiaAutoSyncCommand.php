<?php

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Http\Controllers\Admin\Billing\EmisoresController;
use App\Http\Controllers\Admin\Billing\ReceptoresController;
use Illuminate\Console\Command;
use Throwable;

final class FacturotopiaAutoSyncCommand extends Command
{
    protected $signature = 'p360:facturotopia:auto-sync {--force : Ignora cooldown y fuerza la sincronización}';
    protected $description = 'Sincroniza automáticamente emisores y receptores desde Facturotopía hacia Pactopia360.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        try {
            /** @var EmisoresController $emisores */
            $emisores = app(EmisoresController::class);

            /** @var ReceptoresController $receptores */
            $receptores = app(ReceptoresController::class);

            $this->info('Iniciando sincronización automática con Facturotopía...');

            $emisoresResult = $emisores->runAutomaticSync($force);
            $receptoresResult = $receptores->runAutomaticSync($force);

            $this->line('Emisores: ' . (string) ($emisoresResult['message'] ?? 'Sin respuesta.'));
            $this->line('Receptores: ' . (string) ($receptoresResult['message'] ?? 'Sin respuesta.'));

            $emisoresOk = (bool) ($emisoresResult['ok'] ?? false);
            $receptoresOk = (bool) ($receptoresResult['ok'] ?? false);

            if (!$emisoresOk || !$receptoresOk) {
                $this->error('La sincronización automática terminó con errores.');
                return self::FAILURE;
            }

            $this->info('Sincronización automática completada correctamente.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Falló la sincronización automática: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}