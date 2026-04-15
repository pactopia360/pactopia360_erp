<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SatOpsRfcsController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $origin = trim((string) $request->query('origin', ''));
        $status = trim((string) $request->query('status', ''));
        $account = trim((string) $request->query('account', ''));

                $rows = SatCredential::query()
                    ->orderByDesc('updated_at')
                    ->get()
                    ->map(function (SatCredential $row) {
                $meta = is_array($row->meta) ? $row->meta : [];

                $tipoOrigen = strtolower((string) (
                    $row->tipo_origen
                    ?? ($meta['tipo_origen'] ?? '')
                ));

                if ($tipoOrigen === '') {
                    $tipoOrigen = 'interno';
                }

                $estatusOperativo = strtolower((string) (
                    $row->estatus_operativo
                    ?? ($meta['estatus_operativo'] ?? '')
                ));

                $estatusRaw = strtolower((string) ($row->estatus ?? ''));

                $isValidated = !empty($row->validado)
                    || !empty($row->validated_at)
                    || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid', 'activo', 'active'], true)
                    || in_array($estatusOperativo, ['validated', 'validado', 'activo'], true);

                                $externalZipMeta = is_array($meta['external_zip'] ?? null) ? $meta['external_zip'] : [];
                $externalZipPath = trim((string) ($externalZipMeta['path'] ?? ''));
                $externalZipOriginalName = trim((string) ($externalZipMeta['original_name'] ?? ''));
                $externalZipDisk = trim((string) ($externalZipMeta['disk'] ?? ''));

                $fielCerPath = (string) ($row->fiel_cer_path ?? $row->cer_path ?? '');
                $fielKeyPath = (string) ($row->fiel_key_path ?? $row->key_path ?? '');
                $csdCerPath = (string) ($row->csd_cer_path ?? '');
                $csdKeyPath = (string) ($row->csd_key_path ?? '');

                if ($fielKeyPath === '' && $externalZipPath !== '') {
                    $fielKeyPath = $externalZipPath;
                }

                $fielPasswordPlain = $this->resolveStoredPassword(
                    $meta,
                    [
                        'fiel_password_plain',
                        'fiel_password',
                        'fiel_pass',
                        'password_fiel',
                        'contrasena_fiel',
                        'contrasena_fiel_plain',
                    ],
                    $row->fiel_password_enc ?? $row->key_password_enc ?? null
                );

                $csdPasswordPlain = $this->resolveStoredPassword(
                    $meta,
                    [
                        'csd_password_plain',
                        'csd_password',
                        'csd_pass',
                        'password_csd',
                        'contrasena_csd',
                        'contrasena_csd_plain',
                    ],
                    $row->csd_password_enc ?? null
                );

                $isLogicallyDeleted = $this->isLogicallyDeleted($row);

                $hasLegacyFiles = filled($row->cer_path) && filled($row->key_path);

                $hasFiel = (
                    filled($row->fiel_cer_path ?? null)
                    && filled($row->fiel_key_path ?? null)
                ) || $hasLegacyFiles || $externalZipPath !== '';

                $hasCsd = (
                    filled($row->csd_cer_path ?? null)
                    && filled($row->csd_key_path ?? null)
                );

                if (!$hasFiel && $externalZipPath !== '') {
                    $hasFiel = true;
                }

                return (object) [
                    'id' => (string) $row->id,
                    'cuenta_id' => (string) ($row->cuenta_id ?? ''),
                    'account_id' => (string) ($row->account_id ?? ''),
                    'rfc' => strtoupper((string) $row->rfc),
                    'razon_social' => (string) ($row->razon_social ?? ''),
                    'tipo_origen_ui' => $tipoOrigen === 'externo' ? 'externo' : ($tipoOrigen === 'admin' ? 'admin' : 'interno'),
                    'origen_detalle' => (string) ($row->origen_detalle ?? ''),
                    'source_label' => (string) ($row->source_label ?? ''),
                    'sat_status_ui' => $isValidated ? 'validado' : 'pendiente',
                    'estatus_operativo' => (string) ($row->estatus_operativo ?? ''),
                    'is_active_ui' => !$isLogicallyDeleted,
                    'has_fiel' => $hasFiel,
                    'has_csd' => $hasCsd,
                    'fiel_password_plain' => $fielPasswordPlain,
                    'csd_password_plain' => $csdPasswordPlain,
                    'fiel_cer_path' => $fielCerPath,
                    'fiel_key_path' => $fielKeyPath,
                    'csd_cer_path' => $csdCerPath,
                    'csd_key_path' => $csdKeyPath,
                    'external_zip_path' => $externalZipPath,
                    'external_zip_original_name' => $externalZipOriginalName,
                    'external_zip_disk' => $externalZipDisk,
                    'meta' => $meta,
                    'updated_at' => $row->updated_at,
                    'created_at' => $row->created_at,
                ];
            });

        if ($q !== '') {
            $needle = mb_strtolower($q);

            $rows = $rows->filter(function ($row) use ($needle) {
                return str_contains(mb_strtolower($row->rfc), $needle)
                    || str_contains(mb_strtolower($row->razon_social), $needle)
                    || str_contains(mb_strtolower($row->cuenta_id), $needle)
                    || str_contains(mb_strtolower($row->account_id), $needle)
                    || str_contains(mb_strtolower($row->fiel_password_plain), $needle)
                    || str_contains(mb_strtolower($row->csd_password_plain), $needle)
                    || str_contains(mb_strtolower($row->fiel_cer_path), $needle)
                    || str_contains(mb_strtolower($row->fiel_key_path), $needle)
                    || str_contains(mb_strtolower($row->csd_cer_path), $needle)
                    || str_contains(mb_strtolower($row->csd_key_path), $needle);
            });
        }

        if ($origin !== '') {
            $rows = $rows->filter(fn ($row) => $row->tipo_origen_ui === $origin);
        }

        if ($status !== '') {
            $rows = $rows->filter(fn ($row) => $row->sat_status_ui === $status);
        }

        if ($account !== '') {
            $rows = $rows->filter(function ($row) use ($account) {
                return $row->cuenta_id === $account || $row->account_id === $account;
            });
        }

        $rows = $rows->values();

        $stats = [
            'total' => $rows->count(),
            'activos' => $rows->where('is_active_ui', true)->count(),
            'inactivos' => $rows->where('is_active_ui', false)->count(),
            'internos' => $rows->where('tipo_origen_ui', 'interno')->count(),
            'externos' => $rows->where('tipo_origen_ui', 'externo')->count(),
            'validados' => $rows->where('sat_status_ui', 'validado')->count(),
            'pendientes' => $rows->where('sat_status_ui', 'pendiente')->count(),
            'con_fiel' => $rows->where('has_fiel', true)->count(),
            'con_csd' => $rows->where('has_csd', true)->count(),
        ];

        return view('admin.sat.ops.rfcs.index', [
            'rows' => $rows,
            'stats' => $stats,
            'q' => $q,
            'origin' => $origin,
            'status' => $status,
            'account' => $account,
        ]);
    }

    public function store(\Illuminate\Http\Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cuenta_id' => ['required', 'string', 'max:64'],
            'account_id' => ['nullable', 'string', 'max:64'],
            'rfc' => ['required', 'string', 'min:12', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'tipo_origen' => ['required', 'string', 'in:interno,externo,admin'],
            'origen_detalle' => ['nullable', 'string', 'max:40'],
            'source_label' => ['nullable', 'string', 'max:120'],
            'fiel_password_plain' => ['nullable', 'string', 'max:255'],
            'csd_password_plain' => ['nullable', 'string', 'max:255'],

            'fiel_cer' => ['nullable', 'file', 'mimes:cer'],
            'fiel_key' => ['nullable', 'file', 'mimes:key'],
            'csd_cer' => ['nullable', 'file', 'mimes:cer'],
            'csd_key' => ['nullable', 'file', 'mimes:key'],
        ]);

        $rfc = strtoupper(trim((string) $validated['rfc']));
        $cuentaId = trim((string) $validated['cuenta_id']);

        $exists = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('UPPER(rfc) = ?', [$rfc])
            ->first();

        if ($exists && !$this->isLogicallyDeleted($exists)) {
            return back()->withInput()->with('error', 'Ya existe un RFC activo para esa cuenta.');
        }

        $row = $exists ?: new SatCredential();
        $row->setConnection('mysql_clientes');

        $row->cuenta_id = $cuentaId;

        if ($this->hasColumn('sat_credentials', 'account_id')) {
            $row->account_id = trim((string) ($validated['account_id'] ?? '')) ?: $cuentaId;
        }

        $row->rfc = $rfc;
        $row->razon_social = trim((string) ($validated['razon_social'] ?? '')) ?: null;

        if ($this->hasColumn('sat_credentials', 'tipo_origen')) {
            $row->tipo_origen = trim((string) $validated['tipo_origen']);
        }

        if ($this->hasColumn('sat_credentials', 'origen_detalle')) {
            $row->origen_detalle = trim((string) ($validated['origen_detalle'] ?? '')) ?: 'admin_manual';
        }

        if ($this->hasColumn('sat_credentials', 'source_label')) {
            $row->source_label = trim((string) ($validated['source_label'] ?? '')) ?: 'Registro admin';
        }

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $row->estatus_operativo = 'pending';
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        $meta['is_active'] = true;
        $meta['updated_from'] = 'admin_sat_rfc_master';
        $meta['fiel_password_plain'] = trim((string) ($validated['fiel_password_plain'] ?? ''));
        $meta['csd_password_plain'] = trim((string) ($validated['csd_password_plain'] ?? ''));

        if ($request->hasFile('fiel_cer')) {
            $stored = $this->storeCredentialFile($request->file('fiel_cer'), $cuentaId, $rfc, 'certs', 'cer');
            $row->fiel_cer_path = $stored;
            if ($this->hasColumn('sat_credentials', 'cer_path')) {
                $row->cer_path = $stored;
            }
            $meta['fiel_cer'] = $stored;
        }

        if ($request->hasFile('fiel_key')) {
            $stored = $this->storeCredentialFile($request->file('fiel_key'), $cuentaId, $rfc, 'keys', 'key');
            $row->fiel_key_path = $stored;
            if ($this->hasColumn('sat_credentials', 'key_path')) {
                $row->key_path = $stored;
            }
            $meta['fiel_key'] = $stored;
        }

        if ($request->hasFile('csd_cer')) {
            $stored = $this->storeCredentialFile($request->file('csd_cer'), $cuentaId, $rfc, 'csd/certs', 'cer');
            $row->csd_cer_path = $stored;
            $meta['csd_cer'] = $stored;
        }

        if ($request->hasFile('csd_key')) {
            $stored = $this->storeCredentialFile($request->file('csd_key'), $cuentaId, $rfc, 'csd/keys', 'key');
            $row->csd_key_path = $stored;
            $meta['csd_key'] = $stored;
        }

        if ($this->hasColumn('sat_credentials', 'fiel_password_enc') && trim((string) ($validated['fiel_password_plain'] ?? '')) !== '') {
            $row->fiel_password_enc = Crypt::encryptString(trim((string) $validated['fiel_password_plain']));
        }

        if ($this->hasColumn('sat_credentials', 'csd_password_enc') && trim((string) ($validated['csd_password_plain'] ?? '')) !== '') {
            $row->csd_password_enc = Crypt::encryptString(trim((string) $validated['csd_password_plain']));
        }

        $meta['operational_ready'] = true;
        $row->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $row->deleted_at = null;
        }

        $row->save();

        return redirect()->route('admin.sat.ops.rfcs.index')->with('ok', 'RFC creado correctamente desde admin.');
    }

    public function update(\Illuminate\Http\Request $request, string $id): RedirectResponse
    {
        /** @var SatCredential|null $row */
        $row = SatCredential::query()->where('id', $id)->first();

        if (!$row || $this->isLogicallyDeleted($row)) {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'No se encontró el RFC a editar.');
        }

        $validated = $request->validate([
            'cuenta_id' => ['required', 'string', 'max:64'],
            'account_id' => ['nullable', 'string', 'max:64'],
            'rfc' => ['required', 'string', 'min:12', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'tipo_origen' => ['required', 'string', 'in:interno,externo,admin'],
            'origen_detalle' => ['nullable', 'string', 'max:40'],
            'source_label' => ['nullable', 'string', 'max:120'],
            'fiel_password_plain' => ['nullable', 'string', 'max:255'],
            'csd_password_plain' => ['nullable', 'string', 'max:255'],

            'fiel_cer' => ['nullable', 'file', 'mimes:cer'],
            'fiel_key' => ['nullable', 'file', 'mimes:key'],
            'csd_cer' => ['nullable', 'file', 'mimes:cer'],
            'csd_key' => ['nullable', 'file', 'mimes:key'],
        ]);

        $rfc = strtoupper(trim((string) $validated['rfc']));
        $cuentaId = trim((string) $validated['cuenta_id']);

        $exists = SatCredential::query()
            ->where('id', '!=', $row->id)
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('UPPER(rfc) = ?', [$rfc])
            ->first();

        if ($exists && !$this->isLogicallyDeleted($exists)) {
            return back()->withInput()->with('error', 'Ya existe otro RFC activo con ese valor en esa cuenta.');
        }

        $row->cuenta_id = $cuentaId;

        if ($this->hasColumn('sat_credentials', 'account_id')) {
            $row->account_id = trim((string) ($validated['account_id'] ?? '')) ?: $cuentaId;
        }

        $row->rfc = $rfc;
        $row->razon_social = trim((string) ($validated['razon_social'] ?? '')) ?: null;

        if ($this->hasColumn('sat_credentials', 'tipo_origen')) {
            $row->tipo_origen = trim((string) $validated['tipo_origen']);
        }

        if ($this->hasColumn('sat_credentials', 'origen_detalle')) {
            $row->origen_detalle = trim((string) ($validated['origen_detalle'] ?? '')) ?: 'admin_manual';
        }

        if ($this->hasColumn('sat_credentials', 'source_label')) {
            $row->source_label = trim((string) ($validated['source_label'] ?? '')) ?: 'Registro admin';
        }

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $row->estatus_operativo = 'pending';
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        $meta['is_active'] = true;
        $meta['updated_from'] = 'admin_sat_rfc_master';

        $existingFielPassword = $this->resolveStoredPassword(
            $meta,
            [
                'fiel_password_plain',
                'fiel_password',
                'fiel_pass',
                'password_fiel',
                'contrasena_fiel',
                'contrasena_fiel_plain',
            ],
            $row->fiel_password_enc ?? null
        );

        $existingCsdPassword = $this->resolveStoredPassword(
            $meta,
            [
                'csd_password_plain',
                'csd_password',
                'csd_pass',
                'password_csd',
                'contrasena_csd',
                'contrasena_csd_plain',
            ],
            $row->csd_password_enc ?? null
        );

        $meta['fiel_password_plain'] = trim((string) ($validated['fiel_password_plain'] ?? $existingFielPassword));
        $meta['csd_password_plain'] = trim((string) ($validated['csd_password_plain'] ?? $existingCsdPassword));

        if ($request->hasFile('fiel_cer')) {
            $stored = $this->storeCredentialFile($request->file('fiel_cer'), $cuentaId, $rfc, 'certs', 'cer');
            $row->fiel_cer_path = $stored;
            if ($this->hasColumn('sat_credentials', 'cer_path')) {
                $row->cer_path = $stored;
            }
            $meta['fiel_cer'] = $stored;
        }

        if ($request->hasFile('fiel_key')) {
            $stored = $this->storeCredentialFile($request->file('fiel_key'), $cuentaId, $rfc, 'keys', 'key');
            $row->fiel_key_path = $stored;
            if ($this->hasColumn('sat_credentials', 'key_path')) {
                $row->key_path = $stored;
            }
            $meta['fiel_key'] = $stored;
        }

        if ($request->hasFile('csd_cer')) {
            $stored = $this->storeCredentialFile($request->file('csd_cer'), $cuentaId, $rfc, 'csd/certs', 'cer');
            $row->csd_cer_path = $stored;
            $meta['csd_cer'] = $stored;
        }

        if ($request->hasFile('csd_key')) {
            $stored = $this->storeCredentialFile($request->file('csd_key'), $cuentaId, $rfc, 'csd/keys', 'key');
            $row->csd_key_path = $stored;
            $meta['csd_key'] = $stored;
        }

        if ($this->hasColumn('sat_credentials', 'fiel_password_enc') && $meta['fiel_password_plain'] !== '') {
            $row->fiel_password_enc = Crypt::encryptString($meta['fiel_password_plain']);
        }

        if ($this->hasColumn('sat_credentials', 'csd_password_enc') && $meta['csd_password_plain'] !== '') {
            $row->csd_password_enc = Crypt::encryptString($meta['csd_password_plain']);
        }

        $meta['operational_ready'] = true;
        $row->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $row->deleted_at = null;
        }

        $row->save();

        return redirect()->route('admin.sat.ops.rfcs.index')->with('ok', 'RFC actualizado correctamente desde admin.');
    }

    public function destroy(\Illuminate\Http\Request $request, string $id): RedirectResponse
    {
        /** @var SatCredential|null $row */
        $row = SatCredential::query()->where('id', $id)->first();

        if (!$row) {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'No se encontró el RFC para baja.');
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        $meta['is_active'] = false;
        $meta['deleted_from'] = 'admin_sat_rfc_master';
        $meta['deleted_at'] = now()->toDateTimeString();
        $row->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $row->estatus_operativo = 'inactive';
        }

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $row->deleted_at = now();
        }

        $row->save();

        return redirect()->route('admin.sat.ops.rfcs.index')->with('ok', 'RFC dado de baja correctamente.');
    }

    public function show(string $id): JsonResponse
    {
        /** @var SatCredential|null $row */
        $row = SatCredential::query()->where('id', $id)->first();

        if (!$row || $this->isLogicallyDeleted($row)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró el RFC solicitado.',
            ], 404);
        }

        $meta = is_array($row->meta) ? $row->meta : [];

        $tipoOrigen = strtolower((string) (
            $row->tipo_origen
            ?? ($meta['tipo_origen'] ?? '')
        ));

        if ($tipoOrigen === '') {
            $tipoOrigen = 'interno';
        }

        $estatusOperativo = strtolower((string) (
            $row->estatus_operativo
            ?? ($meta['estatus_operativo'] ?? '')
        ));

        $estatusRaw = strtolower((string) ($row->estatus ?? ''));

        $isValidated = !empty($row->validado)
            || !empty($row->validated_at)
            || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid', 'activo', 'active'], true)
            || in_array($estatusOperativo, ['validated', 'validado', 'activo'], true);

               $externalZipMeta = is_array($meta['external_zip'] ?? null) ? $meta['external_zip'] : [];
        $externalZipPath = trim((string) ($externalZipMeta['path'] ?? ''));
        $externalZipOriginalName = trim((string) ($externalZipMeta['original_name'] ?? ''));
        $externalZipDisk = trim((string) ($externalZipMeta['disk'] ?? ''));

        $fielCerPath = (string) ($row->fiel_cer_path ?? $row->cer_path ?? '');
        $fielKeyPath = (string) ($row->fiel_key_path ?? $row->key_path ?? '');

        if ($fielKeyPath === '' && $externalZipPath !== '') {
            $fielKeyPath = $externalZipPath;
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => (string) $row->id,
                'cuenta_id' => (string) ($row->cuenta_id ?? ''),
                'account_id' => (string) ($row->account_id ?? ''),
                'rfc' => strtoupper((string) $row->rfc),
                'razon_social' => (string) ($row->razon_social ?? ''),
                'tipo_origen' => $tipoOrigen,
                'origen_detalle' => (string) ($row->origen_detalle ?? ''),
                'source_label' => (string) ($row->source_label ?? ''),
                'sat_status_ui' => $isValidated ? 'validado' : 'pendiente',
                'estatus_operativo' => (string) ($row->estatus_operativo ?? ''),
                'has_fiel' => (
                    (
                        filled($row->fiel_cer_path ?? null)
                        && filled($row->fiel_key_path ?? null)
                    ) || (
                        filled($row->cer_path ?? null)
                        && filled($row->key_path ?? null)
                    ) || $externalZipPath !== ''
                ),
                'has_csd' => (
                    filled($row->csd_cer_path ?? null)
                    && filled($row->csd_key_path ?? null)
                ),
                'files' => [
                    'fiel_cer_path' => $fielCerPath,
                    'fiel_key_path' => $fielKeyPath,
                    'csd_cer_path' => (string) ($row->csd_cer_path ?? ''),
                    'csd_key_path' => (string) ($row->csd_key_path ?? ''),
                    'external_zip_path' => $externalZipPath,
                    'external_zip_original_name' => $externalZipOriginalName,
                    'external_zip_disk' => $externalZipDisk,
                ],
                'passwords' => [
                    'fiel_password' => $this->resolveStoredPassword(
                        $meta,
                        [
                            'fiel_password_plain',
                            'fiel_password',
                            'fiel_pass',
                            'password_fiel',
                            'contrasena_fiel',
                            'contrasena_fiel_plain',
                        ],
                        $row->fiel_password_enc ?? $row->key_password_enc ?? null
                    ),
                    'csd_password' => $this->resolveStoredPassword(
                        $meta,
                        [
                            'csd_password_plain',
                            'csd_password',
                            'csd_pass',
                            'password_csd',
                            'contrasena_csd',
                            'contrasena_csd_plain',
                        ],
                        $row->csd_password_enc ?? null
                    ),
                ],
                'meta' => $meta,
                'created_at' => optional($row->created_at)?->toDateTimeString(),
                'updated_at' => optional($row->updated_at)?->toDateTimeString(),
            ],
        ]);
    }

    public function operationalData(string $id): JsonResponse
    {
        /** @var SatCredential|null $row */
        $row = SatCredential::query()->where('id', $id)->first();

        if (!$row || $this->isLogicallyDeleted($row)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró el RFC solicitado.',
            ], 404);
        }

        $meta = is_array($row->meta) ? $row->meta : [];

                $externalZipMeta = is_array($meta['external_zip'] ?? null) ? $meta['external_zip'] : [];
        $externalZipPath = trim((string) ($externalZipMeta['path'] ?? ''));
        $externalZipOriginalName = trim((string) ($externalZipMeta['original_name'] ?? ''));
        $externalZipDisk = trim((string) ($externalZipMeta['disk'] ?? ''));

        $fielCerPath = (string) ($row->fiel_cer_path ?? $row->cer_path ?? '');
        $fielKeyPath = (string) ($row->fiel_key_path ?? $row->key_path ?? '');

        if ($fielKeyPath === '' && $externalZipPath !== '') {
            $fielKeyPath = $externalZipPath;
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'credential_id' => (string) $row->id,
                'cuenta_id' => (string) ($row->cuenta_id ?? ''),
                'account_id' => (string) ($row->account_id ?? ''),
                'rfc' => strtoupper((string) $row->rfc),
                'razon_social' => (string) ($row->razon_social ?? ''),
                'fiel' => [
                    'cer_path' => $fielCerPath,
                    'key_path' => $fielKeyPath,
                    'external_zip_path' => $externalZipPath,
                    'external_zip_original_name' => $externalZipOriginalName,
                    'external_zip_disk' => $externalZipDisk,
                    'password' => $this->resolveStoredPassword(
                        $meta,
                        [
                            'fiel_password_plain',
                            'fiel_password',
                            'fiel_pass',
                            'password_fiel',
                            'contrasena_fiel',
                            'contrasena_fiel_plain',
                        ],
                        $row->fiel_password_enc ?? $row->key_password_enc ?? null
                    ),
                  
                    'download_cer_url' => route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'fiel_cer']),
                    'download_key_url' => route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'fiel_key']),
                ],
                'csd' => [
                    'cer_path' => (string) ($row->csd_cer_path ?? ''),
                    'key_path' => (string) ($row->csd_key_path ?? ''),
                    'password' => $this->resolveStoredPassword(
                        $meta,
                        [
                            'csd_password_plain',
                            'csd_password',
                            'csd_pass',
                            'password_csd',
                            'contrasena_csd',
                            'contrasena_csd_plain',
                        ],
                        $row->csd_password_enc ?? null
                    ),
                    'download_cer_url' => route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'csd_cer']),
                    'download_key_url' => route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'csd_key']),
                ],
                'sat_ready_payload' => [
                    'rfc' => strtoupper((string) $row->rfc),
                    'razon_social' => (string) ($row->razon_social ?? ''),
                    'cuenta_id' => (string) ($row->cuenta_id ?? ''),
                    'account_id' => (string) ($row->account_id ?? ''),
                    'source_label' => (string) ($row->source_label ?? ''),
                    'tipo_origen' => (string) ($row->tipo_origen ?? ''),
                ],
            ],
        ]);
    }

    public function download(string $id, string $kind): BinaryFileResponse|RedirectResponse
    {
        /** @var SatCredential|null $row */
        $row = SatCredential::query()->where('id', $id)->first();

        if (!$row || $this->isLogicallyDeleted($row)) {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'No se encontró el RFC solicitado.');
        }

        $meta = is_array($row->meta) ? $row->meta : [];

        $externalZipMeta = is_array($meta['external_zip'] ?? null) ? $meta['external_zip'] : [];
        $externalZipPath = trim((string) ($externalZipMeta['path'] ?? ''));

        $rawPath = match ($kind) {
            'fiel_cer' => (string) ($row->fiel_cer_path ?? $row->cer_path ?? ($meta['fiel_cer'] ?? '') ?: $externalZipPath),
            'fiel_key' => (string) ($row->fiel_key_path ?? $row->key_path ?? ($meta['fiel_key'] ?? '') ?: $externalZipPath),
            'csd_cer'  => (string) ($row->csd_cer_path ?? ($meta['csd_cer'] ?? '')),
            'csd_key'  => (string) ($row->csd_key_path ?? ($meta['csd_key'] ?? '')),
            default    => '',
        };

        if (trim($rawPath) === '') {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'No existe archivo para descargar.');
        }

        $absolutePath = $this->resolveDownloadAbsolutePath($rawPath);

        if ($absolutePath === null || !File::exists($absolutePath)) {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'El archivo no existe físicamente en el servidor.');
        }

        return response()->download($absolutePath);
    }

    private function isLogicallyDeleted(SatCredential $credential): bool
    {
        $meta = is_array($credential->meta) ? $credential->meta : [];
        $metaActive = $meta['is_active'] ?? null;

        if ($metaActive === false || $metaActive === 0 || $metaActive === '0') {
            return true;
        }

        if ($this->hasColumn('sat_credentials', 'deleted_at') && !empty($credential->deleted_at)) {
            return true;
        }

        if ($this->hasColumn('sat_credentials', 'estatus_operativo') && strtolower((string) ($credential->estatus_operativo ?? '')) === 'inactive') {
            return true;
        }

        return false;
    }

    private function resolveStoredPassword(array $meta, array $candidateKeys, mixed $encryptedValue = null): string
    {
        foreach ($candidateKeys as $key) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $decrypted = $this->tryDecryptValue($encryptedValue);

        if ($decrypted !== '') {
            return $decrypted;
        }

        return '';
    }

    private function tryDecryptValue(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        try {
            return trim((string) Crypt::decryptString($raw));
        } catch (\Throwable) {
            return '';
        }
    }

        private function storeCredentialFile(UploadedFile $file, string $cuentaId, string $rfc, string $folder, string $expectedExtension): string
    {
        $safeRfc = strtoupper(trim($rfc));
        $baseDir = 'sat/rfcs/' . trim($cuentaId) . '/' . trim($folder);

        $filename = $safeRfc
            . '_'
            . now()->format('Ymd_His')
            . '_'
            . bin2hex(random_bytes(4))
            . '.'
            . $expectedExtension;

        return $file->storeAs($baseDir, $filename, 'public');
    }

    private function resolveDownloadAbsolutePath(string $rawPath): ?string
    {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            return null;
        }

        if (File::exists($rawPath)) {
            return $rawPath;
        }

        $normalized = str_replace('\\', '/', $rawPath);
        $normalized = preg_replace('#^/+#', '', $normalized) ?? $normalized;

        $candidates = [
            $normalized,
            preg_replace('#^public/#', '', $normalized) ?? $normalized,
            preg_replace('#^storage/#', '', $normalized) ?? $normalized,
            preg_replace('#^storage/app/public/#', '', $normalized) ?? $normalized,
            preg_replace('#^storage/app/#', '', $normalized) ?? $normalized,
        ];

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                return Storage::disk('public')->path($candidate);
            }

            if (Storage::disk('local')->exists($candidate)) {
                return Storage::disk('local')->path($candidate);
            }

            $absoluteCandidates = [
                storage_path('app/public/' . $candidate),
                storage_path('app/' . $candidate),
                public_path('storage/' . $candidate),
                public_path($candidate),
                base_path($candidate),
            ];

            foreach ($absoluteCandidates as $absolute) {
                if (File::exists($absolute)) {
                    return $absolute;
                }
            }
        }

        return null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}