<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatCartService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatCartService
{
    public function __construct(
        private readonly SatClientContext $ctx,
    ) {}

    public function cartKeyForCuenta(string $cuentaId): string
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return 'sat_cart_guest';

        $safe = preg_replace('/\s+/', '', $cuentaId) ?: $cuentaId;
        return 'sat_cart_' . $safe;
    }

    public function normalizeIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map(static function ($v) {
            $s = trim((string) $v);
            return $s !== '' ? $s : null;
        }, (array) $ids)));

        $out = [];
        $seen = [];
        foreach ($ids as $id) {
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $out[] = $id;
        }
        return $out;
    }

    public function getCartIds(string $cuentaId): array
    {
        $cuentaId = trim((string) $cuentaId);
        $key      = $this->cartKeyForCuenta($cuentaId);

        $ids = session($key, []);
        $ids = array_values(array_filter(array_map('strval', (array) $ids)));
        $ids = array_values(array_unique($ids));

        if ($cuentaId === '') return $ids;

        try {
            $conn   = 'mysql_clientes';
            $schema = Schema::connection($conn);

            if ($schema->hasTable('sat_cart_items')) {
                $dbIds = DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->pluck('download_id')
                    ->map(fn($v) => (string) $v)
                    ->all();

                $dbIds = array_values(array_filter(array_map('strval', (array) $dbIds)));

                if (!empty($dbIds)) {
                    $ids = array_values(array_unique(array_merge($ids, $dbIds)));
                    session([$key => $ids]);
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        return $ids;
    }

    public function putCartIds(string $cuentaId, array $ids): void
    {
        $cuentaId = trim((string) $cuentaId);
        $key      = $this->cartKeyForCuenta($cuentaId);

        $ids = $this->normalizeIds($ids);

        session([$key => $ids]);

        if ($cuentaId === '') return;

        try {
            $conn   = 'mysql_clientes';
            $schema = Schema::connection($conn);

            if (!$schema->hasTable('sat_cart_items')) return;
            if (!$schema->hasColumn('sat_cart_items', 'cuenta_id') || !$schema->hasColumn('sat_cart_items', 'download_id')) return;

            DB::connection($conn)->transaction(function () use ($conn, $cuentaId, $ids) {
                DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->delete();

                if (empty($ids)) return;

                $now = now();
                $hasCreated = false;
                $hasUpdated = false;

                try {
                    $schema = Schema::connection($conn);
                    $hasCreated = $schema->hasColumn('sat_cart_items', 'created_at');
                    $hasUpdated = $schema->hasColumn('sat_cart_items', 'updated_at');
                } catch (\Throwable) {}

                $rows = [];
                foreach ($ids as $downloadId) {
                    $row = [
                        'cuenta_id'   => $cuentaId,
                        'download_id' => (string) $downloadId,
                    ];
                    if ($hasCreated) $row['created_at'] = $now;
                    if ($hasUpdated) $row['updated_at'] = $now;
                    $rows[] = $row;
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::connection($conn)->table('sat_cart_items')->insert($chunk);
                }
            });
        } catch (\Throwable) {
            // no-op
        }
    }

    public function dbUpsert(string $cuentaId, array $ids): void
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return;

        $ids = $this->normalizeIds($ids);
        if (!count($ids)) return;

        $conn   = 'mysql_clientes';
        $schema = Schema::connection($conn);

        if (!$schema->hasTable('sat_cart_items')) return;
        if (!$schema->hasColumn('sat_cart_items', 'cuenta_id') || !$schema->hasColumn('sat_cart_items', 'download_id')) return;

        $hasUpdated = false;
        $hasCreated = false;

        try {
            $hasCreated = $schema->hasColumn('sat_cart_items', 'created_at');
            $hasUpdated = $schema->hasColumn('sat_cart_items', 'updated_at');
        } catch (\Throwable) {}

        $now = now();
        $rows = [];
        foreach ($ids as $downloadId) {
            $row = [
                'cuenta_id'   => $cuentaId,
                'download_id' => (string) $downloadId,
            ];
            if ($hasCreated) $row['created_at'] = $now;
            if ($hasUpdated) $row['updated_at'] = $now;
            $rows[] = $row;
        }

        DB::connection($conn)->table('sat_cart_items')->upsert(
            $rows,
            ['cuenta_id', 'download_id'],
            $hasUpdated ? ['updated_at'] : []
        );
    }

    /**
     * Valida que ids existen en sat_downloads, pertenecen a cuenta y no son vault/boveda.
     * Devuelve [validIds, invalidIds]
     */
    public function validateCartIdsAgainstDownloads(string $cuentaId, array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($cuentaId === '' || !count($ids)) return [[], $ids];

        $validIds = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereIn('id', $ids)
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
            ->pluck('id')
            ->map(static fn($v) => (string) $v)
            ->all();

        $validIds = $this->normalizeIds($validIds);

        $validSet = array_fill_keys($validIds, true);
        $invalid  = array_values(array_filter($ids, static fn($id) => !isset($validSet[$id])));

        return [$validIds, $invalid];
    }

    /**
     * Reconciliar session ∪ db y limpiar fantasmas DB.
     */
    public function reconcileAndClean(string $cuentaId, string $trace): array
    {
        $ids = $this->normalizeIds($this->getCartIds($cuentaId));

        try {
            $conn = 'mysql_clientes';

            if (Schema::connection($conn)->hasTable('sat_cart_items')) {
                $dbIds = DB::connection($conn)->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->pluck('download_id')
                    ->map(static fn($v) => (string) $v)
                    ->values()
                    ->all();

                $dbIds  = $this->normalizeIds($dbIds);
                $merged = $this->normalizeIds(array_merge($ids, $dbIds));

                if (count($merged)) {
                    [$valid, $invalid] = $this->validateCartIdsAgainstDownloads($cuentaId, $merged);

                    $ids = $this->normalizeIds($valid);
                    $this->putCartIds($cuentaId, $ids);

                    try {
                        $ghosts = array_values(array_diff($dbIds, $ids));
                        if (count($ghosts)) {
                            DB::connection($conn)->table('sat_cart_items')
                                ->where('cuenta_id', $cuentaId)
                                ->whereIn('download_id', $ghosts)
                                ->delete();
                        }
                    } catch (\Throwable $e) {
                        Log::debug('[SAT:CartService] DB ghost cleanup no-bloqueante', [
                            'trace_id' => $trace,
                            'cuenta_id' => $cuentaId,
                            'ghosts' => count($ghosts ?? []),
                            'err' => $e->getMessage(),
                        ]);
                    }
                } else {
                    try {
                        DB::connection($conn)->table('sat_cart_items')
                            ->where('cuenta_id', $cuentaId)
                            ->delete();
                    } catch (\Throwable $e) {
                        Log::debug('[SAT:CartService] DB clear no-bloqueante', [
                            'trace_id' => $trace,
                            'cuenta_id' => $cuentaId,
                            'err' => $e->getMessage(),
                        ]);
                    }
                    $ids = [];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[SAT:CartService] Reconcilio no-bloqueante falló', [
                'trace_id' => $trace,
                'cuenta_id' => $cuentaId,
                'err' => $e->getMessage(),
            ]);
        }

        return $ids;
    }
}
