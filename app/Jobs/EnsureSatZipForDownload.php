<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Cliente\SatDownload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureSatZipForDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $downloadId,
        public string $cuentaId,
    ) {}

    public $tries = 3;

    public function handle(): void
    {
        $dl = SatDownload::query()
            ->where('id', $this->downloadId)
            ->where('cuenta_id', $this->cuentaId)
            ->first();

        if (!$dl) {
            Log::warning('[EnsureSatZipForDownload] Download no encontrada', [
                'download_id' => $this->downloadId,
                'cuenta_id'   => $this->cuentaId,
            ]);
            return;
        }

        // Si ya existe zip_path, no hagas nada
        if (!empty($dl->zip_path)) {
            Log::info('[EnsureSatZipForDownload] zip_path ya existe', [
                'download_id' => $this->downloadId,
                'zip_path'    => $dl->zip_path,
            ]);
            return;
        }

        // Llamamos a la misma lógica del controller a través de un método estático utilitario:
        // Para evitar acoplarte al controller, aquí solo marcamos "pendiente_zip" y que tu backend lo procese.
        // Si quieres, puedo extraer ensureZipForDownload a un Service limpio en el siguiente paso.

        try {
            DB::connection('mysql_clientes')
                ->table('sat_downloads')
                ->where('id', $this->downloadId)
                ->update([
                    'meta' => DB::raw("JSON_SET(COALESCE(meta,'{}'), '$.ensure_zip', true)"),
                    'updated_at' => now(),
                ]);

            Log::info('[EnsureSatZipForDownload] Marcado meta.ensure_zip=true', [
                'download_id' => $this->downloadId,
                'cuenta_id'   => $this->cuentaId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EnsureSatZipForDownload] Error marcando ensure_zip', [
                'download_id' => $this->downloadId,
                'cuenta_id'   => $this->cuentaId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
