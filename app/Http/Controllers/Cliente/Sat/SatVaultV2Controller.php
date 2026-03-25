<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatUserAccess;
use App\Models\Cliente\SatUserCfdi;
use App\Models\Cliente\SatUserMetadataItem;
use App\Models\Cliente\SatUserMetadataUpload;
use App\Models\Cliente\SatUserVault;
use App\Models\Cliente\SatUserXmlUpload;
use App\Models\Cliente\SatUserReportUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            ->orderBy('rfc')
            ->get(['id', 'rfc', 'razon_social']);

        $vaults = SatUserVault::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->orderBy('rfc')
            ->get();

        $selectedRfc = strtoupper(trim((string) $request->query('rfc', '')));
        if ($selectedRfc === '' && $rfcs->count() === 1) {
            $selectedRfc = (string) $rfcs->first()->rfc;
        }

        $metadataUploads = collect();
        $xmlUploads      = collect();
        $reportUploads   = collect();
        $metadataCount   = 0;
        $cfdiCount       = 0;
        $reportCount     = 0;
        $reconPending    = 0;

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

            $reportUploads = SatUserReportUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $selectedRfc)
                ->latest('id')
                ->limit(20)
                ->get();

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
        }

        return view('cliente.sat.v2.index', [
            'access'          => $access,
            'rfcs'            => $rfcs,
            'vaults'          => $vaults,
            'selectedRfc'     => $selectedRfc,
            'metadataUploads' => $metadataUploads,
            'xmlUploads'      => $xmlUploads,
            'metadataCount'   => $metadataCount,
            'cfdiCount'       => $cfdiCount,
            'reconPending'    => $reconPending,
            'reportUploads'   => $reportUploads,
            'reportCount'     => $reportCount,
        ]);
    }

    public function uploadMetadata(Request $request): RedirectResponse
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

        SatUserMetadataUpload::query()->create([
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
            ],
        ]);

        return redirect()
            ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
            ->with('success', 'Metadata cargada correctamente para el RFC ' . $rfcOwner . '.');
    }

    public function uploadXml(Request $request): RedirectResponse
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
            $clientBytes,
            $xmlDirection
        );

        if ($duplicateXml) {
            return $this->jsonOrRedirectError(
                $request,
                'Este XML ya fue cargado anteriormente para este RFC y tipo.',
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

        SatUserXmlUpload::query()->create([
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
            'status'             => 'uploaded',
            'meta'               => [
                'extension'                     => $extension,
                'uploaded_from'                 => 'vault_v2_ui',
                'original_mime'                 => (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''),
                'direction_selected'            => $xmlDirection,
                'rfc_source'                    => $request->filled('rfc_new') ? 'new' : ($request->filled('rfc_existing') ? 'existing' : 'active'),
                'razon_social'                  => $razonSocial,
                'linked_metadata_upload_id'     => $linkedMetadata?->id,
                'linked_metadata_original_name' => $linkedMetadata?->original_name,
                'analysis_pending'              => true,
            ],
        ]);

        return $this->jsonOrRedirectSuccess(
            $request,
            'XML cargado correctamente para el RFC ' . $rfcOwner . '.',
            $rfcOwner
        );
    }

    public function uploadReport(Request $request): RedirectResponse
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
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $request->input('rfc_owner')])
                ->with('error', 'Tu usuario no tiene permiso para cargar reportes.');
        }

        $request->validate([
            'rfc_owner'                 => ['nullable', 'string', 'max:20'],
            'rfc_existing'              => ['nullable', 'string', 'max:20'],
            'rfc_new'                   => ['nullable', 'string', 'max:20'],
            'razon_social'              => ['nullable', 'string', 'max:255'],
            'report_type'               => ['required', 'in:csv_report,xlsx_report,xls_report,txt_report'],
            'linked_metadata_upload_id' => ['nullable', 'integer'],
            'linked_xml_upload_id'      => ['nullable', 'integer'],
            'archivo_reporte'           => ['required', 'file', 'max:512000'],
        ], [
            'report_type.required'     => 'Selecciona el tipo de reporte.',
            'report_type.in'           => 'El tipo de reporte no es válido.',
            'archivo_reporte.required' => 'Selecciona un archivo de reporte.',
            'archivo_reporte.max'      => 'El archivo excede el tamaño permitido de 500 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('archivo_reporte');

        $rfcOwner = $this->resolveRfcOwner(
            (string) $request->input('rfc_owner', ''),
            (string) $request->input('rfc_existing', ''),
            (string) $request->input('rfc_new', '')
        );

        if ($rfcOwner === '') {
            return redirect()
                ->route('cliente.sat.v2.index')
                ->with('error', 'Debes seleccionar o capturar un RFC para asociar el reporte.');
        }

        if (!$this->isValidRfc($rfcOwner)) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'El RFC capturado no tiene un formato válido.');
        }

        $razonSocial = trim((string) $request->input('razon_social', ''));
        $reportType  = strtolower(trim((string) $request->input('report_type', 'csv_report')));

        $metadataUploadId = (int) $request->input('linked_metadata_upload_id', 0);
        $xmlUploadId      = (int) $request->input('linked_xml_upload_id', 0);

        $originalName = (string) $file->getClientOriginalName();
        $extension    = strtolower((string) $file->getClientOriginalExtension());

        if (!in_array($extension, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'Solo se permiten archivos CSV, XLSX, XLS o TXT para reportes.');
        }

        $this->ensureRfcAvailableForAccount($cuentaId, $usuarioId, $rfcOwner, $razonSocial);

        $linkedMetadata = null;
        if ($metadataUploadId > 0) {
            $linkedMetadata = SatUserMetadataUpload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('rfc_owner', $rfcOwner)
                ->where('id', $metadataUploadId)
                ->first();

            if (!$linkedMetadata) {
                return redirect()
                    ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                    ->with('error', 'El lote metadata seleccionado no pertenece a este RFC.');
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
                return redirect()
                    ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                    ->with('error', 'El lote XML seleccionado no pertenece a este RFC.');
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
            return redirect()
                ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
                ->with('error', 'No se pudo guardar el archivo del reporte.');
        }

        $bytes = 0;
        try {
            $bytes = (int) Storage::disk(self::DISK)->size($storedPath);
        } catch (\Throwable) {
            $bytes = 0;
        }

        SatUserReportUpload::query()->create([
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
                'linked_metadata_upload_id'     => $linkedMetadata?->id,
                'linked_metadata_original_name' => $linkedMetadata?->original_name,
                'linked_xml_upload_id'          => $linkedXml?->id,
                'linked_xml_original_name'      => $linkedXml?->original_name,
                'analysis_pending'              => true,
            ],
        ]);

        return redirect()
            ->route('cliente.sat.v2.index', ['rfc' => $rfcOwner])
            ->with('success', 'Reporte cargado correctamente para el RFC ' . $rfcOwner . '.');
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

        private function jsonOrRedirectError(Request $request, string $message, string $routeRfc = '') 
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

    private function jsonOrRedirectSuccess(Request $request, string $message, string $rfcOwner)
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
        int $bytes,
        string $xmlDirection
    ): ?SatUserXmlUpload {
        return SatUserXmlUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->where('rfc_owner', $rfcOwner)
            ->where('original_name', $this->sanitizeFilename($originalName))
            ->where('bytes', $bytes)
            ->where('status', 'uploaded')
            ->latest('id')
            ->get()
            ->first(function (SatUserXmlUpload $row) use ($xmlDirection) {
                return strtolower((string) data_get($row->meta, 'direction_selected', '')) === $xmlDirection;
            });
    }
}