<?php

namespace App\Console\Commands;

use App\Models\Cliente\SatDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SatCleanupFreeCommand extends Command
{
    protected $signature = 'sat:cleanup-free {--dry-run}';
    protected $description = 'SAT: elimina ZIPs expirados (usuarios Free) y marca registros como expirados';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $now = now();
        $this->info('SAT cleanup-free: buscando descargas expiradas...');

        $expired = SatDownload::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->whereNotNull('zip_path')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No hay descargas expiradas.');
            return self::SUCCESS;
        }

        foreach ($expired as $d) {
            try {
                $path = ltrim((string) $d->zip_path, '/');
                if (!$dry && $path !== '' && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }

                if (!$dry) {
                    $d->zip_path = null;
                    $d->status = 'expired';
                    $d->save();
                }

                $this->line(($dry ? '[DRY] ' : '') . "✔ Limpieza #{$d->id} ({$path})");
            } catch (\Throwable $e) {
                Log::error('[sat:cleanup-free] Fallo limpiando', ['id' => $d->id, 'ex' => $e->getMessage()]);
                $this->error("✖ Error en #{$d->id}: {$e->getMessage()}");
            }
        }

        $this->info('SAT cleanup-free: terminado.');
        return self::SUCCESS;
    }
}
