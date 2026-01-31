<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\Sat\SatDescargaController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use App\Services\Sat\SatDownloadZipHelper;
use App\Services\Sat\VaultAccountSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;



final class SatDescargaController extends Controller
{
    public function __construct(
    private readonly SatDownloadService         $service,
    private readonly SatDownloadZipHelper       $zipHelper,
    private readonly VaultAccountSummaryService $vaultSummaryService,
    ) {
        // Aquí puedes colocar middleware del controlador si aplica:
        // $this->middleware(['auth:web']); // o el que uses en cliente
        // $this->middleware('throttle:120,1')->only(['dashboardStats']);
    }


    /* ===================================================
    *  AUTH / SESSION HELPERS (SOT Cliente)
    * =================================================== */

    private function clientGuard(): string
    {
        try {
            $guards = (array) config('auth.guards', []);

            if (array_key_exists('cliente', $guards) && auth()->guard('cliente')->check()) {
                return 'cliente';
            }

            if (array_key_exists('web', $guards) && auth()->guard('web')->check()) {
                return 'web';
            }

            if (array_key_exists('cliente', $guards)) {
                return 'cliente';
            }
        } catch (\Throwable) {
            // no-op
        }

        return 'web';
    }


    private function cu(): ?object
    {
        try {
            // Preferir guard resuelto (SOT)
            $g = method_exists($this, 'clientGuard') ? $this->clientGuard() : 'cliente';

            // 1) Guard preferido
            try {
                $u = auth($g)->user();
                if ($u) return $u;
            } catch (\Throwable) {
                // no-op
            }

            // 2) Fallbacks defensivos (por si el guard preferido no está activo)
            foreach (['cliente', 'web'] as $fallback) {
                if ($fallback === $g) continue;
                try {
                    $u = auth($fallback)->user();
                    if ($u) return $u;
                } catch (\Throwable) {
                    // no-op
                }
            }

            // 3) Último recurso: helper global auth()
            try {
                $u = auth()->user();
                if ($u) return $u;
            } catch (\Throwable) {
                // no-op
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }


    private function resolveCuentaIdFromUser($user): ?string
    {
        try {
            if (!$user) return null;

            // ------------------------------------------------------------
            // 1) Campos directos comunes
            // ------------------------------------------------------------
            foreach (['cuenta_id', 'account_id', 'id_cuenta'] as $prop) {
                try {
                    if (isset($user->{$prop}) && $user->{$prop} !== null && (string)$user->{$prop} !== '') {
                        return (string) $user->{$prop};
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }

            // ------------------------------------------------------------
            // 2) Relación/atributo "cuenta" (objeto o array)
            // ------------------------------------------------------------
            try {
                if (isset($user->cuenta)) {
                    $c = $user->cuenta;

                    if (is_object($c)) {
                        foreach (['id', 'cuenta_id', 'account_id'] as $k) {
                            $v = (string) ($c->{$k} ?? '');
                            if ($v !== '') return $v;
                        }
                    } elseif (is_array($c)) {
                        foreach (['id', 'cuenta_id', 'account_id'] as $k) {
                            $v = (string) ($c[$k] ?? '');
                            if ($v !== '') return $v;
                        }
                    }
                }
            } catch (\Throwable) {
                // no-op
            }

            // ------------------------------------------------------------
            // 3) Métodos/getters típicos (sin romper si no existen)
            // ------------------------------------------------------------
            foreach (['getCuentaId', 'getAccountId', 'cuentaId', 'accountId'] as $m) {
                try {
                    if (is_object($user) && method_exists($user, $m)) {
                        $v = (string) $user->{$m}();
                        if ($v !== '') return $v;
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }

            // ------------------------------------------------------------
            // 4) Session fallbacks (legacy)
            // ------------------------------------------------------------
            foreach ([
                'cliente.cuenta_id',
                'cliente.account_id',
                'client.cuenta_id',
                'client.account_id',
                'cuenta_id',
                'account_id',
                'client_cuenta_id',
                'client_account_id',
            ] as $k) {
                try {
                    $v = (string) session($k, '');
                    if ($v !== '') return $v;
                } catch (\Throwable) {
                    // no-op
                }
            }

            // ------------------------------------------------------------
            // 5) (Opcional) Auth actual si coincide el mismo objeto
            //    Evita “inventar” cuenta_id si llega un $user parcial.
            // ------------------------------------------------------------
            try {
                if (method_exists($this, 'cu')) {
                    $cur = $this->cu();
                    if ($cur && $cur === $user) {
                        foreach (['cuenta_id','account_id'] as $prop) {
                            if (isset($cur->{$prop}) && (string)$cur->{$prop} !== '') {
                                return (string) $cur->{$prop};
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // no-op
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function cuId(): string
    {
        try {
            $u = $this->cu();
            if (!$u) return '';

            $id = (string) ($this->resolveCuentaIdFromUser($u) ?? '');
            $id = trim($id);

            // Normalización defensiva: si llega como numérico, lo regresamos como string limpio
            if ($id !== '' && is_numeric($id)) {
                // evita "9.0" en casos raros
                $id = (string) ((int) $id);
            }

            return $id;
        } catch (\Throwable) {
            return '';
        }
    }

    private function trace(): string
    {
        try {
            // ULID: ordenable por tiempo, ideal para logs y trazabilidad distribuida
            return (string) Str::ulid();
        } catch (\Throwable) {
            // Fallback ultra-defensivo (nunca debe romper un request por trazabilidad)
            return (string) uniqid('trace_', true);
        }
    }


    private function isAjax(Request $request): bool
    {
        // Laravel:
        // - ajax(): revisa X-Requested-With
        // - expectsJson()/wantsJson(): headers Accept/Content-Type (APIs, fetch)
        // Extra: soporte explícito a clientes que mandan flags comunes.
        return $request->ajax()
            || $request->expectsJson()
            || $request->wantsJson()
            || $request->isJson()
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest'
            || strtolower((string) $request->header('X-Requested-With')) === 'fetch'
            || in_array(strtolower((string) $request->header('X-P360-AJAX')), ['1','true','yes','on'], true);
    }

    /* ===================================================
    *  HELPERS COSTO / PESO / EXPIRACIÓN
    * =================================================== */

    private function computeDownloadCost(int $numXml): float
    {
        $n = max(0, (int) $numXml);

        // Costo por tramos (MXN) - mantener exactamente la lógica actual,
        // pero más legible y consistente.
        if ($n === 0) {
            return 0.0;
        }

        // <= 5,000 : $1 por XML
        if ($n <= 5_000) {
            return (float) $n;
        }

        // <= 25,000 : $0.08 por XML
        if ($n <= 25_000) {
            return (float) ($n * 0.08);
        }

        // <= 40,000 : $0.05 por XML
        if ($n <= 40_000) {
            return (float) ($n * 0.05);
        }

        // <= 100,000 : $0.03 por XML
        if ($n <= 100_000) {
            return (float) ($n * 0.03);
        }

        // paquetes fijos
        if ($n <= 500_000) {
            return 12_500.0;
        }

        if ($n <= 1_000_000) {
            return 18_500.0;
        }

        if ($n <= 2_000_000) {
            return 25_000.0;
        }

        if ($n <= 3_000_000) {
            return 31_000.0;
        }

        // > 3,000,000 : $0.01 por XML
        return (float) ($n * 0.01);
    }

    protected function computeDownloadCostPhp(int $n): float
    {
        // Alias explícito para compatibilidad/claridad cuando el cálculo
        // se invoca desde contexto PHP (no JS / no UI).
        $n = max(0, (int) $n);

        return $this->computeDownloadCost($n);
    }

    private function computeCostFromSizeOrXml(?int $sizeBytes, int $xmlCount): float
    {
        // 1) Si ya tenemos conteo real de XML, úsalo (fuente de verdad)
        $xml = max(0, (int) $xmlCount);
        if ($xml > 0) {
            return $this->computeDownloadCost($xml);
        }

        // 2) Fallback por tamaño: estima XMLs con un promedio defensivo (4KB por XML)
        $bytes = max(0, (int) ($sizeBytes ?? 0));
        if ($bytes <= 0) {
            return 0.0;
        }

        // Evita overflow y asegura al menos 1 XML estimado
        $avgBytesPerXml = 4096; // 4KB promedio (heurística)
        $estXml = (int) max(1, (int) ceil($bytes / $avgBytesPerXml));

        return $this->computeDownloadCost($estXml);
    }

    private function hydrateDownloadMetrics(SatDownload $d): SatDownload
    {
        $conn   = $d->getConnectionName() ?? 'mysql_clientes';
        $table  = $d->getTable();
        $schema = Schema::connection($conn);

        // Cachea hasColumn por performance (evita N llamadas por registro)
        $colCache = [];
        $has = static function (string $col) use (&$colCache, $schema, $table): bool {
            if (array_key_exists($col, $colCache)) return $colCache[$col];
            try { return $colCache[$col] = $schema->hasColumn($table, $col); }
            catch (\Throwable) { return $colCache[$col] = false; }
        };

        // -----------------------------
        // META (array o JSON)
        // -----------------------------
        $meta = [];
        try {
            $raw = $d->meta ?? null;
            if (is_array($raw)) {
                $meta = $raw;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) $meta = $tmp;
            }
        } catch (\Throwable) {
            $meta = [];
        }

        // -----------------------------
        // SIZE: bytes / MB / GB
        // -----------------------------
        $sizeBytes = null; // int
        $sizeMb    = null; // float
        $sizeGb    = null; // float

        // 1) Bytes candidates (meta primero, luego columnas)
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

        // 2) MB/GB candidates
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

        // 3) Derivaciones (preferir bytes como verdad si existe)
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

        // 4) Si aún no hay bytes, intenta size real del zip_path (disco directo)
        if ((!$sizeBytes || $sizeBytes <= 0) && !empty($d->zip_path)) {
            try {
                $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                $relative = ltrim((string) $d->zip_path, '/');

                if ($relative !== '' && Storage::disk($diskName)->exists($relative)) {
                    $sizeBytes = (int) Storage::disk($diskName)->size($relative);
                }
            } catch (\Throwable) {}
        }

        // 5) Si aún no existe, resuelve ubicación (helper)
        if ((!$sizeBytes || $sizeBytes <= 0)) {
            try {
                [$realDisk, $realPath] = $this->resolveDownloadZipLocation($d);
                $realDisk = (string) $realDisk;
                $realPath = ltrim((string) $realPath, '/');

                if ($realDisk !== '' && $realPath !== '' && Storage::disk($realDisk)->exists($realPath)) {
                    $sizeBytes = (int) Storage::disk($realDisk)->size($realPath);

                    // normaliza zip_path si existe columna
                    if ($has('zip_path')) $d->zip_path = $realPath;

                    // deja evidencia en meta
                    $meta['zip_disk'] = $realDisk;
                    $meta['zip_path'] = $realPath;
                    if ($has('meta')) $d->meta = $meta;
                    else $d->setAttribute('meta', $meta);
                }
            } catch (\Throwable) {}
        }

        // Recalcula MB/GB final si ahora ya hay bytes
        if ($sizeBytes && $sizeBytes > 0) {
            $sizeMb = ($sizeMb && $sizeMb > 0) ? $sizeMb : ($sizeBytes / (1024 * 1024));
            $sizeGb = ($sizeGb && $sizeGb > 0) ? $sizeGb : ($sizeBytes / (1024 * 1024 * 1024));
        }

        // -----------------------------
        // Persist + atributos para UI
        // -----------------------------
        if ($sizeBytes && $sizeBytes > 0) {
            if ($has('size_bytes')) $d->size_bytes = $sizeBytes;
            elseif ($has('bytes'))  $d->bytes = $sizeBytes;

            // siempre expón para UI aunque no exista columna
            $d->setAttribute('size_bytes', $sizeBytes);
        }

        if ($sizeMb && $sizeMb > 0) {
            if ($has('size_mb')) $d->size_mb = $sizeMb;
            $d->setAttribute('size_mb', round($sizeMb, 3));
        }

        if ($sizeGb && $sizeGb > 0) {
            if ($has('size_gb')) $d->size_gb = $sizeGb;
            $d->setAttribute('size_gb', round($sizeGb, 4));
        }

        // -----------------------------
        // XML COUNT
        // -----------------------------
        $xmlCount = data_get($meta, 'xml_count')
            ?? data_get($meta, 'total_xml')
            ?? data_get($meta, 'num_xml')
            ?? ($d->xml_count  ?? null)
            ?? ($d->total_xml  ?? null)
            ?? ($d->num_xml    ?? null);

        $xmlCount = is_numeric($xmlCount) ? (int) $xmlCount : null;

        if (($xmlCount === null || $xmlCount <= 0) && $sizeBytes && $sizeBytes > 0) {
            $xmlCount = (int) max(1, (int) ceil($sizeBytes / 4096)); // 4KB prom
        }

        if ($xmlCount !== null && $xmlCount > 0) {
            if ($has('xml_count')) $d->xml_count = $xmlCount;
            if ($has('total_xml') && ($d->total_xml === null || (int)$d->total_xml <= 0)) $d->total_xml = $xmlCount;

            $d->setAttribute('xml_count', $xmlCount);
            $d->setAttribute('total_xml', $xmlCount);
        }

        // -----------------------------
        // COSTO
        // -----------------------------
        $currentCost = data_get($meta, 'price_mxn')
            ?? data_get($meta, 'costo')
            ?? data_get($meta, 'cost_mxn')
            ?? data_get($meta, 'precio')
            ?? ($d->costo    ?? null)
            ?? ($d->cost_mxn ?? null)
            ?? ($d->precio   ?? null)
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
            elseif ($has('price_mxn')) $d->setAttribute('price_mxn', $finalCost);

            $d->setAttribute('costo', round($finalCost, 2));
            $meta['costo_mxn'] = round($finalCost, 2);
            if ($has('meta')) $d->meta = $meta;
        }

        // -----------------------------
        // EXPIRACIÓN
        // -----------------------------
        if (empty($d->expires_at) && !empty($d->disponible_hasta)) {
            try {
                $d->expires_at = Carbon::parse((string) $d->disponible_hasta);
            } catch (\Throwable) {}
        }

        if (empty($d->expires_at) && !empty($d->created_at)) {
            try {
                $base = $d->created_at instanceof Carbon
                    ? $d->created_at->copy()
                    : Carbon::parse((string) $d->created_at);

                $d->expires_at = $base->addHours(12);
            } catch (\Throwable) {}
        }

        // UI helper
        if (!empty($d->expires_at)) {
            try {
                $exp = $d->expires_at instanceof Carbon ? $d->expires_at : Carbon::parse((string) $d->expires_at);
                $d->setAttribute('expires_at', $exp->toDateTimeString());
            } catch (\Throwable) {}
        }

        return $d;
    }

    private function formatPesoFromDownloadRow(object $dl): string
    {
        // Candidatos (soporta columnas + atributos hidratados + meta si viene pegado)
        $bytes = 0;

        // 1) bytes directos
        foreach (['size_bytes', 'zip_bytes', 'bytes', 'peso_bytes'] as $col) {
            if (isset($dl->{$col}) && is_numeric($dl->{$col})) {
                $v = (int) $dl->{$col};
                if ($v > $bytes) $bytes = $v;
            }
        }

        // 2) si no hay bytes, intenta MB/GB
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

            if ($mb && $mb > 0) {
                $bytes = (int) round($mb * 1024 * 1024);
            } elseif ($gb && $gb > 0) {
                $bytes = (int) round($gb * 1024 * 1024 * 1024);
            }
        }

        // 3) meta (por si llega como array o json string)
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

            if ($bytes <= 0) {
                $mb = $meta['size_mb'] ?? $meta['zip_mb'] ?? $meta['peso_mb'] ?? null;
                $gb = $meta['size_gb'] ?? $meta['zip_gb'] ?? $meta['peso_gb'] ?? null;

                if (is_numeric($mb) && (float)$mb > 0) $bytes = (int) round(((float)$mb) * 1024 * 1024);
                elseif (is_numeric($gb) && (float)$gb > 0) $bytes = (int) round(((float)$gb) * 1024 * 1024 * 1024);
            }
        }

        if ($bytes <= 0) return 'Pendiente';

        // Formato humano (KiB/MiB/GiB/TiB) con sufijos ES
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $val = (float) $bytes;

        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }

        // Decimales: 0 para B/KB, 2 para MB, 3 para GB/TB (ajustable)
        $dec = 0;
        if ($i === 2) $dec = 2;        // MB
        elseif ($i >= 3) $dec = 3;     // GB/TB

        // Nota: "MB/GB" (no "Mb/Gb") para bytes
        return number_format($val, $dec) . ' ' . $units[$i];
    }

    /* ===================================================
    *  BÓVEDA: RESUMEN / ACTIVA (defensivo + multi-esquema)
    * =================================================== */

    private function buildVaultStorageSummary(string $cuentaId, $cuentaObj): array
    {
        // 0) Si el servicio existe y devuelve algo usable, úsalo (SOT)
        try {
            if (isset($this->vaultSummaryService) && method_exists($this->vaultSummaryService, 'buildStorageSummary')) {
                $res = $this->vaultSummaryService->buildStorageSummary($cuentaId, $cuentaObj);
                if (is_array($res) && isset($res['quota_bytes'])) {
                    // normaliza mínimos esperados
                    return array_merge([
                        'quota_gb'    => isset($res['quota_gb']) ? (float) $res['quota_gb'] : round(((float) $res['quota_bytes']) / 1024 / 1024 / 1024, 2),
                        'quota_bytes' => (int) ($res['quota_bytes'] ?? 0),
                        'used_gb'     => isset($res['used_gb']) ? (float) $res['used_gb'] : round(((float) ($res['used_bytes'] ?? 0)) / 1024 / 1024 / 1024, 2),
                        'used_bytes'  => (int) ($res['used_bytes'] ?? 0),
                        'free_gb'     => (float) ($res['free_gb'] ?? 0),
                        'used_pct'    => (float) ($res['used_pct'] ?? 0),
                        'free_pct'    => (float) ($res['free_pct'] ?? 0),
                    ], $res);
                }
            }
        } catch (\Throwable) {
            // fallback
        }

        $conn = 'mysql_clientes';
        $cuentaId = trim((string) $cuentaId);

        $schema = Schema::connection($conn);

        // Helpers
        $hasTable = static function (string $t) use ($schema): bool {
            try { return $schema->hasTable($t); } catch (\Throwable) { return false; }
        };
        $hasCol = static function (string $t, string $c) use ($schema): bool {
            try { return $schema->hasTable($t) && $schema->hasColumn($t, $c); } catch (\Throwable) { return false; }
        };

        // 1) Plan → base GB
        $planRaw = '';
        try {
            $planRaw = (string) data_get($cuentaObj, 'plan_actual', 'FREE');
        } catch (\Throwable) {
            $planRaw = 'FREE';
        }
        $plan = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $vaultBaseGb = $isProPlan
            ? (float) config('services.sat.vault.base_gb_pro', 0.0)
            : (float) config('services.sat.vault.base_gb_free', 0.0);

        $vaultBaseGb = max(0.0, $vaultBaseGb);

        // 2) Quota desde cuenta (si existe)
        $quotaBytesFromAccount = 0;

        // (a) intenta de $cuentaObj aunque no haya columna (no rompe si viene DTO)
        foreach (['vault_quota_bytes', 'vault_quota_gb'] as $k) {
            $v = null;
            try { $v = data_get($cuentaObj, $k); } catch (\Throwable) { $v = null; }

            if ($k === 'vault_quota_bytes' && is_numeric($v) && (int)$v > 0) {
                $quotaBytesFromAccount = (int) $v;
                break;
            }

            if ($k === 'vault_quota_gb' && is_numeric($v) && (float)$v > 0) {
                $quotaBytesFromAccount = (int) round(((float)$v) * 1024 * 1024 * 1024);
                break;
            }
        }

        // (b) valida que realmente exista la columna para evitar “atributos fantasmas”
        if ($quotaBytesFromAccount > 0 && $hasTable('cuentas_cliente')) {
            if (!$hasCol('cuentas_cliente', 'vault_quota_bytes') && !$hasCol('cuentas_cliente', 'vault_quota_gb')) {
                // si no existen columnas, mejor no confiar en ese valor
                $quotaBytesFromAccount = 0;
            }
        }

        $quotaGbFromAccount = $quotaBytesFromAccount > 0
            ? ($quotaBytesFromAccount / 1024 / 1024 / 1024)
            : 0.0;

        // 3) Quota comprada por “vault rows” (sat_downloads tipo vault/boveda pagadas)
        $quotaGbFromVaultRows = 0.0;

        if ($hasTable('sat_downloads')) {
            try {
                // columnas defensivas
                $t = (new SatDownload())->getTable();
                $cols = [];
                try { $cols = $schema->getColumnListing($t); } catch (\Throwable) { $cols = []; }
                $hasDl = static function (string $c) use ($cols): bool { return in_array($c, $cols, true); };

                $colCuenta = $hasDl('cuenta_id') ? 'cuenta_id' : ($hasDl('account_id') ? 'account_id' : null);
                $colTipo   = $hasDl('tipo') ? 'tipo' : null;
                $colPaidAt = $hasDl('paid_at') ? 'paid_at' : null;
                $colStatus = $hasDl('status') ? 'status' : null;

                if ($colCuenta) {
                    $q = DB::connection($conn)->table($t)->where($colCuenta, $cuentaId);

                    if ($colTipo) {
                        $q->whereRaw('LOWER(COALESCE(' . $colTipo . ',"")) IN ("vault","boveda")');
                    } else {
                        // sin tipo no podemos detectar vault; dejamos 0
                        $q = null;
                    }

                    if ($q) {
                        $q->where(function ($qq) use ($colPaidAt, $colStatus) {
                            if ($colPaidAt) {
                                $qq->whereNotNull($colPaidAt);
                            }
                            if ($colStatus) {
                                $qq->orWhereRaw('LOWER(COALESCE(' . $colStatus . ',"")) IN ("paid","pagado","pagada")');
                            }
                        });

                        // Trae solo lo necesario
                        $sel = [];
                        foreach (['vault_gb','gb','alias','nombre','meta'] as $c) {
                            if ($hasDl($c)) $sel[] = $c;
                        }
                        if (empty($sel)) $sel = [$colCuenta]; // mínimo para no romper

                        $rows = $q->select($sel)->get();

                        $totalGb = 0.0;
                        foreach ($rows as $row) {
                            $gb = 0.0;

                            // columnas
                            foreach (['vault_gb','gb'] as $c) {
                                if (isset($row->{$c}) && is_numeric($row->{$c}) && (float)$row->{$c} > 0) {
                                    $gb = (float) $row->{$c};
                                    break;
                                }
                            }

                            // meta (por si viene ahí)
                            if ($gb <= 0 && isset($row->meta) && !empty($row->meta)) {
                                $m = [];
                                try {
                                    if (is_array($row->meta)) $m = $row->meta;
                                    elseif (is_string($row->meta) && trim($row->meta) !== '') {
                                        $tmp = json_decode($row->meta, true);
                                        if (is_array($tmp)) $m = $tmp;
                                    }
                                } catch (\Throwable) { $m = []; }

                                foreach (['vault_gb','gb','quota_gb'] as $k) {
                                    if (isset($m[$k]) && is_numeric($m[$k]) && (float)$m[$k] > 0) {
                                        $gb = (float) $m[$k];
                                        break;
                                    }
                                }
                            }

                            // alias/nombre: “10 GB”
                            if ($gb <= 0) {
                                $alias = '';
                                foreach (['alias','nombre'] as $c) {
                                    if (isset($row->{$c}) && trim((string)$row->{$c}) !== '') {
                                        $alias = (string) $row->{$c};
                                        break;
                                    }
                                }
                                if ($alias !== '' && preg_match('/(\d+(?:\.\d+)?)\s*gb/i', $alias, $m)) {
                                    $gb = (float) $m[1];
                                }
                            }

                            if ($gb > 0) $totalGb += $gb;
                        }

                        $quotaGbFromVaultRows = max(0.0, $totalGb);
                    }
                }
            } catch (\Throwable) {
                $quotaGbFromVaultRows = 0.0;
            }
        }

        // 4) Final quota: base + compras VS quota explícita en cuenta
        $quotaGbComputed = max(0.0, $vaultBaseGb + $quotaGbFromVaultRows);
        $quotaGbFinal    = max($quotaGbComputed, $quotaGbFromAccount);
        $quotaBytesFinal = (int) round($quotaGbFinal * 1024 * 1024 * 1024);

        // 5) Used bytes (cuentas_cliente o suma de sat_vault_files)
        $usedBytes = 0;

        // (a) cuentas_cliente.vault_used_bytes
        if ($hasTable('cuentas_cliente') && $hasCol('cuentas_cliente', 'vault_used_bytes')) {
            try {
                $v = data_get($cuentaObj, 'vault_used_bytes');
                if (is_numeric($v) && (int)$v > 0) $usedBytes = (int) $v;
            } catch (\Throwable) {
                // ignore
            }
        }

        // (b) suma sat_vault_files.bytes
        if ($usedBytes <= 0 && $hasTable('sat_vault_files') && $hasCol('sat_vault_files', 'bytes')) {
            try {
                // cuenta_id vs account_id
                $cols = [];
                try { $cols = $schema->getColumnListing('sat_vault_files'); } catch (\Throwable) { $cols = []; }
                $hv = static function (string $c) use ($cols): bool { return in_array($c, $cols, true); };
                $colCuentaVF = $hv('cuenta_id') ? 'cuenta_id' : ($hv('account_id') ? 'account_id' : null);

                if ($colCuentaVF) {
                    $usedBytes = (int) DB::connection($conn)
                        ->table('sat_vault_files')
                        ->where($colCuentaVF, $cuentaId)
                        ->sum('bytes');
                }
            } catch (\Throwable) {
                $usedBytes = 0;
            }
        }

        $usedBytes = max(0, (int) $usedBytes);

        // Si el usado ya supera la cuota, la “cuota efectiva” debe al menos cubrirlo (no mostrar % > 100 raro)
        $quotaBytes = max((int) $quotaBytesFinal, $usedBytes);

        $usedGb  = $usedBytes / 1024 / 1024 / 1024;
        $quotaGb = $quotaBytes / 1024 / 1024 / 1024;
        $freeGb  = max(0.0, $quotaGb - $usedGb);

        $usedPct = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $freePct = max(0.0, round(100.0 - $usedPct, 2));

        return [
            'quota_gb'    => round($quotaGb, 2),
            'quota_bytes' => (int) $quotaBytes,
            'used_gb'     => round($usedGb, 2),
            'used_bytes'  => (int) $usedBytes,
            'free_gb'     => round($freeGb, 2),
            'used_pct'    => $usedPct,
            'free_pct'    => $freePct,

            // extras útiles para debug/UI sin romper compat
            'base_gb'        => round($vaultBaseGb, 2),
            'purchased_gb'   => round($quotaGbFromVaultRows, 2),
            'account_quota'  => round($quotaGbFromAccount, 2),
            'plan'           => $plan,
            'is_pro_plan'    => $isProPlan,
        ];
    }

    private function hasActiveVault(string $cuentaId, $cuentaObj = null): bool
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return false;

        // Si nos pasan cuentaObj, intenta detectar “plan” rápido sin pegarle a DB
        try {
            $plan = strtoupper(trim((string) data_get($cuentaObj, 'plan_actual', '')));
            if ($plan !== '') {
                // FREE no garantiza bóveda; PRO/PREMIUM/etc sí (según regla de negocio)
                if (in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true)) {
                    $base = (float) config('services.sat.vault.base_gb_pro', 0.0);
                    if ($base > 0.0) return true;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        // Fuente de verdad: resumen (incluye compras + quota explícita)
        try {
            $summary = $this->buildVaultStorageSummary($cuentaId, $cuentaObj ?? (object) []);
            $quotaBytes = (int) ($summary['quota_bytes'] ?? 0);

            // Considera activa si hay cuota positiva
            return $quotaBytes > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function vaultIsActiveForAccount($cuenta): bool
    {
        if (!$cuenta) return false;

        // Normaliza a objeto
        if (is_array($cuenta)) {
            $cuenta = (object) $cuenta;
        }

        // ID robusto (soporta varios shapes)
        $cuentaId = '';
        try {
            $cuentaId = trim((string) (
                data_get($cuenta, 'id')
                ?? data_get($cuenta, 'cuenta_id')
                ?? data_get($cuenta, 'account_id')
                ?? ''
            ));
        } catch (\Throwable) {
            $cuentaId = trim((string) (($cuenta->id ?? $cuenta->cuenta_id ?? $cuenta->account_id ?? '') ?: ''));
        }

        if ($cuentaId === '') return false;

        return $this->hasActiveVault($cuentaId, $cuenta);
    }

    private function fetchCuentaObjForVault(string $cuentaId): object
    {
        $cuentaId = trim((string) $cuentaId);

        // Siempre devolvemos un objeto con al menos un id “usable”
        $fallback = static function () use ($cuentaId): object {
            return (object) [
                'id'       => $cuentaId,
                'cuenta_id'=> $cuentaId,
            ];
        };

        if ($cuentaId === '') {
            // cuenta inválida -> objeto vacío pero consistente
            return (object) ['id' => '', 'cuenta_id' => ''];
        }

        // 1) Busca en mysql_clientes.cuentas_cliente por id (y fallback por cuenta_id si existiera)
        try {
            $conn   = 'mysql_clientes';
            $schema = Schema::connection($conn);

            if ($schema->hasTable('cuentas_cliente')) {
                $q = DB::connection($conn)->table('cuentas_cliente');

                // id es lo normal
                $row = $q->where('id', $cuentaId)->first();

                // algunos setups guardan cuenta_id (defensivo)
                if (!$row && $schema->hasColumn('cuentas_cliente', 'cuenta_id')) {
                    $row = DB::connection($conn)->table('cuentas_cliente')
                        ->where('cuenta_id', $cuentaId)
                        ->first();
                }

                if ($row) {
                    $obj = (object) $row;

                    // asegura que al menos exista id/cuenta_id para el resto de helpers
                    if (empty($obj->id) && !empty($obj->cuenta_id)) $obj->id = (string) $obj->cuenta_id;
                    if (empty($obj->cuenta_id) && !empty($obj->id)) $obj->cuenta_id = (string) $obj->id;

                    return $obj;
                }
            }
        } catch (\Throwable) {
            // no-op -> intenta fallback por sesión/usuario
        }

        // 2) Fallback por sesión/usuario actual (evita nulls)
        try {
            $u = $this->cu();
            $c = $u?->cuenta ?? null;

            if (is_array($c)) $c = (object) $c;

            if (is_object($c)) {
                // normaliza ids
                if (empty($c->id) && !empty($c->cuenta_id)) $c->id = (string) $c->cuenta_id;
                if (empty($c->cuenta_id) && !empty($c->id)) $c->cuenta_id = (string) $c->id;

                // si el objeto “cuenta” trae otro id, respeta el pedido por $cuentaId
                // (pero garantizamos que el id/cuenta_id coincidan con el parámetro si el objeto viene incompleto)
                if (empty($c->id) || (string)$c->id === '') $c->id = $cuentaId;
                if (empty($c->cuenta_id) || (string)$c->cuenta_id === '') $c->cuenta_id = $cuentaId;

                return $c;
            }
        } catch (\Throwable) {
            // no-op
        }

        // 3) Último fallback
        return $fallback();
    }

    /**
     * SOT: Cuenta del cliente desde mysql_clientes.cuentas_cliente
     * (porque en tu entorno NO existe mysql_clientes.cuentas).
     *
     * Mejora:
     * - Normaliza id/cuenta_id
     * - Fallback defensivo si el PK no es exactamente "id"
     * - Nunca truena por schema/DB
     */
    private function fetchCuentaCliente(string $cuentaId): ?object
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return null;

        $conn = 'mysql_clientes';

        try {
            $schema = Schema::connection($conn);

            if (!$schema->hasTable('cuentas_cliente')) {
                return null;
            }

            // Determina columnas disponibles (defensivo)
            $cols = [];
            try { $cols = $schema->getColumnListing('cuentas_cliente'); } catch (\Throwable) { $cols = []; }

            $has = static function (string $c) use ($cols): bool {
                return in_array($c, $cols, true);
            };

            $q = DB::connection($conn)->table('cuentas_cliente');

            // 1) PK típico
            $row = $has('id')
                ? $q->where('id', $cuentaId)->first()
                : null;

            // 2) Fallback por cuenta_id (si existe)
            if (!$row && $has('cuenta_id')) {
                $row = DB::connection($conn)->table('cuentas_cliente')
                    ->where('cuenta_id', $cuentaId)
                    ->first();
            }

            // 3) Fallback por account_id (algunos esquemas usan account_id)
            if (!$row && $has('account_id')) {
                $row = DB::connection($conn)->table('cuentas_cliente')
                    ->where('account_id', $cuentaId)
                    ->first();
            }

            if (!$row) return null;

            $obj = (object) $row;

            // Normaliza campos clave para el resto del controlador
            if (empty($obj->id) && !empty($obj->cuenta_id)) $obj->id = (string) $obj->cuenta_id;
            if (empty($obj->id) && !empty($obj->account_id)) $obj->id = (string) $obj->account_id;

            if (empty($obj->cuenta_id) && !empty($obj->id)) $obj->cuenta_id = (string) $obj->id;

            return $obj;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * RFC options para "Descargas manuales":
     * - Solo RFCs válidos (con CSD / validado).
     * - Formato uniforme para Blade/JS.
     * - Dedup por RFC (por si vienen repetidos).
     * - Alias limpio (sin nulls raros) y fallback por campos comunes.
     */
    private function buildRfcOptionsForManual($credList): array
    {
        $out = [];
        $seen = [];

        foreach (collect($credList ?? []) as $c) {
            // rfc
            $rfc = strtoupper(trim((string) data_get($c, 'rfc', '')));
            if ($rfc === '') continue;

            // dedup
            if (isset($seen[$rfc])) continue;

            $estatusRaw = strtolower(trim((string) data_get($c, 'estatus', data_get($c, 'sat_status', data_get($c, 'estatus_sat', '')))));

            // flags de validación / archivos
            $hasValidated =
                !empty(data_get($c, 'validado'))
                || !empty(data_get($c, 'validated_at'))
                || !empty(data_get($c, 'validado_at'))
                || !empty(data_get($c, 'verificado_at'))
                || !empty(data_get($c, 'sat_validated_at'));

            $hasFiles =
                !empty(data_get($c, 'has_files'))
                || !empty(data_get($c, 'has_csd'))
                || !empty(data_get($c, 'cer_path'))
                || !empty(data_get($c, 'key_path'))
                || !empty(data_get($c, 'cer_file'))
                || !empty(data_get($c, 'key_file'));

            $statusOk = in_array($estatusRaw, [
                'ok','valido','válido','validado','valid','activo','active','enabled'
            ], true);

            $isValid = ($hasValidated || $hasFiles || $statusOk);
            if (!$isValid) continue;

            // alias / razón social
            $alias = trim((string) (
                data_get($c, 'razon_social')
                ?? data_get($c, 'razonSocial')
                ?? data_get($c, 'nombre')
                ?? data_get($c, 'alias')
                ?? data_get($c, 'business_name')
                ?? ''
            ));

            $out[] = [
                'rf'    => $rfc,
                'alias' => ($alias !== '' ? $alias : null),
            ];

            $seen[$rfc] = true;
        }

        // orden por RFC
        usort($out, static fn ($a, $b) => strcmp((string) ($a['rf'] ?? ''), (string) ($b['rf'] ?? '')));

        return $out;
    }

    /* ===================================================
    *  VISTA SAT
    * =================================================== */

    public function index(Request $request)
    {
        $user = $this->cu();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        // ✅ CuentaId primero (SOT)
        $cuentaId = trim((string) ($this->resolveCuentaIdFromUser($user) ?? ''));

        // ✅ SOT: cuentas_cliente (porque NO existe mysql_clientes.cuentas)
        $cuentaCliente = null;
        if ($cuentaId !== '') {
            $cuentaCliente = $this->fetchCuentaCliente($cuentaId);
        }

        // Fallback: si en tu auth/modelo existe $user->cuenta úsalo solo como respaldo
        if (!$cuentaCliente) {
            $tmp = $user?->cuenta ?? null;
            if (is_array($tmp)) $tmp = (object) $tmp;

            if (is_object($tmp)) {
                if ($cuentaId === '') {
                    $cuentaId = trim((string) ($tmp->id ?? $tmp->cuenta_id ?? ''));
                }
                $cuentaCliente = $tmp;
            }
        }

        // Normaliza plan
        $planRaw = (string) (($cuentaCliente->plan_actual ?? $cuentaCliente->plan ?? 'FREE'));
        $plan    = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        // =========================
        // Bóveda summary (defensivo)
        // =========================
        $vaultSummary = [
            'has_quota'     => false,
            'quota_gb'      => 0.0,
            'quota_bytes'   => 0,
            'used_gb'       => 0.0,
            'used_bytes'    => 0,
            'available_gb'  => 0.0,
            'used_pct'      => 0.0,
            'available_pct' => 0.0,
            'files_count'   => 0,
        ];

        $vaultForJs = [
            'quota_gb'     => 0.0,
            'used_gb'      => 0.0,
            'used'         => 0.0,
            'free_gb'      => 0.0,
            'available_gb' => 0.0,
            'used_pct'     => 0.0,
            'files_count'  => 0,
            'enabled'      => false,
        ];

        if ($cuentaId !== '') {
            try {
                $storage = $this->buildVaultStorageSummary($cuentaId, $cuentaCliente ?? (object) []);
                $enabled = ((int) ($storage['quota_bytes'] ?? 0)) > 0;

                // ✅ files_count (si existe sat_vault_files)
                $vaultFilesCount = 0;
                try {
                    $conn = 'mysql_clientes';
                    if (Schema::connection($conn)->hasTable('sat_vault_files')) {
                        $vaultFilesCount = (int) DB::connection($conn)
                            ->table('sat_vault_files')
                            ->where('cuenta_id', $cuentaId)
                            ->count();
                    }
                } catch (\Throwable) {
                    $vaultFilesCount = 0;
                }

                $vaultSummary = [
                    'has_quota'     => $enabled,
                    'quota_gb'      => (float) ($storage['quota_gb'] ?? 0),
                    'quota_bytes'   => (int) ($storage['quota_bytes'] ?? 0),
                    'used_gb'       => (float) ($storage['used_gb'] ?? 0),
                    'used_bytes'    => (int) ($storage['used_bytes'] ?? 0),
                    'available_gb'  => (float) ($storage['free_gb'] ?? 0),
                    'used_pct'      => (float) ($storage['used_pct'] ?? 0),
                    'available_pct' => (float) ($storage['free_pct'] ?? 0),
                    'files_count'   => $vaultFilesCount,
                ];

                $vaultForJs = [
                    'quota_gb'     => (float) ($storage['quota_gb'] ?? 0),
                    'used_gb'      => (float) ($storage['used_gb'] ?? 0),
                    'used'         => (float) ($storage['used_gb'] ?? 0),
                    'free_gb'      => (float) ($storage['free_gb'] ?? 0),
                    'available_gb' => (float) ($storage['free_gb'] ?? 0),
                    'used_pct'     => (float) ($storage['used_pct'] ?? 0),
                    'files_count'  => $vaultFilesCount,
                    'enabled'      => $enabled,
                ];
            } catch (\Throwable $e) {
                Log::warning('[SAT:index] Error calculando resumen de bóveda', [
                    'cuenta_id' => $cuentaId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // =========================
        // Datos principales
        // =========================
        $credList          = collect();
        $credMap           = [];
        $rfcOptions        = [];
        $initialRows       = [];
        $cartIds           = [];
        $downloadsPage     = null;
        $downloadsTotalAll = 0;

        if ($cuentaId !== '') {
            try {
                // limpia expirada (defensivo)
                try { $this->cleanupExpiredHistory($cuentaId, 5); } catch (\Throwable) {}

                $cartIds = $this->getCartIds($cuentaId);

                // =========================
                // Credenciales (RFCs)
                // =========================
                $credList = SatCredential::query()
                    ->where('cuenta_id', $cuentaId)
                    ->orderBy('rfc')
                    ->get();

                foreach ($credList as $c) {
                    $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
                    if ($rfc !== '') {
                        $credMap[$rfc] = (string) ($c->razon_social ?? $c->alias ?? '');
                    }
                }

                // ✅ RFC options para “Manual” (solo validados)
                $rfcOptions = $this->buildRfcOptionsForManual($credList);

                // =========================
                // Descargas (NO bóveda)
                // =========================
                $perPage = (int) $request->query('per', 20);
                if ($perPage < 5) $perPage = 5;
                if ($perPage > 100) $perPage = 100;

                $baseQuery = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                    ->orderByDesc('created_at');

                $downloadsTotalAll = (int) $baseQuery->count();
                $downloadsPage     = $baseQuery->paginate($perPage);

                $now = Carbon::now();

                $collection = $downloadsPage->getCollection()
                    ->filter(function (SatDownload $d) {
                        $tipo = strtolower((string) data_get($d, 'tipo', ''));
                        $isRequest = (bool) data_get($d, 'is_request', false);
                        $esSolicitud = (bool) data_get($d, 'es_solicitud', false);

                        if ($isRequest || $esSolicitud) return false;
                        if (in_array($tipo, ['solicitud', 'request', 'peticion'], true)) return false;

                        return true;
                    })
                    ->values();

                $rowsH = $collection->map(function (SatDownload $d) use ($credMap, $cartIds, $now) {
                    return $this->transformDownloadRow($d, $credMap, $cartIds, $now);
                });

                $downloadsPage->setCollection($rowsH);

                $initialRows = $rowsH->values()->all();
            } catch (\Throwable $e) {
                Log::error('[SAT:index] Error cargando descargas/credenciales', [
                    'cuenta_id' => $cuentaId,
                    'error'     => $e->getMessage(),
                ]);

                // ✅ no rompas Blade
                $credList = collect();
                $credMap = [];
                $rfcOptions = [];
                $initialRows = [];
                $downloadsPage = null;
                $downloadsTotalAll = 0;
                $cartIds = [];
            }
        }

        return view('cliente.sat.index', [
            'plan'               => $plan,
            'isProPlan'          => $isProPlan,

            'credList'           => $credList,
            'initialRows'        => $initialRows,
            'downloads'          => $initialRows,
            'downloadsPaginator' => $downloadsPage,
            'downloadsTotalAll'  => $downloadsTotalAll,

            'cuenta'             => $cuentaCliente,

            'vaultSummary'       => $vaultSummary,
            'storage'            => $vaultSummary,
            'vault'              => $vaultForJs,

            'vault_quota_gb'     => (float) ($vaultSummary['quota_gb'] ?? 0.0),
            'vault_used_gb'      => (float) ($vaultSummary['used_gb'] ?? 0.0),
            'vault_used_pct'     => (float) ($vaultSummary['used_pct'] ?? 0.0),

            'cartIds'            => $cartIds,

            // ✅ requerido por Blade/JS “Descargas manuales”
            'rfcOptions'         => $rfcOptions,
        ]);
    }

    protected function transformDownloadRow(
    SatDownload $d,
    array $credMap,
    array $cartIds,
    Carbon $now
    ): array {
        $rfc   = strtoupper((string) ($d->rfc ?? ''));
        $alias = $credMap[$rfc] ?? (string) ($d->razon_social ?? $d->alias ?? '');

        $from = $d->date_from ?? $d->desde ?? null;
        $to   = $d->date_to   ?? $d->hasta ?? null;

        $fromStr = $from ? substr((string) $from, 0, 10) : null;
        $toStr   = $to   ? substr((string) $to,   0, 10) : null;

        $expRaw = $d->expires_at ?? $d->disponible_hasta ?? null;
        $exp    = null;

        try {
            if ($expRaw instanceof Carbon) $exp = $expRaw->copy();
            elseif ($expRaw) $exp = Carbon::parse((string) $expRaw);
        } catch (\Throwable) {
            $exp = null;
        }

        if (!$exp && $d->paid_at) {
            $exp = $d->paid_at instanceof Carbon
                ? $d->paid_at->copy()->addDays(15)
                : Carbon::parse((string) $d->paid_at)->addDays(15);
        }

        if (!$exp && $d->created_at) {
            $exp = $d->created_at instanceof Carbon
                ? $d->created_at->copy()->addHours(12)
                : Carbon::parse((string) $d->created_at)->addHours(12);
        }

        $secondsLeft = null;
        if ($exp) $secondsLeft = $now->diffInSeconds($exp, false);

        $estadoStr = (string) ($d->estado ?? $d->status ?? $d->sat_status ?? '');
        $estadoLow = strtolower($estadoStr);

        $pagado    = !empty($d->paid_at);
        $isExpired = false;

        if (!$pagado && $secondsLeft !== null && $secondsLeft <= 0) {
            $estadoStr = 'EXPIRADA';
            $estadoLow = 'expirada';
            $isExpired = true;
        }

        if ($pagado) {
            $estadoStr = 'PAID';
            $estadoLow = 'paid';
            $isExpired = false;
        }

        $tipoLow = strtolower((string) ($d->tipo ?? ''));
        $estadoLow = strtolower((string) $estadoStr);

        // ✅ canPay robusto: solo si está “listo” y NO es vault/boveda
        $canPay = !$pagado
        && !in_array($tipoLow, ['vault', 'boveda'], true)
        && in_array($estadoLow, ['ready', 'done', 'listo', 'completed', 'finalizado'], true);


        // ✅ Asegura métricas (peso, xml_count, costo, etc.)
        $this->hydrateDownloadMetrics($d);

        $xmlCount  = (int) ($d->xml_count ?? $d->total_xml ?? 0);
        $sizeGb    = (float) ($d->size_gb ?? 0);
        $sizeMb    = (float) ($d->size_mb ?? 0);
        $sizeBytes = (int) ($d->size_bytes ?? 0);
        $costo     = (float) ($d->costo ?? 0);

        $discount   = $d->discount_pct ?? $d->descuento_pct ?? null;
        $createdStr = $d->created_at ? $d->created_at->format('Y-m-d H:i:s') : null;

        $sizeLabel = (string) ($d->size_label ?? ($sizeMb > 0
            ? number_format($sizeMb, 2) . ' Mb'
            : 'Pendiente'
        ));

        $remainingLabel = '00:00:00';
        if ($secondsLeft !== null && $secondsLeft > 0) {
            $remainingLabel = gmdate('H:i:s', (int) floor($secondsLeft));
        }

        $inCart = in_array((string) $d->id, $cartIds, true);

        $pesoMb    = $sizeMb > 0 ? $sizeMb : (($sizeBytes > 0) ? ($sizeBytes / (1024 * 1024)) : 0.0);
        $pesoLabel = $pesoMb > 0 ? number_format($pesoMb, 2) . ' MB' : 'Pendiente';

        // ✅ Manual flag (columna o meta)
        $isManual = false;
        try {
            $meta = [];
            if (isset($d->meta)) {
                if (is_array($d->meta)) $meta = $d->meta;
                elseif (is_string($d->meta) && $d->meta !== '') {
                    $tmp = json_decode($d->meta, true);
                    if (is_array($tmp)) $meta = $tmp;
                }
            }

            $isManual =
                !empty($d->is_manual ?? null)
                || !empty($d->manual ?? null)
                || !empty($meta['is_manual'] ?? null)
                || !empty($meta['manual'] ?? null);
        } catch (\Throwable) {
            $isManual = false;
        }

        return [
            'id'           => (string) $d->id,
            'dlid'         => (string) $d->id,

            'rfc'          => $rfc,
            'razon'        => $alias,
            'razon_social' => $alias,
            'alias'        => $alias,
            'tipo'         => (string) ($d->tipo ?? ''),

            // ✅ NUEVO: para filtrar/etiquetar "Descargas manuales"
            'is_manual'    => $isManual,
            'manual'       => $isManual,

            'desde'        => $fromStr,
            'hasta'        => $toStr,
            'fecha'        => $createdStr,

            'estado'          => $estadoStr,
            'status'          => $estadoStr,
            'status_sat'      => $estadoStr,
            'status_sat_text' => $estadoStr,

            'pagado'       => $pagado,
            'paid'         => $pagado,
            'paid_at'      => $d->paid_at ? $d->paid_at->toIso8601String() : null,
            'can_pay'      => $canPay,

            'xml_count'    => $xmlCount,
            'total_xml'    => $xmlCount,
            'size_mb'      => $sizeMb,
            'size_gb'      => $sizeGb,
            'size_bytes'   => $sizeBytes,
            'costo'        => $costo,

            'discount_pct' => $discount,

            'expires_at'      => $exp ? $exp->toIso8601String() : null,
            'time_left'       => $secondsLeft,
            'is_expired'      => $isExpired,
            'remaining_label' => $remainingLabel,

            'size_label'   => $sizeLabel,
            'cost_usd'     => $costo, // legacy UI key
            'in_cart'      => $inCart,
            'created_at'   => $d->created_at ? $d->created_at->toIso8601String() : null,

            'peso_mb'      => (float) $pesoMb,
            'peso_label'   => (string) $pesoLabel,
        ];
    }

    /* ===================================================
    *  RFC: registrar / alias / eliminar
    * =================================================== */

    public function registerRfc(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim($this->cuId());
        $isAjax   = $this->isAjax($request);

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper(trim((string) ($data['rfc'] ?? '')));
        $alias = array_key_exists('alias', $data) ? trim((string) $data['alias']) : null;
        if ($alias === '') $alias = null;

        try {
            if ($cuentaId === '') {
                $msg = 'No se pudo determinar la cuenta actual.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 403);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            if ($rfc === '') {
                $msg = 'RFC inválido.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 422);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            // Normaliza: elimina espacios internos por si pega con espacios
            $rfc = preg_replace('/\s+/', '', $rfc) ?: $rfc;

            // UPSERT defensivo (evita duplicados por collation/case)
            $conn = 'mysql_clientes';

            $cred = SatCredential::on($conn)
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                $cred = new SatCredential();
                $cred->setConnection($conn);
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;

                // Defaults defensivos si existen columnas
                try {
                    $table  = $cred->getTable();
                    $schema = Schema::connection($conn);

                    if ($schema->hasColumn($table, 'estatus') && empty($cred->estatus)) {
                        $cred->estatus = 'pending';
                    }
                    if ($schema->hasColumn($table, 'validado') && $cred->validado === null) {
                        $cred->validado = 0;
                    }
                    if ($schema->hasColumn($table, 'source') && empty($cred->source)) {
                        $cred->source = 'cliente';
                    }
                } catch (\Throwable) {}
            } else {
                $cred->setConnection($conn);
            }

            // Alias: si viene, se asigna; si NO viene, no toca el existente
            if ($alias !== null) {
                // prioridad: razon_social; fallback: alias si existe en tu tabla
                $applied = false;
                try {
                    $table  = $cred->getTable();
                    $schema = Schema::connection($conn);

                    if ($schema->hasColumn($table, 'razon_social')) {
                        $cred->razon_social = $alias;
                        $applied = true;
                    } elseif ($schema->hasColumn($table, 'alias')) {
                        $cred->alias = $alias;
                        $applied = true;
                    }
                } catch (\Throwable) {
                    // fallback silencioso
                }

                if (!$applied) {
                    // Último recurso: mantén compat con tu UI (no romper)
                    $cred->razon_social = $alias;
                }
            }

            // Meta mínima (si existe): trazabilidad
            try {
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'meta')) {
                    $meta = [];
                    $cur  = $cred->meta ?? null;

                    if (is_array($cur)) $meta = $cur;
                    elseif (is_string($cur) && $cur !== '') {
                        $tmp = json_decode($cur, true);
                        if (is_array($tmp)) $meta = $tmp;
                    }

                    $meta['last_register_at'] = now()->toDateTimeString();
                    $meta['last_register_ip'] = $request->ip();
                    $meta['last_register_ua'] = (string) $request->userAgent();

                    $cred->meta = $meta;
                }
            } catch (\Throwable) {}

            $cred->save();

            $msg = 'RFC registrado correctamente.';
            if ($isAjax) {
                return response()->json([
                    'ok'       => true,
                    'trace_id' => $trace,
                    'rfc'      => (string) ($cred->rfc ?? $rfc),
                    'alias'    => (string) ($cred->razon_social ?? $cred->alias ?? $alias ?? ''),
                    'msg'      => $msg,
                ], 200);
            }

            return redirect()->route('cliente.sat.index')->with('ok', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:registerRfc] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'ex'        => $e->getMessage(),
            ]);

            $msg = 'No se pudo registrar el RFC.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 500);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }
    }

    public function saveAlias(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim($this->cuId());
        $isAjax   = $this->isAjax($request);

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper(trim((string) ($data['rfc'] ?? '')));
        $rfc   = preg_replace('/\s+/', '', $rfc) ?: $rfc;

        $alias = array_key_exists('alias', $data) ? trim((string) $data['alias']) : null;
        if ($alias === '') $alias = null;

        try {
            if ($cuentaId === '') {
                $msg = 'No se pudo determinar la cuenta actual.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 403);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            if ($rfc === '') {
                $msg = 'RFC inválido.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 422);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            $conn = 'mysql_clientes';

            $cred = SatCredential::on($conn)
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                // Si no existe, lo creamos (pero SOLO para guardar alias)
                $cred = new SatCredential();
                $cred->setConnection($conn);
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;

                // defaults defensivos si existen columnas
                try {
                    $table  = $cred->getTable();
                    $schema = Schema::connection($conn);

                    if ($schema->hasColumn($table, 'estatus') && empty($cred->estatus)) {
                        $cred->estatus = 'pending';
                    }
                    if ($schema->hasColumn($table, 'validado') && $cred->validado === null) {
                        $cred->validado = 0;
                    }
                    if ($schema->hasColumn($table, 'source') && empty($cred->source)) {
                        $cred->source = 'cliente';
                    }
                } catch (\Throwable) {}
            } else {
                $cred->setConnection($conn);
            }

            // Aplica alias en la columna correcta (razon_social / alias)
            $applied = false;
            try {
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'razon_social')) {
                    $cred->razon_social = $alias;
                    $applied = true;
                } elseif ($schema->hasColumn($table, 'alias')) {
                    $cred->alias = $alias;
                    $applied = true;
                }
            } catch (\Throwable) {}

            if (!$applied) {
                // fallback (no romper)
                $cred->razon_social = $alias;
            }

            // Meta mínima para auditoría (si existe)
            try {
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'meta')) {
                    $meta = [];
                    $cur  = $cred->meta ?? null;

                    if (is_array($cur)) $meta = $cur;
                    elseif (is_string($cur) && $cur !== '') {
                        $tmp = json_decode($cur, true);
                        if (is_array($tmp)) $meta = $tmp;
                    }

                    $meta['alias_updated_at'] = now()->toDateTimeString();
                    $meta['alias_updated_ip'] = $request->ip();
                    $meta['alias_updated_ua'] = (string) $request->userAgent();

                    $cred->meta = $meta;
                }
            } catch (\Throwable) {}

            $cred->save();

            $msg = 'Alias actualizado.';
            if ($isAjax) {
                return response()->json([
                    'ok'       => true,
                    'trace_id' => $trace,
                    'rfc'      => (string) ($cred->rfc ?? $rfc),
                    'alias'    => (string) ($cred->razon_social ?? $cred->alias ?? $alias ?? ''),
                    'msg'      => $msg,
                ], 200);
            }

            return redirect()->route('cliente.sat.index')->with('ok', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:saveAlias] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'ex'        => $e->getMessage(),
            ]);

            $msg = 'No se pudo actualizar el alias.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 500);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }
    }

    public function deleteRfc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
        ]);

        $rfcUpper = strtoupper(trim((string) ($data['rfc'] ?? '')));
        $rfcUpper = preg_replace('/\s+/', '', $rfcUpper) ?: $rfcUpper;

        try {
            $conn = 'mysql_clientes';

            $cred = SatCredential::on($conn)
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfcUpper])
                ->first();

            if (!$cred) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'RFC no encontrado en tu cuenta.',
                    'trace_id' => $trace,
                ], 404);
            }

            // 1) Protección: si el RFC tiene descargas/vault asociadas (si existe tabla sat_downloads), bloquea
            try {
                if (Schema::connection($conn)->hasTable('sat_downloads')) {
                    $dlHas = DB::connection($conn)->table('sat_downloads')
                        ->where(function ($q) use ($cuentaId) {
                            $q->where('cuenta_id', $cuentaId);
                            // si algún entorno usa account_id
                            try {
                                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'account_id')) {
                                    $q->orWhere('account_id', $cuentaId);
                                }
                            } catch (\Throwable) {}
                        })
                        ->whereRaw('UPPER(COALESCE(rfc,"")) = ?', [$rfcUpper])
                        ->exists();

                    if ($dlHas) {
                        return response()->json([
                            'ok'       => false,
                            'msg'      => 'No se puede eliminar: el RFC tiene descargas asociadas.',
                            'trace_id' => $trace,
                        ], 409);
                    }
                }
            } catch (\Throwable) {
                // no-op (no romper)
            }

            // 2) Detecta columnas y paths
            $table  = $cred->getTable();
            $schema = Schema::connection($conn);

            $cerPath = $schema->hasColumn($table, 'cer_path') ? (string) ($cred->cer_path ?? '') : '';
            $keyPath = $schema->hasColumn($table, 'key_path') ? (string) ($cred->key_path ?? '') : '';

            // 3) Resuelve disco(s) y elimina archivos si existen
            $diskCandidates = array_values(array_unique(array_filter([
                config('filesystems.disks.private') ? 'private' : null,
                config('filesystems.disks.sat_private') ? 'sat_private' : null,
                config('filesystems.disks.vault') ? 'vault' : null,
                config('filesystems.disks.sat_credentials') ? 'sat_credentials' : null,
                config('filesystems.disks.sat_files') ? 'sat_files' : null,
                config('filesystems.default', 'local'),
            ])));

            $deleteIfExists = static function (string $path) use ($diskCandidates): void {
                $p = ltrim((string) $path, '/');
                if ($p === '') return;

                foreach ($diskCandidates as $disk) {
                    try {
                        $d = Storage::disk($disk);
                        if ($d->exists($p)) {
                            $d->delete($p);
                            return;
                        }
                    } catch (\Throwable) {
                        // intenta siguiente disco
                    }
                }
            };

            $deleteIfExists($cerPath);
            $deleteIfExists($keyPath);

            // 4) Borra registro
            $cred->delete();

            return response()->json([
                'ok'       => true,
                'msg'      => 'RFC eliminado correctamente.',
                'rfc'      => $rfcUpper,
                'trace_id' => $trace,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[SAT:deleteRfc] Error eliminando RFC', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfcUpper,
                'ex'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo eliminar el RFC.',
                'trace_id' => $trace,
            ], 500);
        }
    }

    /* ==========================================================
    * ZIP DIRECTO (por ID)
    * ========================================================== */

    public function zip(Request $request, string $id): StreamedResponse
    {
        $user = $this->cu();
        if (!$user) abort(401, 'Sesión expirada, vuelve a iniciar sesión.');

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $cuentaId = trim((string) ($this->resolveCuentaIdFromUser($user) ?? ''));
        if ($cuentaId === '') abort(403, 'No se pudo determinar la cuenta actual.');

        $conn = 'mysql_clientes';

        /** @var SatDownload $download */
        $download = SatDownload::on($conn)
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->firstOrFail();

        // ----------------------------------------------------------
        // Ready rules (defensivo + consistente con dashboard)
        // ----------------------------------------------------------
        $paid = !empty($download->paid_at);

        $stRaw = (string) ($download->status ?? $download->estado ?? $download->sat_status ?? '');
        $st    = strtolower(trim($stRaw));

        // si hay expires_at y no está pagado, aplica expiración
        $isExpired = false;
        try {
            if (!$paid) {
                $expRaw = $download->expires_at ?? $download->disponible_hasta ?? null;
                if ($expRaw) {
                    $exp = $expRaw instanceof \Illuminate\Support\Carbon
                        ? $expRaw
                        : \Illuminate\Support\Carbon::parse((string) $expRaw);

                    if (\Illuminate\Support\Carbon::now()->greaterThanOrEqualTo($exp)) {
                        $isExpired = true;
                    }
                }
            }
        } catch (\Throwable) {
            $isExpired = false;
        }

        if ($isExpired) {
            abort(403, 'Este paquete ya expiró.');
        }

        $ready = $paid || in_array($st, [
            'done', 'ready', 'paid', 'downloaded', 'listo', 'completed', 'finalizado', 'finished', 'terminado',
        ], true);

        if (!$ready) {
            abort(403, 'Este paquete aún no está listo para descarga.');
        }

        // ----------------------------------------------------------
        // Resolver disk + path (meta.zip_disk, path aliases)
        // ----------------------------------------------------------
        $meta = [];
        try {
            if (is_array($download->meta)) $meta = $download->meta;
            elseif (is_string($download->meta) && $download->meta !== '') {
                $tmp = json_decode($download->meta, true);
                if (is_array($tmp)) $meta = $tmp;
            }
        } catch (\Throwable) {
            $meta = [];
        }

        $disk = (string) ($meta['zip_disk'] ?? '');
        if ($disk === '' || !config("filesystems.disks.$disk")) {
            $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
        }

        $path = $download->zip_path
            ?? $download->path
            ?? $download->file_path
            ?? $download->zip
            ?? $download->archivo
            ?? null;

        $path = $path ? ltrim((string) $path, '/') : '';

        // ----------------------------------------------------------
        // Local/demo zip helper
        // ----------------------------------------------------------
        if (app()->environment(['local', 'development', 'testing'])) {
            try {
                $demoRel = $this->zipHelper->ensureLocalDemoZipWithCfdis($download);
                if ($demoRel) {
                    $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                    $path = ltrim((string) $demoRel, '/');
                }
            } catch (\Throwable) {}
        }

        // ----------------------------------------------------------
        // Existence check
        // ----------------------------------------------------------
        $exists = false;
        try {
            $exists = ($path !== '' && Storage::disk($disk)->exists($path));
        } catch (\Throwable) {
            $exists = false;
        }

        // ----------------------------------------------------------
        // Robust resolver if missing
        // ----------------------------------------------------------
        if (!$exists) {
            try {
                [$realDisk, $realPath] = $this->resolveDownloadZipLocation($download);

                $realDisk = (string) $realDisk;
                $realPath = ltrim((string) $realPath, '/');

                if ($realDisk !== '' && $realPath !== '' && config("filesystems.disks.$realDisk")) {
                    if (Storage::disk($realDisk)->exists($realPath)) {
                        $disk   = $realDisk;
                        $path   = $realPath;
                        $exists = true;

                        // Persistir (no bloqueante) zip_path + meta.zip_disk (si existe columna meta/zip_path)
                        try {
                            $dlTable  = $download->getTable();
                            $dlSchema = Schema::connection($conn);

                            if ($dlSchema->hasColumn($dlTable, 'zip_path')) {
                                $download->zip_path = $path;
                            }

                            if ($dlSchema->hasColumn($dlTable, 'meta')) {
                                $meta['zip_disk'] = $disk;
                                $download->meta = $meta;
                            }

                            // no cambies updated_at si tu modelo lo usa para "reciente" (pero aquí sí conviene guardar)
                            $download->save();
                        } catch (\Throwable) {}
                    }
                }
            } catch (\Throwable) {
                $exists = false;
            }
        }

        // ----------------------------------------------------------
        // Fallback: servir desde BÓVEDA si existe sat_vault_files ligado al download
        // ----------------------------------------------------------
        if (!$exists) {
            try {
                if (class_exists(\App\Models\Cliente\SatVaultFile::class)) {
                    $vf = \App\Models\Cliente\SatVaultFile::on($conn)
                        ->where('cuenta_id', $cuentaId)
                        ->where(function ($q) use ($id) {
                            $q->where('source_id', (string) $id)
                            ->orWhere('download_id', (string) $id);
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($vf) {
                        $vDisk = (string) ($vf->disk ?: (config('filesystems.disks.sat_vault') ? 'sat_vault' : config('filesystems.default', 'local')));
                        $vPath = ltrim((string) ($vf->path ?? ''), '/');

                        if ($vDisk !== '' && $vPath !== '' && config("filesystems.disks.$vDisk")) {
                            if (Storage::disk($vDisk)->exists($vPath)) {
                                // Marcar descargado (best-effort) si existe columna
                                try {
                                    $tbl = (new SatDownload())->getTable();
                                    if (Schema::connection($conn)->hasTable($tbl) && Schema::connection($conn)->hasColumn($tbl, 'status')) {
                                        if (strtolower((string) ($download->status ?? '')) !== 'downloaded') {
                                            $download->status = 'downloaded';
                                            $download->save();
                                        }
                                    }
                                } catch (\Throwable) {}

                                Log::info('[SAT:zip] Servido desde BÓVEDA (mapeo directo)', [
                                    'cuenta_id'   => $cuentaId,
                                    'download_id' => (string) $id,
                                    'vault_id'    => (string) ($vf->id ?? ''),
                                    'disk'        => $vDisk,
                                    'path'        => $vPath,
                                ]);

                                $fileName = $download->zip_name
                                    ?? $download->filename
                                    ?? basename($vPath);

                                return Storage::disk($vDisk)->download($vPath, $fileName);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[SAT:zip] Fallback bóveda falló', [
                    'cuenta_id'   => $cuentaId,
                    'download_id' => (string) $id,
                    'err'         => $e->getMessage(),
                ]);
            }

            abort(404, 'Archivo ZIP no disponible.');
        }

        // ----------------------------------------------------------
        // Marcar descargado si hay columna status (best-effort)
        // - Hazlo al final para no “downloaded” si realmente no existe el archivo.
        // ----------------------------------------------------------
        try {
            $tbl = (new SatDownload())->getTable();
            if (Schema::connection($conn)->hasTable($tbl) && Schema::connection($conn)->hasColumn($tbl, 'status')) {
                if (strtolower((string) ($download->status ?? '')) !== 'downloaded') {
                    $download->status = 'downloaded';
                    $download->save();
                }
            }
        } catch (\Throwable) {}

        // ----------------------------------------------------------
        // Bóveda automática (no bloquea)
        // ----------------------------------------------------------
        try {
            if ($this->vaultIsActiveForAccount($cuenta)) {
                $this->moveZipToVault($download);
            }
        } catch (\Throwable $e) {
            Log::warning('[SAT:zip] Error moviendo ZIP a bóveda (no bloquea descarga)', [
                'download_id' => (string) $download->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $fileName = $download->zip_name
            ?? $download->filename
            ?? ('sat_' . (string) $download->id . '.zip');

        return Storage::disk($disk)->download($path, $fileName);
    }

    /**
     * Determina si el plan de la cuenta es PRO-like.
     * - Acepta object|array|null
     * - Normaliza espacios / casing
     * - Tolerante a variantes comunes (p.ej. "PRO+", "PRO ANUAL", "BUSINESS-PLUS")
     */
    private function isProPlanForCuenta($cuenta): bool
    {
        if (!$cuenta) return false;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $raw = (string) ($cuenta->plan_actual ?? $cuenta->plan ?? $cuenta->plan_name ?? 'FREE');
        $raw = trim($raw);
        if ($raw === '') return false;

        $plan = strtoupper($raw);

        // Normalizaciones suaves: guiones/underscores múltiples espacios
        $plan = str_replace(['_', '-'], ' ', $plan);
        $plan = preg_replace('/\s+/', ' ', $plan) ?: $plan;

        // Match directo
        if (in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true)) return true;

        // Match por prefijo/contiene (para nombres tipo "PRO ANUAL", "BUSINESS PLUS", "PREMIUM 2026")
        foreach (['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'] as $needle) {
            if ($plan === $needle) return true;
            if (str_starts_with($plan, $needle . ' ')) return true;
            if (str_contains($plan, ' ' . $needle . ' ')) return true;
            if (str_ends_with($plan, ' ' . $needle)) return true;
            // casos compactos "PRO+", "PRO-PLUS", "BUSINESSPLUS"
            if (str_starts_with($plan, $needle)) return true;
        }

        return false;
    }

    /**
     * Cuenta meses inclusivos entre dos fechas (por mes calendario).
     * Ej:
     *  - 2026-01-01 a 2026-01-31 => 1
     *  - 2026-01-15 a 2026-02-01 => 2
     *
     * Reglas:
     * - Usa mes calendario (no por días).
     * - Orden defensivo (si vienen invertidas, las intercambia).
     */
    private function monthsSpanInclusive(Carbon $from, Carbon $to): int
    {
        // Normaliza orden
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $a = $from->copy()->startOfMonth();
        $b = $to->copy()->startOfMonth();

        // diff en meses sin perder años
        $months = (($b->year - $a->year) * 12) + ($b->month - $a->month) + 1;

        return max(1, (int) $months);
    }

    /* ===================================================
    *   CREAR SOLICITUDES SAT
    * =================================================== */

    public function request(Request $request): JsonResponse
    {
        $trace = $this->trace();

        $user     = $this->cu();
        $cuentaId = (string) $this->cuId();

        // ✅ SOT: cuentas_cliente
        $cuenta = $cuentaId !== '' ? $this->fetchCuentaCliente($cuentaId) : null;

        // fallback (solo respaldo)
        if (!$cuenta) {
            $tmp = $user?->cuenta ?? null;
            if (is_array($tmp)) $tmp = (object) $tmp;
            if (is_object($tmp)) $cuenta = $tmp;
        }

        if (!$user || !$cuenta || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 422);
        }

        $data = $request->validate([
            'tipo'   => 'required|string|in:emitidos,recibidos,ambos',
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'rfcs'   => 'required|array|min:1',
            'rfcs.*' => 'required|string|min:12|max:13',

            // ✅ bandera manual desde UI (descargas manuales)
            'manual' => 'nullable',
        ]);

        // Manual robusto
        $isManual = false;
        try {
            $v = $data['manual'] ?? null;
            if (is_bool($v)) {
                $isManual = $v;
            } elseif (is_numeric($v)) {
                $isManual = ((int) $v) === 1;
            } elseif (is_string($v)) {
                $vv = strtolower(trim($v));
                $isManual = in_array($vv, ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
            }
        } catch (\Throwable) {
            $isManual = false;
        }

        // Parse fechas (defensivo)
        try {
            $from = Carbon::parse((string) $data['from'])->startOfDay();
            $to   = Carbon::parse((string) $data['to'])->endOfDay();
        } catch (\Throwable) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Rango de fechas inválido.',
                'trace_id' => $trace,
            ], 422);
        }

        // ✅ REGLA NEGOCIO:
        // FREE solo 1 mes POR EJECUCIÓN, pero si es MANUAL (pagada / por ejecución), NO limitamos.
        $isProPlan = $this->isProPlanForCuenta($cuenta);

        if (!$isProPlan && !$isManual) {
            $months = $this->monthsSpanInclusive($from, $to);
            if ($months > 1) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'En FREE sólo puedes solicitar hasta 1 mes por ejecución.',
                    'code'     => 'FREE_MONTH_LIMIT',
                    'trace_id' => $trace,
                    'meta'     => [
                        'months' => $months,
                        'from'   => $from->toDateString(),
                        'to'     => $to->toDateString(),
                    ],
                ], 422);
            }
        }

        // RFCs normalizados
        $rfcs = array_values(array_unique(array_filter(array_map(
            static fn($r) => strtoupper(trim((string) $r)),
            (array) ($data['rfcs'] ?? [])
        ), static fn($r) => $r !== '')));

        if (!count($rfcs)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Debes seleccionar al menos un RFC.',
                'trace_id' => $trace,
            ], 422);
        }

        // Credenciales de la cuenta (solo las solicitadas)
        $credList = SatCredential::on('mysql_clientes')
            ->where('cuenta_id', $cuentaId)
            ->whereIn(DB::raw('UPPER(rfc)'), $rfcs)
            ->get();

        // ✅ Mapa RFC => Credencial (para meta/alias, etc.)
        $credByRfc = $credList->keyBy(function ($c) {
            return strtoupper(trim((string) ($c->rfc ?? '')));
        });

        // Solo RFCs válidos (con CSD/validado)
        $validRfcs = $credList->filter(function ($c) {
            $estatusRaw = strtolower((string) ($c->estatus ?? ''));

            $okFlag =
                !empty($c->validado ?? null)
                || !empty($c->validated_at ?? null)
                || !empty($c->has_files ?? null)
                || !empty($c->has_csd ?? null)
                || !empty($c->cer_path ?? null)
                || !empty($c->key_path ?? null)
                || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);

            return $okFlag;
        })->pluck('rfc')->map(fn($r) => strtoupper((string) $r))->unique()->values()->all();

        if (!count($validRfcs)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Debes seleccionar al menos un RFC validado (con CSD cargado).',
                'trace_id' => $trace,
            ], 422);
        }

        $tipo = (string) $data['tipo'];
        $tipos = $tipo === 'ambos' ? ['emitidos', 'recibidos'] : [$tipo];

        $created = [];

        // Schema sat_downloads (para setear columnas solo si existen)
        $dlModel = new SatDownload();
        $dlModel->setConnection('mysql_clientes');

        $conn   = $dlModel->getConnectionName() ?? 'mysql_clientes';
        $table  = $dlModel->getTable();
        $schema = Schema::connection($conn);

        if (!$schema->hasTable($table)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No existe la tabla sat_downloads.',
                'trace_id' => $trace,
            ], 422);
        }

        $has = static function (string $col) use ($schema, $table): bool {
            try { return $schema->hasColumn($table, $col); } catch (\Throwable) { return false; }
        };

        // Columnas opcionales
        $hasIsManual = $has('is_manual') || $has('manual');
        $hasMeta     = $has('meta');

        // Cuenta column (cuenta_id vs account_id) — defensivo
        $colCuenta = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
        if (!$colCuenta) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La tabla sat_downloads no tiene cuenta_id ni account_id.',
                'trace_id' => $trace,
            ], 422);
        }

        foreach ($validRfcs as $rfc) {
            foreach ($tipos as $tipoSat) {
                try {
                    $dl = new SatDownload();
                    $dl->setConnection('mysql_clientes');

                    $dl->{$colCuenta} = $cuentaId;

                    if ($has('rfc'))  $dl->rfc  = $rfc;
                    if ($has('tipo')) $dl->tipo = $tipoSat;

                    // Estados
                    if ($has('status'))     $dl->status     = 'pending';
                    if ($has('estado'))     $dl->estado     = 'REQUESTED';
                    if ($has('sat_status')) $dl->sat_status = 'REQUESTED';

                    // Fechas (alias de columnas)
                    if ($has('desde'))     $dl->desde     = $from->toDateString();
                    if ($has('hasta'))     $dl->hasta     = $to->toDateString();
                    if ($has('date_from')) $dl->date_from = $from->toDateString();
                    if ($has('date_to'))   $dl->date_to   = $to->toDateString();

                    if ($has('user_id') && isset($user->id)) $dl->user_id = $user->id;

                    // ✅ Marca “manual”
                    if ($isManual) {
                        if ($hasIsManual) {
                            if ($has('is_manual')) $dl->is_manual = 1;
                            if ($has('manual'))    $dl->manual    = 1;
                        }

                        if ($hasMeta) {
                            $meta = [];

                            // (Importante) NO copies todo el meta de la credencial sin control.
                            // Solo tomamos llaves útiles y agregamos trazabilidad.
                            try {
                                $credForMeta = $credByRfc->get(strtoupper($rfc));
                                $raw = $credForMeta?->meta ?? null;

                                $tmp = [];
                                if (is_array($raw)) $tmp = $raw;
                                elseif (is_string($raw) && $raw !== '') {
                                    $j = json_decode($raw, true);
                                    if (is_array($j)) $tmp = $j;
                                }

                                // whitelist liviana
                                foreach (['source','external_email','invite_id','note'] as $k) {
                                    if (array_key_exists($k, $tmp)) $meta[$k] = $tmp[$k];
                                }
                            } catch (\Throwable) {
                                $meta = [];
                            }

                            $meta['is_manual'] = true;
                            $meta['manual']    = true;
                            $meta['source']    = 'manual_ui';
                            $meta['requested_at'] = now()->toDateTimeString();
                            $meta['trace_id']  = $trace;

                            $dl->meta = $meta;
                        }
                    }

                    $dl->save();
                    $created[] = (string) $dl->id;

                    // opcional: crear request en servicio si existe
                    try {
                        if (method_exists($this->service, 'createRequest')) {
                            $satRef = $this->service->createRequest($dl);
                            if ($satRef && $has('sat_request_id')) {
                                $dl->sat_request_id = $satRef;
                                $dl->save();
                            }
                        }
                    } catch (\Throwable $e) {
                        if ($has('estado'))     $dl->estado     = 'ERROR';
                        if ($has('sat_status')) $dl->sat_status = 'ERROR';
                        if ($has('status'))     $dl->status     = 'ERROR';
                        if ($has('error_msg'))  $dl->error_msg  = mb_substr((string) $e->getMessage(), 0, 900);
                        $dl->save();
                    }
                } catch (\Throwable $e) {
                    Log::error('[SAT:request] Error creando registro de descarga', [
                        'trace_id'  => $trace,
                        'cuenta_id' => $cuentaId,
                        'user_id'   => $user->id ?? null,
                        'rfc'       => $rfc,
                        'tipo'      => $tipoSat,
                        'manual'    => $isManual ? 1 : 0,
                        'msg'       => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!count($created)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudieron crear las solicitudes SAT. Revisa el log.',
                'trace_id' => $trace,
            ], 500);
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($created),
            'manual'   => $isManual ? 1 : 0,
            'ids'      => $created, // útil para UI/debug
        ], 200);
    }

    /**
     * Cancelar / eliminar una descarga SAT (borrado duro local + limpia carrito).
     */
    public function cancelDownload(Request $request): JsonResponse
    {
        $trace      = $this->trace();
        $downloadId = trim((string) ($request->input('download_id') ?? ''));
        $cuentaId   = (string) $this->cuId();

        if ($downloadId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'download_id requerido.',
                'trace_id' => $trace,
            ], 422);
        }

        if ($cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        try {
            // ✅ SatDownload puede no tener siempre cuenta_id (a veces account_id)
            $dlModel = new SatDownload();
            $conn    = $dlModel->getConnectionName() ?? 'mysql_clientes';
            $table   = $dlModel->getTable();

            $schema = Schema::connection($conn);

            if (!$schema->hasTable($table)) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'No existe la tabla sat_downloads.',
                    'trace_id' => $trace,
                ], 422);
            }

            $cols = [];
            try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }

            $has = static function (string $c) use ($cols): bool {
                return in_array($c, $cols, true);
            };

            $colCuenta = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
            if (!$colCuenta) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'La tabla sat_downloads no tiene cuenta_id ni account_id.',
                    'trace_id' => $trace,
                ], 422);
            }

            /** @var SatDownload|null $dl */
            $dl = SatDownload::on($conn)
                ->where($colCuenta, $cuentaId)
                ->where('id', $downloadId)
                ->first();

            if (!$dl) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'Solicitud no encontrada.',
                    'trace_id' => $trace,
                ], 404);
            }

            // Si está pagada, por defecto NO permitir borrado duro (evita inconsistencias).
            // Si quieres permitirlo, agrega un flag allow_paid=1 explícito desde Admin.
            $paid = false;
            try {
                $paid = $has('paid_at') && !empty($dl->paid_at);
            } catch (\Throwable) {
                $paid = !empty($dl->paid_at);
            }

            $allowPaid = false;
            try {
                $v = $request->input('allow_paid', null);
                if (is_bool($v)) $allowPaid = $v;
                elseif (is_numeric($v)) $allowPaid = ((int)$v) === 1;
                elseif (is_string($v)) $allowPaid = in_array(strtolower(trim($v)), ['1','true','on','yes','si','sí'], true);
            } catch (\Throwable) {
                $allowPaid = false;
            }

            if ($paid && !$allowPaid) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'Esta descarga está pagada; no se puede eliminar.',
                    'code'     => 'PAID_LOCK',
                    'trace_id' => $trace,
                ], 422);
            }

            DB::connection($conn)->transaction(function () use ($dl, $downloadId, $cuentaId, $conn, $schema, $colCuenta, $has) {

                // 1) carrito por sesión
                try {
                    $key = $this->cartKeyForCuenta($cuentaId);
                    $ids = session($key, []);
                    $ids = array_values(array_filter(array_map('strval', (array) $ids)));
                    $ids = array_values(array_diff($ids, [(string) $downloadId]));
                    session([$key => $ids]);
                } catch (\Throwable) {}

                // 2) carrito por tabla si existe (cuenta_id/account_id)
                try {
                    if ($schema->hasTable('sat_cart_items')) {
                        $colsCart = [];
                        try { $colsCart = Schema::connection($conn)->getColumnListing('sat_cart_items'); } catch (\Throwable) { $colsCart = []; }

                        $cartHas = static function (string $c) use ($colsCart): bool {
                            return in_array($c, $colsCart, true);
                        };

                        $cartCuentaCol = $cartHas('cuenta_id') ? 'cuenta_id' : ($cartHas('account_id') ? 'account_id' : null);

                        $q = DB::connection($conn)->table('sat_cart_items')
                            ->where('download_id', $downloadId);

                        if ($cartCuentaCol) {
                            $q->where($cartCuentaCol, $cuentaId);
                        }

                        $q->delete();
                    }
                } catch (\Throwable) {}

                // 3) borrar ZIP si existe (usa meta.zip_disk si está)
                try {
                    $zipPath = '';
                    if ($has('zip_path')) {
                        $zipPath = (string) ($dl->zip_path ?? '');
                    } else {
                        $zipPath = (string) ($dl->zip_path ?? '');
                    }

                    $zipPath = ltrim($zipPath, '/');

                    if ($zipPath !== '') {
                        $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');

                        // si meta trae zip_disk, respétalo
                        try {
                            $meta = [];
                            if (isset($dl->meta)) {
                                if (is_array($dl->meta)) $meta = $dl->meta;
                                elseif (is_string($dl->meta) && $dl->meta !== '') {
                                    $tmp = json_decode($dl->meta, true);
                                    if (is_array($tmp)) $meta = $tmp;
                                }
                            }
                            if (!empty($meta['zip_disk'])) {
                                $disk = (string) $meta['zip_disk'];
                            }
                        } catch (\Throwable) {}

                        try {
                            if (Storage::disk($disk)->exists($zipPath)) {
                                Storage::disk($disk)->delete($zipPath);
                            }
                        } catch (\Throwable) {}
                    }
                } catch (\Throwable) {}

                // 3.1) borrar XML/CSV adjuntos si existen columnas (defensivo)
                try {
                    foreach (['xml_path','csv_path','meta_path','file_path','path'] as $pcol) {
                        if (!$has($pcol)) continue;

                        $p = ltrim((string) ($dl->{$pcol} ?? ''), '/');
                        if ($p === '') continue;

                        $disk = config('filesystems.default', 'local');
                        try {
                            if (Storage::disk($disk)->exists($p)) Storage::disk($disk)->delete($p);
                        } catch (\Throwable) {}
                    }
                } catch (\Throwable) {}

                // 4) borrar registro
                $dl->delete();
            });

            return response()->json([
                'ok'       => true,
                'msg'      => 'Solicitud eliminada correctamente.',
                'trace_id' => $trace,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[SAT:cancelDownload] error', [
                'trace_id'    => $trace,
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'Error al eliminar.',
                'trace_id' => $trace,
            ], 500);
        }
    }

    /* ===================================================
    * VERIFICAR ESTADO (AJAX)
    * =================================================== */

    public function verify(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = (string) $this->cuId();

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $conn   = 'mysql_clientes';
        $schema = Schema::connection($conn);

        $dlModel = new SatDownload();
        $table   = $dlModel->getTable();

        if (!$schema->hasTable($table)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No existe la tabla sat_downloads.',
                'trace_id' => $trace,
            ], 422);
        }

        // Columnas disponibles (evita hasColumn repetido)
        $cols = [];
        try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }

        $has = static function (string $c) use ($cols): bool {
            return in_array($c, $cols, true);
        };

        // Cuenta: cuenta_id o account_id (SOT robusto)
        $colCuenta = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
        if (!$colCuenta) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La tabla sat_downloads no tiene cuenta_id ni account_id.',
                'trace_id' => $trace,
            ], 422);
        }

        // Estados: elegimos 1 “columna principal” para filtrar
        $colEstado = $has('estado') ? 'estado' : null;
        $colStatus = $has('status') ? 'status' : null;
        $colSat    = $has('sat_status') ? 'sat_status' : null;

        $colState = $colEstado ?: ($colSat ?: $colStatus);
        if (!$colState) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La tabla sat_downloads no tiene columnas de estado (estado/status/sat_status).',
                'trace_id' => $trace,
            ], 422);
        }

        // Columnas opcionales usadas
        $hasReadyAt   = $has('ready_at');
        $hasExpiresAt = $has('expires_at');
        $hasTimeLeft  = $has('time_left');
        $hasErrorMsg  = $has('error_msg');

        $hasXmlCount  = $has('xml_count') || $has('total_xml') || $has('num_xml');
        $hasSizeBytes = $has('size_bytes') || $has('bytes') || $has('zip_bytes') || $has('peso_bytes');
        $hasCosto     = $has('costo') || $has('precio') || $has('price_mxn');

        // Query base
        $q = SatDownload::on($conn)
            ->where($colCuenta, $cuentaId)
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
            ->orderBy($has('created_at') ? 'created_at' : 'id', 'asc');

        // Filtrado por “pendientes” (case-insensitive + compatible con variantes)
        $q->where(function ($qq) use ($colState) {
            $qq->whereRaw('UPPER(COALESCE(' . $colState . ',"")) IN ("REQUESTED","PROCESSING","PENDING","CREATED")')
            ->orWhereRaw('LOWER(COALESCE(' . $colState . ',"")) IN ("requested","processing","pending","created","en_proceso","pendiente","solicitud","request")');
        });

        $rows = $q->get();

        $pending = 0;
        $ready   = 0;

        // Diccionarios de normalización
        $readyStatuses   = ['DONE','READY','LISTO','COMPLETED','COMPLETADO','FINISHED','FINALIZADO','TERMINADO','DOWNLOADED','PAGADO','PAID'];
        $errorStatuses   = ['ERROR','FAILED','CANCELLED','CANCELED'];
        $pendingStatuses = ['PENDING','PENDIENTE','REQUESTED','PROCESSING','EN_PROCESO','CREATED','CREADA','SOLICITUD','REQUEST'];

        // Local/dev: simula DONE + llena métricas y expiración
        if (app()->environment(['local', 'development', 'testing'])) {
            foreach ($rows as $dl) {
                try {
                    if ($colEstado) $dl->estado = 'DONE';
                    if ($colSat)    $dl->sat_status = 'DONE';
                    if ($colStatus) $dl->status = 'DONE';

                    if ($hasReadyAt && empty($dl->ready_at)) $dl->ready_at = now();
                    if ($hasExpiresAt && empty($dl->expires_at)) $dl->expires_at = now()->addHours(12);

                    // métricas (peso/xml/costo/etc.)
                    $dl = $this->hydrateDownloadMetrics($dl);

                    if ($hasTimeLeft && $hasExpiresAt && !empty($dl->expires_at)) {
                        try {
                            $dl->time_left = max(0, now()->diffInSeconds($dl->expires_at, false));
                        } catch (\Throwable) {}
                    }

                    $dl->save();
                    $ready++;
                } catch (\Throwable) {
                    $pending++;
                }
            }

            return response()->json([
                'ok'       => true,
                'pending'  => 0,
                'ready'    => $ready,
                'trace_id' => $trace,
            ], 200);
        }

        foreach ($rows as $dl) {
            try {
                // Si no hay servicio, no rompas el UI; cuenta como pendiente
                if (!method_exists($this->service, 'syncStatus')) {
                    $pending++;
                    continue;
                }

                $statusInfo = $this->service->syncStatus($dl);

                if (!is_array($statusInfo) || empty($statusInfo['status'])) {
                    $pending++;
                    continue;
                }

                $newStatus = strtoupper(trim((string) $statusInfo['status']));

                // ERROR
                if (in_array($newStatus, $errorStatuses, true)) {
                    if ($colEstado) $dl->estado = 'ERROR';
                    if ($colSat)    $dl->sat_status = 'ERROR';
                    if ($colStatus) $dl->status = 'ERROR';

                    if ($hasErrorMsg) {
                        $dl->error_msg = (string) ($statusInfo['message'] ?? 'Error en descarga SAT.');
                    }

                    $dl->save();
                    continue;
                }

                // PENDING/PROCESSING
                if (in_array($newStatus, $pendingStatuses, true)) {
                    if ($colEstado) $dl->estado = 'PROCESSING';
                    if ($colSat)    $dl->sat_status = $newStatus; // conserva granularidad si viene
                    if ($colStatus) $dl->status = 'PROCESSING';

                    $dl->save();
                    $pending++;
                    continue;
                }

                // READY / DONE (cualquier estado no-error lo tratamos como listo)
                if (in_array($newStatus, $readyStatuses, true) || !in_array($newStatus, $errorStatuses, true)) {
                    if ($colEstado) $dl->estado = 'DONE';
                    if ($colSat)    $dl->sat_status = 'DONE';
                    if ($colStatus) $dl->status = 'DONE';

                    if ($hasReadyAt && empty($dl->ready_at)) $dl->ready_at = now();
                    if ($hasExpiresAt && empty($dl->expires_at)) $dl->expires_at = now()->addHours(12);

                    // XML count
                    if ($hasXmlCount) {
                        $xmlInfo = $statusInfo['xml_count']
                            ?? $statusInfo['total_xml']
                            ?? $statusInfo['num_xml']
                            ?? null;

                        if ($xmlInfo !== null) {
                            if ($has('xml_count')) $dl->xml_count = (int) $xmlInfo;
                            if ($has('total_xml')) $dl->total_xml = (int) $xmlInfo;
                            if ($has('num_xml'))   $dl->num_xml   = (int) $xmlInfo;
                        }
                    }

                    // Size bytes
                    if ($hasSizeBytes) {
                        $sizeInfo = $statusInfo['size_bytes']
                            ?? $statusInfo['bytes']
                            ?? $statusInfo['zip_bytes']
                            ?? $statusInfo['peso_bytes']
                            ?? null;

                        if ($sizeInfo !== null) {
                            if ($has('size_bytes')) $dl->size_bytes = (int) $sizeInfo;
                            elseif ($has('bytes'))  $dl->bytes      = (int) $sizeInfo;
                            elseif ($has('zip_bytes')) $dl->zip_bytes = (int) $sizeInfo;
                            elseif ($has('peso_bytes')) $dl->peso_bytes = (int) $sizeInfo;
                        }
                    }

                    // Costo (si viene desde servicio)
                    if ($hasCosto) {
                        $c = $statusInfo['costo']
                            ?? $statusInfo['price_mxn']
                            ?? $statusInfo['cost_mxn']
                            ?? $statusInfo['precio']
                            ?? null;

                        if ($c !== null && (float) $c > 0) {
                            if ($has('costo')) $dl->costo = (float) $c;
                            elseif ($has('precio')) $dl->precio = (float) $c;
                            elseif ($has('price_mxn')) $dl->price_mxn = (float) $c;
                        }
                    }

                    // Re-hidrata métricas (también puede calcular costo por size/xml)
                    $dl = $this->hydrateDownloadMetrics($dl);

                    if ($hasTimeLeft && $hasExpiresAt && !empty($dl->expires_at)) {
                        try {
                            $dl->time_left = max(0, now()->diffInSeconds($dl->expires_at, false));
                        } catch (\Throwable) {}
                    }

                    $dl->save();
                    $ready++;
                    continue;
                }

                $pending++;
            } catch (\Throwable $e) {
                Log::error('[SAT:verify] Error sincronizando estado', [
                    'trace_id'    => $trace,
                    'download_id' => (string) ($dl->id ?? ''),
                    'cuenta_id'   => $cuentaId,
                    'msg'         => $e->getMessage(),
                ]);
                $pending++;
            }
        }

        return response()->json([
            'ok'       => true,
            'pending'  => $pending,
            'ready'    => $ready,
            'trace_id' => $trace,
        ], 200);
    }

    /* ===================================================
    *  CREDENCIALES
    * =================================================== */
    public function storeCredentials(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();
        $isAjax   = $this->isAjax($request);

        // ✅ "solo_guardar" explícito desde UI
        $soloGuardar = ((string) $request->input('solo_guardar', '0') === '1');

        if ($cuentaId !== '') {
            try { $this->cleanupExpiredHistory((string) $cuentaId, 200); } catch (\Throwable) {}
        }

        $data = $request->validate([
            'rfc'          => ['required', 'string', 'min:12', 'max:13'],
            'alias'        => ['nullable', 'string', 'max:190'],
            'cer'          => ['nullable', 'file'],
            'key'          => ['nullable', 'file'],
            'key_password' => ['nullable', 'string', 'min:1'],
            'pwd'          => ['nullable', 'string', 'min:1'],
        ]);

        // RFC / alias / password
        $rfcUpper = strtoupper(trim((string) $data['rfc']));
        $alias    = isset($data['alias']) ? trim((string) $data['alias']) : null;
        $password = trim((string) ($data['key_password'] ?? $data['pwd'] ?? ''));

        $cer = $request->file('cer');
        $key = $request->file('key');

        // -------------------------------------------------
        // Validación de extensiones (defensivo)
        // -------------------------------------------------
        if ($cer && strtolower((string) $cer->getClientOriginalExtension()) !== 'cer') {
            $msg = 'El archivo .cer no es válido.';
            return $isAjax
                ? response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 422)
                : redirect()->route('cliente.sat.index')->with('error', $msg);
        }

        if ($key && strtolower((string) $key->getClientOriginalExtension()) !== 'key') {
            $msg = 'El archivo .key no es válido.';
            return $isAjax
                ? response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 422)
                : redirect()->route('cliente.sat.index')->with('error', $msg);
        }

        // -------------------------------------------------
        // ✅ AUTO-SOLO_GUARDAR (blindaje backend)
        // - Si faltan archivos => NO validar
        // - Si falta password => NO validar (evita loop UI)
        // -------------------------------------------------
        if (!$cer || !$key) {
            $soloGuardar = true;
        }
        if (!$soloGuardar && $password === '') {
            $soloGuardar = true;
        }

        try {
            // =========================================================
            // Upsert SIEMPRE (guarda lo que venga)
            // =========================================================
            $cred = $this->service->upsertCredentials(
                $cuentaId,
                $rfcUpper,
                $cer,
                $key,
                $password
            );

            // Asegura conexión
            try { $cred->setConnection('mysql_clientes'); } catch (\Throwable) {}

            if ($alias !== null && $alias !== '') {
                $cred->razon_social = $alias;
            }

            // =========================================================
            // ✅ VALIDACIÓN (solo cuando aplica)
            // =========================================================
            $didValidate     = false;
            $okValidacion    = false;   // default
            $validationError = null;

            if (!$soloGuardar) {
                $didValidate = true;
                try {
                    $okValidacion = (bool) $this->service->validateCredentials($cred);
                } catch (\Throwable $ex) {
                    $okValidacion    = false;
                    $validationError = $ex->getMessage();

                    Log::warning('[SAT:CSD] Validación lanzó excepción', [
                        'trace_id'  => $trace,
                        'cuenta_id' => $cuentaId,
                        'rfc'       => $rfcUpper,
                        'ex'        => $validationError,
                    ]);
                }
            }

            // =========================================================
            // Persistir flags si columnas existen (una sola detección)
            // + “has_files/has_csd” si existen (mejora UX)
            // =========================================================
            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                $cols = [];
                try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }

                $has = static function (string $c) use ($cols): bool {
                    return in_array($c, $cols, true);
                };

                $hasEstatus     = $has('estatus');
                $hasValidado    = $has('validado');
                $hasValidatedAt = $has('validated_at');
                $hasCsdError    = $has('csd_error');
                $hasErrorMsg    = $has('error_msg');

                // flags extra (si tu tabla los tiene)
                $hasHasFiles = $has('has_files');
                $hasHasCsd   = $has('has_csd');

                // ¿se cargaron archivos en esta petición?
                $filesUploaded = (bool) ($cer && $key);

                if ($soloGuardar || !$didValidate) {
                    // Guardado sin validar
                    if ($hasEstatus)     $cred->estatus      = 'pending';
                    if ($hasValidado)    $cred->validado     = 0;
                    if ($hasValidatedAt) $cred->validated_at = null;

                    if ($hasHasFiles && $filesUploaded) $cred->has_files = 1;
                    if ($hasHasCsd   && $filesUploaded) $cred->has_csd   = 1;

                    if ($hasCsdError) $cred->csd_error = null;
                    if ($hasErrorMsg) $cred->error_msg = null;
                } else {
                    // Validación ejecutada
                    if ($okValidacion) {
                        if ($hasEstatus)     $cred->estatus      = 'valid';
                        if ($hasValidado)    $cred->validado     = 1;
                        if ($hasValidatedAt) $cred->validated_at = now();

                        if ($hasHasFiles) $cred->has_files = 1;
                        if ($hasHasCsd)   $cred->has_csd   = 1;

                        if ($hasCsdError) $cred->csd_error = null;
                        if ($hasErrorMsg) $cred->error_msg = null;
                    } else {
                        if ($hasEstatus)     $cred->estatus      = 'invalid';
                        if ($hasValidado)    $cred->validado     = 0;
                        if ($hasValidatedAt) $cred->validated_at = null;

                        // si subió archivos, sigue siendo “tiene archivos” aunque inválidos
                        if ($hasHasFiles && $filesUploaded) $cred->has_files = 1;
                        if ($hasHasCsd   && $filesUploaded) $cred->has_csd   = 1;

                        $msgErr = $validationError ?: 'Validación CSD no exitosa. Verifica .cer, .key y contraseña.';
                        if ($hasCsdError) $cred->csd_error = $msgErr;
                        if ($hasErrorMsg) $cred->error_msg = $msgErr;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[SAT:CSD] No se pudieron persistir flags de validación', [
                    'trace_id'  => $trace,
                    'cuenta_id' => $cuentaId,
                    'rfc'       => $rfcUpper,
                    'ex'        => $e->getMessage(),
                ]);
            }

            $cred->save();

            // =========================================================
            // Respuesta / mensajes
            // =========================================================
            if ($soloGuardar || !$didValidate) {
                $msg = 'Credenciales guardadas correctamente.';
                $ok  = true;
            } else {
                if ($okValidacion) {
                    $msg = 'CSD validado correctamente.';
                    $ok  = true;
                } else {
                    $msg = 'Archivos CSD guardados, pero la validación no fue exitosa. Verifica el .cer, .key y la contraseña.';
                    $ok  = true; // guardado sí ocurrió
                }
            }

            if ($isAjax) {
                return response()->json([
                    'ok'               => $ok,
                    'validated'        => ($didValidate && $okValidacion) ? true : false,
                    'did_validate'     => $didValidate ? 1 : 0,
                    'validation_error' => $validationError,
                    'trace_id'         => $trace,
                    'rfc'              => (string) ($cred->rfc ?? $rfcUpper),
                    'alias'            => $cred->razon_social ?? null,
                    'solo_guardar'     => $soloGuardar ? 1 : 0,
                    'msg'              => $msg,
                ], 200);
            }

            if ($soloGuardar || ($didValidate && $okValidacion)) {
                return redirect()->route('cliente.sat.index')->with('ok', $msg);
            }

            return redirect()->route('cliente.sat.index')->with('info', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:storeCredentials] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'ex'        => $e->getMessage(),
            ]);

            $msg = 'Error guardando credenciales SAT.';
            return $isAjax
                ? response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 500)
                : redirect()->route('cliente.sat.index')->with('error', $msg);
        }
    }

   /* ===================================================
    *  LIMPIEZA EXPIRADAS + CAP HISTORIAL (SOT + defensivo)
    * =================================================== */
    private function cleanupExpiredHistory(string $cuentaId, int $keep = 0): void
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return;

        $now  = Carbon::now();
        $conn = (new SatDownload())->getConnectionName() ?? 'mysql_clientes';
        $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');

        // Detecta columnas para no romper en ambientes con schema distinto
        $table  = (new SatDownload())->getTable();
        $schema = Schema::connection($conn);

        $cols = [];
        try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }

        $has = static function (string $c) use ($cols): bool {
            return in_array($c, $cols, true);
        };

        $colCuenta   = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
        $colTipo     = $has('tipo') ? 'tipo' : null;
        $colCreated  = $has('created_at') ? 'created_at' : null;
        $colExpires  = $has('expires_at') ? 'expires_at' : null;
        $colZipPath  = $has('zip_path') ? 'zip_path' : ($has('path') ? 'path' : ($has('file_path') ? 'file_path' : null));

        // Si no hay columna cuenta, no podemos garantizar multi-tenant => NO borrar
        if (!$colCuenta) return;

        // Helper: borrar zip de forma segura
        $deleteZip = function ($row) use ($disk, $colZipPath): void {
            if (!$colZipPath) return;

            try {
                $p = (string) data_get($row, $colZipPath);
                $p = ltrim($p, '/');
                if ($p === '') return;

                if (Storage::disk($disk)->exists($p)) {
                    Storage::disk($disk)->delete($p);
                }
            } catch (\Throwable) {}
        };

        // Base query (sin vault/bóveda)
        $base = DB::connection($conn)->table($table)
            ->where($colCuenta, $cuentaId);

        if ($colTipo) {
            $base->whereRaw('LOWER(COALESCE(' . $colTipo . ',"")) NOT IN ("vault","boveda")');
        }

        // =========================================================
        // 1) eliminar expiradas
        // - Si existe expires_at: expires_at <= now
        // - Si NO existe expires_at: created_at <= now - 12h
        // (si tampoco hay created_at, NO hacemos limpieza por tiempo)
        // =========================================================
        $expiredRows = collect();

        try {
            $q = clone $base;

            if ($colExpires) {
                $q->whereNotNull($colExpires)
                ->where($colExpires, '<=', $now->toDateTimeString());
            } elseif ($colCreated) {
                $q->whereNotNull($colCreated)
                ->where($colCreated, '<=', $now->copy()->subHours(12)->toDateTimeString());
            } else {
                $q = null; // no hay forma de determinar expiración
            }

            if ($q) {
                $select = ['id'];
                if ($colZipPath) $select[] = $colZipPath;

                $expiredRows = $q->select($select)->get();
            }
        } catch (\Throwable) {
            $expiredRows = collect();
        }

        foreach ($expiredRows as $r) {
            $deleteZip($r);

            try {
                DB::connection($conn)->table($table)->where('id', (string) $r->id)->delete();
            } catch (\Throwable) {}
        }

        // =========================================================
        // 2) cap historial (mantén los más recientes)
        // - requiere created_at; si no, no aplicamos cap (evita borrar mal)
        // =========================================================
        if ($keep > 0 && $colCreated) {
            try {
                $idsToKeep = (clone $base)
                    ->orderByDesc($colCreated)
                    ->limit((int) $keep)
                    ->pluck('id')
                    ->map(fn ($v) => (string) $v)
                    ->all();

                if (count($idsToKeep) > 0) {
                    $oldRows = (clone $base)
                        ->whereNotIn('id', $idsToKeep)
                        ->select(array_values(array_filter(['id', $colZipPath])))
                        ->get();

                    foreach ($oldRows as $r) {
                        $deleteZip($r);

                        try {
                            DB::connection($conn)->table($table)->where('id', (string) $r->id)->delete();
                        } catch (\Throwable) {}
                    }
                }
            } catch (\Throwable) {}
        }
    }

    /* ===================================================
    *  CARRITO (SESSION + OPTIONAL DB sat_cart_items)
    * =================================================== */

    private function cartKeyForCuenta(string $cuentaId): string
    {
        $cuentaId = trim((string) $cuentaId);

        // Evita keys raras / gigantes si llega vacío o con espacios
        if ($cuentaId === '') {
            return 'sat_cart_guest';
        }

        // Normaliza para key estable (sin espacios)
        $safe = preg_replace('/\s+/', '', $cuentaId) ?: $cuentaId;

        return 'sat_cart_' . $safe;
    }

    private function getCartIds(string $cuentaId): array
    {
        $cuentaId = trim((string) $cuentaId);
        $key      = $this->cartKeyForCuenta($cuentaId);

        // 1) Session (source of truth inmediato)
        $ids = session($key, []);
        $ids = array_values(array_filter(array_map('strval', (array) $ids)));
        $ids = array_values(array_unique($ids));

        // Si no hay cuenta válida, solo regresa lo de sesión
        if ($cuentaId === '') {
            return $ids;
        }

        // 2) Optional: DB sat_cart_items (merge defensivo)
        try {
            $conn = 'mysql_clientes';
            $schema = Schema::connection($conn);

            if ($schema->hasTable('sat_cart_items')) {
                $dbIds = DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->pluck('download_id')
                    ->map(fn($v) => (string) $v)
                    ->all();

                $dbIds = array_values(array_filter(array_map('strval', (array) $dbIds)));

                // Merge + unique
                if (!empty($dbIds)) {
                    $ids = array_values(array_unique(array_merge($ids, $dbIds)));

                    // Opcional: sincroniza sesión para que UI no “parpadee”
                    session([$key => $ids]);
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        return $ids;
    }

    private function putCartIdsForCuenta(string $cuentaId, array $ids): void
    {
        $cuentaId = trim((string) $cuentaId);
        $key      = $this->cartKeyForCuenta($cuentaId);

        // normaliza + limpia
        $ids = array_values(array_filter(array_map(static function ($v) {
            $s = trim((string) $v);
            return $s !== '' ? $s : null;
        }, (array) $ids)));

        $ids = array_values(array_unique($ids));

        // 1) Session (SOT inmediato para UI)
        session([$key => $ids]);

        // 2) Optional: persist DB sat_cart_items (best-effort)
        if ($cuentaId === '') return;

        try {
            $conn   = 'mysql_clientes';
            $schema = Schema::connection($conn);

            if (!$schema->hasTable('sat_cart_items')) return;

            // Columnas mínimas esperadas: cuenta_id + download_id
            if (!$schema->hasColumn('sat_cart_items', 'cuenta_id') || !$schema->hasColumn('sat_cart_items', 'download_id')) {
                return;
            }

            DB::connection($conn)->transaction(function () use ($conn, $cuentaId, $ids) {

                // Borra el carrito actual de esa cuenta y vuelve a insertar (simple y consistente)
                DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->delete();

                if (empty($ids)) return;

                $now = now();
                $rows = [];

                $hasCreated = false;
                $hasUpdated = false;

                try {
                    $schema = Schema::connection($conn);
                    $hasCreated = $schema->hasColumn('sat_cart_items', 'created_at');
                    $hasUpdated = $schema->hasColumn('sat_cart_items', 'updated_at');
                } catch (\Throwable) {
                    $hasCreated = false;
                    $hasUpdated = false;
                }

                foreach ($ids as $downloadId) {
                    $row = [
                        'cuenta_id'   => $cuentaId,
                        'download_id' => (string) $downloadId,
                    ];

                    if ($hasCreated) $row['created_at'] = $now;
                    if ($hasUpdated) $row['updated_at'] = $now;

                    $rows[] = $row;
                }

                // Inserta en chunks por si el carrito crece
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::connection($conn)->table('sat_cart_items')->insert($chunk);
                }
            });
        } catch (\Throwable) {
            // no-op: no rompemos flujo si DB no está lista o falla
        }
    }

    /**
     * ✅ Agregar 1 descarga al carrito.
     * Payload: {download_id}
     */
    public function cartAdd(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim((string) $this->cuId());

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'download_id' => ['required', 'string', 'max:64'],
        ]);

        $id = trim((string) $data['download_id']);
        if ($id === '') {
            return response()->json(['ok' => false, 'msg' => 'download_id inválido.', 'trace_id' => $trace], 422);
        }

        /** @var SatDownload|null $dl */
        $dl = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->first();

        if (!$dl) {
            return response()->json(['ok' => false, 'msg' => 'Descarga no encontrada.', 'trace_id' => $trace], 404);
        }

        // No permite vault/boveda (case-insensitive)
        $tipoLow = strtolower(trim((string) ($dl->tipo ?? '')));
        if (in_array($tipoLow, ['vault', 'boveda'], true)) {
            return response()->json(['ok' => false, 'msg' => 'No puedes agregar un item de bóveda al carrito.', 'trace_id' => $trace], 422);
        }

        // No permite items expirados si NO están pagados
        $paid = !empty($dl->paid_at);
        try {
            $expRaw = $dl->expires_at ?? $dl->disponible_hasta ?? null;
            $exp    = null;

            if ($expRaw instanceof \Illuminate\Support\Carbon) {
                $exp = $expRaw->copy();
            } elseif (!empty($expRaw)) {
                $exp = \Illuminate\Support\Carbon::parse((string) $expRaw);
            }

            if (!$paid && $exp && $exp->isPast()) {
                return response()->json(['ok' => false, 'msg' => 'Este paquete ya expiró.', 'trace_id' => $trace], 422);
            }
        } catch (\Throwable) {
            // no-op
        }

        // Ready-ish: deja agregar solo si está listo o pagado (evita carrito con pendientes)
        $stRaw = (string) ($dl->status ?? $dl->estado ?? $dl->sat_status ?? '');
        $st    = strtolower(trim($stRaw));

        $isReady =
            $paid
            || in_array($st, ['done', 'ready', 'paid', 'downloaded', 'listo', 'completed', 'finalizado', 'finished', 'terminado'], true);

        if (!$isReady) {
            return response()->json(['ok' => false, 'msg' => 'Aún no está listo para pago.', 'trace_id' => $trace], 422);
        }

        // Session SOT + (optional) DB SOT dentro del helper
        $ids = $this->getCartIds($cuentaId);
        if (!in_array($id, $ids, true)) $ids[] = $id;

        // ✅ Centraliza persistencia (session + sat_cart_items si existe)
        $this->putCartIdsForCuenta($cuentaId, $ids);

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($ids),
            'ids'      => $ids,
        ], 200);
    }

    /**
     * ✅ Quitar 1 descarga del carrito.
     * Payload: {download_id}
     *
     * Mejoras:
     * - Normaliza ids (trim + unique)
     * - SOT: sesión (siempre)
     * - Si existe sat_cart_items: elimina también en DB (no bloqueante)
     * - Respuesta consistente + invalid cuando aplica
     */
    public function cartRemove(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim((string) $this->cuId());

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'download_id' => ['required', 'string', 'max:64'],
        ]);

        $id = trim((string) $data['download_id']);
        if ($id === '') {
            return response()->json(['ok' => false, 'msg' => 'download_id inválido.', 'trace_id' => $trace], 422);
        }

        // SOT: sesión
        $cur = $this->getCartIds($cuentaId);
        $cur = $this->normalizeCartIds($cur);

        if (!in_array($id, $cur, true)) {
            // id no estaba: respondemos ok igual (idempotente)
            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'count'    => count($cur),
                'ids'      => $cur,
            ], 200);
        }

        $ids = array_values(array_diff($cur, [$id]));
        $this->putCartIdsForCuenta($cuentaId, $ids);

        // Persistencia opcional DB (no bloqueante)
        try {
            $conn = 'mysql_clientes';
            if (Schema::connection($conn)->hasTable('sat_cart_items')) {
                DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->where('download_id', $id)
                    ->delete();
            }
        } catch (\Throwable $e) {
            Log::debug('[SAT:cartRemove] DB delete no-bloqueante', [
                'trace_id' => $trace,
                'cuenta_id' => $cuentaId,
                'download_id' => $id,
                'err' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($ids),
            'ids'      => $ids,
        ], 200);
    }

    /**
     * ✅ Agregar múltiples ids.
     * Payload: {ids: []} o {download_ids: []}
     *
     * Mejoras:
     * - Normaliza/filtra ids vacíos
     * - Valida existencia + pertenece a cuenta + NO vault/boveda
     * - Merge con SOT (sesión)
     * - Persiste a DB sat_cart_items con UPSERT batch (si existe) en UNA operación
     * - Respuesta incluye invalid/missing para debug UI
     */
    public function cartBulkAdd(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim((string) $this->cuId());

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'ids'             => ['nullable', 'array'],
            'ids.*'           => ['nullable', 'string', 'max:64'],
            'download_ids'    => ['nullable', 'array'],
            'download_ids.*'  => ['nullable', 'string', 'max:64'],
        ]);

        $raw  = $data['ids'] ?? $data['download_ids'] ?? [];
        $idsIn = $this->normalizeCartIds((array) $raw);

        if (!count($idsIn)) {
            return response()->json(['ok' => false, 'msg' => 'ids requerido.', 'trace_id' => $trace], 422);
        }

        // ------------------------------------------------------------
        // Validar que existan + pertenezcan a cuenta + NO vault/boveda
        // ------------------------------------------------------------
        $validIds = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereIn('id', $idsIn)
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
            ->pluck('id')
            ->map(static fn($v) => (string) $v)
            ->all();

        $validIds = $this->normalizeCartIds($validIds);

        $validSet = array_fill_keys($validIds, true);
        $invalid  = array_values(array_filter($idsIn, static fn($id) => !isset($validSet[$id])));

        if (!count($validIds)) {
            return response()->json([
                'ok'        => false,
                'msg'       => 'Ningún id es válido para carrito.',
                'trace_id'  => $trace,
                'requested' => count($idsIn),
                'invalid'   => $invalid,
            ], 422);
        }

        // ------------------------------------------------------------
        // Merge con carrito actual (SOT sesión)
        // ------------------------------------------------------------
        $cur    = $this->normalizeCartIds($this->getCartIds($cuentaId));
        $before = count($cur);

        $merged = $this->normalizeCartIds(array_merge($cur, $validIds));
        $this->putCartIdsForCuenta($cuentaId, $merged);

        // ------------------------------------------------------------
        // Persistencia opcional en DB sat_cart_items (batch UPSERT)
        // ------------------------------------------------------------
        try {
            $this->cartDbUpsert($cuentaId, $validIds);
        } catch (\Throwable $e) {
            Log::debug('[SAT:cartBulkAdd] DB upsert no-bloqueante', [
                'trace_id' => $trace,
                'cuenta_id' => $cuentaId,
                'valid' => count($validIds),
                'err' => $e->getMessage(),
            ]);
        }

        $added = max(0, count($merged) - $before);

        return response()->json([
            'ok'        => true,
            'trace_id'  => $trace,
            'requested' => count($idsIn),
            'valid'     => count($validIds),
            'invalid'   => $invalid,
            'added'     => $added,
            'count'     => count($merged),
            'ids'       => $merged,
        ], 200);
    }

    /**
     * ✅ Vaciar carrito (sesión + opcional DB sat_cart_items)
     *
     * Mejoras:
     * - SOT: sesión primero
     * - Borra DB si existe tabla (no bloqueante)
     * - Respuesta consistente
     */
    public function cartClear(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim((string) $this->cuId());

        if ($cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        // SOT: sesión
        $this->putCartIdsForCuenta($cuentaId, []);

        // Persistencia opcional DB (no bloqueante)
        try {
            $conn = 'mysql_clientes';
            if (Schema::connection($conn)->hasTable('sat_cart_items')) {
                DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->delete();
            }
        } catch (\Throwable $e) {
            Log::debug('[SAT:cartClear] DB delete no-bloqueante', [
                'trace_id' => $trace,
                'cuenta_id' => $cuentaId,
                'err' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => 0,
            'ids'      => [],
        ], 200);
    }

    /**
     * ✅ Obtener carrito.
     *
     * Mejoras:
     * - SOT: sesión
     * - Si hay sat_cart_items: reconcilia session ∪ db (no perder items)
     * - Filtra ids: que existan en sat_downloads y NO vault/boveda
     * - Limpia “fantasmas” en DB (no bloqueante)
     */
    public function cartGet(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = trim((string) $this->cuId());

        if ($cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        // SOT: sesión
        $ids = $this->normalizeCartIds($this->getCartIds($cuentaId));

        try {
            $conn = 'mysql_clientes';

            if (Schema::connection($conn)->hasTable('sat_cart_items')) {
                $dbIds = DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->pluck('download_id')
                    ->map(static fn($v) => (string) $v)
                    ->values()
                    ->all();

                $dbIds  = $this->normalizeCartIds($dbIds);
                $merged = $this->normalizeCartIds(array_merge($ids, $dbIds));

                if (count($merged)) {
                    $valid = SatDownload::query()
                        ->where('cuenta_id', $cuentaId)
                        ->whereIn('id', $merged)
                        ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                        ->pluck('id')
                        ->map(static fn($v) => (string) $v)
                        ->all();

                    $ids = $this->normalizeCartIds($valid);
                    $this->putCartIdsForCuenta($cuentaId, $ids);

                    // limpia DB de fantasmas (no bloqueante)
                    try {
                        $ghosts = array_values(array_diff($dbIds, $ids));
                        if (count($ghosts)) {
                            DB::connection($conn)->table('sat_cart_items')
                                ->where('cuenta_id', $cuentaId)
                                ->whereIn('download_id', $ghosts)
                                ->delete();
                        }
                    } catch (\Throwable $e) {
                        Log::debug('[SAT:cartGet] DB ghost cleanup no-bloqueante', [
                            'trace_id' => $trace,
                            'cuenta_id' => $cuentaId,
                            'ghosts' => count($ghosts ?? []),
                            'err' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // si no hay nada, limpia DB (no bloqueante)
                    try {
                        DB::connection($conn)->table('sat_cart_items')
                            ->where('cuenta_id', $cuentaId)
                            ->delete();
                    } catch (\Throwable $e) {
                        Log::debug('[SAT:cartGet] DB clear no-bloqueante', [
                            'trace_id' => $trace,
                            'cuenta_id' => $cuentaId,
                            'err' => $e->getMessage(),
                        ]);
                    }
                    $ids = [];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[SAT:cartGet] Reconcilio no-bloqueante falló', [
                'trace_id' => $trace,
                'cuenta_id' => $cuentaId,
                'err' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($ids),
            'ids'      => $ids,
        ], 200);
    }

    /* ===================================================
    *  CHARTS (placeholder)
    * =================================================== */

    public function charts(Request $request): JsonResponse
    {
        return response()->json([
            'ok'     => true,
            'rows'   => [],
            'totals' => [
                'count' => 0,
                'sub'   => 0.0,
                'iva'   => 0.0,
                'tot'   => 0.0,
            ],
        ]);
    }

    /* ==========================================================
    *  ZIP: resolver ubicación (disk + path)
    * ========================================================== */

    protected function resolveDownloadZipLocation(SatDownload $download): array
    {
        $meta = $this->decodeMetaToArray($download->meta ?? null);

        $zipDisk = (string) ($meta['zip_disk'] ?? '');
        if ($zipDisk === '') {
            $zipDisk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
        }

        $zipPath = ltrim((string) ($download->zip_path ?? ''), '/');
        if ($zipPath !== '') {
            try {
                if (Storage::disk($zipDisk)->exists($zipPath)) {
                    return [$zipDisk, $zipPath];
                }
            } catch (\Throwable) {}
        }

        $cuentaId  = (string) ($download->cuenta_id ?? '');
        $packageId = (string) ($download->package_id ?? $download->packageId ?? $download->package ?? '');

        if ($cuentaId !== '' && $packageId !== '') {
            $candPkg = "sat/packages/{$cuentaId}/pkg_{$packageId}.zip";
            try {
                if (Storage::disk($zipDisk)->exists($candPkg)) return [$zipDisk, $candPkg];
            } catch (\Throwable) {}
        }

        $id = (string) ($download->id ?? '');
        if ($cuentaId !== '' && $id !== '') {
            $candLegacy = "sat/packages/{$cuentaId}/pkg_{$id}.zip";
            try {
                if (Storage::disk($zipDisk)->exists($candLegacy)) return [$zipDisk, $candLegacy];
            } catch (\Throwable) {}
        }

        try {
            $needle = $packageId !== '' ? $packageId : $id;
            $found  = $this->findZipByListing($zipDisk, $cuentaId, $needle);
            if ($found !== '') return [$zipDisk, ltrim($found, '/')];
        } catch (\Throwable) {}

        return ['', ''];
    }

    protected function findZipByListing(string $disk, string $cuentaId, string $downloadId): string
    {
        $dirs = [
            'packages/' . $cuentaId,
            'sat/packages/' . $cuentaId,
            'sat/packages/' . $cuentaId . '/done',
            'sat/packages/' . $cuentaId . '/paid',
            'sat/demo',
        ];

        foreach ($dirs as $dir) {
            $dir = trim($dir, '/');
            try {
                if (!Storage::disk($disk)->exists($dir)) continue;

                $files = [];
                try { $files = array_merge($files, Storage::disk($disk)->files($dir)); } catch (\Throwable) {}
                try { $files = array_merge($files, Storage::disk($disk)->allFiles($dir)); } catch (\Throwable) {}

                if (!count($files)) continue;

                foreach ($files as $f) {
                    $f = ltrim((string) $f, '/');
                    if ($f === '') continue;

                    $low = strtolower($f);
                    if (!str_ends_with($low, '.zip')) continue;

                    if ($downloadId !== '' && stripos($f, $downloadId) !== false) {
                        return $f;
                    }
                }
            } catch (\Throwable) {}
        }

        return '';
    }

   /**
     * ==========================================================
     *  BÓVEDA: BACKFILL MASIVO
     * ==========================================================
     * - Sólo mueve downloads DONE/PAID/DOWNLOADED que NO sean tipo vault/boveda
     * - Opcional: ?tipo=emitidos|recibidos
     * - Robusto:
     *   - Si no existe sat_vault_files => aborta (evita "moved=0 failed=todo")
     *   - Procesa por chunks para no explotar memoria
     */
    public function vaultBackfill(Request $request): JsonResponse
    {
        $trace = $this->trace();

        $user = $this->cu();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'Sesión expirada.', 'trace_id' => $trace], 401);
        }

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');
        $cuentaId = trim($cuentaId);

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 422);
        }

        if (!$this->vaultIsActiveForAccount($cuenta)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La bóveda no está activa para esta cuenta.',
                'trace_id' => $trace,
            ], 422);
        }

        // Tabla destino obligatoria
        $vaultTableOk = false;
        try { $vaultTableOk = Schema::connection('mysql_clientes')->hasTable('sat_vault_files'); } catch (\Throwable) {}
        if (!$vaultTableOk) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No existe la tabla sat_vault_files. No se puede ejecutar el backfill.',
                'trace_id' => $trace,
            ], 422);
        }

        $onlyTipo = strtolower(trim((string) $request->get('tipo', '')));
        $onlyTipo = in_array($onlyTipo, ['emitidos','recibidos'], true) ? $onlyTipo : '';

        $moved   = 0;
        $skipped = 0;
        $failed  = 0;
        $total   = 0;

        // Query base (sin traer todo a memoria)
        $q = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('LOWER(COALESCE(status,"")) IN ("done","paid","downloaded")')
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda","bóveda")')
            ->orderBy('id', 'asc');

        if ($onlyTipo !== '') {
            $q->whereRaw('LOWER(COALESCE(tipo,"")) = ?', [$onlyTipo]);
        }

        try {
            $q->chunkById(250, function ($chunk) use ($cuentaId, $trace, &$moved, &$skipped, &$failed, &$total) {
                foreach ($chunk as $dl) {
                    $total++;

                    try {
                        // ya existe?
                        $exists = false;
                        try {
                            $exists = DB::connection('mysql_clientes')->table('sat_vault_files')
                                ->where('cuenta_id', $cuentaId)
                                ->where('source', 'sat_download')
                                ->where('source_id', (string) $dl->id)
                                ->exists();
                        } catch (\Throwable) {
                            $exists = false;
                        }

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        // mover/copy + upsert registro
                        $this->moveZipToVault($dl);

                        // verificar resultado
                        $ok = false;
                        try {
                            $ok = DB::connection('mysql_clientes')->table('sat_vault_files')
                                ->where('cuenta_id', $cuentaId)
                                ->where('source', 'sat_download')
                                ->where('source_id', (string) $dl->id)
                                ->exists();
                        } catch (\Throwable) {
                            $ok = false;
                        }

                        if ($ok) $moved++;
                        else $failed++;

                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('[SAT:vaultBackfill] Falló backfill de un download', [
                            'trace_id'    => $trace,
                            'cuenta_id'   => $cuentaId,
                            'download_id' => (string) ($dl->id ?? ''),
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('[SAT:vaultBackfill] chunkById error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'err'       => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => 'Error ejecutando backfill.',
            ], 500);
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'total'    => $total,
            'moved'    => $moved,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'msg'      => 'Backfill finalizado.',
        ]);
    }


    /* ==========================================================
    *  BÓVEDA: mover ZIP (robusto y consistente por RFC)
    * ========================================================== */
    protected function moveZipToVault(SatDownload $download): void
    {
        $conn = 'mysql_clientes';

        try {
            $cuentaId = trim((string) ($download->cuenta_id ?? $download->account_id ?? ''));
             if ($cuentaId === '') return;

            $tipo = strtolower(trim((string) ($download->tipo ?? '')));
            if (!in_array($tipo, ['emitidos', 'recibidos', 'ambos'], true)) return;

            // 1) Resolver origen (disk + path) de forma robusta
            [$srcDisk, $srcPath] = $this->resolveDownloadZipLocation($download);
            $srcDisk = trim((string) $srcDisk);
            $srcPath = ltrim(trim((string) $srcPath), '/');

            if ($srcDisk === '' || $srcPath === '') {
                Log::warning('[SAT:Vault] No se pudo resolver ZIP para mover a bóveda', [
                    'download_id' => (string) ($download->id ?? ''),
                    'cuenta_id'   => $cuentaId,
                    'zip_path'    => (string) ($download->zip_path ?? ''),
                ]);
                return;
            }

            // 2) Validar existencia del archivo origen
            try {
                if (!Storage::disk($srcDisk)->exists($srcPath)) {
                    Log::warning('[SAT:Vault] ZIP origen no existe', [
                        'cuenta_id'   => $cuentaId,
                        'download_id' => (string) ($download->id ?? ''),
                        'src_disk'    => $srcDisk,
                        'src_path'    => $srcPath,
                    ]);
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('[SAT:Vault] No se pudo validar existencia ZIP origen', [
                    'cuenta_id'   => $cuentaId,
                    'download_id' => (string) ($download->id ?? ''),
                    'src_disk'    => $srcDisk,
                    'src_path'    => $srcPath,
                    'err'         => $e->getMessage(),
                ]);
                return;
            }

            // 3) Si ya existe registro en bóveda y el archivo está, no duplicar
            $vaultTableOk = false;
            try { $vaultTableOk = Schema::connection($conn)->hasTable('sat_vault_files'); } catch (\Throwable) {}

            if ($vaultTableOk) {
                try {
                    $exists = DB::connection($conn)->table('sat_vault_files')
                        ->where('cuenta_id', $cuentaId)
                        ->where('source', 'sat_download')
                        ->where('source_id', (string) $download->id)
                        ->first();

                    if ($exists && !empty($exists->path)) {
                        $d = (string) ($exists->disk ?? (config('filesystems.disks.sat_vault') ? 'sat_vault' : 'local'));
                        $p = ltrim((string) $exists->path, '/');

                        if ($p !== '' && Storage::disk($d)->exists($p)) {
                            return; // ya está en bóveda
                        }
                    }
                } catch (\Throwable) {
                    // si falla, reintentamos copiar
                }
            }

            // 4) Validar cuota (si aplica)
            $cuentaObj  = $this->fetchCuentaObjForVault($cuentaId);
            $storage    = $this->buildVaultStorageSummary($cuentaId, $cuentaObj);
            $quotaBytes = (int) ($storage['quota_bytes'] ?? 0);
            $usedBytes  = (int) ($storage['used_bytes'] ?? 0);
            if ($quotaBytes <= 0) return;

            $srcBytes = 0;
            try { $srcBytes = (int) Storage::disk($srcDisk)->size($srcPath); } catch (\Throwable) {}
            if ($srcBytes <= 0) return;

            if (($usedBytes + $srcBytes) > $quotaBytes) {
                Log::info('[SAT:Vault] Sin espacio suficiente para copiar ZIP', [
                    'cuenta_id' => $cuentaId,
                    'need'      => $srcBytes,
                    'used'      => $usedBytes,
                    'quota'     => $quotaBytes,
                ]);
                return;
            }

            // 5) Disk destino (prioridad sat_vault -> vault -> private -> mismo origen)
            $vaultDisk = config('filesystems.disks.sat_vault')
                ? 'sat_vault'
                : (config('filesystems.disks.vault') ? 'vault' : (config('filesystems.disks.private') ? 'private' : $srcDisk));

            // 6) RFC normalizado y seguro para rutas
            $rfc = strtoupper(trim((string) ($download->rfc ?? '')));
            if ($rfc === '') $rfc = 'XAXX010101000';
            $rfcSafe = preg_replace('/[^A-Z0-9&Ñ]/u', '', $rfc) ?: 'XAXX010101000';

            // 7) Ruta destino consistente por RFC
            $now     = Carbon::now();
            $baseDir = "vault/{$cuentaId}/{$rfcSafe}";
            $destName = 'SAT_' . $rfcSafe . '_' . $now->format('Ymd_His') . '_' . (string) $download->id . '.zip';
            $destPath = $baseDir . '/' . $destName;

            // Asegura dir (silencioso si disk no lo requiere)
            try {
                if (!Storage::disk($vaultDisk)->exists($baseDir)) {
                    Storage::disk($vaultDisk)->makeDirectory($baseDir);
                }
            } catch (\Throwable) {}

            // 8) Copia robusta (si mismo disk y soporta move, úsalo; si no, stream copy)
            $ok = false;

            if ($vaultDisk === $srcDisk) {
                try {
                    // move: reduce I/O; si falla, intentamos stream
                    $ok = (bool) Storage::disk($vaultDisk)->move($srcPath, $destPath);
                } catch (\Throwable) {
                    $ok = false;
                }
            }

            if (!$ok) {
                $read = null;
                try { $read = Storage::disk($srcDisk)->readStream($srcPath); } catch (\Throwable) {}
                if (!$read) return;

                try {
                    $ok = (bool) Storage::disk($vaultDisk)->writeStream($destPath, $read);
                } catch (\Throwable) {
                    $ok = false;
                } finally {
                    if (is_resource($read)) {
                        try { fclose($read); } catch (\Throwable) {}
                    }
                }
            }

            if (!$ok) return;

            // 9) Validar bytes en destino (y rollback del archivo si quedó “vacío”)
            $bytes = 0;
            try { $bytes = (int) Storage::disk($vaultDisk)->size($destPath); } catch (\Throwable) {}
            if ($bytes <= 0) {
                try { Storage::disk($vaultDisk)->delete($destPath); } catch (\Throwable) {}
                return;
            }

            // 10) Upsert en sat_vault_files (defensivo por columnas)
            if (!$vaultTableOk) return;

            $schema = null;
            $cols   = [];
            try {
                $schema = Schema::connection($conn);
                $cols   = $schema->getColumnListing('sat_vault_files');
            } catch (\Throwable) {
                $schema = null;
                $cols   = [];
            }

            $hasCol = static function (string $c) use ($cols): bool {
                 return in_array($c, $cols, true);
             };

            // cuenta_id vs account_id (defensivo)
           $vaultCuentaCol = $hasCol('cuenta_id') ? 'cuenta_id' : ($hasCol('account_id') ? 'account_id' : null);
            if (!$vaultCuentaCol) return;
 
             $data = [
                $vaultCuentaCol => $cuentaId,
                 'source'    => 'sat_download',
                 'source_id' => (string) $download->id,
             ];


            if ($hasCol('rfc'))      $data['rfc']      = $rfcSafe;
            if ($hasCol('filename')) $data['filename'] = $destName;
            if ($hasCol('path'))     $data['path']     = $destPath;
            if ($hasCol('disk'))     $data['disk']     = $vaultDisk;
            if ($hasCol('mime'))     $data['mime']     = 'application/zip';
            if ($hasCol('bytes'))    $data['bytes']    = $bytes;

            $nowTs = Carbon::now()->toDateTimeString();
            if ($hasCol('updated_at')) $data['updated_at'] = $nowTs;

            $existing = DB::connection($conn)->table('sat_vault_files')
                ->where($vaultCuentaCol, $cuentaId)
                ->where('source', 'sat_download')
                ->where('source_id', (string) $download->id)
                ->first();

            if ($existing && isset($existing->id)) {
                DB::connection($conn)->table('sat_vault_files')->where('id', $existing->id)->update($data);
            } else {
                if ($hasCol('created_at')) $data['created_at'] = $nowTs;
                DB::connection($conn)->table('sat_vault_files')->insert($data);
            }

            Log::info('[SAT:Vault] ZIP copiado a bóveda', [
                'cuenta_id'   => $cuentaId,
                'download_id' => (string) $download->id,
                'src_disk'    => $srcDisk,
                'src_path'    => $srcPath,
                'vault_disk'  => $vaultDisk,
                'vault_path'  => $destPath,
                'bytes'       => $bytes,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:Vault] Error moviendo ZIP a bóveda', [
                'download_id' => (string) ($download->id ?? ''),
                'cuenta_id'   => (string) ($download->cuenta_id ?? ''),
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /* ==========================================================
    *  ✅ COTIZADOR SAT: CALCULAR (JSON) + PDF
    * ========================================================== */

    public function quoteCalc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
            'xml_count'     => ['required', 'integer', 'min:1', 'max:50000000'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            // IVA flexible: 16 o 0.16 (igual que quick)
            'iva'           => ['nullable'],
            'iva_rate'      => ['nullable'],
            'unit_cost'     => ['nullable'], // ignorado (compat UI)
        ]);

        $xmlCount     = (int) $data['xml_count'];
        $discountCode = trim((string) ($data['discount_code'] ?? ''));

        // IVA flexible: 16 o 0.16
        $ivaRate = 16;
        $ivaRaw  = $data['iva'] ?? $data['iva_rate'] ?? 16;
        if (is_numeric($ivaRaw)) {
            $v = (float) $ivaRaw;
            $ivaRate = ($v > 1) ? (int) round($v) : (int) round($v * 100);
        }
        $ivaRate = max(0, min(16, $ivaRate));

        // Info de cuenta (encabezado / PDF)
        [$plan, $empresa] = $this->resolvePlanAndEmpresa($user, $cuentaId);

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        // Base = regla interna (cotizador “formal”)
        $base = round((float) $this->computeDownloadCostPhp($xmlCount), 2);

        $discountPct    = (float) $this->resolveDiscountPctForQuote($cuentaId, $discountCode);
        $discountPct    = max(0.0, min(100.0, $discountPct));

        $discountAmount = round($base * ($discountPct / 100), 2);
        $subtotal       = max(0, round($base - $discountAmount, 2));

        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . (int) $discountPct . '%.';

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'data'     => [
                'mode'           => 'quote',
                'folio'          => $folio,
                'generated_at'   => $generated->toIso8601String(),
                'valid_until'    => $validUntil->toDateString(),

                'plan'           => $plan,
                'cuenta_id'      => $cuentaId,
                'empresa'        => $empresa ?: '—',

                'xml_count'      => $xmlCount,
                'base'           => $base,

                'discount_code'  => $discountCode !== '' ? $discountCode : null,
                'discount_pct'   => (float) $discountPct,
                'discount_amount'=> $discountAmount,

                'subtotal'       => $subtotal,
                'iva_rate'       => $ivaRate,
                'iva_amount'     => $ivaAmount,
                'total'          => $total,

                'note'           => $note,
            ],
        ]);
    }

    /* ==========================================================
    *  ✅ CALCULADORA RÁPIDA (GUÍAS RÁPIDAS): CALCULAR (JSON) + PDF
    *  - NO solicita RFC
    *  - Usa lista de precios en Admin (mysql_admin) cuando exista
    * ========================================================== */

    public function quickCalc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
            // Compat UI
            'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'discount_code'       => ['nullable', 'string', 'max:64'],
            // IVA flexible: 16 o 0.16
            'iva'                 => ['nullable'],
            'iva_rate'            => ['nullable'],
        ]);

        $xmlCount = (int) (
            $data['xml_count']
            ?? $data['xml_count_estimated']
            ?? 0
        );

        if ($xmlCount <= 0) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'xml_count requerido.',
                'trace_id' => $trace,
            ], 422);
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));

        // IVA flexible: 16 o 0.16
        $ivaRate = 16;
        $ivaRaw  = $data['iva'] ?? $data['iva_rate'] ?? 16;

        if (is_numeric($ivaRaw)) {
            $v = (float) $ivaRaw;
            $ivaRate = ($v > 1) ? (int) round($v) : (int) round($v * 100);
        }
        $ivaRate = max(0, min(16, $ivaRate));

        // Info de cuenta (encabezado / PDF)
        [$plan, $empresa] = $this->resolvePlanAndEmpresa($user, $cuentaId);

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        // ✅ BASE desde Admin (si existe), con fallback seguro
        [$base, $priceNote, $priceSource] = $this->resolveAdminPriceForXml($xmlCount);
        $base = round((float) $base, 2);

        $discountPct    = (float) $this->resolveDiscountPctForQuote($cuentaId, $discountCode);
        $discountPct    = max(0.0, min(100.0, $discountPct));

        $discountAmount = round($base * ($discountPct / 100), 2);
        $subtotal       = max(0, round($base - $discountAmount, 2));

        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $priceNote !== '' ? $priceNote : $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . (int) $discountPct . '%.';
        if ($priceSource !== '') $note .= ' Fuente: ' . $priceSource . '.';

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'data'     => [
                'mode'         => 'quick',
                'folio'        => $folio,
                'generated_at' => $generated->toIso8601String(),
                'valid_until'  => $validUntil->toDateString(),

                'plan'         => $plan,
                'cuenta_id'    => $cuentaId,
                'empresa'      => $empresa ?: '—',

                'xml_count'    => $xmlCount,
                'base'         => $base,

                'discount_code'   => $discountCode !== '' ? $discountCode : null,
                'discount_pct'    => (float) $discountPct,
                'discount_amount' => $discountAmount,

                'subtotal'     => $subtotal,
                'iva_rate'     => $ivaRate,
                'iva_amount'   => $ivaAmount,
                'total'        => $total,

                'note'         => $note,
            ],
        ]);
    }

    // =========================
    // ✅ PATCH: quickPdf robusto
    // - acepta xml_count o xml_count_estimated
    // - acepta iva como 16 o 0.16 (igual que quickCalc)
    // - vista defensiva antes de DomPDF
    // =========================
    public function quickPdf(Request $request)
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = trim((string) $this->cuId());

        if (!$user || $cuentaId === '') {
            abort(401, 'Sesión expirada o cuenta inválida.');
        }

        $data = $request->validate([
            'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'discount_code'       => ['nullable', 'string', 'max:64'],
            'iva'                 => ['nullable'],
            'iva_rate'            => ['nullable'],
        ]);

        $xmlCount = (int) (
            $data['xml_count']
            ?? $data['xml_count_estimated']
            ?? 0
        );

        if ($xmlCount <= 0) {
            abort(422, 'xml_count requerido.');
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));

        // IVA flexible: 16 o 0.16
        $ivaRate = 16;
        $ivaRaw  = $data['iva'] ?? $data['iva_rate'] ?? 16;

        if (is_numeric($ivaRaw)) {
            $v = (float) $ivaRaw;
            $ivaRate = ($v > 1) ? (int) round($v) : (int) round($v * 100);
        }
        $ivaRate = max(0, min(16, $ivaRate));

        // Info de cuenta (encabezado / PDF)
        [$plan, $empresa] = $this->resolvePlanAndEmpresa($user, $cuentaId);

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        // ✅ BASE desde Admin (si existe), con fallback seguro
        [$base, $priceNote, $priceSource] = $this->resolveAdminPriceForXml($xmlCount);
        $base = round((float) $base, 2);

        $discountPct    = (float) $this->resolveDiscountPctForQuote($cuentaId, $discountCode);
        $discountPct    = max(0.0, min(100.0, $discountPct));

        $discountAmount = round($base * ($discountPct / 100), 2);
        $subtotal       = max(0, round($base - $discountAmount, 2));

        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $priceNote !== '' ? $priceNote : $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . (int) $discountPct . '%.';
        if ($priceSource !== '') $note .= ' Fuente: ' . $priceSource . '.';

        $payload = [
            'trace_id'        => $trace,
            'mode'            => 'quick',

            // ✅ Emisor / Branding (para PDF)
            'issuer'          => [
                'name'             => (string) (config('app.name') ?: 'Pactopia360'),
                'website'          => (string) (config('app.url') ?: ''),
                'email'            => (string) (config('mail.from.address') ?: 'notificaciones@pactopia.com'),
                'phone'            => (string) (config('services.pactopia.phone') ?: ''),

                // ✅ Tu logo (NO ruta Windows). DomPDF lo embebe con base64 en el Blade.
                'logo_public_path' => public_path('assets/client/logp360ligjt.png'),
            ],

            'folio'           => $folio,
            'generated_at'    => $generated,
            'valid_until'     => $validUntil,

            'plan'            => $plan,
            'cuenta_id'       => $cuentaId,
            'empresa'         => $empresa ?: '—',

            'xml_count'       => $xmlCount,
            'base'            => $base,

            'discount_code'   => $discountCode !== '' ? $discountCode : null,
            'discount_pct'    => (float) $discountPct,
            'discount_amount' => $discountAmount,

            'subtotal'        => $subtotal,
            'iva_rate'        => $ivaRate,
            'iva_amount'      => $ivaAmount,
            'total'           => $total,

            'note'            => $note,
        ];


        if (!View::exists('cliente.sat.pdf.quote')) {
            return response(
                'No existe la vista PDF: resources/views/cliente/sat/pdf/quote.blade.php (cliente.sat.pdf.quote).',
                501
            );
        }

        try {
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {

                // ✅ Huella (anti-tamper) para el PDF
                $payload['quote_hash'] = $this->quotePdfHash($payload);

                // ✅ Branding / emisor (para header)
                $payload['issuer'] = [
                     'name'    => (string) (config('app.name') ?: 'Pactopia360'),
                     'brand'   => 'Pactopia',
                     'website' => (string) (config('app.url') ?: ''),
                     'email'   => (string) (config('mail.from.address') ?: 'soporte@pactopia.com'),
                     'phone'   => (string) (config('services.pactopia.phone') ?: ''),

                    'logo_public_path' => public_path('assets/client/logp360ligjt.png'),
                 ];

                // ✅ DomPDF seguro y consistente
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cliente.sat.pdf.quote', $payload)
                    ->setPaper('letter', 'portrait')
                    ->setOptions([
                        'defaultFont'           => 'DejaVu Sans',
                        'isRemoteEnabled'       => false, // 🔒 evita cargar recursos externos
                        'isHtml5ParserEnabled'  => true,
                        'isPhpEnabled'          => false, // 🔒 no PHP dentro de la vista
                        'dpi'                   => 96,    // ✅ evita “gigante”
                        'fontHeightRatio'       => 1.0,
                    ]);

                $file = 'cotizacion_sat_rapida_' . $cuentaId . '_' . $generated->format('Ymd_His') . '.pdf';
                return $pdf->download($file);
            }
        } catch (\Throwable $e) {
            Log::error('[SAT:quickPdf] DomPDF error', [
                'trace_id' => $trace,
                'err'      => $e->getMessage(),
            ]);
        }


        return response(
            'No está disponible el generador PDF (DomPDF). Instala/activa barryvdh/laravel-dompdf.',
            501
        );
    }

    /**
     * ✅ Precio desde Admin (mysql_admin) por rangos / catálogo.
     * Retorna: [base_mxn, note, source]
     *
     * Estrategia:
     * - Detecta tabla posible
     * - Detecta columnas min/max + flat/unit
     * - Selecciona rango donde cae $xmlCount (primer match por min asc / prioridad)
     * - Si no encuentra: fallback computeDownloadCostPhp()
     *
     * Soporta valores numéricos tipo:
     * - 12500
     * - "12500.00"
     * - "$12,500.00" (se limpia)
     */
    private function resolveAdminPriceForXml(int $xmlCount): array
    {
    $n = max(0, (int) $xmlCount);
    if ($n <= 0) return [0.0, 'Sin documentos.', 'fallback'];

    $conn = 'mysql_admin';

    $tables = [
        'sat_download_price_ranges',
        'sat_download_prices',
        'sat_price_ranges',
        'sat_prices',
        'sat_price_catalog',
        'sat_cfdi_price_ranges',
    ];

    // helper: limpia $ y comas
    $toNum = static function ($v): float {
        if (is_numeric($v)) return (float) $v;
        $s = trim((string) $v);
        if ($s === '') return 0.0;
        $s = str_replace(['$', ',', ' '], ['', '', ''], $s);
        return is_numeric($s) ? (float) $s : 0.0;
    };

    try {
        $schema = Schema::connection($conn);

        $tableFound = null;
        foreach ($tables as $t) {
            try {
                if ($schema->hasTable($t)) { $tableFound = $t; break; }
            } catch (\Throwable) {
                // sigue buscando
            }
        }

        if (!$tableFound) {
            $base = (float) $this->computeDownloadCostPhp($n);
            return [$base, '', 'fallback'];
        }

        $cols = [];
        try { $cols = $schema->getColumnListing($tableFound); } catch (\Throwable) { $cols = []; }

        $pickCol = static function (array $candidates, array $cols): ?string {
            foreach ($candidates as $c) {
                if (in_array($c, $cols, true)) return $c;
            }
            return null;
        };

        $colMin = $pickCol(['min_xml','min','min_docs','min_count','desde','from'], $cols);
        $colMax = $pickCol(['max_xml','max','max_docs','max_count','hasta','to'], $cols);

        $colFlat = $pickCol(['price','price_mxn','base_mxn','amount','amount_mxn','flat_price','flat_mxn','total','total_mxn'], $cols);
        $colUnit = $pickCol(['unit_price','unit_price_mxn','unit_mxn','per_xml','per_doc','rate','rate_mxn'], $cols);

        $colNote   = $pickCol(['note','label','descripcion','description','name'], $cols);
        $colActive = $pickCol(['is_active','active','enabled','estatus','status'], $cols);
        $colSort   = $pickCol(['sort','priority','orden','order'], $cols);

        $q = DB::connection($conn)->table($tableFound);

        if ($colActive) {
            $q->where(function ($w) use ($colActive) {
                $w->where($colActive, 1)
                    ->orWhere($colActive, true)
                    ->orWhereRaw('LOWER(COALESCE(' . $colActive . ', "")) IN ("1","true","active","activo","enabled","on")');
            });
        }

        if ($colSort) $q->orderBy($colSort, 'asc');
        if ($colMin)  $q->orderBy($colMin, 'asc');

        $rows = $q->get();

        // Elige el primer rango que cumpla (min<=n<=max)
        $best = null;
        foreach ($rows as $row) {
            $minOk = true;
            $maxOk = true;

            if ($colMin && isset($row->{$colMin}) && is_numeric($row->{$colMin})) {
                $minOk = $n >= (int) $row->{$colMin};
            }
            if ($colMax && isset($row->{$colMax}) && is_numeric($row->{$colMax})) {
                $maxOk = $n <= (int) $row->{$colMax};
            }

            if ($minOk && $maxOk) { $best = $row; break; }
        }

        if (!$best) {
            $base = (float) $this->computeDownloadCostPhp($n);
            return [$base, '', 'fallback'];
        }

        $flat = 0.0;
        if ($colFlat && isset($best->{$colFlat})) {
            $flat = $toNum($best->{$colFlat});
        }

        $unit = 0.0;
        if ($colUnit && isset($best->{$colUnit})) {
            $unit = $toNum($best->{$colUnit});
        }

        if ($flat > 0)      $base = $flat;
        elseif ($unit > 0)  $base = $unit * $n;
        else                $base = (float) $this->computeDownloadCostPhp($n);

        $note = '';
        if ($colNote && isset($best->{$colNote}) && trim((string) $best->{$colNote}) !== '') {
            $note = trim((string) $best->{$colNote});
        }

        if ($note === '') {
            $minTxt = ($colMin && isset($best->{$colMin}) && is_numeric($best->{$colMin})) ? (int) $best->{$colMin} : null;
            $maxTxt = ($colMax && isset($best->{$colMax}) && is_numeric($best->{$colMax})) ? (int) $best->{$colMax} : null;

            if ($minTxt !== null && $maxTxt !== null) $note = "Precio Admin ({$minTxt}-{$maxTxt} XML)";
            elseif ($minTxt !== null)                  $note = "Precio Admin (>= {$minTxt} XML)";
            elseif ($maxTxt !== null)                  $note = "Precio Admin (<= {$maxTxt} XML)";
            else                                       $note = 'Precio Admin';
        }

        return [(float) $base, $note, $tableFound];
    } catch (\Throwable $e) {
        Log::warning('[SAT:resolveAdminPriceForXml] Fallback por excepción', [
            'xml_count' => $n,
            'err'       => $e->getMessage(),
        ]);

        $base = (float) $this->computeDownloadCostPhp($n);
        return [$base, '', 'fallback'];
    }
    }


    /**
     * Wrapper de compatibilidad (SOT)
     * Algunos flujos llaman resolveDiscountPctForQuote()
     */
    private function resolveDiscountPctForQuote(string $cuentaId, ?string $code): float
    {
    return (float) $this->resolveDiscountPctFromCode($cuentaId, $code);
    }


    /**
     * ✅ Descuento % por código
     * Retorna 0..100
     *
     * Fuentes (en orden):
     * 1) cuentas_cliente (código + % asociado a la cuenta)
     * 2) discount_codes (tabla general)
     * 3) config('services.sat.discount_codes')
     *
     * Soporta:
     * - pct como 10 o 0.10
     * - discount_rate como 0.10
     * - active como 1/true/"active"/"on"/etc
     * - valid_until null o >= now
     */
    private function resolveDiscountPctFromCode(string $cuentaId, ?string $code): float
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') return 0.0;

        $conn = 'mysql_clientes';

        // helper: convierte 10 o 0.10 a 10 (0..100)
        $toPct = static function ($v): float {
            if (!is_numeric($v)) return 0.0;
            $f = (float) $v;

            // Si viene como 0.10 -> 10
            if ($f > 0 && $f <= 1) $f = $f * 100;

            // clamp
            if ($f < 0) $f = 0;
            if ($f > 100) $f = 100;

            return (float) $f;
        };

        // helper: compara active robusto
        $isActive = static function ($v): bool {
            if (is_bool($v)) return $v;
            if (is_numeric($v)) return ((int)$v) === 1;
            $s = strtolower(trim((string)$v));
            return in_array($s, ['1','true','active','activo','enabled','on','yes','si','sí'], true);
        };

        // ==========================================================
        // 1) cuentas_cliente (código por cuenta)
        // ==========================================================
        try {
            $schema = Schema::connection($conn);

            if ($schema->hasTable('cuentas_cliente')) {
                $cols = [];
                try { $cols = $schema->getColumnListing('cuentas_cliente'); } catch (\Throwable) { $cols = []; }

                $codeCols = array_values(array_filter([
                    in_array('discount_code',  $cols, true) ? 'discount_code'  : null,
                    in_array('codigo_socio',   $cols, true) ? 'codigo_socio'   : null,
                    in_array('codigo_cliente', $cols, true) ? 'codigo_cliente' : null,
                    in_array('coupon_code',    $cols, true) ? 'coupon_code'    : null,
                    in_array('coupon',         $cols, true) ? 'coupon'         : null,
                    in_array('cupon',          $cols, true) ? 'cupon'          : null,
                ]));

                $pctCols = array_values(array_filter([
                    in_array('discount_pct',   $cols, true) ? 'discount_pct'   : null, // 10 o 0.10
                    in_array('descuento_pct',  $cols, true) ? 'descuento_pct'  : null,
                    in_array('discount_rate',  $cols, true) ? 'discount_rate'  : null, // 0.10
                    in_array('descuento_rate', $cols, true) ? 'descuento_rate' : null,
                    in_array('pct',            $cols, true) ? 'pct'            : null,
                ]));

                if ($codeCols && $pctCols) {
                    $row = DB::connection($conn)->table('cuentas_cliente')
                        ->where('id', $cuentaId)
                        ->first();

                    if ($row) {
                        foreach ($codeCols as $cc) {
                            $stored = strtoupper(trim((string) ($row->{$cc} ?? '')));
                            if ($stored !== '' && $stored === $code) {
                                foreach ($pctCols as $pc) {
                                    $pct = $toPct($row->{$pc} ?? 0);
                                    if ($pct > 0) return $pct;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        // ==========================================================
        // 2) discount_codes (tabla)
        // ==========================================================
        try {
            $schema = Schema::connection($conn);

            if ($schema->hasTable('discount_codes')) {
                $cols = [];
                try { $cols = $schema->getColumnListing('discount_codes'); } catch (\Throwable) { $cols = []; }

                $codeCol = in_array('code', $cols, true) ? 'code' : (in_array('codigo', $cols, true) ? 'codigo' : null);

                // pct puede llamarse pct o discount_pct, rate puede venir como 0.10
                $pctCol  = in_array('pct', $cols, true) ? 'pct'
                        : (in_array('discount_pct', $cols, true) ? 'discount_pct'
                        : (in_array('descuento_pct', $cols, true) ? 'descuento_pct' : null));

                $rateCol = in_array('discount_rate', $cols, true) ? 'discount_rate'
                        : (in_array('rate', $cols, true) ? 'rate'
                        : (in_array('descuento_rate', $cols, true) ? 'descuento_rate' : null));

                $actCol  = in_array('active', $cols, true) ? 'active'
                        : (in_array('is_active', $cols, true) ? 'is_active'
                        : (in_array('enabled', $cols, true) ? 'enabled'
                        : (in_array('status', $cols, true) ? 'status'
                        : (in_array('estatus', $cols, true) ? 'estatus' : null))));

                $valCol  = in_array('valid_until', $cols, true) ? 'valid_until'
                        : (in_array('expires_at', $cols, true) ? 'expires_at'
                        : (in_array('expires', $cols, true) ? 'expires' : null));

                if ($codeCol && ($pctCol || $rateCol)) {
                    $q = DB::connection($conn)->table('discount_codes')
                        ->whereRaw('UPPER(' . $codeCol . ') = ?', [$code]);

                    // activo robusto
                    if ($actCol) {
                        $q->where(function ($w) use ($actCol) {
                            $w->where($actCol, 1)
                            ->orWhere($actCol, true)
                            ->orWhereRaw('LOWER(COALESCE(' . $actCol . ', "")) IN ("1","true","active","activo","enabled","on","yes","si","sí")');
                        });
                    }

                    // vigencia
                    if ($valCol) {
                        $q->where(function ($w) use ($valCol) {
                            $w->whereNull($valCol)->orWhere($valCol, '>=', now());
                        });
                    }

                    $row = $q->first();
                    if ($row) {
                        // prioridad: pct > rate
                        if ($pctCol) {
                            $pct = $toPct($row->{$pctCol} ?? 0);
                            if ($pct > 0) return $pct;
                        }
                        if ($rateCol) {
                            $pct = $toPct($row->{$rateCol} ?? 0);
                            if ($pct > 0) return $pct;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        // ==========================================================
        // 3) config
        // ==========================================================
        try {
            $map = (array) config('services.sat.discount_codes', []);
            foreach ($map as $k => $v) {
                if (strtoupper(trim((string) $k)) === $code) {
                    $pct = $toPct($v);
                    if ($pct > 0) return $pct;
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        return 0.0;
    }


    private function pricingNoteForXml(int $xmlCount): string
    {
        $n = max(0, $xmlCount);

        if ($n <= 0) return 'Sin documentos.';
        if ($n <= 5000) return 'Tarifa: $1 MXN por documento (1–5,000).';
        if ($n <= 25000) return 'Tarifa: $0.08 MXN por documento (5,001–25,000).';
        if ($n <= 40000) return 'Tarifa: $0.05 MXN por documento (25,001–40,000).';
        if ($n <= 100000) return 'Tarifa: $0.03 MXN por documento (40,001–100,000).';
        if ($n <= 500000) return 'Tarifa plana: $12,500 MXN (100,001–500,000).';
        if ($n <= 1000000) return 'Tarifa plana: $18,500 MXN (500,001–1,000,000).';
        if ($n <= 2000000) return 'Tarifa plana: $25,000 MXN (1,000,001–2,000,000).';
        if ($n <= 3000000) return 'Tarifa plana: $31,000 MXN (2,000,001–3,000,000).';

        return 'Tarifa: $0.01 MXN por documento (> 3,000,000).';
    }

    /* ==========================================================
    *  Helpers: plan + empresa (evita duplicación en quote/quick/pdf)
    * ========================================================== */

    private function resolvePlanAndEmpresa($user, string $cuentaId): array
    {
        $plan    = 'FREE';
        $empresa = null;

        $cuenta = null;
        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->first();
            }
        } catch (\Throwable) {
            $cuenta = null;
        }

        // plan
        $planRaw = '';
        if ($cuenta && isset($cuenta->plan_actual)) {
            $planRaw = (string) $cuenta->plan_actual;
        } else {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c) && isset($c->plan_actual)) $planRaw = (string) $c->plan_actual;
            } catch (\Throwable) {}
        }
        $plan = strtoupper(trim($planRaw)) ?: 'FREE';

        // empresa
        if ($cuenta) {
            foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                if (isset($cuenta->{$col}) && trim((string) $cuenta->{$col}) !== '') {
                    $empresa = trim((string) $cuenta->{$col});
                    break;
                }
            }
        }

        if (!$empresa) {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c)) {
                    foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                        if (isset($c->{$col}) && trim((string) $c->{$col}) !== '') {
                            $empresa = trim((string) $c->{$col});
                            break;
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        return [$plan, ($empresa && $empresa !== '' ? $empresa : '—')];
    }

     // ============================================================
    // ✅ REGISTRO EXTERNO / INVITE + DASHBOARD STATS (MEJORADO)
    // ============================================================
    // Mejoras clave incluidas:
    // - Invite: trace_id + validación + rate limit (simple) + subject/lineas consistentes
    // - Signed URL: params normalizados + expiración única + respuestas JSON/HTML consistentes
    // - Register fallback: FORM COMPLETO (RFC + ALIAS + CSD cer/key + password + confirm) para que coincida con externalStore
    // - Store: validaciones endurecidas (.cer/.key), control de tamaño, normalización RFC, meta merge robusto, respuesta amigable
    // - Stats: agrega "rfcs" únicos, totales, breakdown, series; normalización robusta; evita queries pesadas; selecciona columnas mínimo
    // - Helpers: resolveCuentaIdFromQuery(), externalRenderHtml(), stats helper functions
    // ============================================================

    /* ============================================================
    * ✅ REGISTRO EXTERNO / INVITE
    * ============================================================ */

    public function externalInvite(Request $request): JsonResponse
    {
        $trace = $this->trace();

        $email = trim((string) $request->input('email', ''));
        $note  = trim((string) $request->input('note', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Correo inválido.',
                'trace_id' => $trace,
            ], 422);
        }

        // ✅ Rate limit simple por IP (evita spam)
        // Nota: si ya usas Throttle middleware, puedes quitar esto.
        try {
            $ip    = (string) $request->ip();
            $key   = 'sat_ext_invite:' . sha1($ip);
            $hits  = (int) cache()->get($key, 0);

            if ($hits >= 10) {
                return response()->json([
                    'ok'       => false,
                    'msg'      => 'Demasiadas solicitudes. Intenta más tarde.',
                    'trace_id' => $trace,
                ], 429);
            }
            cache()->put($key, $hits + 1, now()->addMinutes(10));
        } catch (\Throwable) {
            // no-op
        }

        // Contexto de cuenta (obligatorio)
        $user = $this->cu();
        if (!$user) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada.',
                'trace_id' => $trace,
            ], 401);
        }

        $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');
        $cuentaId = trim($cuentaId);

        if ($cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo determinar la cuenta que invita.',
                'trace_id' => $trace,
            ], 422);
        }

        $inviteId  = (string) Str::ulid();       // correlación
        $expiresAt = now()->addDays(7);          // una sola expiración (consistente)

        // URL firmada GET (register)
        try {
            $signedUrl = URL::temporarySignedRoute(
                'cliente.sat.external.register',
                $expiresAt,
                [
                    'email'     => $email,
                    'cuenta_id' => $cuentaId,
                    'inv'       => $inviteId,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[SAT][externalInvite] signedRoute error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'email'     => $email,
                'err'       => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo generar el link de invitación. Revisa rutas/URL.',
                'trace_id' => $trace,
            ], 500);
        }

        // Contexto para correo
        $cuenta = $user?->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $accountName = (string) ($cuenta?->razon_social ?? $cuenta?->nombre ?? $user?->name ?? 'Pactopia360');
        $appName     = (string) (config('app.name') ?: 'Pactopia360');
        $subject     = $appName . ' · Invitación para registrar RFC/CSD (SAT)';

        // Mensaje simple
        $lines = [];
        $lines[] = "Hola,";
        $lines[] = "";
        $lines[] = "Se te invitó desde {$accountName} para registrar tu RFC y tu CSD (archivos .cer/.key) en {$appName}.";
        $lines[] = "";
        $lines[] = "Enlace (válido hasta " . $expiresAt->format('Y-m-d H:i') . "):";
        $lines[] = $signedUrl;
        $lines[] = "";
        if ($note !== '') {
            $lines[] = "Nota:";
            $lines[] = $note;
            $lines[] = "";
        }
        $lines[] = "Si no esperabas este correo, ignóralo.";

        try {
            Mail::raw(implode("\n", $lines), function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('[SAT][externalInvite] mail error', [
                'trace_id' => $trace,
                'email'    => $email,
                'err'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'Se generó el link, pero falló el envío de correo. Revisa configuración MAIL.',
                'url'      => $signedUrl, // útil para debug
                'trace_id' => $trace,
            ], 500);
        }

        return response()->json([
            'ok'       => true,
            'msg'      => 'Invitación enviada.',
            'url'      => $signedUrl,
            'exp'      => $expiresAt->toIso8601String(),
            'trace_id' => $trace,
        ]);
    }

    /**
     * ==========================================================
     * REGISTRO EXTERNO POR ZIP (FASE 1 – CAPTURA)
     * ==========================================================
     * Objetivo:
     * - Aceptar solicitud desde dashboard
     * - Validar sesión y cuenta
     * - Registrar intento externo tipo "zip"
     * - NO procesa ZIP aún (fase 2)
     * - Retorna 200 OK para frontend
     */
   public function externalZipRegister(Request $request): \Illuminate\Http\JsonResponse
{
    // =========================================================
    // REGISTRO ZIP FIEL EXTERNO
    // Guarda en external_fiel_uploads (mysql_clientes)
    //
    // ✅ En producción: external_fiel_uploads.account_id es BIGINT NOT NULL
    // ✅ En producción: NO existe columna cuenta_id en external_fiel_uploads
    // ✅ El RFC externo NO debe existir como cuenta en accounts
    //
    // Solución:
    // - Resolver SIEMPRE $accountId BIGINT por el CLIENTE LOGUEADO
    // - Guardar RFC externo como dato (external_fiel_uploads.rfc)
    // - Mantener $cuentaId UUID solo para carpeta storage/auditoría
    // =========================================================

    $t0 = microtime(true);

    $conn  = 'mysql_clientes';
    $table = 'external_fiel_uploads';

    // -----------------------------
    // Inputs (acepta alternos)
    // -----------------------------
    $rfc = strtoupper(trim((string) $request->input('rfc', $request->input('rfc_externo', ''))));

    $pass = trim((string) $request->input(
        'fiel_pass',
        $request->input('fiel_password', $request->input('password', ''))
    ));

    $ref = trim((string) $request->input(
        'reference',
        $request->input('ref', $request->input('referencia', ''))
    ));

    $notes = trim((string) $request->input('notes', $request->input('nota', '')));

    // Opcionales (si el form los manda)
    $emailExterno = trim((string) $request->input('email_externo', $request->input('email', '')));
    $razonSocial  = trim((string) $request->input('razon_social', $request->input('razonSocial', '')));

    if ($rfc === '' || !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
        return response()->json(['ok' => false, 'msg' => 'RFC inválido.'], 422);
    }
    if ($pass === '') {
        return response()->json(['ok' => false, 'msg' => 'Contraseña FIEL requerida.'], 422);
    }

    // fallback: algunos forms mandan "archivo_zip"
    if (!$request->hasFile('zip') && $request->hasFile('archivo_zip')) {
        $request->files->set('zip', $request->file('archivo_zip'));
    }

    if (!$request->hasFile('zip') || !$request->file('zip') || !$request->file('zip')->isValid()) {
        return response()->json(['ok' => false, 'msg' => 'Archivo ZIP inválido.'], 422);
    }

    $zip = $request->file('zip');

    if (strtolower((string) $zip->getClientOriginalExtension()) !== 'zip') {
        return response()->json(['ok' => false, 'msg' => 'El archivo debe ser ZIP.'], 422);
    }

    // -----------------------------
    // Resolver account_id BIGINT del CLIENTE LOGUEADO (OBLIGATORIO)
    // -----------------------------
    $accountId = null;

    try {
        $u = $request->user();

        // Intentos de identificación del usuario logueado
        $loginEmail    = null;
        $loginUserCode = null;

        try { $loginEmail    = isset($u->email) ? trim((string) $u->email) : null; } catch (\Throwable) {}
        try { $loginUserCode = isset($u->user_code) ? trim((string) $u->user_code) : null; } catch (\Throwable) {}

        // 1) Preferente: user_code (si existe)
        if (is_string($loginUserCode) && $loginUserCode !== '') {
            $accountId = \DB::connection($conn)->table('accounts')
                ->where('user_code', $loginUserCode)
                ->value('id');
        }

        // 2) Fallback: correo_contacto = email del usuario
        if (!$accountId && is_string($loginEmail) && $loginEmail !== '') {
            $accountId = \DB::connection($conn)->table('accounts')
                ->where('correo_contacto', $loginEmail)
                ->value('id');
        }

        // 3) Fallback adicional: si el usuario trae account_id numérico (raro pero posible)
        if (!$accountId) {
            $maybe = null;
            try { $maybe = $u->account_id ?? null; } catch (\Throwable) {}
            if (is_scalar($maybe) && ctype_digit((string) $maybe)) {
                $accountId = (int) $maybe;
            }
        }
    } catch (\Throwable) {
        $accountId = null;
    }

    if (!$accountId) {
        \Log::warning('external_fiel_account_resolve_failed', [
            'rfc'   => $rfc,
            'email' => $request->user()?->email ?? null,
            'ip'    => $request->ip(),
            'ua'    => (string) $request->userAgent(),
        ]);

        return response()->json([
            'ok'  => false,
            'msg' => 'No se pudo resolver tu cuenta cliente. Cierra sesión e inicia de nuevo o contacta soporte.',
        ], 422);
    }

    // -----------------------------
    // Resolver cuentaId UUID SOLO para storage/auditoría
    // -----------------------------
    $cuentaId = '';
    try {
        $u = $request->user();
        if ($u) {
            if (isset($u->cuenta_id) && is_string($u->cuenta_id) && trim($u->cuenta_id) !== '') $cuentaId = trim((string) $u->cuenta_id);
            elseif (isset($u->account_id) && is_string($u->account_id) && trim($u->account_id) !== '') $cuentaId = trim((string) $u->account_id);
            elseif (isset($u->cuenta) && is_string($u->cuenta) && trim($u->cuenta) !== '') $cuentaId = trim((string) $u->cuenta);
        }
    } catch (\Throwable) {}

    if ($cuentaId === '') {
        try {
            $sid = (string) session('cuenta_id', session('account_id', ''));
            if (trim($sid) !== '') $cuentaId = trim($sid);
        } catch (\Throwable) {}
    }

    if ($cuentaId === '') {
        $cuentaId = trim((string) $request->input('cuenta_id', $request->input('cuenta', '')));
    }

    if ($cuentaId === '' || !preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $cuentaId)) {
        $cuentaId = (string) \Illuminate\Support\Str::uuid();
    }

    // -----------------------------
    // Guardar ZIP (storage)
    // -----------------------------
    $disk = 'public';
    $dir  = "fiel/external/{$cuentaId}";
    $name = 'FIEL-' . preg_replace('/[^A-Z0-9]/', '', $rfc) . '-' . strtoupper(substr(sha1((string) microtime(true)), 0, 12)) . '.zip';

    try {
        $path = $zip->storeAs($dir, $name, $disk);
        if (!$path) {
            return response()->json(['ok' => false, 'msg' => 'No se pudo guardar el ZIP.'], 500);
        }
    } catch (\Throwable $e) {
        \Log::error('external_fiel_zip_store_failed', [
            'account_id' => (int) $accountId,
            'cuenta_id'  => $cuentaId,
            'rfc'        => $rfc,
            'e'          => $e->getMessage(),
        ]);

        return response()->json(['ok' => false, 'msg' => 'Error al guardar el ZIP.'], 500);
    }

    // -----------------------------
    // Insertar en external_fiel_uploads (mysql_clientes)
    // -----------------------------
    try {
        $schema = \Schema::connection($conn);
        if (!$schema->hasTable($table)) {
            return response()->json(['ok' => false, 'msg' => 'No existe la tabla external_fiel_uploads.'], 500);
        }

        $token = bin2hex(random_bytes(32));

        $meta = [
            'source'         => 'external_zip_register',
            'ip'             => $request->ip(),
            'ua'             => (string) $request->userAgent(),
            'notes'          => $notes,
            'original_name'  => $zip->getClientOriginalName(),
            'cuenta_id_uuid' => $cuentaId, // auditoría/organización de storage
        ];

        $row = [
            // ✅ requerido en producción
            'account_id'    => (int) $accountId,

            // Opcionales si existen en tabla
            'email_externo' => ($emailExterno !== '' ? $emailExterno : null),
            'reference'     => ($ref !== '' ? $ref : null),
            'rfc'           => $rfc,
            'razon_social'  => ($razonSocial !== '' ? $razonSocial : null),

            'token'         => $token,

            'file_path'     => $path,
            'file_name'     => $zip->getClientOriginalName(),
            'file_size'     => (int) $zip->getSize(),
            'mime'          => (string) $zip->getClientMimeType(),

            'fiel_password' => \Crypt::encryptString($pass),
            'status'        => 'uploaded',
            'uploaded_at'   => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        // defensivo por columnas
        $safe = [];
        foreach ($row as $k => $v) {
            try {
                if ($schema->hasColumn($table, $k)) $safe[$k] = $v;
            } catch (\Throwable) {}
        }

        // meta si existe
        try {
            if ($schema->hasColumn($table, 'meta')) {
                $safe['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }
        } catch (\Throwable) {}

        $id = \DB::connection($conn)->table($table)->insertGetId($safe);

        \Log::info('external_fiel_zip_saved', [
            'id'         => $id,
            'account_id' => (int) $accountId,
            'cuenta_id'  => $cuentaId,
            'rfc'        => $rfc,
            'ms'         => (int) round((microtime(true) - $t0) * 1000),
        ]);

        return response()->json([
            'ok'   => true,
            'msg'  => 'FIEL externa cargada correctamente.',
            'data' => [
                'id'         => $id,
                'account_id' => (int) $accountId,
                'cuenta_id'  => $cuentaId, // solo referencia de storage
                'rfc'        => $rfc,
                'file_path'  => $path,
            ],
        ]);
    } catch (\Throwable $e) {
        \Log::error('external_fiel_upload_failed', [
            'cuenta_id'  => $cuentaId,
            'account_id' => (int) $accountId,
            'rfc'        => $rfc,
            'error'      => $e->getMessage(),
        ]);

        return response()->json(['ok' => false, 'msg' => 'Error al guardar FIEL externa.'], 500);
    }
}



public function externalZipList(Request $request): \Illuminate\Http\JsonResponse
{
    // =========================================================
    // LISTADO FIEL/ZIP EXTERNO (AUTH)
    // Tabla: mysql_clientes.external_fiel_uploads
    //
    // ✅ En producción: external_fiel_uploads.account_id BIGINT NOT NULL
    // Objetivo:
    // - Resolver SIEMPRE account_id BIGINT del cliente logueado
    // - Consultar en DB correcta (forzar DB.TABLE si hay desalineación de conexión)
    // - Respuesta PLANA (evita doble-wrapper en satFetchJson):
    //      { ok:true, rows:[], count:N }
    // =========================================================

    $t0 = microtime(true);

    $conn  = 'mysql_clientes';
    $table = 'external_fiel_uploads';

    $limit  = (int) ($request->query('limit', 50));
    $limit  = ($limit <= 0) ? 50 : min($limit, 200);

    $offset = (int) ($request->query('offset', 0));
    if ($offset < 0) $offset = 0;

    $status = trim((string) $request->query('status', ''));
    $q      = trim((string) $request->query('q', ''));

    // -----------------------------------------
    // 1) Resolver account_id BIGINT (obligatorio)
    // -----------------------------------------
    $accountId = $this->resolveClientAccountIdBigint($request);

    if (!$accountId || $accountId <= 0) {
        try {
            \Log::warning('external_zip_list_no_account', [
                'ip' => $request->ip(),
                'ua' => (string) $request->userAgent(),
                'user_id' => optional($request->user())->id ?? null,
                'user_email' => optional($request->user())->email ?? null,
                'session_account_id' => session('account_id'),
                'session_cuenta_id' => session('cuenta_id'),
            ]);
        } catch (\Throwable) {}

        return response()->json([
            'ok'    => false,
            'msg'   => 'No se pudo resolver tu cuenta cliente. Cierra sesión e inicia de nuevo o contacta soporte.',
            'rows'  => [],
            'count' => 0,
        ], 422);
    }

    // -----------------------------------------
    // 2) Query listado (FORZANDO DB.TABLE)
    // -----------------------------------------
    try {
        $schema = \Schema::connection($conn);

        // DB name que Laravel CREE que está usando para mysql_clientes
        $cfgDb = (string) (config("database.connections.{$conn}.database") ?? '');

        // DB name REAL que la conexión trae en runtime
        $runtimeDb = null;
        try {
            $runtimeDb = \DB::connection($conn)->selectOne('select database() as db')->db ?? null;
        } catch (\Throwable) {
            $runtimeDb = null;
        }

        // Forzar fully-qualified si tenemos cfgDb
        $tableFq = ($cfgDb !== '') ? ($cfgDb . '.' . $table) : $table;

        // Si schema dice que no existe, intentamos validar con raw count (por si schema cache / db distinta)
        if (!$schema->hasTable($table)) {
            try {
                \Log::error('external_zip_list_table_missing', [
                    'conn' => $conn,
                    'table' => $table,
                    'cfg_db' => $cfgDb,
                    'runtime_db' => $runtimeDb,
                ]);
            } catch (\Throwable) {}

            return response()->json([
                'ok'    => false,
                'msg'   => 'No existe la tabla external_fiel_uploads en la conexión mysql_clientes.',
                'rows'  => [],
                'count' => 0,
            ], 500);
        }

        // IMPORTANTE:
        // NO filtrar por RFC del account logueado (los ZIP externos pueden ser de otro RFC).
        $qb = \DB::connection($conn)
            ->table($tableFq)
            ->where('account_id', (int) $accountId);

        if ($status !== '') {
            $qb->where('status', $status);
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $like = "%{$q}%";
                $w->where('rfc', 'like', $like)
                  ->orWhere('file_name', 'like', $like)
                  ->orWhere('reference', 'like', $like)
                  ->orWhere('email_externo', 'like', $like)
                  ->orWhere('razon_social', 'like', $like);
            });
        }

        $count = (int) (clone $qb)->count();

        $rows = $qb
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'id',
                'account_id',
                'email_externo',
                'reference',
                'rfc',
                'razon_social',
                'file_path',
                'file_name',
                'file_size',
                'mime',
                'uploaded_at',
                'status',
                'created_at',
            ]);

        // Normalizar a array (por si el front espera objetos planos)
        $rowsArr = [];
        foreach ($rows as $r) $rowsArr[] = (array) $r;

        $ms = (int) round((microtime(true) - $t0) * 1000);

        try {
            \Log::info('external_zip_list_ok', [
                'account_id' => (int) $accountId,
                'count'      => $count,
                'rows'       => count($rowsArr),
                'limit'      => $limit,
                'offset'     => $offset,
                'status'     => $status,
                'q'          => $q,
                'ms'         => $ms,
                'cfg_db'     => $cfgDb,
                'runtime_db' => $runtimeDb,
                'table_fq'   => $tableFq,
            ]);
        } catch (\Throwable) {}

        // ✅ Respuesta PLANA (satFetchJson ya envuelve con {ok,status,data})
        return response()->json([
            'ok'    => true,
            'rows'  => $rowsArr,
            'count' => $count,
        ], 200);
    } catch (\Throwable $e) {
        try {
            \Log::error('external_zip_list_failed', [
                'account_id' => (int) $accountId,
                'error'      => $e->getMessage(),
            ]);
        } catch (\Throwable) {}

        return response()->json([
            'ok'    => false,
            'msg'   => 'Error al consultar lista de ZIP externos.',
            'rows'  => [],
            'count' => 0,
        ], 500);
    }
}


/**
 * Resolver account_id BIGINT del cliente logueado.
 * Fuentes:
 * 1) user()->account_id / user()->id (si es bigint)
 * 2) session(account_id)
 * 3) fallback por email: mysql_clientes.accounts.correo_contacto
 */
private function resolveClientAccountIdBigint(Request $request): ?int
{
    $conn = 'mysql_clientes';

    // 1) User
    try {
        $u = $request->user();
        if ($u) {
            // account_id directo
            if (isset($u->account_id) && is_scalar($u->account_id) && ctype_digit((string) $u->account_id)) {
                return (int) $u->account_id;
            }

            // id del user si fuese bigint (algunos setups usan accounts como auth)
            if (isset($u->id) && is_scalar($u->id) && ctype_digit((string) $u->id)) {
                return (int) $u->id;
            }

            // relaciones típicas
            try {
                if (isset($u->account) && $u->account && isset($u->account->id) && ctype_digit((string) $u->account->id)) {
                    return (int) $u->account->id;
                }
            } catch (\Throwable) {}
        }
    } catch (\Throwable) {}

    // 2) Session
    try {
        $sid = session('account_id', null);
        if (is_scalar($sid) && ctype_digit((string) $sid)) {
            return (int) $sid;
        }
    } catch (\Throwable) {}

    // 3) Fallback por email contra accounts.correo_contacto
    try {
        $email = null;
        $u = $request->user();
        if ($u && isset($u->email) && is_string($u->email) && trim($u->email) !== '') {
            $email = trim($u->email);
        }

        if ($email) {
            $id = \DB::connection($conn)
                ->table('accounts')
                ->where('correo_contacto', $email)
                ->value('id');

            if (is_scalar($id) && ctype_digit((string) $id)) {
                return (int) $id;
            }
        }
    } catch (\Throwable) {}

    return null;
}



    public function externalRegister(Request $request)
    {
        // Hard-check signature
        if (!method_exists($request, 'hasValidSignature') || !$request->hasValidSignature()) {
            return response('Firma inválida o expirada.', 403);
        }

        $email    = trim((string) $request->query('email', ''));
        $cuentaId = trim((string) $request->query('cuenta_id', ''));
        $inv      = trim((string) $request->query('inv', ''));

        if ($email === '' || $cuentaId === '') {
            return response('Parámetros incompletos.', 422);
        }

        // Si existe view, úsalo
        if (View::exists('cliente.sat.external.register')) {
            return view('cliente.sat.external.register', [
                'email'    => $email,
                'cuentaId' => $cuentaId,
                'inv'      => $inv,
            ]);
        }

        // ✅ Fallback HTML: FORM COMPLETO (RFC + ALIAS + CSD + confirm) acorde a externalStore
        $postUrl = '';
        try {
            $postUrl = URL::temporarySignedRoute(
                'cliente.sat.external.store',
                now()->addDays(7),
                [
                    'email'     => $email,
                    'cuenta_id' => $cuentaId,
                    'inv'       => $inv,
                ]
            );
        } catch (\Throwable) {
            $postUrl = '';
        }

        if ($postUrl === '') {
            return response('No se pudo preparar el formulario (ruta POST).', 500);
        }

        $safeEmail    = e($email);
        $safeCuentaId = e($cuentaId);
        $safeInv      = e($inv);
        $safePostUrl  = e($postUrl);
        $csrf         = e(csrf_token());

        $html = <<<HTML
    <!doctype html>
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pactopia360 · Registro externo</title>
    <style>
        :root{
            --bg:#0b1220; --card:#101a33; --bd:rgba(255,255,255,.08);
            --txt:#e7eefc; --mut:rgba(231,238,252,.72);
            --in:rgba(255,255,255,.04); --inbd:rgba(255,255,255,.12);
            --pri:#3b82f6; --warnbg:rgba(245,158,11,.12); --warnbd:rgba(245,158,11,.25);
        }
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--txt); margin:0; padding:24px;}
        .card{max-width:760px; margin:0 auto; background:var(--card); border:1px solid var(--bd); border-radius:16px; padding:18px;}
        h1{font-size:18px; margin:0 0 6px;}
        p{opacity:.92; line-height:1.45}
        .mut{color:var(--mut); font-size:13px}
        label{display:block; margin-top:10px; font-size:13px; color:var(--mut)}
        input{width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--inbd); background:var(--in); color:var(--txt); outline:none}
        input[type="file"]{padding:9px 12px}
        .row{display:grid; grid-template-columns:1fr; gap:10px}
        @media (min-width: 720px){ .row.two{grid-template-columns:1fr 1fr} }
        .btn{margin-top:14px; padding:10px 12px; border-radius:12px; border:0; background:var(--pri); color:white; font-weight:700; cursor:pointer; width:100%}
        .warn{margin-top:10px; background:var(--warnbg); border:1px solid var(--warnbd); padding:10px; border-radius:12px}
        code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:12px}
        .check{display:flex; gap:10px; align-items:flex-start; margin-top:12px}
        .check input{width:auto; margin-top:3px}
    </style>
    </head>
    <body>
    <div class="card">
        <h1>Registro externo · RFC + CSD</h1>
        <p class="mut">Este RFC se registrará dentro de la cuenta que te invitó y aparecerá en “Mis RFCs”.</p>

        <div class="warn">
            <div class="mut">Correo invitado:</div>
            <div><code>{$safeEmail}</code></div>
            <div class="mut" style="margin-top:6px">Cuenta destino:</div>
            <div><code>{$safeCuentaId}</code></div>
            <div class="mut" style="margin-top:6px">Invitación:</div>
            <div><code>{$safeInv}</code></div>
        </div>

        <form method="POST" action="{$safePostUrl}" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="{$csrf}">

            <div class="row two">
                <div>
                    <label>RFC</label>
                    <input name="rfc" required minlength="12" maxlength="13" placeholder="XAXX010101000">
                </div>
                <div>
                    <label>Alias / Razón social (opcional)</label>
                    <input name="alias" maxlength="190" placeholder="Mi empresa S.A. de C.V.">
                </div>
            </div>

            <div class="row two">
                <div>
                    <label>Archivo CSD (.cer)</label>
                    <input type="file" name="cer" required accept=".cer">
                </div>
                <div>
                    <label>Archivo CSD (.key)</label>
                    <input type="file" name="key" required accept=".key">
                </div>
            </div>

            <div class="row">
                <label>Contraseña del .key (si aplica)</label>
                <input name="key_password" maxlength="120" placeholder="(opcional)">
            </div>

            <div class="row">
                <label>Nota (opcional)</label>
                <input name="note" maxlength="500" placeholder="(opcional)">
            </div>

            <div class="check">
                <input type="checkbox" name="confirm" value="1" required>
                <div class="mut">
                    Confirmo que estoy autorizado(a) para registrar este RFC y subir el CSD en la cuenta indicada.
                </div>
            </div>

            <button class="btn" type="submit">Registrar RFC y CSD</button>

            <p class="mut" style="margin-top:10px">
                Tip: asegúrate de que los archivos sean exactamente <code>.cer</code> y <code>.key</code>.
            </p>
        </form>
    </div>
    </body>
    </html>
    HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function externalStore(Request $request)
    {
        // ======================================================
        // 1) Signed URL hard-check
        // ======================================================
        if (!method_exists($request, 'hasValidSignature') || !$request->hasValidSignature()) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'Firma inválida o expirada.'], 403)
                : response('Firma inválida o expirada.', 403);
        }

        // ======================================================
        // 2) Query params (SOT) + cuenta_id variants
        // ======================================================
        $email = trim((string) $request->query('email', ''));

        $cuentaId = $this->resolveCuentaIdFromQuery($request);
        $inv      = trim((string) $request->query('inv', ''));

        if ($email === '' || $cuentaId === null || $cuentaId === '') {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'Parámetros incompletos.'], 422)
                : response('Parámetros incompletos.', 422);
        }

        $cuentaIdNorm = ctype_digit((string) $cuentaId) ? (string) ((int) $cuentaId) : (string) $cuentaId;

        // ======================================================
        // 3) Validación BODY (multipart/form-data)
        // ======================================================
        $data = $request->validate([
            'rfc'          => ['required', 'string', 'min:12', 'max:13'],
            'alias'        => ['nullable', 'string', 'max:190'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'cer'          => ['required', 'file', 'max:5120'], // 5MB
            'key'          => ['required', 'file', 'max:5120'], // 5MB
            'key_password' => ['nullable', 'string', 'max:120'],
            'pwd'          => ['nullable', 'string', 'max:120'],
            'note'         => ['nullable', 'string', 'max:500'],
            'confirm'      => ['accepted'],
        ], [
            'confirm.accepted' => 'Debes confirmar la autorización para registrar este RFC.',
        ]);

        $rfc = strtoupper(trim((string) $data['rfc']));

        if (!preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'RFC inválido.'], 422)
                : response('RFC inválido.', 422);
        }

        $alias = trim((string) ($data['alias'] ?? $data['razon_social'] ?? ''));
        $note  = trim((string) ($data['note'] ?? ''));

        $cer = $request->file('cer');
        $key = $request->file('key');

        // Extensiones strict
        if (!$cer || strtolower((string) $cer->getClientOriginalExtension()) !== 'cer') {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'El archivo .cer no es válido.'], 422)
                : response('El archivo .cer no es válido.', 422);
        }
        if (!$key || strtolower((string) $key->getClientOriginalExtension()) !== 'key') {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'El archivo .key no es válido.'], 422)
                : response('El archivo .key no es válido.', 422);
        }

        $password = (string) ($data['key_password'] ?? $data['pwd'] ?? '');

        // ======================================================
        // 4) Persistencia (mysql_clientes) reusando upsertCredentials
        // ======================================================
        try {
            $resp = DB::connection('mysql_clientes')->transaction(function () use (
                $request, $cuentaIdNorm, $email, $inv, $rfc, $alias, $note, $cer, $key, $password
            ) {
                // Reusa servicio
                $cred = $this->service->upsertCredentials(
                    $cuentaIdNorm,
                    $rfc,
                    $cer,
                    $key,
                    $password
                );

                // Alias
                if ($alias !== '') {
                    try { $cred->razon_social = $alias; } catch (\Throwable) {}
                    try { if (property_exists($cred, 'alias')) $cred->alias = $alias; } catch (\Throwable) {}
                }

                // Flags externos + password + meta merge
                try {
                    $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                    $table  = $cred->getTable();
                    $schema = Schema::connection($conn);

                    // pending
                    if ($schema->hasColumn($table, 'estatus'))      $cred->estatus      = 'pending';
                    if ($schema->hasColumn($table, 'status'))       $cred->status       = 'pending';
                    if ($schema->hasColumn($table, 'validado'))     $cred->validado     = 0;
                    if ($schema->hasColumn($table, 'validated_at')) $cred->validated_at = null;

                    // password cifrada (si upsert no la cifra)
                    if ($password !== '') {
                        $enc = null;
                        try { $enc = encrypt($password); } catch (\Throwable) { $enc = base64_encode($password); }

                        if ($schema->hasColumn($table, 'key_password_enc')) {
                            $cred->key_password_enc = $enc;
                            if ($schema->hasColumn($table, 'key_password')) $cred->key_password = null;
                        } elseif ($schema->hasColumn($table, 'key_password')) {
                            $cred->key_password = $enc;
                        }
                    }

                    // meta merge
                    if ($schema->hasColumn($table, 'meta')) {
                        $meta = [];
                        $current = $cred->meta ?? null;

                        if (is_array($current)) $meta = $current;
                        elseif (is_string($current) && $current !== '') {
                            $tmp = json_decode($current, true);
                            if (is_array($tmp)) $meta = $tmp;
                        }

                        $meta['source']         = 'external_register';
                        $meta['external_email'] = $email;
                        $meta['invite_id']      = ($inv !== '' ? $inv : null);
                        $meta['note']           = ($note !== '' ? $note : null);
                        $meta['ip']             = (string) $request->ip();
                        $meta['ua']             = (string) $request->userAgent();
                        $meta['updated_at']     = now()->toDateTimeString();

                        $meta['stored'] = [
                            'cer' => data_get($cred, 'cer_path'),
                            'key' => data_get($cred, 'key_path'),
                        ];

                        $cred->meta = $meta;
                    }
                } catch (\Throwable) {
                    // no-op
                }

                $cred->save();

                return ['rfc' => $rfc];
            });

            $msg = 'RFC y CSD registrados. Ya puedes cerrar esta pestaña.';

            return $request->wantsJson()
                ? response()->json(['ok' => true, 'msg' => $msg, 'rfc' => (string)($resp['rfc'] ?? '')], 200)
                : response($msg, 200);

        } catch (\Throwable $e) {
            Log::error('[SAT][externalStore] Error', [
                'cuenta_id' => $cuentaIdNorm ?? null,
                'email'     => $email ?? null,
                'inv'       => $inv ?? null,
                'rfc'       => $rfc ?? null,
                'err'       => $e->getMessage(),
            ]);

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'No se pudo registrar.'], 500)
                : response('No se pudo registrar.', 500);
        }
    }

    /* ============================================================
    * ✅ DASHBOARD STATS
    * ============================================================ */
    public function dashboardStats(Request $request): JsonResponse
    {
        $trace = $this->trace();

        $user = $this->cu();
        if (!$user) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada.',
                'trace_id' => $trace,
            ], 401);
        }

        $cuentaId = (string) ($this->cuId() ?: $this->resolveCuentaIdFromUser($user) ?: '');
        $cuentaId = trim($cuentaId);

        if ($cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Cuenta inválida.',
                'trace_id' => $trace,
            ], 422);
        }

        // Rango defensivo
        [$from, $to, $rangeDays] = $this->statsResolveDateRange($request);

        $conn  = 'mysql_clientes';
        $dl    = new SatDownload();
        $table = $dl->getTable();

        $schema = Schema::connection($conn);

        if (!$schema->hasTable($table)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'No existe la tabla sat_downloads.',
                'trace_id' => $trace,
            ], 422);
        }

        $cols = [];
        try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }

        $has = static function (string $c) use ($cols): bool {
            return in_array($c, $cols, true);
        };

        $colCreated = $has('created_at') ? 'created_at' : null;

        $colTipo    = $has('tipo') ? 'tipo' : null;
        $colRfc     = $has('rfc') ? 'rfc' : null;

        $colStatus  = $has('status') ? 'status' : null;
        $colEstado  = $has('estado') ? 'estado' : null;
        $colSatStat = $has('sat_status') ? 'sat_status' : null;

        $colPaidAt  = $has('paid_at') ? 'paid_at' : null;

        $colXmlA = $has('xml_count') ? 'xml_count' : null;
        $colXmlB = $has('total_xml') ? 'total_xml' : null;
        $colXmlC = $has('num_xml') ? 'num_xml' : null;

        $colCostoA = $has('costo') ? 'costo' : null;
        $colCostoB = $has('price_mxn') ? 'price_mxn' : null;
        $colCostoC = $has('precio') ? 'precio' : null;

        $colIsManual = $has('is_manual') ? 'is_manual' : ($has('manual') ? 'manual' : null);

        $colCuenta = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
        if (!$colCuenta) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La tabla sat_downloads no tiene cuenta_id ni account_id.',
                'trace_id' => $trace,
            ], 422);
        }

        $q = DB::connection($conn)->table($table)->where($colCuenta, $cuentaId);

        // Excluir vault/bóveda (si hay tipo)
        if ($colTipo) {
            $q->whereRaw('LOWER(COALESCE(' . $colTipo . ', "")) NOT IN ("vault","boveda","bóveda")');
        }

        // =====================================================
        // ✅ EXCLUIR REGISTROS "EXTERNAL ZIP REGISTER"
        // No deben contar ni aparecer como Solicitudes SAT.
        // Criterios:
        // - zip_path inicia con sat/external_zip/
        // - o meta contiene source=external_zip_register
        // =====================================================
        try {
            // zip_path (tu tabla sí tiene zip_path)
            if ($has('zip_path')) {
                $q->where(function ($w) {
                    $w->whereNull('zip_path')
                    ->orWhere('zip_path', '=', '')
                    ->orWhere('zip_path', 'not like', 'sat/external_zip/%');
                });
            }

            // meta (tu tabla sí tiene meta)
            if ($has('meta')) {
                $q->where(function ($w) {
                    $w->whereNull('meta')
                    ->orWhere('meta', '=', '')
                    ->orWhere('meta', 'not like', '%"source":"external_zip_register"%')
                    ->orWhere('meta', 'not like', '%external_zip_register%');
                });
            }
        } catch (\Throwable $e) {
            // defensivo: no romper dashboard por filtros
        }


        // Fecha (si existe created_at)
        if ($colCreated) {
            $q->whereBetween($colCreated, [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ]);
        }

        // Select mínimo
        $select = ['id', $colCuenta];
        if ($colCreated) $select[] = $colCreated;
        if ($colTipo)    $select[] = $colTipo;
        if ($colRfc)     $select[] = $colRfc;

        if ($colStatus)  $select[] = $colStatus;
        if ($colEstado)  $select[] = $colEstado;
        if ($colSatStat) $select[] = $colSatStat;

        if ($colPaidAt)  $select[] = $colPaidAt;

        if ($colXmlA)    $select[] = $colXmlA;
        if ($colXmlB)    $select[] = $colXmlB;
        if ($colXmlC)    $select[] = $colXmlC;

        if ($colCostoA)  $select[] = $colCostoA;
        if ($colCostoB)  $select[] = $colCostoB;
        if ($colCostoC)  $select[] = $colCostoC;

        if ($colIsManual) $select[] = $colIsManual;

        $hasMeta = $has('meta');
        if ($hasMeta) $select[] = 'meta';

        $rows = $q->select($select)->get();

        // Totales
        $totCount = 0;
        $totXml   = 0;
        $totCost  = 0.0;

        $byStatus = [
            'pending'     => 0,
            'processing'  => 0,
            'ready'       => 0,
            'paid'        => 0,
            'downloaded'  => 0,
            'expired'     => 0,
            'error'       => 0,
            'other'       => 0,
        ];

        $byTipo = [
            'emitidos'  => 0,
            'recibidos' => 0,
            'ambos'     => 0,
            'other'     => 0,
        ];

        $byManual = [
            'manual'  => 0,
            'regular' => 0,
        ];

        $uniqueRfcs = [];

        // Serie diaria
        $series = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $k = $cursor->format('Y-m-d');
            $series[$k] = [
                'date'       => $k,
                'count'      => 0,
                'ready'      => 0,
                'paid'       => 0,
                'pending'    => 0,
                'processing' => 0,
                'error'      => 0,
                'downloaded' => 0,
                'other'      => 0,
            ];
            $cursor->addDay();
        }

        foreach ($rows as $r) {
            $totCount++;

            // tipo
            $tipoLow = '';
            if ($colTipo && isset($r->{$colTipo})) {
                $tipoLow = strtolower(trim((string) $r->{$colTipo}));
            }

            $tipoBucket = 'other';
            if ($tipoLow !== '') {
                if (in_array($tipoLow, ['emitidos','emitido','out','salida'], true) || str_contains($tipoLow, 'emit')) {
                    $tipoBucket = 'emitidos';
                } elseif (in_array($tipoLow, ['recibidos','recibido','in','entrada'], true) || str_contains($tipoLow, 'recib')) {
                    $tipoBucket = 'recibidos';
                } elseif (in_array($tipoLow, ['ambos','both','mix','mixto'], true)) {
                    $tipoBucket = 'ambos';
                }
            }
            $byTipo[$tipoBucket]++;

            // RFCs únicos
            if ($colRfc && isset($r->{$colRfc})) {
                $rr = strtoupper(trim((string) $r->{$colRfc}));
                if ($rr !== '') $uniqueRfcs[$rr] = true;
            }

            // xml_count
            $xml = 0;
            foreach ([$colXmlA, $colXmlB, $colXmlC] as $c) {
                if ($c && isset($r->{$c}) && is_numeric($r->{$c}) && (int)$r->{$c} > 0) {
                    $xml = (int) $r->{$c};
                    break;
                }
            }
            if ($xml <= 0 && $hasMeta && !empty($r->meta)) {
                $m = $this->statsDecodeMeta($r->meta);
                foreach (['xml_count','total_xml','num_xml'] as $k) {
                    if (isset($m[$k]) && (int)$m[$k] > 0) { $xml = (int)$m[$k]; break; }
                }
            }
            $totXml += max(0, $xml);

            // costo
            $cost = 0.0;
            foreach ([$colCostoA, $colCostoB, $colCostoC] as $c) {
                if ($c && isset($r->{$c}) && is_numeric($r->{$c}) && (float)$r->{$c} > 0) {
                    $cost = (float) $r->{$c};
                    break;
                }
            }
            if ($cost <= 0 && $hasMeta && !empty($r->meta)) {
                $m = $this->statsDecodeMeta($r->meta);
                foreach (['price_mxn','costo','cost_mxn','precio'] as $k) {
                    if (isset($m[$k]) && (float)$m[$k] > 0) { $cost = (float)$m[$k]; break; }
                }
            }
            $totCost += max(0.0, $cost);

            // manual?
            $isManual = false;
            if ($colIsManual && isset($r->{$colIsManual})) {
                $v = $r->{$colIsManual};
                if (is_bool($v)) $isManual = $v;
                elseif (is_numeric($v)) $isManual = ((int)$v) === 1;
                elseif (is_string($v)) $isManual = in_array(strtolower(trim($v)), ['1','true','yes','si','sí','on'], true);
            } elseif ($hasMeta && !empty($r->meta)) {
                $m = $this->statsDecodeMeta($r->meta);
                $isManual = !empty($m['is_manual'] ?? null) || !empty($m['manual'] ?? null);
            }
            $byManual[$isManual ? 'manual' : 'regular']++;

            // status normalizado
            $paid = false;
            if ($colPaidAt && !empty($r->{$colPaidAt})) $paid = true;

            $rawStatus = '';
            foreach ([$colStatus, $colEstado, $colSatStat] as $c) {
                if ($c && isset($r->{$c}) && trim((string)$r->{$c}) !== '') {
                    $rawStatus = (string) $r->{$c};
                    break;
                }
            }

            $st = $this->statsNormalizeStatus($rawStatus, $paid);
            if (!isset($byStatus[$st])) $st = 'other';
             $byStatus[$st]++;

            // serie por día
            if ($colCreated && !empty($r->{$colCreated})) {
                try {
                    $dt  = Carbon::parse((string) $r->{$colCreated});
                    $key = $dt->format('Y-m-d');

                     if (isset($series[$key])) {
                         $series[$key]['count']++;
                        $bucket = isset($series[$key][$st]) ? $st : 'other';
                        if (!isset($series[$key][$bucket])) $series[$key][$bucket] = 0;
                        $series[$key][$bucket]++;
                     }
                } catch (\Throwable) {}
            }
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'range'    => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'days' => $rangeDays,
            ],
            'totals' => [
                'count'        => $totCount,
                'rfcs'         => count($uniqueRfcs),
                'xml_count'    => $totXml,
                'cost_mxn'     => round($totCost, 2),
                'avg_cost_mxn' => $totCount > 0 ? round($totCost / $totCount, 2) : 0.0,
            ],
            'breakdown' => [
                'status' => $byStatus,
                'tipo'   => $byTipo,
                'manual' => $byManual,
            ],
            'series' => array_values($series),
        ], 200);
    }

    /**
     * Rango por defecto: últimos 30 días.
     * Cap: máx 180 días (evita consultas pesadas).
     * Acepta ?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
     private function statsResolveDateRange(Request $request): array
    {
        $maxDays = 180;

        $fromQ = trim((string) $request->query('from', ''));
        $toQ   = trim((string) $request->query('to', ''));


        $tz   = (string) (config('app.timezone') ?: 'UTC');
        $now  = Carbon::now($tz);
        $to   = $now->copy()->endOfDay();
        $from = $now->copy()->subDays(29)->startOfDay(); // 30 días incl.


        // parse defensivo: YYYY-MM-DD => createFromFormat para evitar timezone drift
        try {
            if ($toQ !== '') {
                $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toQ)
                    ? Carbon::createFromFormat('Y-m-d', $toQ, $tz)->endOfDay()
                    : Carbon::parse($toQ, $tz)->endOfDay();
            }
        } catch (\Throwable) {}
        try {
            if ($fromQ !== '') {
                $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromQ)
                    ? Carbon::createFromFormat('Y-m-d', $fromQ, $tz)->startOfDay()
                    : Carbon::parse($fromQ, $tz)->startOfDay();
            }
        } catch (\Throwable) {}
    
        // No permitir "to" en el futuro (reduce queries raras)
        if ($to->gt($now->copy()->endOfDay())) {
            $to = $now->copy()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $days = (int) max(1, $from->diffInDays($to) + 1);

        if ($days > $maxDays) {
            $from = $to->copy()->subDays($maxDays - 1)->startOfDay();
            $days = $maxDays;
        }

        return [$from, $to, $days];
    }


     private function statsDecodeMeta($meta): array
    {
        try {
            if (is_array($meta)) return $meta;
            if (is_object($meta)) return (array) $meta;
            if (is_string($meta) && $meta !== '') {
                $tmp = json_decode($meta, true);
                return is_array($tmp) ? $tmp : [];
            }
        } catch (\Throwable) {}
        return [];
    }

    
    /**
     * Normaliza status para dashboard:
     * - paid_at manda (paid)
     * - downloaded reconocido
     * - ready/done/listo/completed -> ready
     * - requested/pending -> pending
     * - processing/en_proceso -> processing
     * - error/failed/cancelled -> error
     * - expirada/expired -> expired
     */
     private function statsNormalizeStatus(string $raw, bool $paid): string
    {
        if ($paid) return 'paid';

        $s = strtolower(trim($raw));
        if ($s === '') return 'pending';

        // normaliza separadores comunes
        $s = str_replace(['-', ' '], ['_', '_'], $s);
        if (in_array($s, ['downloaded','descargado','descargada','download','descarga'], true)) return 'downloaded';
        if (in_array($s, ['ready','done','listo','completed','finalizado','finished','terminado','success','ok','ready_to_download'], true)) return 'ready';
        if (in_array($s, ['pending','pendiente','requested','request','solicitud','created','creada','queued','en_cola','queue'], true)) return 'pending';
        if (in_array($s, ['processing','en_proceso','procesando','working','running','in_progress','progress'], true)) return 'processing';
        if (in_array($s, ['expired','expirada','expirado','timeout','timed_out'], true)) return 'expired';
        if (in_array($s, ['error','failed','fail','cancelled','canceled','cancelado','cancelada'], true)) return 'error';
        if (in_array($s, ['paid','pagado','pagada','payment_ok','paid_ok'], true)) return 'paid';

        return 'other';
    }

    /* ============================================================
    * Helpers internos para externalStore
    * ============================================================ */

    private function resolveCuentaIdFromQuery(Request $request): ?string
    {
        foreach (['cuenta_id', 'cuenta', 'account_id', 'account'] as $k) {
            $v = $request->query($k, null);
            if ($v === null) $v = $request->input($k, null);
            if (is_scalar($v)) {
                $s = trim((string) $v);
                if ($s !== '') return $s;
            }
        }
        return null;
    }


    /**
     * ✅ Huella del PDF (anti-tamper / protección ligera)
     * - No es seguridad criptográfica "legal", pero evita cambios invisibles.
     * - Se calcula con campos clave + APP_KEY.
     */
     private function quotePdfHash(array $payload): string
    {
        try {
            $fmt2 = static fn($v) => number_format((float)$v, 2, '.', '');
            $safe = [
                'mode'         => (string) ($payload['mode'] ?? ''),
                'folio'        => (string) ($payload['folio'] ?? ''),
                'cuenta_id'    => (string) ($payload['cuenta_id'] ?? ''),
                'empresa'      => (string) ($payload['empresa'] ?? ''),
                'xml_count'    => (int)    ($payload['xml_count'] ?? 0),

                'base'         => $fmt2($payload['base'] ?? 0),
                'discount_pct' => $fmt2($payload['discount_pct'] ?? 0),
                'subtotal'     => $fmt2($payload['subtotal'] ?? 0),
                'iva_rate'     => (int)    ($payload['iva_rate'] ?? 0),
                'iva_amount'   => $fmt2($payload['iva_amount'] ?? 0),
                'total'        => $fmt2($payload['total'] ?? 0),
                'valid_until'  => is_object($payload['valid_until'] ?? null)
                    ? (string) ($payload['valid_until']->toDateString() ?? '')
                    : (string) ($payload['valid_until'] ?? ''),
            ];

            $key = (string) config('app.key', '');
            $raw = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Normaliza "base64:" por si viene así
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7)) ?: $key;
            }

            // Si no hay APP_KEY, usa una sal “estable” por instalación (URL + app.name)
            if (trim($key) === '') {
                $key = (string) (config('app.url') . '|' . config('app.name'));
            }

            // HMAC SHA256 -> corto (16) para imprimir en PDF
            return strtoupper(substr(hash_hmac('sha256', (string)$raw, (string)$key), 0, 16));
        } catch (\Throwable) {
            return strtoupper(substr(sha1((string) microtime(true)), 0, 16));
        }
    }



}