<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanExpiredOtps extends Command
{
    /**
     * El nombre del comando que se ejecutará en consola.
     */
    protected $signature = 'otp:clean';

    /**
     * Descripción del comando (aparece en `php artisan list`).
     */
    protected $description = 'Elimina códigos OTP expirados o ya usados de la tabla phone_otps';

    /**
     * Ejecuta la limpieza.
     */
    public function handle(): int
    {
        try {
            $total = DB::connection('mysql_admin')
                ->table('phone_otps')
                ->where(function ($q) {
                    $q->whereNotNull('used_at')
                      ->orWhere('expires_at', '<', now());
                })
                ->delete();

            Log::info('[OTP-CLEAN] Limpieza completada', ['eliminados' => $total]);

            $this->info("✅ OTPs eliminados: {$total}");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[OTP-CLEAN] Error durante la limpieza', ['e' => $e->getMessage()]);
            $this->error("❌ Error al limpiar OTPs: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
