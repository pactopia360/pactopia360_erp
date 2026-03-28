<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\SatUserCfdi;
use App\Models\Cliente\SatUserMetadataUpload;
use App\Models\Cliente\SatUserReportUpload;
use App\Models\Cliente\SatUserXmlUpload;
use App\Models\Cliente\VaultFile;
use App\Models\Cliente\SatUserMetadataItem;
use App\Models\Cliente\SatUserReportItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class SatOpsDownloadsController extends Controller
{
    private const CONN = 'mysql_clientes';

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('q', ''));
        $type   = trim((string) $request->get('type', ''));

        $items = collect()
            ->merge($this->queryMetadata($search))
            ->merge($this->queryXml($search))
            ->merge($this->queryReport($search))
            ->merge($this->queryVaultFilesV1($search))
            ->merge($this->querySatDownloadsV1($search))
            ->sortByDesc(fn (array $item) => $item['created_at'] ?? null)
            ->values();

        if ($type !== '') {
            $items = $items
                ->filter(fn (array $item) => (string) ($item['type'] ?? '') === $type)
                ->values();
        }

        $cfdiFilters = $this->resolveCfdiFilters($request);
        $cfdiItems   = $this->buildCfdiItems($cfdiFilters);

        $metadataRecordFilters = $this->resolveMetadataRecordFilters($request);
        $metadataRecordItems   = $this->buildMetadataRecordItems($metadataRecordFilters);

        $reportRecordFilters = $this->resolveReportRecordFilters($request);
        $reportRecordItems   = $this->buildReportRecordItems($reportRecordFilters);

        return view('admin.sat.ops.downloads.index', [
            'title'                 => 'SAT · Operación · Descargas',
            'items'                 => $items,
            'filters'               => [
                'q'    => $search,
                'type' => $type,
            ],
            'cfdiItems'             => $cfdiItems,
            'cfdiFilters'           => $cfdiFilters,
            'cfdiCounts'            => [
                'v1' => $cfdiItems->where('source', 'vault_cfdi')->count(),
                'v2' => $cfdiItems->where('source', 'user_cfdi')->count(),
            ],
            'metadataRecordItems'   => $metadataRecordItems,
            'metadataRecordFilters' => $metadataRecordFilters,
            'reportRecordItems'     => $reportRecordItems,
            'reportRecordFilters'   => $reportRecordFilters,
        ]);
    }

    public function download(string $type, string $id)
    {
        $model = $this->resolveModel($type, $id);
        abort_unless($model !== null, 404);

        $file = $this->resolveFilePayload($type, $model);
        abort_unless($file['disk'] !== '' && $file['path'] !== '', 404, 'Archivo no configurado');

        if (!Storage::disk($file['disk'])->exists($file['path'])) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::disk($file['disk'])->download($file['path'], $file['name']);
    }

    public function destroy(string $type, string $id): RedirectResponse
    {
        $deleted = $this->deleteFileByTypeAndId($type, $id);

        return back()->with(
            $deleted ? 'success' : 'error',
            $deleted ? 'Archivo eliminado correctamente' : 'No se pudo eliminar el archivo.'
        );
    }

    public function destroyCfdi(string $source, string $id, Request $request): RedirectResponse
    {
        $mode = $this->normalizeDeleteMode((string) $request->input('mode', 'index_only'));

        if ($source === 'vault_cfdi') {
            $deleted = $this->deleteVaultCfdiById((int) $id, $mode);

            return back()->with(
                $deleted ? 'success' : 'error',
                $deleted
                    ? 'Registro CFDI de bóveda v1 eliminado correctamente.'
                    : 'No se pudo eliminar el registro CFDI de bóveda v1.'
            );
        }

        if ($source === 'user_cfdi') {
            $row = SatUserCfdi::query()->find($id);

            if (!$row) {
                return back()->with('error', 'El CFDI de bóveda v2 no existe.');
            }

            $this->deleteUserCfdiRow($row, $mode);

            return back()->with('success', 'Registro CFDI de bóveda v2 eliminado correctamente.');
        }

        return back()->with('error', 'Origen de CFDI no soportado.');
    }

    public function purgeCfdi(Request $request): RedirectResponse
    {
        $filters = $this->resolveCfdiFilters($request);
        $mode    = $this->normalizeDeleteMode((string) $request->input('mode', 'index_only'));

        $deletedV1 = 0;
        $deletedV2 = 0;

        if (in_array($filters['source'], ['all', 'vault_cfdi'], true)) {
            $v1Rows = $this->buildVaultCfdiQuery($filters)->get();

            foreach ($v1Rows as $row) {
                if ($this->deleteVaultCfdiById((int) $row->id, $mode)) {
                    $deletedV1++;
                }
            }
        }

        if (in_array($filters['source'], ['all', 'user_cfdi'], true)) {
            $v2Rows = $this->buildUserCfdiQuery($filters)->get();

            foreach ($v2Rows as $row) {
                $this->deleteUserCfdiRow($row, $mode);
                $deletedV2++;
            }
        }

        $total = $deletedV1 + $deletedV2;

        return back()->with(
            'success',
            'Limpieza ejecutada. Registros eliminados: ' . number_format($total)
            . ' · v1: ' . number_format($deletedV1)
            . ' · v2: ' . number_format($deletedV2)
        );
    }

        public function bulkDestroyFiles(Request $request): RedirectResponse
    {
        $selected = $request->input('selected_files', []);

        if (!is_array($selected) || count($selected) === 0) {
            return back()->with('error', 'Selecciona al menos un archivo.');
        }

        $deleted = 0;
        $errors  = 0;

        foreach ($selected as $raw) {
            if (!is_string($raw) || !str_contains($raw, '::')) {
                $errors++;
                continue;
            }

            [$type, $id] = explode('::', $raw, 2);

            if ($this->deleteFileByTypeAndId((string) $type, (string) $id)) {
                $deleted++;
            } else {
                $errors++;
            }
        }

        return back()->with(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0
                ? 'Archivos eliminados: ' . number_format($deleted) . ($errors > 0 ? ' · omitidos: ' . number_format($errors) : '')
                : 'No se pudo eliminar ningún archivo.'
        );
    }

    public function bulkDestroyCfdi(Request $request): RedirectResponse
    {
        $selected = $request->input('selected_cfdi', []);
        $mode     = $this->normalizeDeleteMode((string) $request->input('mode', 'index_only'));

        if (!is_array($selected) || count($selected) === 0) {
            return back()->with('error', 'Selecciona al menos un CFDI.');
        }

        $deleted = 0;
        $errors  = 0;

        foreach ($selected as $raw) {
            if (!is_string($raw) || !str_contains($raw, '::')) {
                $errors++;
                continue;
            }

            [$source, $id] = explode('::', $raw, 2);

            if ($source === 'vault_cfdi') {
                if ($this->deleteVaultCfdiById((int) $id, $mode)) {
                    $deleted++;
                } else {
                    $errors++;
                }
                continue;
            }

            if ($source === 'user_cfdi') {
                $row = SatUserCfdi::query()->find($id);

                if (!$row) {
                    $errors++;
                    continue;
                }

                $this->deleteUserCfdiRow($row, $mode);
                $deleted++;
                continue;
            }

            $errors++;
        }

        return back()->with(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0
                ? 'CFDI eliminados: ' . number_format($deleted) . ($errors > 0 ? ' · omitidos: ' . number_format($errors) : '')
                : 'No se pudo eliminar ningún CFDI.'
        );
    }

    private function resolveCfdiFilters(Request $request): array
    {
        $olderThanMonths = (int) $request->input('older_than_months', 0);

        $filters = [
            'source'            => trim((string) $request->input('cfdi_source', 'all')),
            'q'                 => trim((string) $request->input('cfdi_q', '')),
            'rfc'               => strtoupper(trim((string) $request->input('cfdi_rfc', ''))),
            'tipo'              => strtolower(trim((string) $request->input('cfdi_tipo', ''))),
            'desde'             => trim((string) $request->input('cfdi_desde', '')),
            'hasta'             => trim((string) $request->input('cfdi_hasta', '')),
            'periodo'           => trim((string) $request->input('cfdi_periodo', '')),
            'older_than_months' => $olderThanMonths > 0 ? $olderThanMonths : 0,
            'limit'             => 300,
        ];

        if (!in_array($filters['source'], ['all', 'vault_cfdi', 'user_cfdi'], true)) {
            $filters['source'] = 'all';
        }

        if (!in_array($filters['tipo'], ['', 'emitidos', 'recibidos'], true)) {
            $filters['tipo'] = '';
        }

        if ($filters['periodo'] !== '' && preg_match('/^\d{4}\-\d{2}$/', $filters['periodo'])) {
            $start = Carbon::createFromFormat('Y-m', $filters['periodo'])->startOfMonth();
            $end   = (clone $start)->endOfMonth();

            if ($filters['desde'] === '') {
                $filters['desde'] = $start->format('Y-m-d');
            }

            if ($filters['hasta'] === '') {
                $filters['hasta'] = $end->format('Y-m-d');
            }
        }

        if ($filters['older_than_months'] > 0) {
            $cutoff = now()->startOfDay()->subMonths($filters['older_than_months']);
            $filters['hasta'] = $cutoff->format('Y-m-d');
        }

        return $filters;
    }

    private function buildCfdiItems(array $filters): Collection
    {
        $rows = collect();

        if (in_array($filters['source'], ['all', 'vault_cfdi'], true)) {
            $rows = $rows->merge(
                $this->buildVaultCfdiQuery($filters)
                    ->limit($filters['limit'])
                    ->get()
                    ->map(function ($row) {
                        return [
                            'id'         => (string) $row->id,
                            'source'     => 'vault_cfdi',
                            'source_ui'  => 'Bóveda v1',
                            'cuenta_id'  => (string) ($row->cuenta_id ?? ''),
                            'uuid'       => strtoupper((string) ($row->uuid ?? '')),
                            'fecha'      => (string) ($row->fecha_ui ?? ''),
                            'tipo'       => strtolower((string) ($row->tipo_ui ?? '')),
                            'rfc_emisor' => strtoupper((string) ($row->rfc_emisor ?? '')),
                            'rfc_receptor'=> strtoupper((string) ($row->rfc_receptor ?? '')),
                            'nombre_emisor' => (string) ($row->nombre_emisor_ui ?? ''),
                            'nombre_receptor' => (string) ($row->nombre_receptor_ui ?? ''),
                            'subtotal'   => (float) ($row->subtotal ?? 0),
                            'iva'        => (float) ($row->iva ?? 0),
                            'total'      => (float) ($row->total ?? 0),
                            'direction'  => strtolower((string) ($row->tipo_ui ?? '')),
                            'status'     => 'indexado',
                            'created_at' => $row->created_at ?? null,
                        ];
                    })
            );
        }

        if (in_array($filters['source'], ['all', 'user_cfdi'], true)) {
            $rows = $rows->merge(
                $this->buildUserCfdiQuery($filters)
                    ->limit($filters['limit'])
                    ->get()
                    ->map(function (SatUserCfdi $row) {
                        return [
                            'id'         => (string) $row->getKey(),
                            'source'     => 'user_cfdi',
                            'source_ui'  => 'Bóveda v2',
                            'cuenta_id'  => (string) ($row->cuenta_id ?? ''),
                            'uuid'       => strtoupper((string) ($row->uuid ?? '')),
                            'fecha'      => optional($row->fecha_emision)?->format('Y-m-d H:i:s') ?? '',
                            'tipo'       => strtolower((string) ($row->tipo_comprobante ?? '')),
                            'rfc_emisor' => strtoupper((string) ($row->rfc_emisor ?? '')),
                            'rfc_receptor'=> strtoupper((string) ($row->rfc_receptor ?? '')),
                            'nombre_emisor' => (string) ($row->nombre_emisor ?? ''),
                            'nombre_receptor' => (string) ($row->nombre_receptor ?? ''),
                            'subtotal'   => (float) ($row->subtotal ?? 0),
                            'iva'        => (float) ($row->iva ?? 0),
                            'total'      => (float) ($row->total ?? 0),
                            'direction'  => strtolower((string) ($row->direction ?? '')),
                            'status'     => 'indexado',
                            'created_at' => $row->created_at ?? null,
                        ];
                    })
            );
        }

        return $rows
            ->sortByDesc(fn (array $row) => $row['fecha'] !== '' ? $row['fecha'] : ($row['created_at'] ?? null))
            ->values();
    }

    private function buildVaultCfdiQuery(array $filters)
    {
        $conn   = DB::connection(self::CONN);
        $schema = Schema::connection(self::CONN);
        $table  = 'sat_vault_cfdis';

        abort_unless($schema->hasTable($table), 404, 'Tabla sat_vault_cfdis no existe.');

        $fechaCol = $schema->hasColumn($table, 'fecha_emision')
            ? 'fecha_emision'
            : ($schema->hasColumn($table, 'fecha') ? 'fecha' : null);

        $nombreEmisorCol = $schema->hasColumn($table, 'razon_emisor')
            ? 'razon_emisor'
            : ($schema->hasColumn($table, 'razon_social_emisor') ? 'razon_social_emisor' : null);

        $nombreReceptorCol = $schema->hasColumn($table, 'razon_receptor')
            ? 'razon_receptor'
            : ($schema->hasColumn($table, 'razon_social_receptor') ? 'razon_social_receptor' : null);

        $qb = $conn->table($table);

        $select = [
            'id',
            'cuenta_id',
            'uuid',
            'subtotal',
            'iva',
            'total',
            'created_at',
        ];

        if ($schema->hasColumn($table, 'rfc_emisor')) {
            $select[] = 'rfc_emisor';
        } else {
            $select[] = DB::raw('NULL as rfc_emisor');
        }

        if ($schema->hasColumn($table, 'rfc_receptor')) {
            $select[] = 'rfc_receptor';
        } else {
            $select[] = DB::raw('NULL as rfc_receptor');
        }

        if ($schema->hasColumn($table, 'tipo')) {
            $select[] = DB::raw('tipo as tipo_ui');
        } else {
            $select[] = DB::raw('NULL as tipo_ui');
        }

        if ($fechaCol !== null) {
            $select[] = DB::raw($fechaCol . ' as fecha_ui');
        } else {
            $select[] = DB::raw('NULL as fecha_ui');
        }

        if ($nombreEmisorCol !== null) {
            $select[] = DB::raw($nombreEmisorCol . ' as nombre_emisor_ui');
        } else {
            $select[] = DB::raw('NULL as nombre_emisor_ui');
        }

        if ($nombreReceptorCol !== null) {
            $select[] = DB::raw($nombreReceptorCol . ' as nombre_receptor_ui');
        } else {
            $select[] = DB::raw('NULL as nombre_receptor_ui');
        }

        $qb->select($select);

        if ($filters['q'] !== '') {
            $q = strtoupper($filters['q']);

            $qb->where(function ($sub) use ($schema, $table, $q) {
                $sub->orWhereRaw('UPPER(COALESCE(uuid, "")) like ?', ['%' . $q . '%']);

                if ($schema->hasColumn($table, 'rfc_emisor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(rfc_emisor, "")) like ?', ['%' . $q . '%']);
                }

                if ($schema->hasColumn($table, 'rfc_receptor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(rfc_receptor, "")) like ?', ['%' . $q . '%']);
                }

                if ($schema->hasColumn($table, 'razon_emisor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(razon_emisor, "")) like ?', ['%' . $q . '%']);
                }

                if ($schema->hasColumn($table, 'razon_receptor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(razon_receptor, "")) like ?', ['%' . $q . '%']);
                }

                if ($schema->hasColumn($table, 'razon_social_emisor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(razon_social_emisor, "")) like ?', ['%' . $q . '%']);
                }

                if ($schema->hasColumn($table, 'razon_social_receptor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(razon_social_receptor, "")) like ?', ['%' . $q . '%']);
                }
            });
        }

        if ($filters['rfc'] !== '') {
            $rfc = $filters['rfc'];

            $qb->where(function ($sub) use ($schema, $table, $rfc) {
                if ($schema->hasColumn($table, 'rfc_emisor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(rfc_emisor, "")) = ?', [$rfc]);
                }

                if ($schema->hasColumn($table, 'rfc_receptor')) {
                    $sub->orWhereRaw('UPPER(COALESCE(rfc_receptor, "")) = ?', [$rfc]);
                }

                if ($schema->hasColumn($table, 'rfc')) {
                    $sub->orWhereRaw('UPPER(COALESCE(rfc, "")) = ?', [$rfc]);
                }
            });
        }

        if ($filters['tipo'] !== '' && $schema->hasColumn($table, 'tipo')) {
            $qb->whereRaw('LOWER(COALESCE(tipo, "")) = ?', [$filters['tipo']]);
        }

        if ($fechaCol !== null) {
            if ($filters['desde'] !== '') {
                $qb->whereDate($fechaCol, '>=', $filters['desde']);
            }

            if ($filters['hasta'] !== '') {
                $qb->whereDate($fechaCol, '<=', $filters['hasta']);
            }

            $qb->orderByDesc($fechaCol);
        } else {
            $qb->orderByDesc('id');
        }

        return $qb->orderByDesc('id');
    }

    private function buildUserCfdiQuery(array $filters)
    {
        $qb = SatUserCfdi::query();

        if ($filters['q'] !== '') {
            $q = $filters['q'];

            $qb->where(function ($sub) use ($q) {
                $sub->where('uuid', 'like', '%' . $q . '%')
                    ->orWhere('rfc_owner', 'like', '%' . $q . '%')
                    ->orWhere('rfc_emisor', 'like', '%' . $q . '%')
                    ->orWhere('nombre_emisor', 'like', '%' . $q . '%')
                    ->orWhere('rfc_receptor', 'like', '%' . $q . '%')
                    ->orWhere('nombre_receptor', 'like', '%' . $q . '%');
            });
        }

        if ($filters['rfc'] !== '') {
            $rfc = $filters['rfc'];

            $qb->where(function ($sub) use ($rfc) {
                $sub->where('rfc_owner', $rfc)
                    ->orWhere('rfc_emisor', $rfc)
                    ->orWhere('rfc_receptor', $rfc);
            });
        }

        if ($filters['tipo'] !== '') {
            $qb->where('direction', $filters['tipo']);
        }

        if ($filters['desde'] !== '') {
            $qb->whereDate('fecha_emision', '>=', $filters['desde']);
        }

        if ($filters['hasta'] !== '') {
            $qb->whereDate('fecha_emision', '<=', $filters['hasta']);
        }

        return $qb
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');
    }

    private function deleteVaultCfdiById(int $id, string $mode): bool
    {
        if ($id <= 0) {
            return false;
        }

        $conn   = DB::connection(self::CONN);
        $schema = Schema::connection(self::CONN);
        $table  = 'sat_vault_cfdis';

        if (!$schema->hasTable($table)) {
            return false;
        }

        $row = $conn->table($table)->where('id', $id)->first();
        if (!$row) {
            return false;
        }

        $vaultFileId = (int) ($row->vault_file_id ?? 0);

        $conn->table($table)->where('id', $id)->delete();

        if ($mode === 'with_files') {
            if ($schema->hasColumn($table, 'xml_path')) {
                $this->deleteLooseStoragePath((string) ($row->xml_path ?? ''));
            }

            if ($schema->hasColumn($table, 'pdf_path')) {
                $this->deleteLooseStoragePath((string) ($row->pdf_path ?? ''));
            }

            if ($vaultFileId > 0) {
                $stillUsed = $conn->table($table)
                    ->where('vault_file_id', $vaultFileId)
                    ->exists();

                if (!$stillUsed) {
                    $vaultFile = VaultFile::query()->find($vaultFileId);

                    if ($vaultFile) {
                        $this->deleteFileModelPhysical($vaultFile->disk ?? '', $vaultFile->path ?? '');
                        $vaultFile->delete();
                    }
                }
            }
        }

        return true;
    }

    private function deleteUserCfdiRow(SatUserCfdi $row, string $mode): void
    {
        $xmlUploadId = (int) ($row->xml_upload_id ?? 0);
        $xmlPath     = (string) ($row->xml_path ?? '');

        $row->delete();

        if ($xmlUploadId > 0) {
            $this->syncXmlUploadCounter($xmlUploadId);
        }

        if ($mode !== 'with_files') {
            return;
        }

        $this->deleteLooseStoragePath($xmlPath, 'sat_vault');

        if ($xmlUploadId > 0) {
            $remaining = SatUserCfdi::query()
                ->where('xml_upload_id', $xmlUploadId)
                ->exists();

            if (!$remaining) {
                $upload = SatUserXmlUpload::query()->find($xmlUploadId);

                if ($upload) {
                    $this->deleteFileModelPhysical((string) ($upload->disk ?? ''), (string) ($upload->path ?? ''));
                    $upload->delete();
                }
            }
        }
    }

        private function deleteFileByTypeAndId(string $type, string $id): bool
    {
        $model = $this->resolveModel($type, $id);

        if (!$model) {
            return false;
        }

        $file = $this->resolveFilePayload($type, $model);

        if ($file['disk'] !== '' && $file['path'] !== '' && Storage::disk($file['disk'])->exists($file['path'])) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        $model->delete();

        return true;
    }

    private function deleteLooseStoragePath(string $stored, string $defaultDisk = 'private'): void
    {
        $stored = trim($stored);
        if ($stored === '' || str_contains($stored, '#')) {
            return;
        }

        $path = ltrim($stored, '/');

        foreach ([$defaultDisk, 'private', 'sat_vault', config('filesystems.default', 'local')] as $disk) {
            $disk = (string) $disk;

            if ($disk === '' || !config('filesystems.disks.' . $disk)) {
                continue;
            }

            try {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                    return;
                }
            } catch (\Throwable) {
                // no-op
            }
        }
    }

    private function deleteFileModelPhysical(string $disk, string $path): void
    {
        $disk = trim($disk) !== '' ? trim($disk) : (string) config('filesystems.default', 'local');
        $path = ltrim(trim($path), '/');

        if ($path === '' || !config('filesystems.disks.' . $disk)) {
            return;
        }

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable) {
            // no-op
        }
    }

    private function normalizeDeleteMode(string $mode): string
    {
        return $mode === 'with_files' ? 'with_files' : 'index_only';
    }

    private function queryMetadata(string $search): Collection
    {
        return SatUserMetadataUpload::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('original_name', 'like', "%{$search}%")
                        ->orWhere('rfc_owner', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('direction_detected', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(fn ($row) => $this->mapV2Upload($row, 'metadata'));
    }

    private function queryXml(string $search): Collection
    {
        return SatUserXmlUpload::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('original_name', 'like', "%{$search}%")
                        ->orWhere('rfc_owner', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('direction_detected', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(fn ($row) => $this->mapV2Upload($row, 'xml'));
    }

    private function queryReport(string $search): Collection
    {
        return SatUserReportUpload::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('original_name', 'like', "%{$search}%")
                        ->orWhere('rfc_owner', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('report_type', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(fn ($row) => $this->mapV2Upload($row, 'report'));
    }

    private function queryVaultFilesV1(string $search): Collection
    {
        return VaultFile::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('filename', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhere('mime', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(fn ($row) => $this->mapVaultFile($row));
    }

    private function querySatDownloadsV1(string $search): Collection
    {
        return SatDownload::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('rfc', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('zip_path', 'like', "%{$search}%")
                        ->orWhere('vault_path', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(fn ($row) => $this->mapSatDownload($row));
    }

    private function mapV2Upload(object $row, string $type): array
    {
        return [
            'id'         => (string) $row->getKey(),
            'type'       => $type,
            'cuenta_id'  => $row->cuenta_id ?? null,
            'rfc'        => $row->rfc_owner ?? null,
            'name'       => $row->original_name ?? ('Archivo ' . strtoupper($type)),
            'disk'       => $row->disk ?? '',
            'path'       => $row->path ?? '',
            'bytes'      => (int) ($row->bytes ?? 0),
            'status'     => (string) ($row->status ?? ''),
            'direction'  => $row->direction_detected ?? null,
            'created_at' => $row->created_at ?? null,
        ];
    }

    private function mapVaultFile(VaultFile $row): array
    {
        $bytes = (int) ($row->bytes ?? 0);
        if ($bytes <= 0) {
            $bytes = (int) ($row->size_bytes ?? 0);
        }

        return [
            'id'         => (string) $row->getKey(),
            'type'       => 'vault',
            'cuenta_id'  => $row->cuenta_id ?? null,
            'rfc'        => $row->rfc ?? null,
            'name'       => $row->filename ?: 'Archivo bóveda v1',
            'disk'       => (string) ($row->disk ?? ''),
            'path'       => (string) ($row->path ?? ''),
            'bytes'      => $bytes,
            'status'     => (string) ($row->source ?? 'vault'),
            'direction'  => (string) ($row->tipo ?? ''),
            'created_at' => $row->created_at ?? null,
        ];
    }

    private function mapSatDownload(SatDownload $row): array
    {
        $file = $this->resolveFilePayload('satdownload', $row);

        return [
            'id'         => (string) $row->getKey(),
            'type'       => 'satdownload',
            'cuenta_id'  => $row->cuenta_id ?? null,
            'rfc'        => $row->rfc ?? null,
            'name'       => $file['name'],
            'disk'       => $file['disk'],
            'path'       => $file['path'],
            'bytes'      => (int) $file['bytes'],
            'status'     => (string) ($row->status ?? ''),
            'direction'  => (string) ($row->tipo ?? ''),
            'created_at' => $row->created_at ?? null,
        ];
    }

    private function resolveModel(string $type, string $id): object|null
    {
        return match ($type) {
            'metadata'    => SatUserMetadataUpload::query()->find($id),
            'xml'         => SatUserXmlUpload::query()->find($id),
            'report'      => SatUserReportUpload::query()->find($id),
            'vault'       => VaultFile::query()->find($id),
            'satdownload' => SatDownload::query()->find($id),
            default       => null,
        };
    }

    private function resolveFilePayload(string $type, object $model): array
    {
        if ($type === 'metadata' || $type === 'xml' || $type === 'report') {
            return [
                'disk'  => (string) ($model->disk ?? ''),
                'path'  => (string) ($model->path ?? ''),
                'name'  => (string) ($model->original_name ?? ('archivo-' . $type)),
                'bytes' => (int) ($model->bytes ?? 0),
            ];
        }

        if ($type === 'vault') {
            $bytes = (int) ($model->bytes ?? 0);
            if ($bytes <= 0) {
                $bytes = (int) ($model->size_bytes ?? 0);
            }

            return [
                'disk'  => (string) ($model->disk ?? ''),
                'path'  => (string) ($model->path ?? ''),
                'name'  => (string) ($model->filename ?? 'archivo-boveda-v1'),
                'bytes' => $bytes,
            ];
        }

        if ($type === 'satdownload') {
            $zipDisk   = (string) ($model->zip_disk ?? '');
            $zipPath   = (string) ($model->zip_path ?? '');
            $vaultPath = (string) ($model->vault_path ?? '');

            $disk = $zipDisk;
            $path = $zipPath;

            if ($path === '' && $vaultPath !== '') {
                $path = $vaultPath;
            }

            if ($disk === '' && isset($model->disk)) {
                $disk = (string) $model->disk;
            }

            if ($disk === '') {
                $disk = (string) config('filesystems.default', 'local');
            }

            $name = basename($path);
            if ($name === '' || $name === '.' || $name === DIRECTORY_SEPARATOR) {
                $name = 'sat-download-' . (string) $model->getKey() . '.zip';
            }

            $bytes = (int) ($model->zip_bytes ?? 0);
            if ($bytes <= 0) {
                $bytes = (int) ($model->size_bytes ?? 0);
            }
            if ($bytes <= 0) {
                $bytes = (int) ($model->bytes ?? 0);
            }

            return [
                'disk'  => $disk,
                'path'  => $path,
                'name'  => $name,
                'bytes' => $bytes,
            ];
        }

        return [
            'disk'  => '',
            'path'  => '',
            'name'  => 'archivo',
            'bytes' => 0,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | METADATA
    |--------------------------------------------------------------------------
    */

    public function updateMetadata(Request $request, int $id): RedirectResponse
    {
        $row = SatUserMetadataUpload::findOrFail($id);

        $row->update([
            'rfc_owner'          => strtoupper((string) $request->input('rfc_owner', $row->rfc_owner)),
            'direction_detected' => $request->input('direction_detected', $row->direction_detected),
            'status'             => $request->input('status', $row->status),
            'original_name'      => $request->input('original_name', $row->original_name),
        ]);

        return back()->with('success', 'Metadata actualizado correctamente.');
    }

    public function resetMetadataCount(int $id): RedirectResponse
    {
        $row = SatUserMetadataUpload::findOrFail($id);
        $row->update(['rows_count' => 0]);

        return back()->with('success', 'Contador de metadata reiniciado.');
    }

    public function recountMetadata(int $id): RedirectResponse
    {
        $count = SatUserMetadataItem::where('metadata_upload_id', $id)->count();

        SatUserMetadataUpload::where('id', $id)->update([
            'rows_count' => $count,
        ]);

        return back()->with('success', 'Contador recalculado: ' . number_format($count));
    }

    public function purgeMetadataItems(int $id): RedirectResponse
    {
        $deleted = SatUserMetadataItem::where('metadata_upload_id', $id)->delete();

        $this->syncMetadataUploadCounter($id);

        return back()->with('success', 'Metadata limpiada. Registros eliminados: ' . number_format($deleted));
    }

    public function destroyMetadataFull(int $id): RedirectResponse
    {
        $row = SatUserMetadataUpload::findOrFail($id);

        SatUserMetadataItem::where('metadata_upload_id', $id)->delete();

        $this->deleteFileModelPhysical($row->disk ?? '', $row->path ?? '');

        $row->delete();

        return back()->with('success', 'Metadata eliminada completamente.');
    }

    /*
    |--------------------------------------------------------------------------
    | XML
    |--------------------------------------------------------------------------
    */

    public function updateXml(Request $request, int $id): RedirectResponse
    {
        $row = SatUserXmlUpload::findOrFail($id);

        $row->update([
            'rfc_owner'          => strtoupper((string) $request->input('rfc_owner', $row->rfc_owner)),
            'direction_detected' => $request->input('direction_detected', $row->direction_detected),
            'status'             => $request->input('status', $row->status),
            'original_name'      => $request->input('original_name', $row->original_name),
        ]);

        return back()->with('success', 'XML actualizado correctamente.');
    }

    public function resetXmlCount(int $id): RedirectResponse
    {
        SatUserXmlUpload::where('id', $id)->update(['files_count' => 0]);

        return back()->with('success', 'Contador XML reiniciado.');
    }

    public function recountXml(int $id): RedirectResponse
    {
        $count = SatUserCfdi::where('xml_upload_id', $id)->count();

        SatUserXmlUpload::where('id', $id)->update([
            'files_count' => $count,
        ]);

        return back()->with('success', 'CFDI recalculados: ' . number_format($count));
    }

    public function purgeXmlCfdi(int $id): RedirectResponse
    {
        $deleted = SatUserCfdi::where('xml_upload_id', $id)->delete();

        $this->syncXmlUploadCounter($id);

        return back()->with('success', 'CFDI eliminados: ' . number_format($deleted));
    }

    public function destroyXmlFull(int $id): RedirectResponse
    {
        $row = SatUserXmlUpload::findOrFail($id);

        SatUserCfdi::where('xml_upload_id', $id)->delete();

        $this->deleteFileModelPhysical($row->disk ?? '', $row->path ?? '');

        $row->delete();

        return back()->with('success', 'XML eliminado completamente.');
    }

    /*
    |--------------------------------------------------------------------------
    | REPORTES
    |--------------------------------------------------------------------------
    */

    public function updateReport(Request $request, int $id): RedirectResponse
    {
        $row = SatUserReportUpload::findOrFail($id);

        $row->update([
            'rfc_owner'                   => strtoupper((string) $request->input('rfc_owner', $row->rfc_owner)),
            'report_type'                 => $request->input('report_type', $row->report_type),
            'status'                      => $request->input('status', $row->status),
            'original_name'               => $request->input('original_name', $row->original_name),
            'linked_metadata_upload_id'   => $request->input('linked_metadata_upload_id'),
            'linked_xml_upload_id'        => $request->input('linked_xml_upload_id'),
        ]);

        return back()->with('success', 'Reporte actualizado correctamente.');
    }

    public function resetReportCount(int $id): RedirectResponse
    {
        SatUserReportUpload::where('id', $id)->update(['rows_count' => 0]);

        return back()->with('success', 'Contador de reporte reiniciado.');
    }

    public function recountReport(int $id): RedirectResponse
    {
        $count = SatUserReportItem::where('report_upload_id', $id)->count();

        SatUserReportUpload::where('id', $id)->update([
            'rows_count' => $count,
        ]);

        return back()->with('success', 'Registros recalculados: ' . number_format($count));
    }

    public function purgeReportItems(int $id): RedirectResponse
    {
        $deleted = SatUserReportItem::where('report_upload_id', $id)->delete();

        $this->syncReportUploadCounter($id);

        return back()->with('success', 'Reporte limpiado. Registros eliminados: ' . number_format($deleted));
    }

    public function destroyReportFull(int $id): RedirectResponse
    {
        $row = SatUserReportUpload::findOrFail($id);

        SatUserReportItem::where('report_upload_id', $id)->delete();

        $this->deleteFileModelPhysical($row->disk ?? '', $row->path ?? '');

        $row->delete();

        return back()->with('success', 'Reporte eliminado completamente.');
    }

    /*
|--------------------------------------------------------------------------
| REGISTROS DERIVADOS · METADATA
|--------------------------------------------------------------------------
*/

public function destroyMetadataRecord(int $id): RedirectResponse
{
    $row = SatUserMetadataItem::query()->find($id);

    if (!$row) {
        return back()->with('error', 'El registro de metadata no existe.');
    }

    $uploadId = (int) ($row->metadata_upload_id ?? 0);

    $row->delete();

    if ($uploadId > 0) {
        $this->syncMetadataUploadCounter($uploadId);
    }

    return back()->with('success', 'Registro de metadata eliminado correctamente.');
}

public function bulkDestroyMetadataRecords(Request $request): RedirectResponse
{
    $selected = $request->input('selected_metadata_records', []);

    if (!is_array($selected) || count($selected) === 0) {
        return back()->with('error', 'Selecciona al menos un registro de metadata.');
    }

    $deleted = 0;
    $errors  = 0;
    $uploadIds = [];

    foreach ($selected as $id) {
        $row = SatUserMetadataItem::query()->find((int) $id);

        if (!$row) {
            $errors++;
            continue;
        }

        $uploadId = (int) ($row->metadata_upload_id ?? 0);
        if ($uploadId > 0) {
            $uploadIds[] = $uploadId;
        }

        $row->delete();
        $deleted++;
    }

    $this->syncMetadataUploadCounters($uploadIds);

    return back()->with(
        $deleted > 0 ? 'success' : 'error',
        $deleted > 0
            ? 'Registros metadata eliminados: ' . number_format($deleted) . ($errors > 0 ? ' · omitidos: ' . number_format($errors) : '')
            : 'No se pudo eliminar ningún registro de metadata.'
    );
}

/*
|--------------------------------------------------------------------------
| REGISTROS DERIVADOS · REPORTES
|--------------------------------------------------------------------------
*/

public function destroyReportRecord(int $id): RedirectResponse
{
    $row = SatUserReportItem::query()->find($id);

    if (!$row) {
        return back()->with('error', 'El registro de reporte no existe.');
    }

    $uploadId = (int) ($row->report_upload_id ?? 0);

    $row->delete();

    if ($uploadId > 0) {
        $this->syncReportUploadCounter($uploadId);
    }

    return back()->with('success', 'Registro de reporte eliminado correctamente.');
}

public function bulkDestroyReportRecords(Request $request): RedirectResponse
{
    $selected = $request->input('selected_report_records', []);

    if (!is_array($selected) || count($selected) === 0) {
        return back()->with('error', 'Selecciona al menos un registro de reporte.');
    }

    $deleted = 0;
    $errors  = 0;
    $uploadIds = [];

    foreach ($selected as $id) {
        $row = SatUserReportItem::query()->find((int) $id);

        if (!$row) {
            $errors++;
            continue;
        }

        $uploadId = (int) ($row->report_upload_id ?? 0);
        if ($uploadId > 0) {
            $uploadIds[] = $uploadId;
        }

        $row->delete();
        $deleted++;
    }

    $this->syncReportUploadCounters($uploadIds);

    return back()->with(
        $deleted > 0 ? 'success' : 'error',
        $deleted > 0
            ? 'Registros reporte eliminados: ' . number_format($deleted) . ($errors > 0 ? ' · omitidos: ' . number_format($errors) : '')
            : 'No se pudo eliminar ningún registro de reporte.'
    );
}

/*
|--------------------------------------------------------------------------
| FILTROS + LISTADOS ADMIN · METADATA ITEMS
|--------------------------------------------------------------------------
*/

private function resolveMetadataRecordFilters(Request $request): array
{
    return [
        'q'         => trim((string) $request->input('mr_q', '')),
        'rfc'       => strtoupper(trim((string) $request->input('mr_rfc', ''))),
        'direction' => strtolower(trim((string) $request->input('mr_direction', ''))),
        'desde'     => trim((string) $request->input('mr_desde', '')),
        'hasta'     => trim((string) $request->input('mr_hasta', '')),
        'page'      => max(1, (int) $request->input('mr_page', 1)),
        'per_page'  => min(200, max(10, (int) $request->input('mr_per_page', 50))),
    ];
}

private function buildMetadataRecordItems(array $filters): LengthAwarePaginator
{
    $qb = SatUserMetadataItem::query();

    if ($filters['q'] !== '') {
        $q = $filters['q'];

        $qb->where(function ($sub) use ($q) {
            $sub->where('uuid', 'like', '%' . $q . '%')
                ->orWhere('rfc_owner', 'like', '%' . $q . '%')
                ->orWhere('rfc_emisor', 'like', '%' . $q . '%')
                ->orWhere('nombre_emisor', 'like', '%' . $q . '%')
                ->orWhere('rfc_receptor', 'like', '%' . $q . '%')
                ->orWhere('nombre_receptor', 'like', '%' . $q . '%')
                ->orWhere('estatus', 'like', '%' . $q . '%');
        });
    }

    if ($filters['rfc'] !== '') {
        $rfc = $filters['rfc'];

        $qb->where(function ($sub) use ($rfc) {
            $sub->where('rfc_owner', $rfc)
                ->orWhere('rfc_emisor', $rfc)
                ->orWhere('rfc_receptor', $rfc);
        });
    }

    if (in_array($filters['direction'], ['emitidos', 'recibidos'], true)) {
        $qb->where('direction', $filters['direction']);
    }

    if ($filters['desde'] !== '') {
        $qb->whereDate('fecha_emision', '>=', $filters['desde']);
    }

    if ($filters['hasta'] !== '') {
        $qb->whereDate('fecha_emision', '<=', $filters['hasta']);
    }

    return $qb
        ->orderByDesc('fecha_emision')
        ->orderByDesc('id')
        ->paginate(
            perPage: $filters['per_page'],
            columns: ['*'],
            pageName: 'mr_page',
            page: $filters['page']
        );
}

/*
|--------------------------------------------------------------------------
| FILTROS + LISTADOS ADMIN · REPORT ITEMS
|--------------------------------------------------------------------------
*/

private function resolveReportRecordFilters(Request $request): array
{
    return [
        'q'         => trim((string) $request->input('rr_q', '')),
        'rfc'       => strtoupper(trim((string) $request->input('rr_rfc', ''))),
        'direction' => strtolower(trim((string) $request->input('rr_direction', ''))),
        'desde'     => trim((string) $request->input('rr_desde', '')),
        'hasta'     => trim((string) $request->input('rr_hasta', '')),
        'page'      => max(1, (int) $request->input('rr_page', 1)),
        'per_page'  => min(200, max(10, (int) $request->input('rr_per_page', 50))),
    ];
}

private function buildReportRecordItems(array $filters): LengthAwarePaginator
{
    $qb = SatUserReportItem::query();

    if ($filters['q'] !== '') {
        $q = $filters['q'];

        $qb->where(function ($sub) use ($q) {
            $sub->where('uuid', 'like', '%' . $q . '%')
                ->orWhere('rfc_owner', 'like', '%' . $q . '%')
                ->orWhere('emisor_rfc', 'like', '%' . $q . '%')
                ->orWhere('emisor_nombre', 'like', '%' . $q . '%')
                ->orWhere('receptor_rfc', 'like', '%' . $q . '%')
                ->orWhere('receptor_nombre', 'like', '%' . $q . '%')
                ->orWhere('report_type', 'like', '%' . $q . '%');
        });
    }

    if ($filters['rfc'] !== '') {
        $rfc = $filters['rfc'];

        $qb->where(function ($sub) use ($rfc) {
            $sub->where('rfc_owner', $rfc)
                ->orWhere('emisor_rfc', $rfc)
                ->orWhere('receptor_rfc', $rfc);
        });
    }

    if (in_array($filters['direction'], ['emitidos', 'recibidos'], true)) {
        $qb->where('direction', $filters['direction']);
    }

    if ($filters['desde'] !== '') {
        $qb->whereDate('fecha_emision', '>=', $filters['desde']);
    }

    if ($filters['hasta'] !== '') {
        $qb->whereDate('fecha_emision', '<=', $filters['hasta']);
    }

    return $qb
        ->orderByDesc('fecha_emision')
        ->orderByDesc('id')
        ->paginate(
            perPage: $filters['per_page'],
            columns: ['*'],
            pageName: 'rr_page',
            page: $filters['page']
        );
}

/*
|--------------------------------------------------------------------------
| RESYNC CONTADORES
|--------------------------------------------------------------------------
*/

private function syncMetadataUploadCounter(int $uploadId): void
{
    if ($uploadId <= 0) {
        return;
    }

    $count = SatUserMetadataItem::query()
        ->where('metadata_upload_id', $uploadId)
        ->count();

    SatUserMetadataUpload::query()
        ->whereKey($uploadId)
        ->update(['rows_count' => $count]);
}

private function syncMetadataUploadCounters(array $uploadIds): void
{
    foreach (array_unique(array_filter(array_map('intval', $uploadIds))) as $uploadId) {
        $this->syncMetadataUploadCounter($uploadId);
    }
}

private function syncReportUploadCounter(int $uploadId): void
{
    if ($uploadId <= 0) {
        return;
    }

    $count = SatUserReportItem::query()
        ->where('report_upload_id', $uploadId)
        ->count();

    SatUserReportUpload::query()
        ->whereKey($uploadId)
        ->update(['rows_count' => $count]);
}

private function syncReportUploadCounters(array $uploadIds): void
{
    foreach (array_unique(array_filter(array_map('intval', $uploadIds))) as $uploadId) {
        $this->syncReportUploadCounter($uploadId);
    }
}

private function syncXmlUploadCounter(int $uploadId): void
{
    if ($uploadId <= 0) {
        return;
    }

    $count = SatUserCfdi::query()
        ->where('xml_upload_id', $uploadId)
        ->count();

    SatUserXmlUpload::query()
        ->whereKey($uploadId)
        ->update(['files_count' => $count]);
}


/*
|--------------------------------------------------------------------------
| ELIMINACIÓN MASIVA POR FILTRO ACTIVO · METADATA
|--------------------------------------------------------------------------
*/

public function purgeFilteredMetadataRecords(Request $request): RedirectResponse
{
    $filters = $this->resolveMetadataRecordFilters($request);

    $qb = SatUserMetadataItem::query();

    if ($filters['q'] !== '') {
        $q = $filters['q'];

        $qb->where(function ($sub) use ($q) {
            $sub->where('uuid', 'like', '%' . $q . '%')
                ->orWhere('rfc_owner', 'like', '%' . $q . '%')
                ->orWhere('rfc_emisor', 'like', '%' . $q . '%')
                ->orWhere('nombre_emisor', 'like', '%' . $q . '%')
                ->orWhere('rfc_receptor', 'like', '%' . $q . '%')
                ->orWhere('nombre_receptor', 'like', '%' . $q . '%')
                ->orWhere('estatus', 'like', '%' . $q . '%');
        });
    }

    if ($filters['rfc'] !== '') {
        $rfc = $filters['rfc'];

        $qb->where(function ($sub) use ($rfc) {
            $sub->where('rfc_owner', $rfc)
                ->orWhere('rfc_emisor', $rfc)
                ->orWhere('rfc_receptor', $rfc);
        });
    }

    if (in_array($filters['direction'], ['emitidos', 'recibidos'], true)) {
        $qb->where('direction', $filters['direction']);
    }

    if ($filters['desde'] !== '') {
        $qb->whereDate('fecha_emision', '>=', $filters['desde']);
    }

    if ($filters['hasta'] !== '') {
        $qb->whereDate('fecha_emision', '<=', $filters['hasta']);
    }

    $uploadIds = $qb->clone()
        ->whereNotNull('metadata_upload_id')
        ->pluck('metadata_upload_id')
        ->map(fn ($v) => (int) $v)
        ->filter(fn ($v) => $v > 0)
        ->unique()
        ->values()
        ->all();

    $deleted = $qb->delete();

    $this->syncMetadataUploadCounters($uploadIds);

    return back()->with(
        $deleted > 0 ? 'success' : 'error',
        $deleted > 0
            ? 'Registros metadata filtrados eliminados: ' . number_format($deleted)
            : 'No hubo registros metadata filtrados para eliminar.'
    );
}

/*
|--------------------------------------------------------------------------
| ELIMINACIÓN MASIVA POR FILTRO ACTIVO · REPORTES
|--------------------------------------------------------------------------
*/

public function purgeFilteredReportRecords(Request $request): RedirectResponse
{
    $filters = $this->resolveReportRecordFilters($request);

    $qb = SatUserReportItem::query();

    if ($filters['q'] !== '') {
        $q = $filters['q'];

        $qb->where(function ($sub) use ($q) {
            $sub->where('uuid', 'like', '%' . $q . '%')
                ->orWhere('rfc_owner', 'like', '%' . $q . '%')
                ->orWhere('emisor_rfc', 'like', '%' . $q . '%')
                ->orWhere('emisor_nombre', 'like', '%' . $q . '%')
                ->orWhere('receptor_rfc', 'like', '%' . $q . '%')
                ->orWhere('receptor_nombre', 'like', '%' . $q . '%')
                ->orWhere('report_type', 'like', '%' . $q . '%');
        });
    }

    if ($filters['rfc'] !== '') {
        $rfc = $filters['rfc'];

        $qb->where(function ($sub) use ($rfc) {
            $sub->where('rfc_owner', $rfc)
                ->orWhere('emisor_rfc', $rfc)
                ->orWhere('receptor_rfc', $rfc);
        });
    }

    if (in_array($filters['direction'], ['emitidos', 'recibidos'], true)) {
        $qb->where('direction', $filters['direction']);
    }

    if ($filters['desde'] !== '') {
        $qb->whereDate('fecha_emision', '>=', $filters['desde']);
    }

    if ($filters['hasta'] !== '') {
        $qb->whereDate('fecha_emision', '<=', $filters['hasta']);
    }

    $uploadIds = $qb->clone()
        ->whereNotNull('report_upload_id')
        ->pluck('report_upload_id')
        ->map(fn ($v) => (int) $v)
        ->filter(fn ($v) => $v > 0)
        ->unique()
        ->values()
        ->all();

    $deleted = $qb->delete();

    $this->syncReportUploadCounters($uploadIds);

    return back()->with(
        $deleted > 0 ? 'success' : 'error',
        $deleted > 0
            ? 'Registros reporte filtrados eliminados: ' . number_format($deleted)
            : 'No hubo registros reporte filtrados para eliminar.'
    );
}

/*
|--------------------------------------------------------------------------
| ELIMINACIÓN POR LOTE COMPLETO
|--------------------------------------------------------------------------
*/

public function destroyMetadataBatch(int $uploadId): RedirectResponse
{
    if ($uploadId <= 0) {
        return back()->with('error', 'Lote de metadata inválido.');
    }

    $upload = SatUserMetadataUpload::query()->find($uploadId);

    $deletedItems = SatUserMetadataItem::query()
        ->where('metadata_upload_id', $uploadId)
        ->delete();

    if ($upload) {
        $this->deleteFileModelPhysical((string) ($upload->disk ?? ''), (string) ($upload->path ?? ''));
        $upload->delete();
    }

    return back()->with(
        'success',
        'Lote metadata #' . number_format($uploadId) . ' eliminado. Registros: ' . number_format($deletedItems)
    );
}

public function destroyReportBatch(int $uploadId): RedirectResponse
{
    if ($uploadId <= 0) {
        return back()->with('error', 'Lote de reporte inválido.');
    }

    $upload = SatUserReportUpload::query()->find($uploadId);

    $deletedItems = SatUserReportItem::query()
        ->where('report_upload_id', $uploadId)
        ->delete();

    if ($upload) {
        $this->deleteFileModelPhysical((string) ($upload->disk ?? ''), (string) ($upload->path ?? ''));
        $upload->delete();
    }

    return back()->with(
        'success',
        'Lote reporte #' . number_format($uploadId) . ' eliminado. Registros: ' . number_format($deletedItems)
    );
}

}