<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatDownloadMetrics.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SatDownloadMetrics
{
    public function computeDownloadCost(int $numXml): float
    {
        $n = max(0, (int) $numXml);

        if ($n === 0) return 0.0;

        if ($n <= 5_000)   return (float) $n;
        if ($n <= 25_000)  return (float) ($n * 0.08);
        if ($n <= 40_000)  return (float) ($n * 0.05);
        if ($n <= 100_000) return (float) ($n * 0.03);

        if ($n <= 500_000)   return 12_500.0;
        if ($n <= 1_000_000) return 18_500.0;
        if ($n <= 2_000_000) return 25_000.0;
        if ($n <= 3_000_000) return 31_000.0;

        return (float) ($n * 0.01);
    }

    public function computeCostFromSizeOrXml(?int $sizeBytes, int $xmlCount): float
    {
        $xml = max(0, (int) $xmlCount);
        if ($xml > 0) return $this->computeDownloadCost($xml);

        $bytes = max(0, (int) ($sizeBytes ?? 0));
        if ($bytes <= 0) return 0.0;

        $avgBytesPerXml = 4096;
        $estXml = (int) max(1, (int) ceil($bytes / $avgBytesPerXml));

        return $this->computeDownloadCost($estXml);
    }

    /**
     * IMPORTANTE: Para mantener compat con tu Controller actual,
     * este método NO intenta resolver "resolveDownloadZipLocation".
     * Eso se queda en Controller (porque no nos pasaste esa parte aún).
     *
     * Aquí solo:
     * - normaliza meta
     * - intenta size/xml/costo
     * - expiración por columnas
     */
    public function hydrateDownloadMetrics(SatDownload $d): SatDownload
    {
        $conn   = $d->getConnectionName() ?? 'mysql_clientes';
        $table  = $d->getTable();
        $schema = Schema::connection($conn);

        $colCache = [];
        $has = static function (string $col) use (&$colCache, $schema, $table): bool {
            if (array_key_exists($col, $colCache)) return $colCache[$col];
            try { return $colCache[$col] = $schema->hasColumn($table, $col); }
            catch (\Throwable) { return $colCache[$col] = false; }
        };

        // META
        $meta = [];
        try {
            $raw = $d->meta ?? null;
            if (is_array($raw)) $meta = $raw;
            elseif (is_string($raw) && trim($raw) !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) $meta = $tmp;
            }
        } catch (\Throwable) {
            $meta = [];
        }

        // SIZE
        $sizeBytes = null;
        $sizeMb    = null;
        $sizeGb    = null;

        $bytesCandidates = [
            data_get($meta, 'zip_bytes'),
            data_get($meta, 'zip_size_bytes'),
            data_get($meta, 'size_bytes'),
            data_get($meta, 'bytes'),

            $d->size_bytes ?? null,
            $d->zip_bytes  ?? null,
            $d->bytes      ?? null,
            $d->peso_bytes ?? null,
        ];

        foreach ($bytesCandidates as $val) {
            if ($val === null) continue;
            $iv = is_numeric($val) ? (int) $val : 0;
            if ($iv > 0) { $sizeBytes = $iv; break; }
        }

        $mbCandidates = [
            data_get($meta, 'size_mb'),
            data_get($meta, 'zip_mb'),
            data_get($meta, 'peso_mb'),
            $d->size_mb ?? null,
            $d->peso_mb ?? null,
            $d->tam_mb  ?? null,
        ];
        foreach ($mbCandidates as $val) {
            if ($val === null) continue;
            $fv = is_numeric($val) ? (float) $val : 0.0;
            if ($fv > 0) { $sizeMb = $fv; break; }
        }

        $gbCandidates = [
            data_get($meta, 'size_gb'),
            data_get($meta, 'zip_gb'),
            data_get($meta, 'peso_gb'),
            $d->size_gb ?? null,
            $d->peso_gb ?? null,
            $d->tam_gb  ?? null,
        ];
        foreach ($gbCandidates as $val) {
            if ($val === null) continue;
            $fv = is_numeric($val) ? (float) $val : 0.0;
            if ($fv > 0) { $sizeGb = $fv; break; }
        }

        if ((!$sizeBytes || $sizeBytes <= 0) && $sizeMb && $sizeMb > 0) {
            $sizeBytes = (int) round($sizeMb * 1024 * 1024);
        }
        if ((!$sizeBytes || $sizeBytes <= 0) && $sizeGb && $sizeGb > 0) {
            $sizeBytes = (int) round($sizeGb * 1024 * 1024 * 1024);
        }

        if ($sizeBytes && $sizeBytes > 0) {
            if (!$sizeMb || $sizeMb <= 0) $sizeMb = $sizeBytes / (1024 * 1024);
            if (!$sizeGb || $sizeGb <= 0) $sizeGb = $sizeBytes / (1024 * 1024 * 1024);
        } elseif ($sizeGb && $sizeGb > 0 && (!$sizeMb || $sizeMb <= 0)) {
            $sizeMb = $sizeGb * 1024;
        }

        // intento tamaño real por zip_path si existe
        if ((!$sizeBytes || $sizeBytes <= 0) && !empty($d->zip_path)) {
            try {
                $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                $relative = ltrim((string) $d->zip_path, '/');

                if ($relative !== '' && Storage::disk($diskName)->exists($relative)) {
                    $sizeBytes = (int) Storage::disk($diskName)->size($relative);
                }
            } catch (\Throwable) {}
        }

        if ($sizeBytes && $sizeBytes > 0) {
            if ($has('size_bytes')) $d->size_bytes = $sizeBytes;
            elseif ($has('bytes'))  $d->bytes = $sizeBytes;

            $d->setAttribute('size_bytes', $sizeBytes);
            $d->setAttribute('size_mb', round(($sizeBytes / (1024 * 1024)), 3));
            $d->setAttribute('size_gb', round(($sizeBytes / (1024 * 1024 * 1024)), 4));
        } else {
            if ($sizeMb && $sizeMb > 0) $d->setAttribute('size_mb', round($sizeMb, 3));
            if ($sizeGb && $sizeGb > 0) $d->setAttribute('size_gb', round($sizeGb, 4));
        }

        // XML COUNT
        $xmlCount = data_get($meta, 'xml_count')
            ?? data_get($meta, 'total_xml')
            ?? data_get($meta, 'num_xml')
            ?? ($d->xml_count ?? null)
            ?? ($d->total_xml ?? null)
            ?? ($d->num_xml ?? null);

        $xmlCount = is_numeric($xmlCount) ? (int) $xmlCount : null;

        if (($xmlCount === null || $xmlCount <= 0) && $sizeBytes && $sizeBytes > 0) {
            $xmlCount = (int) max(1, (int) ceil($sizeBytes / 4096));
        }

        if ($xmlCount !== null && $xmlCount > 0) {
            if ($has('xml_count')) $d->xml_count = $xmlCount;
            if ($has('total_xml') && ($d->total_xml === null || (int)$d->total_xml <= 0)) $d->total_xml = $xmlCount;

            $d->setAttribute('xml_count', $xmlCount);
            $d->setAttribute('total_xml', $xmlCount);
        }

        // COSTO
        $currentCost = data_get($meta, 'price_mxn')
            ?? data_get($meta, 'costo')
            ?? data_get($meta, 'cost_mxn')
            ?? data_get($meta, 'precio')
            ?? ($d->costo ?? null)
            ?? ($d->cost_mxn ?? null)
            ?? ($d->precio ?? null)
            ?? 0.0;

        $finalCost = (is_numeric($currentCost) ? (float) $currentCost : 0.0);

        if ($finalCost <= 0.0) {
            $finalCost = $this->computeCostFromSizeOrXml(
                ($sizeBytes && $sizeBytes > 0) ? (int) $sizeBytes : null,
                (int) ($xmlCount ?? 0)
            );
        }

        if ($finalCost > 0) {
            if ($has('costo')) $d->costo = $finalCost;
            $d->setAttribute('costo', round($finalCost, 2));

            $meta['costo_mxn'] = round($finalCost, 2);
            if ($has('meta')) $d->meta = $meta;
        }

        // EXPIRACIÓN
        if (empty($d->expires_at) && !empty($d->disponible_hasta)) {
            try { $d->expires_at = Carbon::parse((string) $d->disponible_hasta); } catch (\Throwable) {}
        }
        if (empty($d->expires_at) && !empty($d->created_at)) {
            try {
                $base = $d->created_at instanceof Carbon ? $d->created_at->copy() : Carbon::parse((string) $d->created_at);
                $d->expires_at = $base->addHours(12);
            } catch (\Throwable) {}
        }

        if (!empty($d->expires_at)) {
            try {
                $exp = $d->expires_at instanceof Carbon ? $d->expires_at : Carbon::parse((string) $d->expires_at);
                $d->setAttribute('expires_at', $exp->toDateTimeString());
            } catch (\Throwable) {}
        }

        return $d;
    }

    public function formatPesoFromDownloadRow(object $dl): string
    {
        $bytes = 0;

        foreach (['size_bytes', 'zip_bytes', 'bytes', 'peso_bytes'] as $col) {
            if (isset($dl->{$col}) && is_numeric($dl->{$col})) {
                $v = (int) $dl->{$col};
                if ($v > $bytes) $bytes = $v;
            }
        }

        if ($bytes <= 0) {
            $mb = null;
            foreach (['size_mb', 'zip_mb', 'peso_mb', 'tam_mb'] as $col) {
                if (isset($dl->{$col}) && is_numeric($dl->{$col})) {
                    $v = (float) $dl->{$col};
                    if ($v > 0) { $mb = $v; break; }
                }
            }

            $gb = null;
            foreach (['size_gb', 'zip_gb', 'peso_gb', 'tam_gb'] as $col) {
                if (isset($dl->{$col}) && is_numeric($dl->{$col})) {
                    $v = (float) $dl->{$col};
                    if ($v > 0) { $gb = $v; break; }
                }
            }

            if ($mb && $mb > 0) $bytes = (int) round($mb * 1024 * 1024);
            elseif ($gb && $gb > 0) $bytes = (int) round($gb * 1024 * 1024 * 1024);
        }

        if ($bytes <= 0 && isset($dl->meta) && !empty($dl->meta)) {
            $meta = [];
            try {
                if (is_array($dl->meta)) $meta = $dl->meta;
                elseif (is_string($dl->meta) && trim($dl->meta) !== '') {
                    $tmp = json_decode($dl->meta, true);
                    if (is_array($tmp)) $meta = $tmp;
                }
            } catch (\Throwable) {}

            foreach (['zip_bytes','zip_size_bytes','size_bytes','bytes'] as $k) {
                $v = $meta[$k] ?? null;
                if (is_numeric($v) && (int)$v > $bytes) $bytes = (int)$v;
            }
        }

        if ($bytes <= 0) return 'Pendiente';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $val = (float) $bytes;

        while ($val >= 1024 && $i < count($units) - 1) { $val /= 1024; $i++; }

        $dec = 0;
        if ($i === 2) $dec = 2;
        elseif ($i >= 3) $dec = 3;

        return number_format($val, $dec) . ' ' . $units[$i];
    }
}