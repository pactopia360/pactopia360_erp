<?php

namespace App\Console\Commands;

use App\Models\Cliente\SatDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SatRecalculateDownloadCosts extends Command
{
    protected $signature = 'sat:recalculate-download-costs
        {--cuenta_id= : UUID cuenta_id}
        {--limit=5000 : Máximo de filas}
        {--force : Recalcula aunque ya tenga costo/peso}
        {--all : No filtra por status (toma todas las filas con bytes)}
    ';

    protected $description = 'Recalcula peso_gb/tam_gb y costo desde size_bytes/bytes.';

    public function handle(): int
    {
        $conn = 'mysql_clientes';
        $schema = Schema::connection($conn);

        if (!$schema->hasTable('sat_downloads')) {
            $this->error('Tabla sat_downloads no existe en mysql_clientes.');
            return self::FAILURE;
        }

        $hasSizeBytes = $schema->hasColumn('sat_downloads', 'size_bytes');
        $hasBytes     = $schema->hasColumn('sat_downloads', 'bytes');
        $hasTamGb     = $schema->hasColumn('sat_downloads', 'tam_gb');
        $hasPesoGb    = $schema->hasColumn('sat_downloads', 'peso_gb');
        $hasCosto     = $schema->hasColumn('sat_downloads', 'costo');

        if (!$hasSizeBytes && !$hasBytes) {
            $this->error('sat_downloads no tiene size_bytes ni bytes.');
            return self::FAILURE;
        }

        $cuentaId = trim((string)($this->option('cuenta_id') ?? ''));
        $limit    = (int)($this->option('limit') ?? 5000);
        $limit    = max(1, min($limit, 50000));
        $force    = (bool)$this->option('force');
        $all      = (bool)$this->option('all');

        $qb = SatDownload::on($conn)->newQuery();

        if ($cuentaId !== '') $qb->where('cuenta_id', $cuentaId);

        // Solo filas con bytes > 0
        $qb->where(function ($w) use ($hasSizeBytes, $hasBytes) {
            if ($hasSizeBytes) $w->orWhere('size_bytes', '>', 0);
            if ($hasBytes)     $w->orWhere('bytes', '>', 0);
        });

        if (!$all) {
            // si no es --all, limitar a “finalizadas”
            $qb->whereIn('status', ['ready','done','listo','paid','PAID','pagado','PAGADO']);
        }

        if (!$force) {
            $qb->where(function ($w) use ($hasCosto, $hasPesoGb, $hasTamGb) {
                if ($hasCosto)  $w->orWhereNull('costo')->orWhere('costo', '<=', 0);
                if ($hasPesoGb) $w->orWhereNull('peso_gb')->orWhere('peso_gb', '<=', 0);
                if ($hasTamGb)  $w->orWhereNull('tam_gb')->orWhere('tam_gb', '<=', 0);
            });
        }

        $rows = $qb->orderByDesc('created_at')->limit($limit)->get();
        $this->info('Encontradas: ' . $rows->count() . ' descargas para recálculo');

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $dl) {
            $bytes = 0;
            if ($hasSizeBytes) $bytes = max($bytes, (int)($dl->size_bytes ?? 0));
            if ($hasBytes)     $bytes = max($bytes, (int)($dl->bytes ?? 0));

            if ($bytes <= 0) {
                $skipped++;
                continue;
            }

            $gb = $bytes / 1024 / 1024 / 1024;

            $payload = [];
            if ($hasTamGb)  $payload['tam_gb']  = round($gb, 6);
            if ($hasPesoGb) $payload['peso_gb'] = round($gb, 6);

            // IMPORTANTE: aquí integraremos TU pricing real. Por ahora, un mínimo anti-$0.00.
            if ($hasCosto) {
                $payload['costo'] = $this->calculateCostFallback($gb);
            }

            SatDownload::on($conn)->where('id', $dl->id)->update($payload);
            $updated++;
        }

        $this->info("Actualizadas: {$updated} | Omitidas: {$skipped}");
        return self::SUCCESS;
    }

    private function calculateCostFallback(float $gb): float
    {
        $gb = max(0.0, $gb);
        if ($gb <= 0) return 0.00;

        // Bloques de 0.01 GB, mínimo 1 bloque
        $blocks = (int)ceil($gb / 0.01);
        $blocks = max(1, $blocks);

        // $2 por bloque (ajústalo a tu tabla real en cuanto me pases tu pricing)
        return round($blocks * 2.00, 2);
    }
}
