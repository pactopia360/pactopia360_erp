<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Models\Cliente\SatUserReportItem;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatUserAccess;
use App\Models\Cliente\SatUserCfdi;
use App\Models\Cliente\SatUserMetadataItem;
use App\Models\Cliente\SatUserMetadataUpload;
use App\Models\Cliente\SatUserVault;
use App\Models\Cliente\SatUserXmlUpload;
use App\Models\Cliente\SatUserReportUpload;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\VaultFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class SatVaultV2Controller extends Controller
{
    private const DISK = 'sat_vault';

    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $access = SatUserAccess::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->first();

        $rfcs = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->get()
            ->filter(function (SatCredential $row) {
                return $this->credentialIsActive($row);
            })
            ->sortBy('rfc')
            ->values();

        $vaults = SatUserVault::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->orderBy('rfc')
            ->get();

        $selectedRfc = strtoupper(trim((string) $request->query('rfc', '')));
        if ($selectedRfc === '' && $rfcs->count() === 1) {
            $selectedRfc = (string) $rfcs->first()->rfc;
        }

        $metadataUploads  = collect();
        $xmlUploads       = collect();
        $reportUploads        = collect();
        $downloadItems        = collect();
        $unifiedDownloadItems = collect();
        $downloadSources      = [
            'centro_sat' => 0,
            'boveda_v1'  => 0,
            'boveda_v2'  => 0,
        ];
        $storageBreakdown     = [
            'used_bytes'      => 0,
            'available_bytes' => 0,
            'quota_bytes'     => 0,
            'used_gb'         => 0.0,
            'available_gb'    => 0.0,
            'quota_gb'        => 0.0,
            'used_pct'        => 0.0,
            'available_pct'   => 0.0,
            'chart'           => [
                'series' => [0, 0],
                'labels' => ['Usado', 'Disponible'],
            ],
        ];
        $metadataItems        = collect();
        $metadataCount        = 0;
        $cfdiCount            = 0;
        $reportCount          = 0;
        $reconPending         = 0;
        $metadataStatuses     = collect();

        $quoteItems           = collect();
        $quoteStats           = [
            'total'           => 0,
            'draft'           => 0,
            'requested'       => 0,
            'ready'           => 0,
            'paid'            => 0,
            'in_progress'     => 0,
            'done'            => 0,
            'canceled'        => 0,
            'payable'         => 0,
        ];
        $quoteActive          = null;

        $metadataSummary = [
            'emitidos'       => 0,
            'recibidos'      => 0,
            'vigentes'       => 0,
            'cancelados'     => 0,
            'total_monto'    => 0,
            'rfc_emisores'   => 0,
            'rfc_receptores' => 0,
            'meses'          => [],
            'estatuses'      => [],
            'directions'     => [],
        ];

        $metadataFilters = [
            'q'         => trim((string) $request->query('q', '')),
            'direction' => strtolower(trim((string) $request->query('direction', ''))),
            'estatus'   => trim((string) $request->query('estatus', '')),
            'desde'     => trim((string) $request->query('desde', '')),
            'hasta'     => trim((string) $request->query('hasta', '')),
            'page_size' => (int) $request->query('page_size', 25),
        ];

        if (!in_array($metadataFilters['direction'], ['emitidos', 'recibidos'], true)) {
            $metadataFilters['direction'] = '';
        }

        if ($metadataFilters['page_size'] < 10) {
            $metadataFilters['page_size'] = 10;
        }
        if ($metadataFilters['page_size'] > 200) {
            $metadataFilters['page_size'] = 200;
        }

        if ($selectedRfc !== '') {
            $metadataUploads = SatUserMetadataUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->latest('id')
                ->limit(20)
                ->get();

            $xmlUploads = SatUserXmlUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->latest('id')
                ->limit(20)
                ->get();

            $reportUploads = SatUserReportUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->latest('id')
                ->limit(20)
                ->get();

            $downloadItems = collect()
            ->merge(
                $metadataUploads->map(function ($upload) {
                    return [
                        'kind'          => 'metadata',
                        'id'            => (int) $upload->id,
                        'rfc_owner'     => (string) $upload->rfc_owner,
                        'direction'     => (string) ($upload->direction_detected ?? ''),
                        'source_type'   => (string) ($upload->source_type ?? 'metadata'),
                        'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('metadata_' . $upload->id)),
                        'stored_name'   => (string) ($upload->stored_name ?? ''),
                        'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                        'bytes'         => (int) ($upload->bytes ?? 0),
                        'rows_count'    => (int) ($upload->rows_count ?? 0),
                        'files_count'   => 0,
                        'status'        => (string) ($upload->status ?? ''),
                        'created_at'    => $upload->created_at,
                    ];
                })
            )
            ->merge(
                $xmlUploads->map(function ($upload) {
                    return [
                        'kind'          => 'xml',
                        'id'            => (int) $upload->id,
                        'rfc_owner'     => (string) $upload->rfc_owner,
                        'direction'     => (string) ($upload->direction_detected ?? ''),
                        'source_type'   => (string) ($upload->source_type ?? 'xml'),
                        'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('xml_' . $upload->id)),
                        'stored_name'   => (string) ($upload->stored_name ?? ''),
                        'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                        'bytes'         => (int) ($upload->bytes ?? 0),
                        'rows_count'    => 0,
                        'files_count'   => (int) ($upload->files_count ?? 0),
                        'status'        => (string) ($upload->status ?? ''),
                        'created_at'    => $upload->created_at,
                    ];
                })
            )
            ->merge(
                $reportUploads->map(function ($upload) {
                    return [
                        'kind'          => 'report',
                        'id'            => (int) $upload->id,
                        'rfc_owner'     => (string) $upload->rfc_owner,
                        'direction'     => (string) data_get($upload->meta, 'report_direction', ''),
                        'source_type'   => (string) ($upload->report_type ?? 'report'),
                        'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('reporte_' . $upload->id)),
                        'stored_name'   => (string) ($upload->stored_name ?? ''),
                        'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                        'bytes'         => (int) ($upload->bytes ?? 0),
                        'rows_count'    => (int) ($upload->rows_count ?? 0),
                        'files_count'   => 0,
                        'status'        => (string) ($upload->status ?? ''),
                        'created_at'    => $upload->created_at,
                    ];
                })
            )
            ->sortByDesc(function (array $row) {
                return optional($row['created_at'] ?? null)?->timestamp ?? 0;
            })
            ->values();

            $unifiedDownloadItems = $this->buildUnifiedDownloadItems(
                cuentaId: $cuentaId,
                usuarioId: $usuarioId,
                selectedRfc: $selectedRfc,
                v2Items: $downloadItems
            );

            $downloadSources = [
                'centro_sat' => (int) $unifiedDownloadItems->where('origin', 'centro_sat')->count(),
                'boveda_v1'  => (int) $unifiedDownloadItems->where('origin', 'boveda_v1')->count(),
                'boveda_v2'  => (int) $unifiedDownloadItems->where('origin', 'boveda_v2')->count(),
            ];

            $storageBreakdown = $this->buildUnifiedStorageBreakdown(
                cuentaId: $cuentaId,
                selectedRfc: $selectedRfc,
                unifiedItems: $unifiedDownloadItems
            );

            $metadataCount = SatUserMetadataItem::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->count();

            $cfdiCount = SatUserCfdi::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->count();

            $reportCount = SatUserReportUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->count();
            
            $quoteItems = $this->buildQuoteItems(
                cuentaId: $cuentaId,
                usuarioId: $usuarioId,
                selectedRfc: $selectedRfc
            );

            $quoteStats = $this->buildQuoteStats($quoteItems);

            $quoteActive = $quoteItems
                ->first(function (array $item) {
                    return in_array((string) ($item['status_ui'] ?? ''), ['en_descarga', 'cotizada', 'pagada', 'en_proceso'], true);
                });

            if ($quoteActive === null) {
                $quoteActive = $quoteItems->first();
            }

            $baseItemsQuery = SatUserMetadataItem::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc);

            if ($metadataFilters['q'] !== '') {
                $q = $metadataFilters['q'];
                $baseItemsQuery->where(function ($sub) use ($q) {
                    $sub->where('uuid', 'like', '%' . $q . '%')
                        ->orWhere('rfc_emisor', 'like', '%' . $q . '%')
                        ->orWhere('nombre_emisor', 'like', '%' . $q . '%')
                        ->orWhere('rfc_receptor', 'like', '%' . $q . '%')
                        ->orWhere('nombre_receptor', 'like', '%' . $q . '%')
                        ->orWhere('estatus', 'like', '%' . $q . '%');
                });
            }

            if ($metadataFilters['direction'] !== '') {
                $baseItemsQuery->where('direction', $metadataFilters['direction']);
            }

            if ($metadataFilters['estatus'] !== '') {
                if ($metadataFilters['estatus'] === 'Sin estatus') {
                    $baseItemsQuery->where(function ($q) {
                        $q->whereNull('estatus')
                        ->orWhere('estatus', '')
                        ->orWhereRaw('TRIM(estatus) = ?', ['']);
                    });
                } else {
                    $baseItemsQuery->whereRaw('TRIM(COALESCE(estatus, "")) = ?', [$metadataFilters['estatus']]);
                }
            }

            if ($metadataFilters['desde'] !== '') {
                $baseItemsQuery->whereDate('fecha_emision', '>=', $metadataFilters['desde']);
            }

            if ($metadataFilters['hasta'] !== '') {
                $baseItemsQuery->whereDate('fecha_emision', '<=', $metadataFilters['hasta']);
            }

            $summaryRow = (clone $baseItemsQuery)
                ->selectRaw("
                    SUM(CASE WHEN direction = 'emitidos' THEN 1 ELSE 0 END) AS emitidos,
                    SUM(CASE WHEN direction = 'recibidos' THEN 1 ELSE 0 END) AS recibidos,
                    SUM(
                        CASE
                            WHEN estatus IS NULL
                            OR TRIM(COALESCE(estatus, '')) = ''
                            OR LOWER(TRIM(COALESCE(estatus, ''))) NOT LIKE '%cancel%'
                            THEN 1 ELSE 0
                        END
                    ) AS vigentes,
                    SUM(
                        CASE
                            WHEN LOWER(TRIM(COALESCE(estatus, ''))) LIKE '%cancel%'
                            THEN 1 ELSE 0
                        END
                    ) AS cancelados,
                    COALESCE(SUM(monto), 0) AS total_monto,
                    COUNT(DISTINCT NULLIF(TRIM(COALESCE(rfc_emisor, '')), '')) AS rfc_emisores,
                    COUNT(DISTINCT NULLIF(TRIM(COALESCE(rfc_receptor, '')), '')) AS rfc_receptores
                ")
                ->first();

            $metadataSummary['emitidos']       = (int) ($summaryRow->emitidos ?? 0);
            $metadataSummary['recibidos']      = (int) ($summaryRow->recibidos ?? 0);
            $metadataSummary['vigentes']       = (int) ($summaryRow->vigentes ?? 0);
            $metadataSummary['cancelados']     = (int) ($summaryRow->cancelados ?? 0);
            $metadataSummary['total_monto']    = (float) ($summaryRow->total_monto ?? 0);
            $metadataSummary['rfc_emisores']   = (int) ($summaryRow->rfc_emisores ?? 0);
            $metadataSummary['rfc_receptores'] = (int) ($summaryRow->rfc_receptores ?? 0);

            $rawChart = (clone $baseItemsQuery)
                ->selectRaw("
                    DATE_FORMAT(fecha_emision, '%Y-%m') as ym,
                    direction,
                    COUNT(*) as total
                ")
                ->whereNotNull('fecha_emision')
                ->groupBy(DB::raw("DATE_FORMAT(fecha_emision, '%Y-%m')"), 'direction')
                ->orderBy('ym')
                ->get();

            $grouped = [];

            foreach ($rawChart as $row) {
                $ym = (string) $row->ym;

                if (!isset($grouped[$ym])) {
                    $grouped[$ym] = [
                        'ym'        => $ym,
                        'emitidos'  => 0,
                        'recibidos' => 0,
                    ];
                }

                if ((string) $row->direction === 'emitidos') {
                    $grouped[$ym]['emitidos'] = (int) $row->total;
                }

                if ((string) $row->direction === 'recibidos') {
                    $grouped[$ym]['recibidos'] = (int) $row->total;
                }
            }

            $metadataSummary['meses'] = array_values($grouped);

            $statusBase = SatUserMetadataItem::query()
                ->selectRaw("COALESCE(NULLIF(TRIM(estatus), ''), 'Sin estatus') as estatus_label")
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->distinct()
                ->orderBy('estatus_label');

            if ($metadataFilters['direction'] !== '') {
                $statusBase->where('direction', $metadataFilters['direction']);
            }

            if ($metadataFilters['q'] !== '') {
                $q = $metadataFilters['q'];
                $statusBase->where(function ($sub) use ($q) {
                    $sub->where('uuid', 'like', '%' . $q . '%')
                        ->orWhere('rfc_emisor', 'like', '%' . $q . '%')
                        ->orWhere('nombre_emisor', 'like', '%' . $q . '%')
                        ->orWhere('rfc_receptor', 'like', '%' . $q . '%')
                        ->orWhere('nombre_receptor', 'like', '%' . $q . '%')
                        ->orWhere('estatus', 'like', '%' . $q . '%');
                });
            }

            if ($metadataFilters['desde'] !== '') {
                $statusBase->whereDate('fecha_emision', '>=', $metadataFilters['desde']);
            }

            if ($metadataFilters['hasta'] !== '') {
                $statusBase->whereDate('fecha_emision', '<=', $metadataFilters['hasta']);
            }

            $metadataStatuses = $statusBase
                ->pluck('estatus_label')
                ->values();

            $metadataSummary['estatuses'] = (clone $baseItemsQuery)
                ->selectRaw("COALESCE(NULLIF(TRIM(estatus), ''), 'Sin estatus') as label, COUNT(*) as total")
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(estatus), ''), 'Sin estatus')"))
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'label' => (string) $row->label,
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all();

            $metadataSummary['directions'] = [
                ['label' => 'Emitidos', 'total' => (int) $metadataSummary['emitidos']],
                ['label' => 'Recibidos', 'total' => (int) $metadataSummary['recibidos']],
            ];

            $metadataItems = (clone $baseItemsQuery)
                ->orderByDesc('fecha_emision')
                ->orderByDesc('id')
                ->paginate($metadataFilters['page_size'])
                ->appends($request->query());
        }

        return view('cliente.sat.v2.index', [
            'access'               => $access,
            'rfcs'                 => $rfcs,
            'vaults'               => $vaults,
            'selectedRfc'          => $selectedRfc,
            'metadataUploads'      => $metadataUploads,
            'xmlUploads'           => $xmlUploads,
            'reportUploads'        => $reportUploads,
            'downloadItems'        => $downloadItems,
            'unifiedDownloadItems' => $unifiedDownloadItems,
            'downloadSources'      => $downloadSources,
            'storageBreakdown'     => $storageBreakdown,
            'metadataCount'        => $metadataCount,
            'cfdiCount'            => $cfdiCount,
            'reportCount'          => $reportCount,
            'reconPending'         => $reconPending,
            'metadataItems'        => $metadataItems,
            'metadataSummary'      => $metadataSummary,
            'metadataFilters'      => $metadataFilters,
            'metadataStatuses'     => $metadataStatuses,
            'quoteItems'           => $quoteItems,
            'quoteStats'           => $quoteStats,
            'quoteActive'          => $quoteActive,
        ]);
    }

    public function uploadMetadata(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $access = SatUserAccess::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (!$access || !$access->can_upload_metadata) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $request->input('rfc_owner')])
                ->with('error', 'Tu usuario no tiene permiso para cargar metadata.');
        }

        $request->validate([
            'rfc_owner'          => ['nullable', 'string', 'max:20'],
            'rfc_existing'       => ['nullable', 'string', 'max:20'],
            'rfc_new'            => ['nullable', 'string', 'max:20'],
            'razon_social'       => ['nullable', 'string', 'max:255'],
            'metadata_direction' => ['required', 'in:emitidos,recibidos'],
            'archivo'            => ['required', 'file', 'max:512000'],
        ], [
            'metadata_direction.required' => 'Selecciona si la metadata es de emitidos o recibidos.',
            'metadata_direction.in'       => 'La dirección de metadata no es válida.',
            'archivo.required'            => 'Selecciona un archivo TXT, CSV o ZIP.',
            'archivo.max'                 => 'El archivo excede el tamaño permitido de 500 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('archivo');

        $rfcOwner = $this->resolveRfcOwner(
            (string) $request->input('rfc_owner', ''),
            (string) $request->input('rfc_existing', ''),
            (string) $request->input('rfc_new', '')
        );

        if ($rfcOwner === '') {
            return redirect()
                ->route('cliente.sat.v2.index')
                ->with('error', 'Debes seleccionar o capturar un RFC para asociar la metadata.');
        }

        if (!$this->isValidRfc($rfcOwner)) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'El RFC capturado no tiene un formato válido.');
        }

        $razonSocial       = trim((string) $request->input('razon_social', ''));
        $metadataDirection = strtolower(trim((string) $request->input('metadata_direction', '')));

        $originalName = (string) $file->getClientOriginalName();
        $extension    = strtolower((string) $file->getClientOriginalExtension());
        $clientBytes  = (int) ($file->getSize() ?: 0);

        if (!in_array($extension, ['txt', 'csv', 'zip'], true)) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'Solo se permiten archivos TXT, CSV o ZIP para metadata.');
        }

        $this->ensureRfcAvailableForAccount($cuentaId, $usuarioId, $rfcOwner, $razonSocial);

        $duplicateMetadata = $this->existingMetadataDuplicate(
            $cuentaId,
            $usuarioId,
            $rfcOwner,
            $originalName,
            $metadataDirection
        );

        if ($duplicateMetadata) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'Este archivo de metadata ya fue cargado anteriormente para este RFC y dirección.');
        }

        $safeOriginalName = $this->sanitizeFilename($originalName);
        $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $safeOriginalName;
        $folder = 'boveda_v2/' . $cuentaId . '/' . $usuarioId . '/' . $rfcOwner . '/metadata/' . now()->format('Y/m');

        if (!Storage::disk(self::DISK)->exists($folder)) {
            Storage::disk(self::DISK)->makeDirectory($folder);
        }

        $storedPath = $file->storeAs($folder, $storedName, self::DISK);
        if (!$storedPath) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'No se pudo guardar el archivo de metadata.');
        }

        $bytes = 0;
        try {
            $bytes = (int) Storage::disk(self::DISK)->size($storedPath);
        } catch (\Throwable) {
            $bytes = 0;
        }

        $sourceType = $this->detectSourceType($extension, $originalName);

        $upload = SatUserMetadataUpload::query()->create([
            'cuenta_id'          => $cuentaId,
            'usuario_id'         => $usuarioId,
            'rfc_owner'          => $rfcOwner,
            'source_type'        => $sourceType,
            'original_name'      => $safeOriginalName,
            'stored_name'        => $storedName,
            'disk'               => self::DISK,
            'path'               => $storedPath,
            'mime'               => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream'),
            'bytes'              => $bytes,
            'rows_count'         => 0,
            'direction_detected' => $metadataDirection,
            'status'             => 'uploaded',
            'meta'               => [
                'extension'          => $extension,
                'uploaded_from'      => 'vault_v2_ui',
                'original_mime'      => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''),
                'direction_selected' => $metadataDirection,
                'rfc_source'         => $request->filled('rfc_new') ? 'new' : ($request->filled('rfc_existing') ? 'existing' : 'active'),
                'razon_social'       => $razonSocial,
                'analysis_pending'   => true,
                'client_bytes'       => $clientBytes,
            ],
        ]);

        try {
            $parsedRows = $this->parseMetadataFile($storedPath, $extension);

            if (empty($parsedRows)) {
                $upload->update([
                    'status' => 'processed_empty',
                    'meta'   => array_merge((array) $upload->meta, [
                        'analysis_pending' => false,
                        'processed_at'     => now()->toDateTimeString(),
                        'processed_rows'   => 0,
                        'inserted_rows'    => 0,
                        'updated_rows'     => 0,
                        'warning'          => 'No se detectaron filas válidas en el archivo.',
                    ]),
                ]);

                return $this->jsonOrRedirectError(
                    $request,
                    'El archivo se guardó, pero no se detectaron filas válidas de metadata.',
                    $rfcOwner
                );
            }

            $preparedRows = [];
            $uuidsInFile = [];

            foreach ($parsedRows as $row) {
                $uuid = strtoupper(trim((string) ($row['uuid'] ?? '')));

                if ($uuid === '') {
                    continue;
                }

                if (isset($uuidsInFile[$uuid])) {
                    continue;
                }

                $uuidsInFile[$uuid] = true;

                $preparedRows[$uuid] = [
                    'metadata_upload_id'      => $upload->id,
                    'cuenta_id'               => $cuentaId,
                    'usuario_id'              => $usuarioId,
                    'rfc_owner'               => $rfcOwner,
                    'uuid'                    => $uuid,
                    'rfc_emisor'              => $this->nullIfEmpty($row['rfc_emisor'] ?? null),
                    'nombre_emisor'           => $this->nullIfEmpty($row['nombre_emisor'] ?? null),
                    'rfc_receptor'            => $this->nullIfEmpty($row['rfc_receptor'] ?? null),
                    'nombre_receptor'         => $this->nullIfEmpty($row['nombre_receptor'] ?? null),
                    'fecha_emision'           => $this->parseDateValue($row['fecha_emision'] ?? null),
                    'fecha_certificacion_sat' => $this->parseDateValue($row['fecha_certificacion_sat'] ?? null),
                    'monto'                   => $this->parseMoneyValue($row['monto'] ?? null),
                    'efecto_comprobante'      => $this->nullIfEmpty($row['efecto_comprobante'] ?? null),
                    'estatus'                 => $this->nullIfEmpty($row['estatus'] ?? null),
                    'fecha_cancelacion'       => $this->parseDateValue($row['fecha_cancelacion'] ?? null),
                    'direction'               => $metadataDirection,
                    'raw_line'                => (string) ($row['raw_line'] ?? ''),
                    'meta'                    => [
                        'source_type'   => $sourceType,
                        'parsed_from'   => $extension,
                        'line_number'   => (int) ($row['_line_number'] ?? 0),
                        'columns_found' => array_values((array) ($row['_columns_found'] ?? [])),
                    ],
                ];
            }

            if (empty($preparedRows)) {
                $upload->update([
                    'status' => 'processed_empty',
                    'meta'   => array_merge((array) $upload->meta, [
                        'analysis_pending' => false,
                        'processed_at'     => now()->toDateTimeString(),
                        'processed_rows'   => 0,
                        'inserted_rows'    => 0,
                        'updated_rows'     => 0,
                        'warning'          => 'No se detectaron UUID válidos en el archivo.',
                    ]),
                ]);

                return $this->jsonOrRedirectError(
                    $request,
                    'El archivo se guardó, pero no se detectaron UUID válidos para procesar.',
                    $rfcOwner
                );
            }

            $uuids = array_keys($preparedRows);

            $existingUuids = SatUserMetadataItem::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $rfcOwner)
                ->where('direction', $metadataDirection)
                ->whereIn('uuid', $uuids)
                ->pluck('uuid')
                ->map(fn ($value) => strtoupper(trim((string) $value)))
                ->filter()
                ->values()
                ->all();

            $existingUuidMap = array_fill_keys($existingUuids, true);

            $rowsToInsert = [];
            $rowsToUpdate = [];

            foreach ($preparedRows as $uuid => $rowData) {
                if (isset($existingUuidMap[$uuid])) {
                    $rowsToUpdate[] = $rowData;
                } else {
                    $rowsToInsert[] = array_merge($rowData, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::connection('mysql_clientes')->transaction(function () use ($rowsToInsert, $rowsToUpdate, $cuentaId, $usuarioId, $rfcOwner, $metadataDirection) {
                if (!empty($rowsToInsert)) {
                    foreach (array_chunk($rowsToInsert, 500) as $chunk) {
                        $insertChunk = [];

                        foreach ($chunk as $chunkRow) {
                            $chunkRow['meta'] = json_encode($chunkRow['meta'], JSON_UNESCAPED_UNICODE);
                            $insertChunk[] = $chunkRow;
                        }

                        SatUserMetadataItem::query()->insert($insertChunk);
                    }
                }

                if (!empty($rowsToUpdate)) {
                    foreach (array_chunk($rowsToUpdate, 200) as $chunk) {
                        foreach ($chunk as $rowData) {
                            SatUserMetadataItem::query()
                                ->where('cuenta_id', $cuentaId)
                                ->where('usuario_id', $usuarioId)
                                ->where('rfc_owner', $rfcOwner)
                                ->where('direction', $metadataDirection)
                                ->where('uuid', $rowData['uuid'])
                                ->update([
                                    'metadata_upload_id'      => $rowData['metadata_upload_id'],
                                    'rfc_emisor'              => $rowData['rfc_emisor'],
                                    'nombre_emisor'           => $rowData['nombre_emisor'],
                                    'rfc_receptor'            => $rowData['rfc_receptor'],
                                    'nombre_receptor'         => $rowData['nombre_receptor'],
                                    'fecha_emision'           => $rowData['fecha_emision'],
                                    'fecha_certificacion_sat' => $rowData['fecha_certificacion_sat'],
                                    'monto'                   => $rowData['monto'],
                                    'efecto_comprobante'      => $rowData['efecto_comprobante'],
                                    'estatus'                 => $rowData['estatus'],
                                    'fecha_cancelacion'       => $rowData['fecha_cancelacion'],
                                    'raw_line'                => $rowData['raw_line'],
                                    'meta'                    => json_encode($rowData['meta'], JSON_UNESCAPED_UNICODE),
                                    'updated_at'              => now(),
                                ]);
                        }
                    }
                }
            });

            $rowsInserted = count($rowsToInsert);
            $rowsUpdated = count($rowsToUpdate);
            $rowsProcessed = $rowsInserted + $rowsUpdated;

            $upload->update([
                'rows_count' => $rowsProcessed,
                'status'     => 'processed',
                'meta'       => array_merge((array) $upload->meta, [
                    'analysis_pending' => false,
                    'processed_at'     => now()->toDateTimeString(),
                    'processed_rows'   => $rowsProcessed,
                    'inserted_rows'    => $rowsInserted,
                    'updated_rows'     => $rowsUpdated,
                ]),
            ]);

            return $this->jsonOrRedirectSuccess(
                $request,
                'Metadata procesada correctamente. Nuevos: ' . number_format($rowsInserted) . ' · Actualizados: ' . number_format($rowsUpdated) . '.',
                $rfcOwner
            );
        } catch (\Throwable $e) {
            $upload->update([
                'status' => 'error',
                'meta'   => array_merge((array) $upload->meta, [
                    'analysis_pending' => false,
                    'processed_at'     => now()->toDateTimeString(),
                    'error_message'    => $e->getMessage(),
                ]),
            ]);

            return $this->jsonOrRedirectError(
                $request,
                'El archivo se guardó pero no se pudo leer la metadata: ' . $e->getMessage(),
                $rfcOwner
            );
        }
    }

    public function uploadXml(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $access = SatUserAccess::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (!$access || !($access->can_upload_xml ?? true)) {
            return $this->jsonOrRedirectError(
                $request,
                'Tu usuario no tiene permiso para cargar XML.',
                (string) $request->input('rfc_owner', '')
            );
        }

        $request->validate([
            'rfc_owner'                 => ['nullable', 'string', 'max:20'],
            'rfc_existing'              => ['nullable', 'string', 'max:20'],
            'rfc_new'                   => ['nullable', 'string', 'max:20'],
            'razon_social'              => ['nullable', 'string', 'max:255'],
            'xml_direction'             => ['required', 'in:emitidos,recibidos'],
            'linked_metadata_upload_id' => ['nullable', 'integer'],
            'archivo_xml'               => ['required', 'file', 'max:512000'],
        ], [
            'xml_direction.required' => 'Selecciona si el XML es de emitidos o recibidos.',
            'xml_direction.in'       => 'La dirección de XML no es válida.',
            'archivo_xml.required'   => 'Selecciona un archivo XML o ZIP.',
            'archivo_xml.max'        => 'El archivo excede el tamaño permitido de 500 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('archivo_xml');

        $rfcOwner = $this->resolveRfcOwner(
            (string) $request->input('rfc_owner', ''),
            (string) $request->input('rfc_existing', ''),
            (string) $request->input('rfc_new', '')
        );

        if ($rfcOwner === '') {
            return $this->jsonOrRedirectError(
                $request,
                'Debes seleccionar o capturar un RFC para asociar los XML.'
            );
        }

        if (!$this->isValidRfc($rfcOwner)) {
            return $this->jsonOrRedirectError(
                $request,
                'El RFC capturado no tiene un formato válido.',
                $rfcOwner
            );
        }

        $razonSocial      = trim((string) $request->input('razon_social', ''));
        $xmlDirection     = strtolower(trim((string) $request->input('xml_direction', '')));
        $metadataUploadId = (int) $request->input('linked_metadata_upload_id', 0);

        $originalName = (string) $file->getClientOriginalName();
        $extension    = strtolower((string) $file->getClientOriginalExtension());
        $clientBytes  = (int) ($file->getSize() ?: 0);

        if (!in_array($extension, ['xml', 'zip'], true)) {
            return $this->jsonOrRedirectError(
                $request,
                'Solo se permiten archivos XML o ZIP para carga XML.',
                $rfcOwner
            );
        }

        $this->ensureRfcAvailableForAccount($cuentaId, $usuarioId, $rfcOwner, $razonSocial);

        $duplicateXml = $this->existingXmlDuplicate(
            $cuentaId,
            $usuarioId,
            $rfcOwner,
            $originalName,
            $xmlDirection
        );

        if ($duplicateXml) {
            return $this->jsonOrRedirectError(
                $request,
                'Este archivo XML ya fue cargado anteriormente para este RFC y dirección.',
                $rfcOwner
            );
        }

        $linkedMetadata = null;
        if ($metadataUploadId > 0) {
            $linkedMetadata = SatUserMetadataUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $rfcOwner)
                ->where('id', $metadataUploadId)
                ->first();

            if (!$linkedMetadata) {
                return $this->jsonOrRedirectError(
                    $request,
                    'El lote metadata seleccionado no pertenece a este RFC.',
                    $rfcOwner
                );
            }
        }

        $safeOriginalName = $this->sanitizeFilename($originalName);
        $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $safeOriginalName;
        $folder = 'boveda_v2/' . $cuentaId . '/' . $usuarioId . '/' . $rfcOwner . '/xml/' . now()->format('Y/m');

        if (!Storage::disk(self::DISK)->exists($folder)) {
            Storage::disk(self::DISK)->makeDirectory($folder);
        }

        $storedPath = $file->storeAs($folder, $storedName, self::DISK);
        if (!$storedPath) {
            return $this->jsonOrRedirectError(
                $request,
                'No se pudo guardar el archivo XML.',
                $rfcOwner
            );
        }

        $bytes = 0;
        try {
            $bytes = (int) Storage::disk(self::DISK)->size($storedPath);
        } catch (\Throwable) {
            $bytes = 0;
        }

        $xmlUpload = SatUserXmlUpload::query()->create([
            'cuenta_id'          => $cuentaId,
            'usuario_id'         => $usuarioId,
            'rfc_owner'          => $rfcOwner,
            'source_type'        => $extension === 'zip' ? 'zip_xml' : 'xml',
            'original_name'      => $safeOriginalName,
            'stored_name'        => $storedName,
            'disk'               => self::DISK,
            'path'               => $storedPath,
            'mime'               => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream'),
            'bytes'              => $bytes,
            'files_count'        => 0,
            'direction_detected' => $xmlDirection,
            'status'             => 'processing',
            'meta'               => [
                'extension'          => $extension,
                'uploaded_from'      => 'vault_v2_ui',
                'original_mime'      => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''),
                'direction_selected' => $xmlDirection,
                'rfc_source'         => $request->filled('rfc_new') ? 'new' : ($request->filled('rfc_existing') ? 'existing' : 'active'),
                'razon_social'       => $razonSocial,
                'analysis_pending'   => true,
                'client_bytes'       => $clientBytes,
            ],
        ]);

                try {
            $result = $this->processStoredXmlUpload(
                $xmlUpload,
                $storedPath,
                $extension,
                $cuentaId,
                $usuarioId,
                $rfcOwner,
                $xmlDirection,
                $linkedMetadata
            );

            $processed        = (int) ($result['processed'] ?? 0);
            $duplicatesUuid   = (int) ($result['duplicates_uuid'] ?? 0);
            $duplicatesHash   = (int) ($result['duplicates_hash'] ?? 0);
            $invalidFiles     = (int) ($result['invalid'] ?? 0);

            $xmlUpload->update([
                'files_count' => $processed,
                'status'      => 'processed',
                'meta'        => array_merge((array) $xmlUpload->meta, [
                    'analysis_pending' => false,
                    'processed_files'  => $processed,
                    'duplicates_uuid'  => $duplicatesUuid,
                    'duplicates_hash'  => $duplicatesHash,
                    'invalid_files'    => $invalidFiles,
                    'processed_at'     => now()->toDateTimeString(),
                    'linked_metadata_upload_id' => $linkedMetadata?->id,
                    'linked_metadata_original_name' => $linkedMetadata?->original_name,
                ]),
            ]);

            $message = 'XML procesados: ' . number_format($processed);

            if ($duplicatesUuid > 0 || $duplicatesHash > 0 || $invalidFiles > 0) {
                $message .= ' · UUID duplicados: ' . number_format($duplicatesUuid);
                $message .= ' · Hash duplicados: ' . number_format($duplicatesHash);
                $message .= ' · Inválidos: ' . number_format($invalidFiles);
            }

            return $this->jsonOrRedirectSuccess(
                $request,
                $message,
                $rfcOwner
            );
        } catch (\Throwable $e) {
            $xmlUpload->update([
                'status' => 'error',
                'meta'   => array_merge((array) $xmlUpload->meta, [
                    'analysis_pending' => false,
                    'error'            => $e->getMessage(),
                ]),
            ]);

            return $this->jsonOrRedirectError(
                $request,
                'Error procesando XML: ' . $e->getMessage(),
                $rfcOwner
            );
        }
    }

    public function reprocessExistingXml(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $request->validate([
            'rfc_owner'   => ['required', 'string', 'max:20'],
            'scope'       => ['nullable', 'in:missing,month,date_range,direction,top_limit,smart,all'],
            'mode'        => ['nullable', 'in:missing,all'], // compatibilidad vieja
            'period_ym'   => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'desde'       => ['nullable', 'date'],
            'hasta'       => ['nullable', 'date'],
            'direction'   => ['nullable', 'in:emitidos,recibidos'],
            'limit'       => ['nullable', 'integer', 'min:1', 'max:2000'],
            'chunk_size'  => ['nullable', 'integer', 'min:25', 'max:500'],
            'preview'     => ['nullable', 'boolean'],
            'force_all'   => ['nullable', 'boolean'],
        ], [
            'rfc_owner.required' => 'Debes indicar el RFC a reprocesar.',
            'scope.in'           => 'El alcance de reproceso no es válido.',
            'mode.in'            => 'El modo de reproceso no es válido.',
            'period_ym.regex'    => 'El periodo debe venir con formato YYYY-MM.',
            'direction.in'       => 'La dirección debe ser emitidos o recibidos.',
            'limit.max'          => 'El límite máximo permitido es 2000 CFDI por operación.',
            'chunk_size.max'     => 'El tamaño máximo de lote permitido es 500 CFDI.',
        ]);

        $rfcOwner = strtoupper(trim((string) $request->input('rfc_owner')));
        if (!$this->isValidRfc($rfcOwner)) {
            return $this->jsonOrRedirectError(
                $request,
                'El RFC capturado no tiene un formato válido.',
                $rfcOwner
            );
        }

        $scope = strtolower(trim((string) $request->input('scope', '')));
        $legacyMode = strtolower(trim((string) $request->input('mode', 'missing')));

        if ($scope === '') {
            $scope = $legacyMode === 'all' ? 'all' : 'missing';
        }

        $periodYm  = trim((string) $request->input('period_ym', ''));
        $desde     = trim((string) $request->input('desde', ''));
        $hasta     = trim((string) $request->input('hasta', ''));
        $direction = strtolower(trim((string) $request->input('direction', '')));
        $limit     = (int) $request->input('limit', 0);
        $chunkSize = (int) $request->input('chunk_size', 200);
        $preview   = (bool) $request->boolean('preview', false);
        $forceAll  = (bool) $request->boolean('force_all', false);

        if (!in_array($direction, ['emitidos', 'recibidos'], true)) {
            $direction = '';
        }

        if ($chunkSize < 25) {
            $chunkSize = 25;
        }
        if ($chunkSize > 500) {
            $chunkSize = 500;
        }

        if ($limit <= 0) {
            $limit = match ($scope) {
                'missing'    => 300,
                'month'      => 500,
                'date_range' => 500,
                'direction'  => 500,
                'top_limit'  => 200,
                'smart'      => 300,
                'all'        => 1000,
                default      => 300,
            };
        }

        if ($limit > 2000) {
            $limit = 2000;
        }

        $scopeConfig = [
            'scope'      => $scope,
            'period_ym'  => $periodYm,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'direction'  => $direction,
            'limit'      => $limit,
            'chunk_size' => $chunkSize,
        ];

        if ($scope === 'smart') {
            $scopeConfig = $this->resolveSmartReprocessScope(
                $cuentaId,
                $usuarioId,
                $rfcOwner,
                $scopeConfig
            );
        }

        $baseQuery = $this->buildReprocessCfdiQuery(
            $cuentaId,
            $usuarioId,
            $rfcOwner,
            $scopeConfig
        );

        $totalCandidates = (clone $baseQuery)->count();

        if ($totalCandidates <= 0) {
            return $this->jsonOrRedirectError(
                $request,
                'No se encontraron CFDI para reprocesar con el alcance seleccionado.',
                $rfcOwner
            );
        }

        $safeMaxWithoutForce = 1200;
        if (
            $scopeConfig['scope'] === 'all'
            && $totalCandidates > $safeMaxWithoutForce
            && !$forceAll
        ) {
            return response()->json([
                'ok'      => false,
                'message' => 'El alcance "todo el RFC" encontró demasiados CFDI para una sola corrida. Usa un bloque mensual, rango, dirección o activa force_all.',
                'data'    => [
                    'requires_confirmation' => true,
                    'total_candidates'      => $totalCandidates,
                    'safe_max'              => $safeMaxWithoutForce,
                    'scope_summary'         => $this->buildReprocessScopeSummary($scopeConfig, $totalCandidates),
                    'suggested_scope'       => $this->suggestSaferReprocessScope($cuentaId, $usuarioId, $rfcOwner),
                ],
            ], 422);
        }

        $scopeSummary = $this->buildReprocessScopeSummary($scopeConfig, $totalCandidates);

        if ($preview) {
            $sampleRows = (clone $baseQuery)
                ->orderByDesc('fecha_emision')
                ->orderByDesc('id')
                ->limit(8)
                ->get([
                    'id',
                    'uuid',
                    'direction',
                    'fecha_emision',
                    'subtotal',
                    'iva',
                    'total',
                    'rfc_emisor',
                    'rfc_receptor',
                ])
                ->map(function (SatUserCfdi $row) {
                    return [
                        'id'            => (int) $row->id,
                        'uuid'          => (string) ($row->uuid ?? ''),
                        'direction'     => (string) ($row->direction ?? ''),
                        'fecha_emision' => optional($row->fecha_emision)->format('Y-m-d H:i:s'),
                        'subtotal'      => (float) ($row->subtotal ?? 0),
                        'iva'           => (float) ($row->iva ?? 0),
                        'total'         => (float) ($row->total ?? 0),
                        'rfc_emisor'    => (string) ($row->rfc_emisor ?? ''),
                        'rfc_receptor'  => (string) ($row->rfc_receptor ?? ''),
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'ok'      => true,
                'message' => 'Preview generado correctamente.',
                'data'    => [
                    'preview'           => true,
                    'rfc_owner'         => $rfcOwner,
                    'scope_summary'     => $scopeSummary,
                    'total_candidates'  => $totalCandidates,
                    'estimated_batches' => (int) ceil($totalCandidates / max(1, $chunkSize)),
                    'chunk_size'        => $chunkSize,
                    'sample_rows'       => $sampleRows,
                ],
            ]);
        }

        $cfdis = (clone $baseQuery)
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($cfdis->isEmpty()) {
            return $this->jsonOrRedirectError(
                $request,
                'No se encontraron CFDI para reprocesar con el alcance seleccionado.',
                $rfcOwner
            );
        }

        $updated = 0;
        $invalid = 0;
        $notFound = 0;
        $processed = 0;

        foreach ($cfdis->chunk($chunkSize) as $chunk) {
            foreach ($chunk as $cfdi) {
                $processed++;

                try {
                    $xmlContent = $this->readStoredXmlContentForCfdi($cfdi);

                    if (!is_string($xmlContent) || trim($xmlContent) === '') {
                        $notFound++;
                        continue;
                    }

                    $parsed = $this->parseXmlContent($xmlContent);

                    if (!$parsed || trim((string) ($parsed['uuid'] ?? '')) === '') {
                        $invalid++;
                        continue;
                    }

                    $this->refreshExistingCfdiFromParsed($cfdi, $parsed);
                    $updated++;
                } catch (\Throwable $e) {
                    $invalid++;

                    Log::warning('SAT V2 reproceso histórico XML falló', [
                        'cfdi_id'        => $cfdi->id,
                        'uuid'           => $cfdi->uuid,
                        'rfc_owner'      => $cfdi->rfc_owner,
                        'scope'          => $scopeConfig['scope'] ?? null,
                        'period_ym'      => $scopeConfig['period_ym'] ?? null,
                        'direction'      => $scopeConfig['direction'] ?? null,
                        'fecha_emision'  => optional($cfdi->fecha_emision)->format('Y-m-d H:i:s'),
                        'message'        => $e->getMessage(),
                    ]);
                }
            }
        }

        $message = 'Reproceso XML completado. Procesados: ' . number_format($processed)
            . ' · Actualizados: ' . number_format($updated)
            . ' · Inválidos: ' . number_format($invalid)
            . ' · No encontrados: ' . number_format($notFound) . '.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'           => true,
                'message'      => $message,
                'redirect_url' => route('cliente.sat.v2.index', ['rfc' => $rfcOwner]),
                'data'         => [
                    'rfc_owner'         => $rfcOwner,
                    'scope_summary'     => $scopeSummary,
                    'processed'         => $processed,
                    'updated'           => $updated,
                    'invalid'           => $invalid,
                    'not_found'         => $notFound,
                    'estimated_batches' => (int) ceil($processed / max(1, $chunkSize)),
                    'chunk_size'        => $chunkSize,
                ],
            ]);
        }

        return redirect()
            ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
            ->with('success', $message);
    }

    public function previewReprocessExistingXml(Request $request): Response
    {
        $request->merge([
            'preview' => true,
        ]);

        return $this->reprocessExistingXml($request);
    }

    private function buildReprocessCfdiQuery(
    string $cuentaId,
    string $usuarioId,
    string $rfcOwner,
    array $scopeConfig
    )
    {
        $scope = strtolower(trim((string) ($scopeConfig['scope'] ?? 'missing')));
        $periodYm = trim((string) ($scopeConfig['period_ym'] ?? ''));
        $desde = trim((string) ($scopeConfig['desde'] ?? ''));
        $hasta = trim((string) ($scopeConfig['hasta'] ?? ''));
        $direction = strtolower(trim((string) ($scopeConfig['direction'] ?? '')));
        $limit = (int) ($scopeConfig['limit'] ?? 300);

        $query = SatUserCfdi::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner);

        switch ($scope) {
            case 'missing':
                $query->where(function ($q) {
                    $q->whereNull('iva')
                        ->orWhere('iva', '<=', 0)
                        ->orWhereNull('subtotal')
                        ->orWhere('subtotal', '<=', 0)
                        ->orWhereRaw('JSON_EXTRACT(meta, "$.parsed_impuestos.iva") IS NULL');
                });
                break;

            case 'month':
                if ($periodYm !== '' && preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
                    $query->whereRaw("DATE_FORMAT(fecha_emision, '%Y-%m') = ?", [$periodYm]);
                }
                break;

            case 'date_range':
                if ($desde !== '') {
                    $query->whereDate('fecha_emision', '>=', $desde);
                }
                if ($hasta !== '') {
                    $query->whereDate('fecha_emision', '<=', $hasta);
                }
                break;

            case 'direction':
                if (in_array($direction, ['emitidos', 'recibidos'], true)) {
                    $query->where('direction', $direction);
                }
                break;

            case 'top_limit':
                // sólo usa orden descendente y límite más abajo
                break;

            case 'all':
                // sin filtros extra
                break;

            case 'smart':
                // smart ya debe venir resuelto antes de entrar aquí
                $query->where(function ($q) {
                    $q->whereNull('iva')
                        ->orWhere('iva', '<=', 0)
                        ->orWhereNull('subtotal')
                        ->orWhere('subtotal', '<=', 0)
                        ->orWhereRaw('JSON_EXTRACT(meta, "$.parsed_impuestos.iva") IS NULL');
                });
                break;

            default:
                $query->where(function ($q) {
                    $q->whereNull('iva')
                        ->orWhere('iva', '<=', 0)
                        ->orWhereNull('subtotal')
                        ->orWhere('subtotal', '<=', 0)
                        ->orWhereRaw('JSON_EXTRACT(meta, "$.parsed_impuestos.iva") IS NULL');
                });
                break;
        }

        if (
            $scope !== 'direction'
            && in_array($direction, ['emitidos', 'recibidos'], true)
        ) {
            $query->where('direction', $direction);
        }

        if ($scope === 'top_limit') {
            $query->orderByDesc('fecha_emision')
                ->orderByDesc('id')
                ->limit($limit);
        }

        return $query;
    }

    private function resolveSmartReprocessScope(
        string $cuentaId,
        string $usuarioId,
        string $rfcOwner,
        array $scopeConfig
    ): array {
        $missingBase = SatUserCfdi::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where(function ($q) {
                $q->whereNull('iva')
                    ->orWhere('iva', '<=', 0)
                    ->orWhereNull('subtotal')
                    ->orWhere('subtotal', '<=', 0)
                    ->orWhereRaw('JSON_EXTRACT(meta, "$.parsed_impuestos.iva") IS NULL');
            });

        if (!empty($scopeConfig['direction']) && in_array($scopeConfig['direction'], ['emitidos', 'recibidos'], true)) {
            $missingBase->where('direction', $scopeConfig['direction']);
        }

        $bestMonth = (clone $missingBase)
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as ym, COUNT(*) as total")
            ->whereNotNull('fecha_emision')
            ->groupBy(DB::raw("DATE_FORMAT(fecha_emision, '%Y-%m')"))
            ->orderByDesc('total')
            ->orderByDesc('ym')
            ->first();

        if ($bestMonth && !empty($bestMonth->ym)) {
            $scopeConfig['scope'] = 'month';
            $scopeConfig['period_ym'] = (string) $bestMonth->ym;

            if (empty($scopeConfig['limit']) || (int) $scopeConfig['limit'] <= 0) {
                $scopeConfig['limit'] = min(800, max(100, (int) $bestMonth->total));
            }

            return $scopeConfig;
        }

        $scopeConfig['scope'] = 'missing';
        return $scopeConfig;
    }

    private function buildReprocessScopeSummary(array $scopeConfig, int $totalCandidates): array
    {
        $scope = strtolower(trim((string) ($scopeConfig['scope'] ?? 'missing')));
        $direction = strtolower(trim((string) ($scopeConfig['direction'] ?? '')));
        $periodYm = trim((string) ($scopeConfig['period_ym'] ?? ''));
        $desde = trim((string) ($scopeConfig['desde'] ?? ''));
        $hasta = trim((string) ($scopeConfig['hasta'] ?? ''));
        $limit = (int) ($scopeConfig['limit'] ?? 0);
        $chunkSize = (int) ($scopeConfig['chunk_size'] ?? 200);

        $label = match ($scope) {
            'missing'    => 'Solo CFDI con datos fiscales incompletos',
            'month'      => 'Solo bloque mensual',
            'date_range' => 'Solo rango de fechas',
            'direction'  => 'Solo una dirección',
            'top_limit'  => 'Últimos CFDI del RFC',
            'smart'      => 'Selección inteligente',
            'all'        => 'Todo el RFC visible',
            default      => 'Reproceso personalizado',
        };

        return [
            'scope'             => $scope,
            'label'             => $label,
            'direction'         => $direction !== '' ? $direction : null,
            'period_ym'         => $periodYm !== '' ? $periodYm : null,
            'desde'             => $desde !== '' ? $desde : null,
            'hasta'             => $hasta !== '' ? $hasta : null,
            'limit'             => $limit,
            'chunk_size'        => $chunkSize,
            'total_candidates'  => $totalCandidates,
            'estimated_batches' => (int) ceil($totalCandidates / max(1, $chunkSize)),
            'risk_level'        => $this->resolveReprocessRiskLevel($totalCandidates),
        ];
    }

    private function resolveReprocessRiskLevel(int $totalCandidates): string
    {
        return match (true) {
            $totalCandidates <= 150 => 'low',
            $totalCandidates <= 600 => 'medium',
            default                 => 'high',
        };
    }

    private function suggestSaferReprocessScope(
        string $cuentaId,
        string $usuarioId,
        string $rfcOwner
    ): array {
        $missingByMonth = SatUserCfdi::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where(function ($q) {
                $q->whereNull('iva')
                    ->orWhere('iva', '<=', 0)
                    ->orWhereNull('subtotal')
                    ->orWhere('subtotal', '<=', 0)
                    ->orWhereRaw('JSON_EXTRACT(meta, "$.parsed_impuestos.iva") IS NULL');
            })
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as ym, COUNT(*) as total")
            ->whereNotNull('fecha_emision')
            ->groupBy(DB::raw("DATE_FORMAT(fecha_emision, '%Y-%m')"))
            ->orderByDesc('total')
            ->orderByDesc('ym')
            ->first();

        if ($missingByMonth && !empty($missingByMonth->ym)) {
            return [
                'scope'     => 'month',
                'period_ym' => (string) $missingByMonth->ym,
                'limit'     => min(800, max(100, (int) $missingByMonth->total)),
                'label'     => 'Te conviene reprocesar primero el mes con más faltantes.',
            ];
        }

        return [
            'scope' => 'missing',
            'limit' => 300,
            'label' => 'Te conviene reprocesar primero solo faltantes.',
        ];
    }

    public function uploadReport(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $access = SatUserAccess::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (!$access || !($access->can_upload_metadata ?? true)) {
            return $this->jsonOrRedirectError(
                $request,
                'Tu usuario no tiene permiso para cargar reportes.',
                (string) $request->input('rfc_owner', '')
            );
        }

        $request->validate([
            'rfc_owner'                 => ['nullable', 'string', 'max:20'],
            'rfc_existing'              => ['nullable', 'string', 'max:20'],
            'rfc_new'                   => ['nullable', 'string', 'max:20'],
            'razon_social'              => ['nullable', 'string', 'max:255'],
            'report_type'               => ['required', 'in:csv_report,xlsx_report,xls_report,txt_report'],
            'report_direction'          => ['required', 'in:emitidos,recibidos'],
            'linked_metadata_upload_id' => ['nullable', 'integer'],
            'linked_xml_upload_id'      => ['nullable', 'integer'],
            'archivo_reporte'           => ['required', 'file', 'max:512000'],
        ], [
            'report_type.required'      => 'Selecciona el tipo de reporte.',
            'report_type.in'            => 'El tipo de reporte no es válido.',
            'report_direction.required' => 'Selecciona si el reporte es de emitidos o recibidos.',
            'report_direction.in'       => 'La dirección del reporte no es válida.',
            'archivo_reporte.required'  => 'Selecciona un archivo de reporte.',
            'archivo_reporte.max'       => 'El archivo excede el tamaño permitido de 500 MB.',
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('archivo_reporte');

        $rfcOwner = $this->resolveRfcOwner(
            (string) $request->input('rfc_owner', ''),
            (string) $request->input('rfc_existing', ''),
            (string) $request->input('rfc_new', '')
        );

        if ($rfcOwner === '') {
            return $this->jsonOrRedirectError(
                $request,
                'Debes seleccionar o capturar un RFC para asociar el reporte.'
            );
        }

        if (!$this->isValidRfc($rfcOwner)) {
            return $this->jsonOrRedirectError(
                $request,
                'El RFC capturado no tiene un formato válido.',
                $rfcOwner
            );
        }

        $razonSocial     = trim((string) $request->input('razon_social', ''));
        $reportType      = strtolower(trim((string) $request->input('report_type', 'csv_report')));
        $reportDirection = strtolower(trim((string) $request->input('report_direction', 'emitidos')));

        $metadataUploadId = (int) $request->input('linked_metadata_upload_id', 0);
        $xmlUploadId      = (int) $request->input('linked_xml_upload_id', 0);

        $originalName = (string) $file->getClientOriginalName();
        $extension    = strtolower((string) $file->getClientOriginalExtension());
        $clientBytes  = (int) ($file->getSize() ?: 0);

        if (!in_array($extension, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            return $this->jsonOrRedirectError(
                $request,
                'Solo se permiten archivos CSV, XLSX, XLS o TXT para reportes.',
                $rfcOwner
            );
        }

        $this->ensureRfcAvailableForAccount($cuentaId, $usuarioId, $rfcOwner, $razonSocial);

        $duplicateReport = $this->existingReportDuplicate(
            $cuentaId,
            $usuarioId,
            $rfcOwner,
            $originalName,
            $reportType,
            $reportDirection
        );

        if ($duplicateReport) {
            return $this->jsonOrRedirectError(
                $request,
                'Este archivo de reporte ya fue cargado anteriormente para este RFC, tipo y dirección.',
                $rfcOwner
            );
        }

        $linkedMetadata = null;
        if ($metadataUploadId > 0) {
            $linkedMetadata = SatUserMetadataUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $rfcOwner)
                ->where('id', $metadataUploadId)
                ->first();

            if (!$linkedMetadata) {
                return $this->jsonOrRedirectError(
                    $request,
                    'El lote metadata seleccionado no pertenece a este RFC.',
                    $rfcOwner
                );
            }
        }

        $linkedXml = null;
        if ($xmlUploadId > 0) {
            $linkedXml = SatUserXmlUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $rfcOwner)
                ->where('id', $xmlUploadId)
                ->first();

            if (!$linkedXml) {
                return $this->jsonOrRedirectError(
                    $request,
                    'El lote XML seleccionado no pertenece a este RFC.',
                    $rfcOwner
                );
            }
        }

        $safeOriginalName = $this->sanitizeFilename($originalName);
        $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $safeOriginalName;
        $folder = 'boveda_v2/' . $cuentaId . '/' . $usuarioId . '/' . $rfcOwner . '/reportes/' . now()->format('Y/m');

        if (!Storage::disk(self::DISK)->exists($folder)) {
            Storage::disk(self::DISK)->makeDirectory($folder);
        }

        $storedPath = $file->storeAs($folder, $storedName, self::DISK);
        if (!$storedPath) {
            return $this->jsonOrRedirectError(
                $request,
                'No se pudo guardar el archivo del reporte.',
                $rfcOwner
            );
        }

        $bytes = 0;
        try {
            $bytes = (int) Storage::disk(self::DISK)->size($storedPath);
        } catch (\Throwable) {
            $bytes = 0;
        }

        $upload = SatUserReportUpload::query()->create([
            'cuenta_id'                 => $cuentaId,
            'usuario_id'                => $usuarioId,
            'rfc_owner'                 => $rfcOwner,
            'report_type'               => $reportType,
            'linked_metadata_upload_id' => $linkedMetadata?->id,
            'linked_xml_upload_id'      => $linkedXml?->id,
            'original_name'             => $safeOriginalName,
            'stored_name'               => $storedName,
            'disk'                      => self::DISK,
            'path'                      => $storedPath,
            'mime'                      => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream'),
            'bytes'                     => $bytes,
            'rows_count'                => 0,
            'status'                    => 'uploaded',
            'meta'                      => [
                'extension'                     => $extension,
                'uploaded_from'                 => 'vault_v2_ui',
                'original_mime'                 => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''),
                'rfc_source'                    => $request->filled('rfc_new') ? 'new' : ($request->filled('rfc_existing') ? 'existing' : 'active'),
                'razon_social'                  => $razonSocial,
                'report_direction'              => $reportDirection,
                'linked_metadata_upload_id'     => $linkedMetadata?->id,
                'linked_metadata_original_name' => $linkedMetadata?->original_name,
                'linked_xml_upload_id'          => $linkedXml?->id,
                'linked_xml_original_name'      => $linkedXml?->original_name,
                'analysis_pending'              => true,
                'client_bytes'                  => $clientBytes,
            ],
        ]);

        try {
            $importedRows = $this->importReportUpload($upload, $reportDirection);

            Log::info('SAT V2 reporte importado', [
                'upload_id'        => $upload->id,
                'cuenta_id'        => $cuentaId,
                'usuario_id'       => $usuarioId,
                'rfc_owner'        => $rfcOwner,
                'report_type'      => $reportType,
                'report_direction' => $reportDirection,
                'rows_imported'    => $importedRows,
                'stored_path'      => $storedPath,
            ]);

            return $this->jsonOrRedirectSuccess(
                $request,
                'Reporte cargado y procesado correctamente para el RFC ' . $rfcOwner . '.',
                $rfcOwner
            );
        } catch (\Throwable $e) {
            Log::error('SAT V2 error importando reporte', [
                'upload_id'        => $upload->id,
                'rfc_owner'        => $rfcOwner,
                'report_direction' => $reportDirection,
                'message'          => $e->getMessage(),
                'line'             => $e->getLine(),
                'file'             => $e->getFile(),
            ]);

            $meta = (array) ($upload->meta ?? []);
            $meta['analysis_pending'] = false;
            $meta['analysis_error'] = $e->getMessage();

            $upload->update([
                'status'     => 'error',
                'rows_count' => 0,
                'meta'       => $meta,
            ]);

            return $this->jsonOrRedirectError(
                $request,
                'El archivo se cargó pero no se pudo procesar: ' . $e->getMessage(),
                $rfcOwner
            );
        }
    }

    public function downloadUploadedFile(Request $request, string $type, int $id)
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $type = strtolower(trim($type));

        [$modelClass, $label] = match ($type) {
            'metadata' => [SatUserMetadataUpload::class, 'metadata'],
            'xml'      => [SatUserXmlUpload::class, 'xml'],
            'report'   => [SatUserReportUpload::class, 'reporte'],
            default    => [null, null],
        };

        abort_if($modelClass === null, 404);

        $upload = $modelClass::query()
            ->where('id', $id)
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (!$upload) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => (string) $request->query('rfc', '')])
                ->with('error', 'El archivo solicitado no existe o no pertenece a tu cuenta.');
        }

        $disk = (string) ($upload->disk ?: self::DISK);
        $path = (string) ($upload->path ?? '');
        $name = (string) ($upload->original_name ?: $upload->stored_name ?: ($label . '_' . $upload->id));
        $mime = (string) ($upload->mime ?: 'application/octet-stream');

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => (string) ($upload->rfc_owner ?? '')])
                ->with('error', 'El archivo existe en base de datos pero no se encontró en almacenamiento.');
        }

        $view = (int) $request->query('view', 0) === 1;

        if ($view && $this->canInlinePreview($name, $mime)) {
            $content = Storage::disk($disk)->get($path);

            return response($content, 200, [
                'Content-Type'              => $this->normalizeInlineMime($name, $mime),
                'Content-Disposition'       => 'inline; filename="' . addslashes($name) . '"',
                'X-Content-Type-Options'    => 'nosniff',
                'Cache-Control'             => 'private, no-store, max-age=0',
            ]);
        }

        return Storage::disk($disk)->download($path, $name, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function storeRfc(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $request->validate([
            'rfc'          => ['required', 'string', 'max:20'],
            'razon_social' => ['nullable', 'string', 'max:255'],
        ], [
            'rfc.required' => 'Debes capturar el RFC.',
            'rfc.max'      => 'El RFC excede la longitud permitida.',
        ]);

        $rfc = strtoupper(trim((string) $request->input('rfc')));
        $razonSocial = trim((string) $request->input('razon_social', ''));

        if (!$this->isValidRfc($rfc)) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $request->query('rfc', '')])
                ->with('error', 'El RFC capturado no tiene un formato válido.');
        }

        $credential = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->where('rfc', $rfc)
            ->first();

        if ($credential) {
            $meta = is_array($credential->meta) ? $credential->meta : [];
            $meta['is_active'] = true;
            $meta['deleted_at'] = null;
            $meta['deleted_by'] = null;

            if ($razonSocial !== '') {
                $credential->razon_social = $razonSocial;
            }

            $credential->meta = $meta;
            $credential->save();
        } else {
            $credential = new SatCredential();
            $credential->cuenta_id = $cuentaId;
            $credential->rfc = $rfc;
            $credential->razon_social = $razonSocial !== '' ? $razonSocial : null;
            $credential->meta = [
                'created_from' => 'sat_v2_rfc_modal',
                'is_active'    => true,
            ];
            $credential->save();
        }

        SatUserVault::query()->firstOrCreate([
            'cuenta_id'  => $cuentaId,
            'usuario_id' => $usuarioId,
            'rfc'        => $rfc,
        ], []);

        return redirect()
            ->route('cliente.sat.v2.index', ['rfc' => $rfc])
            ->with('success', 'RFC registrado correctamente.');
    }

    public function updateRfc(Request $request, string $id): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));

        $request->validate([
            'razon_social' => ['nullable', 'string', 'max:255'],
        ], [
            'razon_social.max' => 'La razón social excede la longitud permitida.',
        ]);

        $credential = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->where('id', (string) $id)
            ->first();

        if (!$credential) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $request->query('rfc', '')])
                ->with('error', 'El RFC solicitado no existe o no pertenece a tu cuenta.');
        }

        $credential->razon_social = trim((string) $request->input('razon_social', '')) ?: null;

        $meta = is_array($credential->meta) ? $credential->meta : [];
        $meta['is_active'] = true;
        $meta['updated_from'] = 'sat_v2_rfc_modal';
        $credential->meta = $meta;

        $credential->save();

        return redirect()
            ->route('cliente.sat.v2.index', ['rfc' => (string) $request->query('rfc', $credential->rfc)])
            ->with('success', 'RFC actualizado correctamente.');
    }

    public function deleteRfc(Request $request, string $id): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 401);

        $cuentaId  = (string) ($user->cuenta_id ?? ($user->cuenta->id ?? ''));
        $usuarioId = (string) ($user->id ?? '');

        $credential = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->where('id', (string) $id)
            ->first();

        if (!$credential) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $request->query('rfc', '')])
                ->with('error', 'El RFC solicitado no existe o no pertenece a tu cuenta.');
        }

        $targetRfc = (string) $credential->rfc;

        $meta = is_array($credential->meta) ? $credential->meta : [];
        $meta['is_active'] = false;
        $meta['deleted_at'] = now()->toDateTimeString();
        $meta['deleted_by'] = $usuarioId;
        $meta['updated_from'] = 'sat_v2_rfc_modal';
        $credential->meta = $meta;
        $credential->save();

        SatUserVault::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc', $targetRfc)
            ->delete();

        $redirectRfc = (string) $request->query('rfc', '');
        if (strtoupper($redirectRfc) === strtoupper($targetRfc)) {
            $redirectRfc = '';
        }

        return redirect()
            ->route('cliente.sat.v2.index', $redirectRfc !== '' ? ['rfc' => $redirectRfc] : [])
            ->with('success', 'RFC dado de baja correctamente.');
    }

    private function credentialIsActive(SatCredential $credential): bool
    {
        $meta = is_array($credential->meta) ? $credential->meta : [];

        if (array_key_exists('is_active', $meta)) {
            return (bool) $meta['is_active'];
        }

        return true;
    }

    private function canInlinePreview(string $filename, string $mime): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime      = strtolower(trim($mime));

        if (in_array($extension, ['txt', 'csv', 'xml', 'json', 'log'], true)) {
            return true;
        }

        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        if (in_array($mime, [
            'application/xml',
            'text/xml',
            'text/csv',
            'application/json',
            'application/pdf',
        ], true)) {
            return true;
        }

        return false;
    }

    private function normalizeInlineMime(string $filename, string $mime): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime      = strtolower(trim($mime));

        return match ($extension) {
            'txt', 'log' => 'text/plain; charset=UTF-8',
            'csv'        => 'text/csv; charset=UTF-8',
            'xml'        => 'application/xml; charset=UTF-8',
            'json'       => 'application/json; charset=UTF-8',
            default      => ($mime !== '' ? $mime : 'application/octet-stream'),
        };
    }

    private function resolveRfcOwner(string $rfcOwner, string $rfcExisting, string $rfcNew): string
    {
        $rfcNew = strtoupper(trim($rfcNew));
        $rfcExisting = strtoupper(trim($rfcExisting));
        $rfcOwner = strtoupper(trim($rfcOwner));

        if ($rfcNew !== '') {
            return $rfcNew;
        }

        if ($rfcExisting !== '') {
            return $rfcExisting;
        }

        return $rfcOwner;
    }

    private function ensureRfcAvailableForAccount(string $cuentaId, string $usuarioId, string $rfc, string $razonSocial = ''): void
    {
        SatCredential::query()->firstOrCreate(
            [
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
            ],
            [
                'razon_social' => $razonSocial !== '' ? $razonSocial : null,
            ]
        );

        SatUserVault::query()->firstOrCreate(
            [
                'cuenta_id'  => $cuentaId,
                'usuario_id' => $usuarioId,
                'rfc'        => $rfc,
            ],
            []
        );
    }

    private function isValidRfc(string $rfc): bool
    {
        $rfc = strtoupper(trim($rfc));

        return (bool) preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc);
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'archivo.dat';
        }

        $name = preg_replace('/[^A-Za-z0-9\.\-_]+/', '_', $name) ?: 'archivo.dat';
        $name = ltrim($name, '._');

        return $name !== '' ? $name : 'archivo.dat';
    }

    private function detectSourceType(string $extension, string $originalName): string
    {
        $name = strtolower($originalName);

        if ($extension === 'zip') {
            return 'zip';
        }

        if ($extension === 'csv') {
            return str_contains($name, 'metadata') ? 'csv_metadata' : 'csv';
        }

        if ($extension === 'txt') {
            return str_contains($name, 'metadata') ? 'txt_metadata' : 'txt';
        }

        return 'file';
    }

    private function jsonOrRedirectError(Request $request, string $message, string $routeRfc = ''): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
            ], 422);
        }

        return redirect()
            ->route('cliente.sat.v2.index', $routeRfc !== '' ? ['rfc' => $routeRfc] : [])
            ->with('error', $message);
    }

    private function jsonOrRedirectSuccess(Request $request, string $message, string $rfcOwner): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'           => true,
                'message'      => $message,
                'redirect_url' => route('cliente.sat.v2.index', ['rfc' => $rfcOwner]),
            ]);
        }

        return redirect()
            ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
            ->with('success', $message);
    }

    private function existingXmlDuplicate(
    string $cuentaId,
    string $usuarioId,
    string $rfcOwner,
    string $originalName,
    string $xmlDirection
    ): ?SatUserXmlUpload {
        return SatUserXmlUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('original_name', $this->sanitizeFilename($originalName))
            ->where(function ($q) use ($xmlDirection) {
                $q->where('direction_detected', $xmlDirection)
                ->orWhereRaw('LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.direction_selected")), "")) = ?', [$xmlDirection]);
            })
            ->whereNotIn('status', ['error'])
            ->latest('id')
            ->first();
    }

    private function existingMetadataDuplicate(
    string $cuentaId,
    string $usuarioId,
    string $rfcOwner,
    string $originalName,
    string $metadataDirection
    ): ?SatUserMetadataUpload {
        return SatUserMetadataUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('original_name', $this->sanitizeFilename($originalName))
            ->where('direction_detected', $metadataDirection)
            ->whereNotIn('status', ['error'])
            ->latest('id')
            ->first();
    }

    private function existingReportDuplicate(
        string $cuentaId,
        string $usuarioId,
        string $rfcOwner,
        string $originalName,
        string $reportType,
        string $reportDirection
    ): ?SatUserReportUpload {
        return SatUserReportUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('original_name', $this->sanitizeFilename($originalName))
            ->where('report_type', $reportType)
            ->where(function ($q) use ($reportDirection) {
                $q->whereRaw('LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.report_direction")), "")) = ?', [$reportDirection]);
            })
            ->whereNotIn('status', ['error'])
            ->latest('id')
            ->first();
    }

    private function parseMetadataFile(string $storedPath, string $extension): array
    {
        return match ($extension) {
            'csv', 'txt' => $this->parseFlatMetadataFile($storedPath),
            'zip'        => $this->parseMetadataZipFile($storedPath),
            default      => [],
        };
    }

      private function parseMetadataZipFile(string $storedPath): array
    {
        $absolutePath = Storage::disk(self::DISK)->path($storedPath);
        $zip = new ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('No se pudo abrir el ZIP de metadata.');
        }

        $rows = [];
        $debugEntries = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = (string) $zip->getNameIndex($i);

                if ($entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                $entryExt = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
                $debugEntries[] = $entryName;

                if (!in_array($entryExt, ['csv', 'txt'], true)) {
                    continue;
                }

                $content = $zip->getFromIndex($i);
                if (!is_string($content) || $content === '') {
                    continue;
                }

                $parsed = $this->parseFlatMetadataContent($content, $entryName);

                if (!empty($parsed)) {
                    $rows = array_merge($rows, $parsed);
                }
            }
        } finally {
            $zip->close();
        }

        return $rows;
    }

    private function parseFlatMetadataFile(string $storedPath): array
    {
        $content = Storage::disk(self::DISK)->get($storedPath);
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        return $this->parseFlatMetadataContent($content, basename($storedPath));
    }

        private function parseFlatMetadataContent(string $content, string $sourceName = ''): array
    {
        $content = $this->decodeTextToUtf8($content);
        $content = preg_replace("/^\xEF\xBB\xBF/", '', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $lines = array_values(array_filter(
            array_map(static fn ($line) => trim((string) $line), explode("\n", $content)),
            static fn ($line) => $line !== ''
        ));

        if (empty($lines)) {
            return [];
        }

        $rows = [];

        // ==========================================================
        // CASO 1: archivo SAT sin encabezados, separado por "~"
        // Formato detectado por tu muestra:
        // 0 uuid
        // 1 rfc_emisor
        // 2 nombre_emisor
        // 3 rfc_receptor
        // 4 nombre_receptor
        // 5 ?? (a veces rfc tercero / complemento)
        // 6 fecha_emision
        // 7 fecha_certificacion_sat
        // 8 monto
        // 9 efecto / tipo comprobante
        // 10 estatus
        // ==========================================================
        $tildeLikeRows = 0;
        $sampleScan = min(count($lines), 15);

        for ($i = 0; $i < $sampleScan; $i++) {
            $parts = array_map('trim', explode('~', $lines[$i]));
            if (count($parts) >= 10 && preg_match('/^[A-F0-9\-]{30,40}$/i', (string) ($parts[0] ?? ''))) {
                $tildeLikeRows++;
            }
        }

        if ($tildeLikeRows >= 2) {
            foreach ($lines as $index => $line) {
                $parts = array_map('trim', explode('~', $line));

                if (count($parts) < 10) {
                    continue;
                }

                $uuid = (string) ($parts[0] ?? '');
                if ($uuid === '') {
                    continue;
                }

                $rows[] = [
                    'uuid'                    => $uuid,
                    'rfc_emisor'              => $parts[1] ?? null,
                    'nombre_emisor'           => $parts[2] ?? null,
                    'rfc_receptor'            => $parts[3] ?? null,
                    'nombre_receptor'         => $parts[4] ?? null,
                    'fecha_emision'           => $parts[6] ?? null,
                    'fecha_certificacion_sat' => $parts[7] ?? null,
                    'monto'                   => $parts[8] ?? null,
                    'efecto_comprobante'      => $parts[9] ?? null,
                    'estatus'                 => $parts[10] ?? null,
                    'fecha_cancelacion'       => $parts[11] ?? null,
                    'raw_line'                => $line,
                    '_line_number'            => $index + 1,
                    '_columns_found'          => [
                        'uuid',
                        'rfc_emisor',
                        'nombre_emisor',
                        'rfc_receptor',
                        'nombre_receptor',
                        'fecha_emision',
                        'fecha_certificacion_sat',
                        'monto',
                        'efecto_comprobante',
                        'estatus',
                        'fecha_cancelacion',
                    ],
                ];
            }

            return $rows;
        }

        // ==========================================================
        // CASO 2: archivo con encabezado
        // ==========================================================
        $headerIndex = null;
        $headers = [];
        $delimiter = ',';

        $maxScan = min(count($lines), 20);

        for ($i = 0; $i < $maxScan; $i++) {
            $candidateDelimiter = $this->detectDelimiter([$lines[$i]]);
            $candidateHeaders = $this->normalizeHeaderRow(str_getcsv($lines[$i], $candidateDelimiter));

            $score = 0;
            foreach ($candidateHeaders as $h) {
                if (in_array($h, [
                    'uuid',
                    'rfc_emisor',
                    'nombre_emisor',
                    'rfc_receptor',
                    'nombre_receptor',
                    'fecha_emision',
                    'fecha_certificacion_sat',
                    'monto',
                    'efecto_comprobante',
                    'estatus',
                    'fecha_cancelacion',
                ], true)) {
                    $score++;
                }
            }

            if ($score >= 3) {
                $headerIndex = $i;
                $headers = $candidateHeaders;
                $delimiter = $candidateDelimiter;
                break;
            }
        }

        if ($headerIndex === null || empty($headers)) {
            return [];
        }

        foreach ($lines as $index => $line) {
            if ($index <= $headerIndex) {
                continue;
            }

            $columns = str_getcsv($line, $delimiter);
            if (count($columns) === 1 && trim((string) $columns[0]) === '') {
                continue;
            }

            $assoc = [];
            foreach ($headers as $k => $normalizedHeader) {
                if ($normalizedHeader === '') {
                    continue;
                }
                $assoc[$normalizedHeader] = isset($columns[$k]) ? trim((string) $columns[$k]) : null;
            }

            $mapped = [
                'uuid'                    => $assoc['uuid'] ?? null,
                'rfc_emisor'              => $assoc['rfc_emisor'] ?? null,
                'nombre_emisor'           => $assoc['nombre_emisor'] ?? null,
                'rfc_receptor'            => $assoc['rfc_receptor'] ?? null,
                'nombre_receptor'         => $assoc['nombre_receptor'] ?? null,
                'fecha_emision'           => $assoc['fecha_emision'] ?? null,
                'fecha_certificacion_sat' => $assoc['fecha_certificacion_sat'] ?? null,
                'monto'                   => $assoc['monto'] ?? null,
                'efecto_comprobante'      => $assoc['efecto_comprobante'] ?? null,
                'estatus'                 => $assoc['estatus'] ?? null,
                'fecha_cancelacion'       => $assoc['fecha_cancelacion'] ?? null,
                'raw_line'                => $line,
                '_line_number'            => $index + 1,
                '_columns_found'          => array_values(array_filter($headers)),
            ];

            $hasData = false;
            foreach ([
                'uuid',
                'rfc_emisor',
                'nombre_emisor',
                'rfc_receptor',
                'nombre_receptor',
                'fecha_emision',
                'monto',
                'estatus',
            ] as $field) {
                if (trim((string) ($mapped[$field] ?? '')) !== '') {
                    $hasData = true;
                    break;
                }
            }

            if ($hasData) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    private function detectDelimiter(array $lines): string
    {
        $sample = implode("\n", array_slice($lines, 0, 5));

        $candidates = [
            '|'  => substr_count($sample, '|'),
            ';'  => substr_count($sample, ';'),
            "\t" => substr_count($sample, "\t"),
            ','  => substr_count($sample, ','),
            '~'  => substr_count($sample, '~'),
        ];

        arsort($candidates);
        $delimiter = (string) array_key_first($candidates);

        return (($candidates[$delimiter] ?? 0) > 0) ? $delimiter : '|';
    }

    private function normalizeHeaderRow(array $headers): array
    {
        return array_map(function ($header) {
            $header = strtolower(trim((string) $header));
            $header = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $header);
            $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? '';
            $header = trim($header, '_');

            return match ($header) {
                'uuid',
                'folio_fiscal',
                'id_fiscal',
                'uuid_fiscal',
                'folio_uuid' => 'uuid',

                'rfc_emisor',
                'emisor_rfc',
                'rfc_del_emisor',
                'rfc__emisor',
                'rfcemisor' => 'rfc_emisor',

                'nombre_emisor',
                'emisor_nombre',
                'razon_social_emisor',
                'nombre_o_razon_social_emisor',
                'nombre_razon_social_emisor' => 'nombre_emisor',

                'rfc_receptor',
                'receptor_rfc',
                'rfc_del_receptor',
                'rfc__receptor',
                'rfcreceptor' => 'rfc_receptor',

                'nombre_receptor',
                'receptor_nombre',
                'razon_social_receptor',
                'nombre_o_razon_social_receptor',
                'nombre_razon_social_receptor' => 'nombre_receptor',

                'fecha_emision',
                'fecha',
                'fecha_de_emision',
                'fecha_factura',
                'fecha_expedicion' => 'fecha_emision',

                'fecha_certificacion_sat',
                'fecha_certificacion',
                'fecha_timbrado',
                'fecha_de_certificacion',
                'fecha_certificacion_del_sat' => 'fecha_certificacion_sat',

                'monto',
                'total',
                'importe',
                'importe_total',
                'monto_total' => 'monto',

                'efecto_comprobante',
                'tipo_efecto',
                'efecto',
                'tipo_de_comprobante',
                'tipo_comprobante' => 'efecto_comprobante',

                'estatus',
                'estado',
                'status',
                'estado_del_comprobante' => 'estatus',

                'fecha_cancelacion',
                'cancelacion_fecha',
                'fecha_de_cancelacion' => 'fecha_cancelacion',

                default => $header,
            };
        }, $headers);
    }

    private function parseDateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMoneyValue(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.00;
        }

        $value = str_replace(['$', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } elseif (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return 0.00;
        }

        return round((float) $value, 2);
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

        private function decodeTextToUtf8(string $content): string
    {
        if ($content === '') {
            return '';
        }

        if (str_starts_with($content, "\xFF\xFE")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);

        if ($encoding && strtoupper($encoding) !== 'UTF-8' && strtoupper($encoding) !== 'ASCII') {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return $content;
    }

    // ==========================================================
    // XML PARSER CFDI (3.2 / 3.3 / 4.0)
    // ==========================================================
    private function parseXmlContent(string $xmlContent): ?array
    {
        try {
            $xml = @simplexml_load_string($xmlContent);
            if (!$xml) {
                return null;
            }

            $namespaces = $xml->getNamespaces(true);

            $cfdiNs = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            if (!$cfdiNs) {
                return null;
            }

            $xml->registerXPathNamespace('cfdi', $cfdiNs);

            $comprobante = $xml->xpath('//cfdi:Comprobante')[0] ?? null;
            if (!$comprobante) {
                return null;
            }

            $version = (string) ($comprobante['Version'] ?? $comprobante['version'] ?? '');

            $emisor   = $xml->xpath('//cfdi:Emisor')[0] ?? null;
            $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;

            $timbre = null;
            if (isset($namespaces['tfd'])) {
                $xml->registerXPathNamespace('tfd', $namespaces['tfd']);
                $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
            }

            $uuid = strtoupper(trim((string) ($timbre['UUID'] ?? '')));

            $subtotal   = $this->parseMoneyValue((string) ($comprobante['SubTotal'] ?? $comprobante['subTotal'] ?? '0'));
            $descuento  = $this->parseMoneyValue((string) ($comprobante['Descuento'] ?? $comprobante['descuento'] ?? '0'));
            $total      = $this->parseMoneyValue((string) ($comprobante['Total'] ?? $comprobante['total'] ?? '0'));
            $iva        = $this->extractIvaFromXml($xml, $namespaces);
            $retenidos  = $this->extractRetencionesFromXml($xml, $namespaces);

            $tipoComprobante = (string) ($comprobante['TipoDeComprobante'] ?? $comprobante['tipoDeComprobante'] ?? '');
            $moneda          = (string) ($comprobante['Moneda'] ?? $comprobante['moneda'] ?? '');
            $metodoPago      = (string) ($comprobante['MetodoPago'] ?? $comprobante['metodoPago'] ?? '');
            $formaPago       = (string) ($comprobante['FormaPago'] ?? $comprobante['formaPago'] ?? '');
            $fechaEmision    = (string) ($comprobante['Fecha'] ?? $comprobante['fecha'] ?? '');

            $traslados = $this->extractTrasladosBreakdownFromXml($xml, $namespaces);
            $retenciones = $this->extractRetencionesBreakdownFromXml($xml, $namespaces);

            return [
                'uuid'             => $uuid,
                'version_cfdi'     => $version,
                'rfc_emisor'       => (string) ($emisor['Rfc'] ?? $emisor['rfc'] ?? ''),
                'nombre_emisor'    => (string) ($emisor['Nombre'] ?? $emisor['nombre'] ?? ''),
                'rfc_receptor'     => (string) ($receptor['Rfc'] ?? $receptor['rfc'] ?? ''),
                'nombre_receptor'  => (string) ($receptor['Nombre'] ?? $receptor['nombre'] ?? ''),
                'fecha_emision'    => $fechaEmision,
                'subtotal'         => $subtotal,
                'descuento'        => $descuento,
                'iva'              => $iva,
                'retenciones'      => $retenidos,
                'total'            => $total,
                'moneda'           => $moneda,
                'tipo_comprobante' => $tipoComprobante,
                'metodo_pago'      => $metodoPago,
                'forma_pago'       => $formaPago,
                'meta'             => [
                    'traslados'    => $traslados,
                    'retenciones'  => $retenciones,
                    'impuestos'    => [
                        'iva'               => $iva,
                        'retenciones_total' => $retenidos,
                    ],
                ],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function xmlAlreadyExists(string $cuentaId, string $usuarioId, string $rfcOwner, string $uuid, string $hash): bool
    {
        return SatUserCfdi::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where(function ($q) use ($uuid, $hash) {
                $q->where('uuid', $uuid)
                ->orWhere('xml_hash', $hash);
            })
            ->exists();
    }


    private function extractIvaFromXml(\SimpleXMLElement $xml, array $namespaces): float
    {
        try {
            $totalIva = 0.0;

            $cfdiNs = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            if (!$cfdiNs) {
                return 0.0;
            }

            $xml->registerXPathNamespace('cfdi', $cfdiNs);

            $traslados = $xml->xpath('//cfdi:Impuestos//cfdi:Traslado') ?: [];

            foreach ($traslados as $traslado) {
                $impuesto = strtoupper(trim((string) ($traslado['Impuesto'] ?? $traslado['impuesto'] ?? '')));
                $importe  = $this->parseMoneyValue((string) ($traslado['Importe'] ?? $traslado['importe'] ?? '0'));

                // 002 = IVA en CFDI 3.3/4.0
                // IVA  = CFDI 3.2 / variantes viejas
                if ($impuesto === '002' || $impuesto === 'IVA') {
                    $totalIva += $importe;
                }
            }

            return round($totalIva, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

        private function extractRetencionesFromXml(\SimpleXMLElement $xml, array $namespaces): float
    {
        try {
            $totalRetenciones = 0.0;

            $cfdiNs = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            if (!$cfdiNs) {
                return 0.0;
            }

            $xml->registerXPathNamespace('cfdi', $cfdiNs);

            $retenciones = $xml->xpath('//cfdi:Impuestos//cfdi:Retencion') ?: [];

            foreach ($retenciones as $retencion) {
                $importe = $this->parseMoneyValue((string) ($retencion['Importe'] ?? $retencion['importe'] ?? '0'));
                $totalRetenciones += $importe;
            }

            return round($totalRetenciones, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function extractTrasladosBreakdownFromXml(\SimpleXMLElement $xml, array $namespaces): array
    {
        try {
            $items = [];

            $cfdiNs = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            if (!$cfdiNs) {
                return [];
            }

            $xml->registerXPathNamespace('cfdi', $cfdiNs);

            $traslados = $xml->xpath('//cfdi:Impuestos//cfdi:Traslado') ?: [];

            foreach ($traslados as $traslado) {
                $items[] = [
                    'impuesto' => strtoupper(trim((string) ($traslado['Impuesto'] ?? $traslado['impuesto'] ?? ''))),
                    'tipo'     => trim((string) ($traslado['TipoFactor'] ?? $traslado['tipoFactor'] ?? '')),
                    'tasa'     => $this->parseMoneyValue((string) ($traslado['TasaOCuota'] ?? $traslado['tasaOCuota'] ?? '0')),
                    'base'     => $this->parseMoneyValue((string) ($traslado['Base'] ?? $traslado['base'] ?? '0')),
                    'importe'  => $this->parseMoneyValue((string) ($traslado['Importe'] ?? $traslado['importe'] ?? '0')),
                ];
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractRetencionesBreakdownFromXml(\SimpleXMLElement $xml, array $namespaces): array
    {
        try {
            $items = [];

            $cfdiNs = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            if (!$cfdiNs) {
                return [];
            }

            $xml->registerXPathNamespace('cfdi', $cfdiNs);

            $retenciones = $xml->xpath('//cfdi:Impuestos//cfdi:Retencion') ?: [];

            foreach ($retenciones as $retencion) {
                $items[] = [
                    'impuesto' => strtoupper(trim((string) ($retencion['Impuesto'] ?? $retencion['impuesto'] ?? ''))),
                    'base'     => $this->parseMoneyValue((string) ($retencion['Base'] ?? $retencion['base'] ?? '0')),
                    'importe'  => $this->parseMoneyValue((string) ($retencion['Importe'] ?? $retencion['importe'] ?? '0')),
                ];
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private function generateXmlHash(string $content): string
    {
        return hash('sha256', $content);
    }

    private function xmlAlreadyExistsByUuid(string $cuentaId, string $usuarioId, string $rfcOwner, string $uuid): bool
    {
        if ($uuid === '') {
            return false;
        }

        return SatUserCfdi::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('uuid', $uuid)
            ->exists();
    }

    private function xmlAlreadyExistsByHash(string $cuentaId, string $usuarioId, string $rfcOwner, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return SatUserCfdi::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('xml_hash', $hash)
            ->exists();
    }

    private function processStoredXmlUpload(
    SatUserXmlUpload $xmlUpload,
    string $storedPath,
    string $extension,
    string $cuentaId,
    string $usuarioId,
    string $rfcOwner,
    string $xmlDirection,
    ?SatUserMetadataUpload $linkedMetadata = null
        ): array {
            $candidates = [];
            $invalidFiles = 0;

            if ($extension === 'xml') {
                $content = Storage::disk(self::DISK)->get($storedPath);

                if (!is_string($content) || trim($content) === '') {
                    return [
                        'processed'       => 0,
                        'duplicates_uuid' => 0,
                        'duplicates_hash' => 0,
                        'invalid'         => 1,
                    ];
                }

                $parsed = $this->parseXmlContent($content);

                if (!$parsed || trim((string) ($parsed['uuid'] ?? '')) === '') {
                    return [
                        'processed'       => 0,
                        'duplicates_uuid' => 0,
                        'duplicates_hash' => 0,
                        'invalid'         => 1,
                    ];
                }

                $candidates[] = [
                    'uuid'       => strtoupper(trim((string) $parsed['uuid'])),
                    'xml_hash'   => $this->generateXmlHash($content),
                    'parsed'     => $parsed,
                    'zip_entry'  => null,
                    'storedPath' => $storedPath,
                ];
            } elseif ($extension === 'zip') {
                $absolutePath = Storage::disk(self::DISK)->path($storedPath);
                $zip = new ZipArchive();

                if ($zip->open($absolutePath) !== true) {
                    throw new \RuntimeException('No se pudo abrir el ZIP de XML.');
                }

                try {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entryName = (string) $zip->getNameIndex($i);

                        if ($entryName === '' || str_ends_with($entryName, '/')) {
                            continue;
                        }

                        if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'xml') {
                            continue;
                        }

                        $content = $zip->getFromIndex($i);

                        if (!is_string($content) || trim($content) === '') {
                            $invalidFiles++;
                            continue;
                        }

                        $parsed = $this->parseXmlContent($content);

                        if (!$parsed || trim((string) ($parsed['uuid'] ?? '')) === '') {
                            $invalidFiles++;
                            continue;
                        }

                        $candidates[] = [
                            'uuid'       => strtoupper(trim((string) $parsed['uuid'])),
                            'xml_hash'   => $this->generateXmlHash($content),
                            'parsed'     => $parsed,
                            'zip_entry'  => $entryName,
                            'storedPath' => $storedPath,
                        ];
                    }
                } finally {
                    $zip->close();
                }
            }

            if (empty($candidates)) {
                return [
                    'processed'       => 0,
                    'duplicates_uuid' => 0,
                    'duplicates_hash' => 0,
                    'invalid'         => $invalidFiles,
                ];
            }

            $seenUuid = [];
            $seenHash = [];
            $dedupedCandidates = [];
            $duplicatesByUuid = 0;
            $duplicatesByHash = 0;

            foreach ($candidates as $candidate) {
                $uuid = (string) $candidate['uuid'];
                $hash = (string) $candidate['xml_hash'];

                if ($uuid === '') {
                    $invalidFiles++;
                    continue;
                }

                if (isset($seenUuid[$uuid])) {
                    $duplicatesByUuid++;
                    continue;
                }

                if ($hash !== '' && isset($seenHash[$hash])) {
                    $duplicatesByHash++;
                    continue;
                }

                $seenUuid[$uuid] = true;

                if ($hash !== '') {
                    $seenHash[$hash] = true;
                }

                $dedupedCandidates[] = $candidate;
            }

            if (empty($dedupedCandidates)) {
                return [
                    'processed'       => 0,
                    'duplicates_uuid' => $duplicatesByUuid,
                    'duplicates_hash' => $duplicatesByHash,
                    'invalid'         => $invalidFiles,
                ];
            }

            $uuids = array_values(array_unique(array_map(
                fn ($row) => (string) $row['uuid'],
                $dedupedCandidates
            )));

            $hashes = array_values(array_unique(array_filter(array_map(
                fn ($row) => (string) $row['xml_hash'],
                $dedupedCandidates
            ))));

            $existingUuidMap = [];
            if (!empty($uuids)) {
                $existingUuids = SatUserCfdi::query()
                    ->where('cuenta_id', $cuentaId)
                    ->where('usuario_id', $usuarioId)
                    ->where('rfc_owner', $rfcOwner)
                    ->whereIn('uuid', $uuids)
                    ->pluck('uuid')
                    ->map(fn ($value) => strtoupper(trim((string) $value)))
                    ->filter()
                    ->values()
                    ->all();

                $existingUuidMap = array_fill_keys($existingUuids, true);
            }

            $existingHashMap = [];
            if (!empty($hashes)) {
                $existingHashes = SatUserCfdi::query()
                    ->where('cuenta_id', $cuentaId)
                    ->where('usuario_id', $usuarioId)
                    ->where('rfc_owner', $rfcOwner)
                    ->whereIn('xml_hash', $hashes)
                    ->pluck('xml_hash')
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values()
                    ->all();

                $existingHashMap = array_fill_keys($existingHashes, true);
            }

            $metadataMap = [];
            if (!empty($uuids)) {
                $metadataRows = SatUserMetadataItem::query()
                    ->where('cuenta_id', $cuentaId)
                    ->where('usuario_id', $usuarioId)
                    ->where('rfc_owner', $rfcOwner)
                    ->whereIn('uuid', $uuids)
                    ->get(['id', 'uuid']);

                foreach ($metadataRows as $row) {
                    $metadataMap[strtoupper(trim((string) $row->uuid))] = (int) $row->id;
                }
            }

            $rowsToInsert = [];
            $now = now();

            foreach ($dedupedCandidates as $candidate) {
                $uuid = (string) $candidate['uuid'];
                $hash = (string) $candidate['xml_hash'];

                if (isset($existingUuidMap[$uuid])) {
                    $duplicatesByUuid++;
                    continue;
                }

                if ($hash !== '' && isset($existingHashMap[$hash])) {
                    $duplicatesByHash++;
                    continue;
                }

                $parsed = (array) $candidate['parsed'];

                $rowsToInsert[] = [
                    'xml_upload_id'     => $xmlUpload->id,
                    'cuenta_id'         => $cuentaId,
                    'usuario_id'        => $usuarioId,
                    'rfc_owner'         => $rfcOwner,
                    'uuid'              => $uuid,
                    'version_cfdi'      => $this->nullIfEmpty($parsed['version_cfdi'] ?? null),
                    'rfc_emisor'        => $this->nullIfEmpty($parsed['rfc_emisor'] ?? null),
                    'nombre_emisor'     => $this->nullIfEmpty($parsed['nombre_emisor'] ?? null),
                    'rfc_receptor'      => $this->nullIfEmpty($parsed['rfc_receptor'] ?? null),
                    'nombre_receptor'   => $this->nullIfEmpty($parsed['nombre_receptor'] ?? null),
                    'fecha_emision'     => $this->parseDateValue($parsed['fecha_emision'] ?? null),
                    'subtotal'          => $this->parseMoneyValue($parsed['subtotal'] ?? 0),
                    'descuento'         => $this->parseMoneyValue($parsed['descuento'] ?? 0),
                    'iva'               => $this->parseMoneyValue($parsed['iva'] ?? 0),
                    'total'             => $this->parseMoneyValue($parsed['total'] ?? 0),
                    'tipo_comprobante'  => $this->nullIfEmpty($parsed['tipo_comprobante'] ?? null),
                    'moneda'            => $this->nullIfEmpty($parsed['moneda'] ?? null),
                    'metodo_pago'       => $this->nullIfEmpty($parsed['metodo_pago'] ?? null),
                    'forma_pago'        => $this->nullIfEmpty($parsed['forma_pago'] ?? null),
                    'direction'         => $xmlDirection,
                    'xml_path'          => $candidate['storedPath'],
                    'xml_hash'          => $hash,
                    'meta'              => json_encode([
                        'zip_entry'                     => $candidate['zip_entry'],
                        'linked_metadata_upload_id'     => $linkedMetadata?->id,
                        'linked_metadata_original_name' => $linkedMetadata?->original_name,
                        'matched_metadata_item_id'      => $metadataMap[$uuid] ?? null,
                        'matched_by'                    => isset($metadataMap[$uuid]) ? 'uuid' : null,
                        'source'                        => 'vault_v2_xml_upload',
                        'parsed_impuestos'              => (array) data_get($parsed, 'meta.impuestos', []),
                        'parsed_traslados'              => (array) data_get($parsed, 'meta.traslados', []),
                        'parsed_retenciones'            => (array) data_get($parsed, 'meta.retenciones', []),
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (!empty($rowsToInsert)) {
                DB::connection('mysql_clientes')->transaction(function () use ($rowsToInsert) {
                    foreach (array_chunk($rowsToInsert, 300) as $chunk) {
                        SatUserCfdi::query()->insert($chunk);
                    }
                });
            }

            return [
                'processed'       => count($rowsToInsert),
                'duplicates_uuid' => $duplicatesByUuid,
                'duplicates_hash' => $duplicatesByHash,
                'invalid'         => $invalidFiles,
            ];
        }

    private function processSingleXmlContent(
        string $content,
        string $storedPath,
        ?string $zipEntryName,
        SatUserXmlUpload $xmlUpload,
        string $cuentaId,
        string $usuarioId,
        string $rfcOwner,
        string $xmlDirection,
        ?SatUserMetadataUpload $linkedMetadata,
        array &$seenInCurrentBatch
    ): array {
        $parsed = $this->parseXmlContent($content);

        if (!$parsed || trim((string) ($parsed['uuid'] ?? '')) === '') {
            return [
                'inserted'       => 0,
                'duplicate_uuid' => 0,
                'duplicate_hash' => 0,
                'invalid'        => 1,
            ];
        }

        $uuid = strtoupper(trim((string) $parsed['uuid']));
        $xmlHash = $this->generateXmlHash($content);

        if (isset($seenInCurrentBatch['uuid:' . $uuid]) || $this->xmlAlreadyExistsByUuid($cuentaId, $usuarioId, $rfcOwner, $uuid)) {
            return [
                'inserted'       => 0,
                'duplicate_uuid' => 1,
                'duplicate_hash' => 0,
                'invalid'        => 0,
            ];
        }

        if (isset($seenInCurrentBatch['hash:' . $xmlHash]) || $this->xmlAlreadyExistsByHash($cuentaId, $usuarioId, $rfcOwner, $xmlHash)) {
            return [
                'inserted'       => 0,
                'duplicate_uuid' => 0,
                'duplicate_hash' => 1,
                'invalid'        => 0,
            ];
        }

        $seenInCurrentBatch['uuid:' . $uuid] = true;
        $seenInCurrentBatch['hash:' . $xmlHash] = true;

        $matchedMetadata = SatUserMetadataItem::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('uuid', $uuid)
            ->first();

        SatUserCfdi::query()->create([
            'xml_upload_id'     => $xmlUpload->id,
            'cuenta_id'         => $cuentaId,
            'usuario_id'        => $usuarioId,
            'rfc_owner'         => $rfcOwner,
            'uuid'              => $uuid,
            'version_cfdi'      => $this->nullIfEmpty($parsed['version_cfdi'] ?? null),
            'rfc_emisor'        => $this->nullIfEmpty($parsed['rfc_emisor'] ?? null),
            'nombre_emisor'     => $this->nullIfEmpty($parsed['nombre_emisor'] ?? null),
            'rfc_receptor'      => $this->nullIfEmpty($parsed['rfc_receptor'] ?? null),
            'nombre_receptor'   => $this->nullIfEmpty($parsed['nombre_receptor'] ?? null),
            'fecha_emision'     => $this->parseDateValue($parsed['fecha_emision'] ?? null),
            'subtotal'          => $this->parseMoneyValue($parsed['subtotal'] ?? 0),
            'descuento'         => $this->parseMoneyValue($parsed['descuento'] ?? 0),
            'iva'               => $this->parseMoneyValue($parsed['iva'] ?? 0),
            'total'             => $this->parseMoneyValue($parsed['total'] ?? 0),
            'tipo_comprobante'  => $this->nullIfEmpty($parsed['tipo_comprobante'] ?? null),
            'moneda'            => $this->nullIfEmpty($parsed['moneda'] ?? null),
            'metodo_pago'       => $this->nullIfEmpty($parsed['metodo_pago'] ?? null),
            'forma_pago'        => $this->nullIfEmpty($parsed['forma_pago'] ?? null),
            'direction'         => $xmlDirection,
            'xml_path'          => $storedPath,
            'xml_hash'          => $xmlHash,
            'meta'              => [
                'zip_entry'                     => $zipEntryName,
                'linked_metadata_upload_id'     => $linkedMetadata?->id,
                'linked_metadata_original_name' => $linkedMetadata?->original_name,
                'matched_metadata_item_id'      => $matchedMetadata?->id,
                'matched_by'                    => $matchedMetadata ? 'uuid' : null,
                'source'                        => 'vault_v2_xml_upload',
                'parsed_impuestos'              => (array) data_get($parsed, 'meta.impuestos', []),
                'parsed_traslados'              => (array) data_get($parsed, 'meta.traslados', []),
                'parsed_retenciones'            => (array) data_get($parsed, 'meta.retenciones', []),
            ],
        ]);

        return [
            'inserted'       => 1,
            'duplicate_uuid' => 0,
            'duplicate_hash' => 0,
            'invalid'        => 0,
        ];
    }

    private function readStoredXmlContentForCfdi(SatUserCfdi $cfdi): ?string
    {
        $xmlPath = trim((string) ($cfdi->xml_path ?? ''));
        if ($xmlPath === '') {
            return null;
        }

        if (!Storage::disk(self::DISK)->exists($xmlPath)) {
            return null;
        }

        $meta = is_array($cfdi->meta) ? $cfdi->meta : [];
        $zipEntry = trim((string) ($meta['zip_entry'] ?? ''));

        $extension = strtolower(pathinfo($xmlPath, PATHINFO_EXTENSION));

        if ($extension === 'xml') {
            $content = Storage::disk(self::DISK)->get($xmlPath);
            return is_string($content) ? $content : null;
        }

        if ($extension === 'zip') {
            if ($zipEntry === '') {
                return null;
            }

            $absolutePath = Storage::disk(self::DISK)->path($xmlPath);
            $zip = new ZipArchive();

            if ($zip->open($absolutePath) !== true) {
                return null;
            }

            try {
                $content = $zip->getFromName($zipEntry);
                return is_string($content) ? $content : null;
            } finally {
                $zip->close();
            }
        }

        return null;
    }

    private function refreshExistingCfdiFromParsed(SatUserCfdi $cfdi, array $parsed): void
    {
        $currentMeta = is_array($cfdi->meta) ? $cfdi->meta : [];

        $currentMeta['source'] = $currentMeta['source'] ?? 'vault_v2_xml_upload';
        $currentMeta['reprocessed_at'] = now()->toDateTimeString();
        $currentMeta['parsed_impuestos'] = (array) data_get($parsed, 'meta.impuestos', []);
        $currentMeta['parsed_traslados'] = (array) data_get($parsed, 'meta.traslados', []);
        $currentMeta['parsed_retenciones'] = (array) data_get($parsed, 'meta.retenciones', []);
        $currentMeta['last_reprocess_summary'] = [
            'subtotal' => $this->parseMoneyValue($parsed['subtotal'] ?? 0),
            'iva'      => $this->parseMoneyValue($parsed['iva'] ?? 0),
            'total'    => $this->parseMoneyValue($parsed['total'] ?? 0),
        ];

        $cfdi->update([
            'version_cfdi'     => $this->nullIfEmpty($parsed['version_cfdi'] ?? null),
            'rfc_emisor'       => $this->nullIfEmpty($parsed['rfc_emisor'] ?? null),
            'nombre_emisor'    => $this->nullIfEmpty($parsed['nombre_emisor'] ?? null),
            'rfc_receptor'     => $this->nullIfEmpty($parsed['rfc_receptor'] ?? null),
            'nombre_receptor'  => $this->nullIfEmpty($parsed['nombre_receptor'] ?? null),
            'fecha_emision'    => $this->parseDateValue($parsed['fecha_emision'] ?? null),
            'subtotal'         => $this->parseMoneyValue($parsed['subtotal'] ?? 0),
            'descuento'        => $this->parseMoneyValue($parsed['descuento'] ?? 0),
            'iva'              => $this->parseMoneyValue($parsed['iva'] ?? 0),
            'total'            => $this->parseMoneyValue($parsed['total'] ?? 0),
            'tipo_comprobante' => $this->nullIfEmpty($parsed['tipo_comprobante'] ?? null),
            'moneda'           => $this->nullIfEmpty($parsed['moneda'] ?? null),
            'metodo_pago'      => $this->nullIfEmpty($parsed['metodo_pago'] ?? null),
            'forma_pago'       => $this->nullIfEmpty($parsed['forma_pago'] ?? null),
            'meta'             => $currentMeta,
        ]);
    }

    private function importReportUpload(SatUserReportUpload $upload, string $direction): int
    {
        $disk = (string) ($upload->disk ?: self::DISK);
        $path = (string) $upload->path;

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            throw new \RuntimeException('No se encontró el archivo almacenado del reporte.');
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $extension = strtolower((string) data_get($upload->meta, 'extension', pathinfo($absolutePath, PATHINFO_EXTENSION)));

        $rows = match ($extension) {
            'csv'  => $this->parseDelimitedReportFile($absolutePath, ','),
            'txt'  => $this->parseDelimitedReportFile($absolutePath, null),
            'xlsx', 'xls' => $this->parseSpreadsheetReportFile($absolutePath),
            default => throw new \RuntimeException('Extensión de reporte no soportada: ' . $extension),
        };

        if (count($rows) === 0) {
            throw new \RuntimeException('El archivo no contiene filas legibles.');
        }

        SatUserReportItem::query()
            ->where('report_upload_id', $upload->id)
            ->delete();

        $batch = [];
        $lineNo = 1;
        $inserted = 0;

        foreach ($rows as $row) {
            $mapped = $this->mapReportRow($row, $upload, $direction, $lineNo);
            $lineNo++;

            if ($mapped === null) {
                continue;
            }

            $batch[] = $mapped;

            if (count($batch) >= 300) {
                SatUserReportItem::query()->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            SatUserReportItem::query()->insert($batch);
            $inserted += count($batch);
        }

        $meta = (array) ($upload->meta ?? []);
        $meta['analysis_pending'] = false;
        $meta['analysis_done_at'] = now()->toDateTimeString();
        $meta['imported_rows'] = $inserted;

        $upload->update([
            'rows_count' => $inserted,
            'status'     => 'processed',
            'meta'       => $meta,
        ]);

        return $inserted;
    }

    private function parseDelimitedReportFile(string $absolutePath, ?string $forcedDelimiter = null): array
    {
        $rows = [];
        $handle = fopen($absolutePath, 'r');

        if (!$handle) {
            throw new \RuntimeException('No se pudo abrir el archivo de reporte.');
        }

        $headers = null;
        $delimiter = $forcedDelimiter;

        while (($data = fgetcsv($handle, 0, $delimiter ?? ',')) !== false) {
            if ($delimiter === null) {
                $delimiter = $this->detectCsvDelimiterFromRow($data);
                fclose($handle);
                $handle = fopen($absolutePath, 'r');
                if (!$handle) {
                    throw new \RuntimeException('No se pudo reabrir el archivo de reporte.');
                }
                $headers = null;
                continue;
            }

            if ($data === [null] || $data === false) {
                continue;
            }

            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

            if ($headers === null) {
                $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $data);
                continue;
            }

            if ($this->rowLooksEmpty($data)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($data[$i]) ? trim((string) $data[$i]) : null;
            }

            if (!empty($assoc)) {
                $rows[] = $assoc;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function parseSpreadsheetReportFile(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->toArray(null, false, false, false);

        $rows = [];
        $headers = null;

        foreach ($raw as $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

            if ($headers === null) {
                $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $row);
                continue;
            }

            if ($this->rowLooksEmpty($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($row[$i]) ? trim((string) $row[$i]) : null;
            }

            if (!empty($assoc)) {
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    private function mapReportRow(array $row, SatUserReportUpload $upload, string $direction, int $lineNo): ?array
    {
        $uuid = $this->pickFirstNonEmpty($row, [
            'uuid', 'folio_fiscal', 'uuid_cfdi', 'id_fiscal',
        ]);

        $fechaRaw = $this->pickFirstNonEmpty($row, [
            'fecha', 'fecha_emision', 'fecha_timbrado', 'fecha_cfdi',
        ]);

        $emisorRfc = strtoupper((string) $this->pickFirstNonEmpty($row, [
            'emisor_rfc', 'rfc_emisor',
        ]));

        $emisorNombre = $this->pickFirstNonEmpty($row, [
            'emisor_nombre', 'nombre_emisor',
        ]);

        $receptorRfc = strtoupper((string) $this->pickFirstNonEmpty($row, [
            'receptor_rfc', 'rfc_receptor',
        ]));

        $receptorNombre = $this->pickFirstNonEmpty($row, [
            'receptor_nombre', 'nombre_receptor',
        ]);

        $tipoComprobante = strtoupper((string) $this->pickFirstNonEmpty($row, [
            'tipo_de_comprobante', 'tipo_comprobante',
        ]));

        $moneda = strtoupper((string) $this->pickFirstNonEmpty($row, [
            'moneda',
        ]));

        $subtotal = $this->toMoney($this->pickFirstNonEmpty($row, [
            'sub_total', 'subtotal',
        ]));

        $descuento = $this->toMoney($this->pickFirstNonEmpty($row, [
            'descuento',
        ]));

        $traslados = $this->toMoney($this->pickFirstNonEmpty($row, [
            'total_traslados', 'traslados',
        ]));

        $retenidos = $this->toMoney($this->pickFirstNonEmpty($row, [
            'total_retenidos', 'retenidos',
        ]));

        $pagosMonto = $this->toMoney($this->pickFirstNonEmpty($row, [
            'pagos_monto_total_pagos', 'monto_total_pagos',
        ]));

        $explicitTotal = $this->toMoney($this->pickFirstNonEmpty($row, [
            'total', 'monto_total',
        ]));

        $total = 0.00;
        if ($pagosMonto > 0) {
            $total = $pagosMonto;
        } elseif ($explicitTotal > 0) {
            $total = $explicitTotal;
        } else {
            $total = max(0, $subtotal + $traslados - $retenidos - $descuento);
        }

        $fecha = $this->parseReportDate($fechaRaw);
        $periodoYm = $fecha ? $fecha->format('Y-m') : (string) $this->pickFirstNonEmpty($row, [
            'periodo_a_m', 'periodo', 'periodo_ym',
        ]);

        if ($periodoYm !== '' && preg_match('/^\d{6}$/', $periodoYm)) {
            $periodoYm = substr($periodoYm, 0, 4) . '-' . substr($periodoYm, 4, 2);
        }

        if ($uuid === '' && !$fecha && $total <= 0 && $emisorRfc === '' && $receptorRfc === '') {
            return null;
        }

            $now = now();

            return [
                'cuenta_id'        => (string) $upload->cuenta_id,
                'usuario_id'       => (string) $upload->usuario_id,
                'rfc_owner'        => (string) $upload->rfc_owner,
                'report_upload_id' => (int) $upload->id,
                'report_type'      => (string) $upload->report_type,
                'direction'        => $direction,
                'line_no'          => $lineNo,
                'uuid'             => $uuid !== '' ? $uuid : null,
                'fecha_emision'    => $fecha?->format('Y-m-d H:i:s'),
                'periodo_ym'       => $periodoYm !== '' ? $periodoYm : null,
                'emisor_rfc'       => $emisorRfc !== '' ? $emisorRfc : null,
                'emisor_nombre'    => $emisorNombre !== '' ? $emisorNombre : null,
                'receptor_rfc'     => $receptorRfc !== '' ? $receptorRfc : null,
                'receptor_nombre'  => $receptorNombre !== '' ? $receptorNombre : null,
                'tipo_comprobante' => $tipoComprobante !== '' ? $tipoComprobante : null,
                'moneda'           => $moneda !== '' ? $moneda : null,
                'subtotal'         => $subtotal,
                'descuento'        => $descuento,
                'traslados'        => $traslados,
                'retenidos'        => $retenidos,
                'total'            => $total,
                'raw_row'          => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'meta'             => json_encode([
                    'source'    => 'report_upload',
                    'upload_id' => $upload->id,
                    'line_no'   => $lineNo,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
    }

    private function toJsonValue(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }
    
    private function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = str_replace(["\xEF\xBB\xBF", "\r", "\n", "\t"], ' ', $value);
        $value = str_replace(
            ['á','é','í','ó','ú','ü','ñ','/','\\','-','.','(',')'],
            ['a','e','i','o','u','u','n',' ',' ',' ',' ',' ',''],
            $value
        );
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? $value;
        return trim($value, '_');
    }

    private function rowLooksEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function pickFirstNonEmpty(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function toMoney(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(['$', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } elseif (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 2);
    }

    private function parseReportDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y',
            'm/d/Y H:i:s',
            'm/d/Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectCsvDelimiterFromRow(array $row): string
    {
        $joined = implode('|', array_map(fn ($v) => (string) $v, $row));

        $candidates = [',', ';', "\t", '|'];
        $scores = [];

        foreach ($candidates as $candidate) {
            $scores[$candidate] = substr_count($joined, $candidate);
        }

        arsort($scores);
        $best = (string) array_key_first($scores);

        return $scores[$best] > 0 ? $best : ',';
    }

    private function buildUnifiedDownloadItems(
    string $cuentaId,
    string $usuarioId,
    string $selectedRfc,
    Collection $v2Items
): Collection {
    $items = collect();

    /*
    |--------------------------------------------------------------------------
    | BÓVEDA V2
    |--------------------------------------------------------------------------
    */
    $items = $items->merge(
        $v2Items->map(function (array $item) use ($selectedRfc) {
            $bytes = (int) ($item['bytes'] ?? 0);

            return [
                'origin'         => 'boveda_v2',
                'origin_label'   => 'Bóveda v2',
                'origin_badge'   => 'V2',
                'kind'           => (string) ($item['kind'] ?? 'archivo'),
                'id'             => (string) ($item['id'] ?? ''),
                'rfc_owner'      => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                'direction'      => (string) ($item['direction'] ?? ''),
                'original_name'  => (string) ($item['original_name'] ?? 'Archivo'),
                'stored_name'    => (string) ($item['stored_name'] ?? ''),
                'mime'           => (string) ($item['mime'] ?? 'application/octet-stream'),
                'bytes'          => $bytes,
                'bytes_human'    => $this->formatBytesHuman($bytes),
                'detail'         => $this->resolveV2DetailLabel($item),
                'status'         => (string) ($item['status'] ?? ''),
                'created_at'     => $item['created_at'] ?? null,
                'download_url'   => route('cliente.sat.v2.download', [
                    'type' => (string) ($item['kind'] ?? 'metadata'),
                    'id'   => (int) ($item['id'] ?? 0),
                    'rfc'  => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                ]),
                'view_url'       => route('cliente.sat.v2.download', [
                    'type' => (string) ($item['kind'] ?? 'metadata'),
                    'id'   => (int) ($item['id'] ?? 0),
                    'rfc'  => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                    'view' => 1,
                ]),
            ];
        })
    );

    /*
    |--------------------------------------------------------------------------
    | BÓVEDA V1
    |--------------------------------------------------------------------------
    */
    if (Schema::connection('mysql_clientes')->hasTable('sat_vault_files')) {
        $vaultFilesQuery = DB::connection('mysql_clientes')
            ->table('sat_vault_files')
            ->where('cuenta_id', $cuentaId)
            ->orderByDesc('id');

        if ($selectedRfc !== '') {
            $vaultFilesQuery->where('rfc', $selectedRfc);
        }

        $vaultFiles = $vaultFilesQuery->limit(300)->get();

        $items = $items->merge(
            collect($vaultFiles)->map(function ($row) {
                $bytes = (int) ($row->bytes ?? $row->size_bytes ?? 0);
                $filename = trim((string) ($row->original_name ?? $row->filename ?? ''));
                if ($filename === '') {
                    $filename = basename((string) ($row->path ?? 'archivo'));
                }

                return [
                    'origin'         => 'boveda_v1',
                    'origin_label'   => 'Bóveda v1',
                    'origin_badge'   => 'V1',
                    'kind'           => $this->resolveKindFromFilename($filename, (string) ($row->mime ?? '')),
                    'id'             => (string) ($row->id ?? ''),
                    'rfc_owner'      => strtoupper((string) ($row->rfc ?? '')),
                    'direction'      => '',
                    'original_name'  => $filename,
                    'stored_name'    => (string) ($row->filename ?? ''),
                    'mime'           => (string) ($row->mime ?? 'application/octet-stream'),
                    'bytes'          => $bytes,
                    'bytes_human'    => $this->formatBytesHuman($bytes),
                    'detail'         => 'Archivo de bóveda',
                    'status'         => 'disponible',
                    'created_at'     => $row->created_at ?? null,
                    'download_url'   => route('cliente.sat.vault.file', ['id' => (string) ($row->id ?? '')]),
                    'view_url'       => route('cliente.sat.vault.file', ['id' => (string) ($row->id ?? '')]),
                ];
            })
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CENTRO SAT (descargas reales con ZIP)
    |--------------------------------------------------------------------------
    */
    if (Schema::connection('mysql_clientes')->hasTable('sat_downloads')) {
            $satDownloadsQuery = DB::connection('mysql_clientes')
                ->table('sat_downloads')
                ->where('cuenta_id', $cuentaId)
                ->whereNotNull('zip_path')
                ->where('zip_path', '<>', '')
                ->orderByDesc('created_at');

            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'usuario_id')) {
                $satDownloadsQuery->where('usuario_id', $usuarioId);
            }

            if ($selectedRfc !== '' && Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'rfc')) {
                $satDownloadsQuery->where('rfc', $selectedRfc);
            }

            $satDownloads = $satDownloadsQuery->limit(300)->get();

            $items = $items->merge(
                collect($satDownloads)->map(function ($row) {
                    $bytes = 0;
                    if (isset($row->bytes) && is_numeric($row->bytes)) {
                        $bytes = (int) $row->bytes;
                    } elseif (isset($row->size_bytes) && is_numeric($row->size_bytes)) {
                        $bytes = (int) $row->size_bytes;
                    }

                    $filename = trim((string) ($row->zip_filename ?? $row->filename ?? ''));
                    if ($filename === '') {
                        $filename = basename((string) ($row->zip_path ?? 'descarga.zip'));
                    }

                    return [
                        'origin'         => 'centro_sat',
                        'origin_label'   => 'Centro SAT',
                        'origin_badge'   => 'SAT',
                        'kind'           => 'zip',
                        'id'             => (string) ($row->id ?? ''),
                        'rfc_owner'      => strtoupper((string) ($row->rfc ?? '')),
                        'direction'      => strtolower((string) ($row->tipo ?? '')),
                        'original_name'  => $filename,
                        'stored_name'    => (string) ($row->zip_path ?? ''),
                        'mime'           => 'application/zip',
                        'bytes'          => $bytes,
                        'bytes_human'    => $this->formatBytesHuman($bytes),
                        'detail'         => 'Paquete SAT',
                        'status'         => (string) ($row->status ?? 'disponible'),
                        'created_at'     => $row->created_at ?? null,
                        'download_url'   => null,
                        'view_url'       => null,
                    ];
                })
            );
        }

        return $items
            ->filter(function (array $item) {
                return trim((string) ($item['original_name'] ?? '')) !== '';
            })
            ->sortByDesc(function (array $item) {
                return optional($item['created_at'] ?? null)->timestamp ?? 0;
            })
            ->values();
    }

    private function buildUnifiedStorageBreakdown(
        string $cuentaId,
        string $selectedRfc,
        Collection $unifiedItems
    ): array {
        $usedBytes = (int) $unifiedItems->sum(function (array $item) {
            return (int) ($item['bytes'] ?? 0);
        });

        $quotaBytes = 0;

        if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            $cuenta = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->first();

            if ($cuenta) {
                if (property_exists($cuenta, 'vault_quota_bytes') && is_numeric($cuenta->vault_quota_bytes)) {
                    $quotaBytes = (int) $cuenta->vault_quota_bytes;
                } elseif (property_exists($cuenta, 'espacio_asignado_mb') && is_numeric($cuenta->espacio_asignado_mb)) {
                    $quotaBytes = (int) round(((float) $cuenta->espacio_asignado_mb) * 1024 * 1024);
                }
            }
        }

        if ($quotaBytes < $usedBytes) {
            $quotaBytes = $usedBytes;
        }

        $availableBytes = max(0, $quotaBytes - $usedBytes);

        $usedGb = $usedBytes / 1073741824;
        $availableGb = $availableBytes / 1073741824;
        $quotaGb = $quotaBytes / 1073741824;

        $usedPct = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $availablePct = $quotaBytes > 0 ? round(($availableBytes / $quotaBytes) * 100, 2) : 0.0;

        return [
            'used_bytes'      => $usedBytes,
            'available_bytes' => $availableBytes,
            'quota_bytes'     => $quotaBytes,
            'used_gb'         => round($usedGb, 2),
            'available_gb'    => round($availableGb, 2),
            'quota_gb'        => round($quotaGb, 2),
            'used_pct'        => $usedPct,
            'available_pct'   => $availablePct,
            'chart'           => [
                'series' => [
                    round($usedGb, 2),
                    round($availableGb, 2),
                ],
                'labels' => ['Usado', 'Disponible'],
            ],
        ];
    }

    private function resolveV2DetailLabel(array $item): string
    {
        $kind = strtolower((string) ($item['kind'] ?? ''));

        return match ($kind) {
            'metadata' => number_format((int) ($item['rows_count'] ?? 0)) . ' registros',
            'xml'      => number_format((int) ($item['files_count'] ?? 0)) . ' archivo(s)',
            'report'   => number_format((int) ($item['rows_count'] ?? 0)) . ' filas',
            default    => 'Archivo',
        };
    }

    private function resolveKindFromFilename(string $filename, string $mime = ''): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext !== '') {
            return $ext;
        }

        $mime = strtolower(trim($mime));

        return match ($mime) {
            'application/zip' => 'zip',
            'application/xml', 'text/xml' => 'xml',
            'text/csv', 'application/csv' => 'csv',
            'application/pdf' => 'pdf',
            default => 'archivo',
        };
    }

    private function formatBytesHuman(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }

    private function buildQuoteItems(
    string $cuentaId,
    string $usuarioId,
    string $selectedRfc
    ): Collection {
        $rows = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('rfc', $selectedRfc)
            ->when(
                Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'usuario_id'),
                fn ($q) => $q->where('usuario_id', $usuarioId)
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return $rows
            ->filter(fn (SatDownload $row) => $this->isQuoteLikeDownload($row))
            ->map(fn (SatDownload $row) => $this->transformQuoteItem($row))
            ->values();
    }

    private function buildQuoteStats(Collection $quoteItems): array
    {
        $stats = [
            'total'       => (int) $quoteItems->count(),
            'draft'       => 0,
            'requested'   => 0,
            'ready'       => 0,
            'paid'        => 0,
            'in_progress' => 0,
            'done'        => 0,
            'canceled'    => 0,
            'payable'     => 0,
        ];

        foreach ($quoteItems as $item) {
            $status = (string) ($item['status_db'] ?? '');

            switch ($status) {
                case 'pending':
                    $stats['draft']++;
                    break;
                case 'requested':
                    $stats['requested']++;
                    break;
                case 'ready':
                    $stats['ready']++;
                    break;
                case 'paid':
                    $stats['paid']++;
                    break;
                case 'done':
                    $stats['done']++;
                    break;
                case 'canceled':
                    $stats['canceled']++;
                    break;
            }

            if (($item['status_ui'] ?? '') === 'en_descarga') {
                $stats['in_progress']++;
            }

            if ((bool) ($item['can_pay'] ?? false) === true) {
                $stats['payable']++;
            }
        }

        return $stats;
    }

    private function isQuoteLikeDownload(SatDownload $row): bool
    {
        $meta = is_array($row->meta) ? $row->meta : [];
        $payload = is_array($row->payload) ? $row->payload : [];
        $response = is_array($row->response) ? $row->response : [];

        $type = strtolower(trim((string) (
            $meta['flow_type']
            ?? $meta['type']
            ?? $payload['flow_type']
            ?? $payload['type']
            ?? ''
        )));

        if (in_array($type, ['quote', 'quotation', 'cotizacion', 'cotización'], true)) {
            return true;
        }

        if (Arr::has($meta, 'quote') || Arr::has($payload, 'quote') || Arr::has($response, 'quote')) {
            return true;
        }

        if (Arr::has($meta, 'quote_breakdown') || Arr::has($response, 'quote_breakdown')) {
            return true;
        }

        if (
            isset($row->subtotal) || isset($row->iva) || isset($row->total)
        ) {
            $status = strtolower(trim((string) $row->status));
            if (in_array($status, ['pending', 'requested', 'ready', 'paid', 'done', 'canceled'], true)) {
                return true;
            }
        }

        return false;
    }

    private function transformQuoteItem(SatDownload $row): array
    {
        $meta = is_array($row->meta) ? $row->meta : [];
        $payload = is_array($row->payload) ? $row->payload : [];
        $response = is_array($row->response) ? $row->response : [];

        $statusDb = strtolower(trim((string) $row->status));
        $statusUi = $this->resolveQuoteStatusUi($row, $meta);

        $subtotal = (float) ($row->subtotal ?? data_get($response, 'subtotal', 0) ?? 0);
        $iva      = (float) ($row->iva ?? data_get($response, 'iva', 0) ?? 0);
        $total    = (float) ($row->total ?? data_get($response, 'total', 0) ?? 0);

        $progress = (int) (
            $meta['progress']
            ?? $this->resolveQuoteProgress($statusDb, $statusUi)
        );

        if ($statusUi === 'en_descarga') {
            $progress = max($progress, 90);
        } elseif ($statusUi === 'pagada') {
            $progress = max($progress, 82);
        } elseif ($statusUi === 'completada') {
            $progress = 100;
        } elseif ($statusUi === 'cancelada') {
            $progress = 0;
        }

        $folio = trim((string) (
            $meta['folio']
            ?? $payload['folio']
            ?? $response['folio']
            ?? ('SAT-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT))
        ));

        $typeLabel = trim((string) (
            $meta['quote_type_label']
            ?? $payload['tipo_label']
            ?? $payload['tipo']
            ?? $response['tipo_label']
            ?? $response['tipo']
            ?? 'Cotización SAT'
        ));

        $canPay = (bool) ($meta['can_pay'] ?? false);

        return [
            'id'                => (int) $row->id,
            'folio'             => $folio,
            'rfc'               => (string) ($row->rfc ?? ''),
            'status_db'         => $statusDb,
            'status_ui'         => $statusUi,
            'status_label'      => $this->resolveQuoteStatusLabel($statusUi),
            'progress'          => max(0, min(100, $progress)),
            'can_pay'           => $canPay && $statusDb === 'ready' && $statusUi === 'cotizada',
            'is_paid'           => $statusUi === 'pagada',
            'is_in_progress'    => $statusUi === 'en_descarga',
            'is_done'           => $statusDb === 'done' || $statusUi === 'completada',
            'is_canceled'       => $statusDb === 'canceled' || $statusUi === 'cancelada',
            'type_label'        => $typeLabel,
            'subtotal'          => round($subtotal, 2),
            'iva'               => round($iva, 2),
            'total'             => round($total, 2),
            'notes'             => (string) (
                $meta['notes']
                ?? $payload['notes']
                ?? $response['notes']
                ?? ''
            ),
            'xml_count'         => (int) (
                $meta['xml_count']
                ?? $payload['xml_count']
                ?? $response['xml_count']
                ?? 0
            ),
            'date_from'         => (string) (
                $meta['date_from']
                ?? $payload['date_from']
                ?? $response['date_from']
                ?? ''
            ),
            'date_to'           => (string) (
                $meta['date_to']
                ?? $payload['date_to']
                ?? $response['date_to']
                ?? ''
            ),
            'paid_at'           => $row->paid_at,
            'created_at'        => $row->created_at,
            'updated_at'        => $row->updated_at,
            'stripe_session_id' => (string) ($row->stripe_session_id ?? ''),
        ];
    }

    private function resolveQuoteStatusUi(SatDownload $row, array $meta): string
    {
        $statusDb = strtolower(trim((string) $row->status));
        $customerAction = strtolower(trim((string) ($meta['customer_action'] ?? '')));
        $paidVia = strtolower(trim((string) ($meta['paid_via'] ?? '')));

        if ($statusDb === 'pending') {
            return 'borrador';
        }

        if ($statusDb === 'requested') {
            return 'en_proceso';
        }

        if ($statusDb === 'ready') {
            return 'cotizada';
        }

        if (
            $statusDb === 'paid'
            && in_array($customerAction, ['download_in_progress', 'processing_download', 'download_started'], true)
        ) {
            return 'en_descarga';
        }

        if ($statusDb === 'paid' && $paidVia !== '') {
            return 'pagada';
        }

        if ($statusDb === 'paid') {
            return 'pagada';
        }

        if ($statusDb === 'done') {
            return 'completada';
        }

        if ($statusDb === 'canceled') {
            return 'cancelada';
        }

        return 'en_proceso';
    }

    private function resolveQuoteStatusLabel(string $statusUi): string
    {
        return match ($statusUi) {
            'borrador'    => 'Borrador',
            'en_proceso'  => 'En proceso',
            'cotizada'    => 'Cotizada',
            'pagada'      => 'Pagada',
            'en_descarga' => 'En descarga',
            'completada'  => 'Completada',
            'cancelada'   => 'Cancelada',
            default       => 'En proceso',
        };
    }

    private function resolveQuoteProgress(string $statusDb, string $statusUi): int
    {
        if ($statusUi === 'en_descarga') {
            return 90;
        }

        if ($statusUi === 'pagada') {
            return 82;
        }

        if ($statusUi === 'completada') {
            return 100;
        }

        if ($statusUi === 'cancelada') {
            return 0;
        }

        return match ($statusDb) {
            'pending'   => 10,
            'requested' => 35,
            'ready'     => 65,
            'paid'      => 82,
            'done'      => 100,
            'canceled'  => 0,
            default     => 20,
        };
    }
}