<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatRfcOptionsService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatRfcOptionsService
{
    /**
     * Carga credenciales SAT por cuenta.
     *
     * Compatibilidad:
     * - cuenta_id = UUID cliente
     * - account_id = UUID cliente
     * - account_id = admin_account_id numérico (registros legacy / admin)
     */
    public function loadCredentials(string $cuentaId, string $conn = 'mysql_clientes'): Collection
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') {
            return collect();
        }

        try {
            $adminAccountId = $this->resolveAdminAccountId($cuentaId, $conn);

            $query = SatCredential::on($conn)
                ->where(function ($q) use ($cuentaId, $adminAccountId) {
                    $q->where('cuenta_id', $cuentaId)
                        ->orWhere('account_id', $cuentaId);

                    if ($adminAccountId !== null) {
                        $q->orWhere('account_id', (string) $adminAccountId);
                    }
                })
                ->orderBy('rfc');

            $rows = $query->get();

            $externalMap = $this->loadExternalUploadsMap($cuentaId, $adminAccountId, $conn);

            return $rows
                ->filter(function ($row) {
                    $meta = $this->metaArray($row->meta ?? []);

                    if (isset($meta['is_active'])) {
                        $isActive = $meta['is_active'];
                        if ($isActive === false || $isActive === 0 || $isActive === '0') {
                            return false;
                        }
                    }

                    if (property_exists($row, 'deleted_at') && !empty($row->deleted_at)) {
                        return false;
                    }

                    $estatusOperativo = strtolower(trim((string) ($row->estatus_operativo ?? '')));
                    if ($estatusOperativo === 'inactive') {
                        return false;
                    }

                    return strtoupper(trim((string) ($row->rfc ?? ''))) !== '';
                })
                ->map(function ($row) use ($externalMap) {
                    $meta = $this->metaArray($row->meta ?? []);

                    $rfc = strtoupper(trim((string) ($row->rfc ?? '')));

                    $storedCer = trim((string) data_get($meta, 'stored.cer', ''));
                    $storedKey = trim((string) data_get($meta, 'stored.key', ''));

                    $legacyCer = trim((string) ($row->cer_path ?? ''));
                    $legacyKey = trim((string) ($row->key_path ?? ''));

                    $legacyPasswordEnc = trim((string) (
                        $row->key_password_enc
                        ?? $row->key_password
                        ?? ''
                    ));

                    $externalUploadId = (string) (
                        $row->external_upload_id
                        ?? data_get($meta, 'external_upload_id')
                        ?? ''
                    );

                    $externalRow = $this->resolveExternalRowForCredential(
                        externalMap: $externalMap,
                        externalUploadId: $externalUploadId,
                        rfc: $rfc
                    );

                    $externalFilePath = trim((string) ($externalRow['file_path'] ?? ''));
                    $externalFileName = trim((string) ($externalRow['file_name'] ?? ''));
                    $externalPassword = trim((string) ($externalRow['fiel_password'] ?? ''));
                    $externalRazonSocial = trim((string) ($externalRow['razon_social'] ?? ''));

                    // Normalizar razón social
                    if (trim((string) ($row->razon_social ?? '')) === '') {
                        $row->razon_social = trim((string) (
                            $externalRazonSocial
                            ?: data_get($meta, 'razon_social')
                            ?: data_get($meta, 'quote.razon_social')
                            ?: data_get($meta, 'empresa')
                            ?: ''
                        ));
                    }

                    // Normalizar origen
                    $source = strtolower(trim((string) (
                        data_get($meta, 'source')
                        ?: data_get($meta, 'updated_from')
                        ?: ''
                    )));

                    if (trim((string) ($row->tipo_origen ?? '')) === '') {
                        $row->tipo_origen = (
                            $source === 'external_register'
                            || $source === 'external_zip_backfill'
                            || $externalUploadId !== ''
                            || $externalRow !== null
                        ) ? 'externo' : 'interno';
                    }

                    if (trim((string) ($row->source_label ?? '')) === '') {
                        $row->source_label = (
                            $source === 'external_register'
                            || $source === 'external_zip_backfill'
                            || $externalUploadId !== ''
                            || $externalRow !== null
                        ) ? 'Registro externo' : 'Registro interno';
                    }

                    // Resolver mejor ruta disponible para FIEL
                    $resolvedCerPath = '';
                    $resolvedKeyPath = '';
                    $resolvedPasswordEnc = '';

                    if ($legacyCer !== '') {
                        $resolvedCerPath = $legacyCer;
                    } elseif ($storedCer !== '') {
                        $resolvedCerPath = $storedCer;
                    } elseif ($externalFilePath !== '') {
                        $resolvedCerPath = $externalFilePath;
                    }

                    if ($legacyKey !== '') {
                        $resolvedKeyPath = $legacyKey;
                    } elseif ($storedKey !== '') {
                        $resolvedKeyPath = $storedKey;
                    } elseif ($externalFilePath !== '') {
                        // En externos el archivo suele ser ZIP único
                        $resolvedKeyPath = $externalFilePath;
                    }

                    if ($legacyPasswordEnc !== '') {
                        $resolvedPasswordEnc = $legacyPasswordEnc;
                    } elseif ($externalPassword !== '') {
                        $resolvedPasswordEnc = $externalPassword;
                    }

                    if (trim((string) ($row->fiel_cer_path ?? '')) === '' && $resolvedCerPath !== '') {
                        $row->fiel_cer_path = $resolvedCerPath;
                    }

                    if (trim((string) ($row->fiel_key_path ?? '')) === '' && $resolvedKeyPath !== '') {
                        $row->fiel_key_path = $resolvedKeyPath;
                    }

                    if (trim((string) ($row->fiel_password_enc ?? '')) === '' && $resolvedPasswordEnc !== '') {
                        $row->fiel_password_enc = $resolvedPasswordEnc;
                    }

                    // Compatibilidad de vista actual
                    if (trim((string) ($row->cer_path ?? '')) === '' && $resolvedCerPath !== '') {
                        $row->cer_path = $resolvedCerPath;
                    }

                    if (trim((string) ($row->key_path ?? '')) === '' && $resolvedKeyPath !== '') {
                        $row->key_path = $resolvedKeyPath;
                    }

                    if (trim((string) ($row->key_password ?? '')) === '' && $resolvedPasswordEnc !== '') {
                        $row->key_password = $resolvedPasswordEnc;
                    }

                    // Complementar meta para la UI
                    if (!isset($meta['fiel']) || !is_array($meta['fiel'])) {
                        $meta['fiel'] = [];
                    }

                    if (empty($meta['fiel']['cer']) && $resolvedCerPath !== '') {
                        $meta['fiel']['cer'] = $resolvedCerPath;
                    }

                    if (empty($meta['fiel']['key']) && $resolvedKeyPath !== '') {
                        $meta['fiel']['key'] = $resolvedKeyPath;
                    }

                    if ($externalUploadId !== '' && empty($meta['external_upload_id'])) {
                        $meta['external_upload_id'] = $externalUploadId;
                    }

                    if ($externalFileName !== '' && empty($meta['external_file_name'])) {
                        $meta['external_file_name'] = $externalFileName;
                    }

                    if (
                        (!isset($meta['tipo_origen']) || trim((string) $meta['tipo_origen']) === '')
                        && trim((string) ($row->tipo_origen ?? '')) !== ''
                    ) {
                        $meta['tipo_origen'] = (string) $row->tipo_origen;
                    }

                    if (
                        (!isset($meta['source_label']) || trim((string) $meta['source_label']) === '')
                        && trim((string) ($row->source_label ?? '')) !== ''
                    ) {
                        $meta['source_label'] = (string) $row->source_label;
                    }

                    $row->meta = $meta;

                    return $row;
                })
                ->unique(function ($row) {
                    return strtoupper(trim((string) ($row->rfc ?? '')));
                })
                ->values();
        } catch (\Throwable $e) {
            Log::debug('[SatRfcOptionsService:loadCredentials] fallback empty', [
                'cuenta_id'        => $cuentaId,
                'admin_account_id' => isset($adminAccountId) ? $adminAccountId : null,
                'err'              => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Mapa RFC => alias/razón (para el presenter / UI).
     */
    public function buildCredMap(iterable $credList): array
    {
        $map = [];

        foreach ($credList as $c) {
            $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
            if ($rfc === '') {
                continue;
            }

            $map[$rfc] = (string) ($c->razon_social ?? $c->alias ?? $c->nombre ?? '');
        }

        return $map;
    }

    /**
     * Opciones RFC para UI (smart):
     * - Siempre lista RFCs dados de alta
     * - Marca validated=true con heurística robusta (sin depender de un solo campo)
     */
    public function buildRfcOptionsSmart(iterable $credList, string $conn = 'mysql_clientes'): array
    {
        $table = (new SatCredential())->getTable();

        $cols = [];
        try {
            $cols = Schema::connection($conn)->getColumnListing($table);
        } catch (\Throwable) {
            $cols = [];
        }

        $has = static fn(string $c): bool => in_array($c, $cols, true);

        $options = [];

        foreach ($credList as $c) {
            $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
            if ($rfc === '') {
                continue;
            }

            $alias = trim((string) ($c->razon_social ?? $c->alias ?? $c->nombre ?? ''));
            if ($alias === '') {
                $alias = '—';
            }

            $estatusRaw = '';
            foreach (['estatus', 'status', 'estado', 'state'] as $k) {
                if ($has($k) && isset($c->{$k})) {
                    $estatusRaw = (string) $c->{$k};
                    break;
                }
                if (isset($c->{$k})) {
                    $estatusRaw = (string) $c->{$k};
                    break;
                }
            }

            $st = strtolower(trim($estatusRaw));
            $validated = false;

            if (!$validated && !empty($c->validado ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->validated ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->validated_at ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->verified_at ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->ok_at ?? null)) {
                $validated = true;
            }

            if (
                !$validated
                && $st !== ''
                && in_array($st, ['ok', 'valido', 'válido', 'validado', 'valid', 'activo', 'active', 'enabled', 'on', 'ready', 'done'], true)
            ) {
                $validated = true;
            }

            foreach (['is_valid', 'is_verified', 'active', 'enabled'] as $k) {
                if ($validated) {
                    break;
                }

                if (isset($c->{$k}) && (string) $c->{$k} !== '') {
                    $v = $c->{$k};
                    if ($v === 1 || $v === true || strtolower((string) $v) === '1' || strtolower((string) $v) === 'true') {
                        $validated = true;
                    }
                }
            }

            $options[] = [
                'rfc'       => $rfc,
                'alias'     => $alias,
                'validated' => $validated,
            ];
        }

        usort($options, fn ($a, $b) => strcmp($a['rfc'], $b['rfc']));

        return $options;
    }

    private function resolveAdminAccountId(string $cuentaId, string $conn): ?int
    {
        try {
            if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
                return null;
            }

            $query = DB::connection($conn)
                ->table('cuentas_cliente')
                ->where('id', $cuentaId);

            if (Schema::connection($conn)->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $row = $query->select('admin_account_id')->first();

                $value = trim((string) ($row->admin_account_id ?? ''));
                if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
                    return (int) $value;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[SatRfcOptionsService:resolveAdminAccountId] no admin_account_id', [
                'cuenta_id' => $cuentaId,
                'err'       => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function loadExternalUploadsMap(string $cuentaId, ?int $adminAccountId, string $conn): array
    {
        try {
            if (!Schema::connection($conn)->hasTable('external_fiel_uploads')) {
                return [
                    'by_id' => [],
                    'by_rfc' => [],
                ];
            }

            $query = DB::connection($conn)
                ->table('external_fiel_uploads');

            $hasCuentaId = Schema::connection($conn)->hasColumn('external_fiel_uploads', 'cuenta_id');
            $hasAccountId = Schema::connection($conn)->hasColumn('external_fiel_uploads', 'account_id');

            $query->where(function ($q) use ($cuentaId, $adminAccountId, $hasCuentaId, $hasAccountId) {
                if ($hasCuentaId) {
                    $q->orWhere('cuenta_id', $cuentaId);
                }

                if ($hasAccountId && $adminAccountId !== null) {
                    $q->orWhere('account_id', $adminAccountId);
                }
            });

            $rows = $query->orderByDesc('id')->get();

            $byId = [];
            $byRfc = [];

            foreach ($rows as $row) {
                $normalized = [
                    'id' => (string) ($row->id ?? ''),
                    'rfc' => strtoupper(trim((string) ($row->rfc ?? ''))),
                    'razon_social' => trim((string) ($row->razon_social ?? '')),
                    'file_path' => trim((string) (
                        $row->file_path
                        ?? $row->zip_path
                        ?? $row->path
                        ?? ''
                    )),
                    'file_name' => trim((string) (
                        $row->file_name
                        ?? $row->zip_name
                        ?? $row->name
                        ?? ''
                    )),
                    'fiel_password' => trim((string) ($row->fiel_password ?? '')),
                    'status' => trim((string) ($row->status ?? '')),
                ];

                if ($normalized['id'] !== '') {
                    $byId[$normalized['id']] = $normalized;
                }

                if ($normalized['rfc'] !== '' && !isset($byRfc[$normalized['rfc']])) {
                    $byRfc[$normalized['rfc']] = $normalized;
                }
            }

            return [
                'by_id' => $byId,
                'by_rfc' => $byRfc,
            ];
        } catch (\Throwable $e) {
            Log::debug('[SatRfcOptionsService:loadExternalUploadsMap] fallback empty', [
                'cuenta_id' => $cuentaId,
                'admin_account_id' => $adminAccountId,
                'err' => $e->getMessage(),
            ]);

            return [
                'by_id' => [],
                'by_rfc' => [],
            ];
        }
    }

    private function resolveExternalRowForCredential(array $externalMap, string $externalUploadId, string $rfc): ?array
    {
        if ($externalUploadId !== '' && isset($externalMap['by_id'][$externalUploadId])) {
            return $externalMap['by_id'][$externalUploadId];
        }

        if ($rfc !== '' && isset($externalMap['by_rfc'][$rfc])) {
            return $externalMap['by_rfc'][$rfc];
        }

        return null;
    }

    private function metaArray(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}