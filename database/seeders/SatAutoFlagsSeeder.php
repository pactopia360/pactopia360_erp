<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\CuentaCliente;

class SatAutoFlagsSeeder extends Seeder
{
    /**
     * Activa auto_download y alertas en todas las credenciales SAT.
     * - auto_download := true
     * - alert_canceled := true
     * - alert_email := si está vacío, usa correo de la cuenta (correo_contacto || email)
     */
    public function run(): void
    {
        $count = 0;
        $updated = 0;

        // Procesa en chunks para no saturar memoria en catálogos grandes
        SatCredential::query()->orderBy('id')->chunkById(200, function ($creds) use (&$count, &$updated) {
            /** @var \App\Models\Cliente\SatCredential $cred */
            foreach ($creds as $cred) {
                $count++;

                // Recupera correo de la cuenta (si existe)
                $acc = null;
                try {
                    $acc = CuentaCliente::query()
                        ->where('id', $cred->cuenta_id)
                        ->first(['id','correo_contacto','email']);
                } catch (\Throwable $e) {
                    // Si hay multi conexión u otro esquema, solo continúa
                    Log::warning('[SatAutoFlagsSeeder] No se pudo leer CuentaCliente', [
                        'cuenta_id' => $cred->cuenta_id,
                        'ex' => $e->getMessage(),
                    ]);
                }

                $changed = false;

                if (! $cred->auto_download) {
                    $cred->auto_download = true;
                    $changed = true;
                }

                if ($cred->alert_canceled !== true) {
                    $cred->alert_canceled = true;
                    $changed = true;
                }

                if (empty($cred->alert_email)) {
                    $email = $acc?->correo_contacto ?: $acc?->email ?: null;
                    if (! empty($email)) {
                        $cred->alert_email = $email;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $cred->save();
                    $updated++;
                }
            }
        });

        $this->command?->info("[SatAutoFlagsSeeder] Revisados: {$count}, Actualizados: {$updated}");
        Log::info('[SatAutoFlagsSeeder] Done', ['checked' => $count, 'updated' => $updated]);
    }
}
