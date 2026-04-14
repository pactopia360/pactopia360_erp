<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SatOpsRfcsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $origin = trim((string) $request->query('origin', ''));
        $status = trim((string) $request->query('status', ''));
        $account = trim((string) $request->query('account', ''));

        $rows = SatCredential::query()
            ->orderByDesc('updated_at')
            ->get()
            ->reject(fn (SatCredential $row) => $this->isLogicallyDeleted($row))
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

                $hasLegacyFiles = filled($row->cer_path) && filled($row->key_path);

                $hasFiel = (
                    filled($row->fiel_cer_path ?? null)
                    && filled($row->fiel_key_path ?? null)
                    && filled($row->fiel_password_enc ?? null)
                ) || $hasLegacyFiles;

                $hasCsd = (
                    filled($row->csd_cer_path ?? null)
                    && filled($row->csd_key_path ?? null)
                );

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
                    'has_fiel' => $hasFiel,
                    'has_csd' => $hasCsd,
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
                    || str_contains(mb_strtolower($row->account_id), $needle);
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

    public function store(Request $request): RedirectResponse
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
        $meta['operational_ready'] = true;
        $row->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $row->deleted_at = null;
        }

        $row->save();

        return redirect()->route('admin.sat.ops.rfcs.index')->with('ok', 'RFC creado correctamente desde admin.');
    }

    public function update(Request $request, string $id): RedirectResponse
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
        $meta['fiel_password_plain'] = trim((string) ($validated['fiel_password_plain'] ?? ($meta['fiel_password_plain'] ?? '')));
        $meta['csd_password_plain'] = trim((string) ($validated['csd_password_plain'] ?? ($meta['csd_password_plain'] ?? '')));
        $meta['operational_ready'] = true;
        $row->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $row->deleted_at = null;
        }

        $row->save();

        return redirect()->route('admin.sat.ops.rfcs.index')->with('ok', 'RFC actualizado correctamente desde admin.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
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
                        && filled($row->fiel_password_enc ?? null)
                    ) || (
                        filled($row->cer_path ?? null)
                        && filled($row->key_path ?? null)
                    )
                ),
                'has_csd' => (
                    filled($row->csd_cer_path ?? null)
                    && filled($row->csd_key_path ?? null)
                ),
                'files' => [
                    'fiel_cer_path' => (string) ($row->fiel_cer_path ?? $row->cer_path ?? ''),
                    'fiel_key_path' => (string) ($row->fiel_key_path ?? $row->key_path ?? ''),
                    'csd_cer_path'  => (string) ($row->csd_cer_path ?? ''),
                    'csd_key_path'  => (string) ($row->csd_key_path ?? ''),
                ],
                'passwords' => [
                    'fiel_password' => (string) ($meta['fiel_password_plain'] ?? ''),
                    'csd_password'  => (string) ($meta['csd_password_plain'] ?? ''),
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

        return response()->json([
            'ok' => true,
            'data' => [
                'credential_id' => (string) $row->id,
                'cuenta_id' => (string) ($row->cuenta_id ?? ''),
                'account_id' => (string) ($row->account_id ?? ''),
                'rfc' => strtoupper((string) $row->rfc),
                'razon_social' => (string) ($row->razon_social ?? ''),
                'fiel' => [
                    'cer_path' => (string) ($row->fiel_cer_path ?? $row->cer_path ?? ''),
                    'key_path' => (string) ($row->fiel_key_path ?? $row->key_path ?? ''),
                    'password' => (string) ($meta['fiel_password_plain'] ?? ''),
                    'download_cer_url' => route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'fiel_cer']),
                    'download_key_url' => route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'fiel_key']),
                ],
                'csd' => [
                    'cer_path' => (string) ($row->csd_cer_path ?? ''),
                    'key_path' => (string) ($row->csd_key_path ?? ''),
                    'password' => (string) ($meta['csd_password_plain'] ?? ''),
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

        $path = match ($kind) {
            'fiel_cer' => (string) ($row->fiel_cer_path ?? $row->cer_path ?? ''),
            'fiel_key' => (string) ($row->fiel_key_path ?? $row->key_path ?? ''),
            'csd_cer'  => (string) ($row->csd_cer_path ?? ''),
            'csd_key'  => (string) ($row->csd_key_path ?? ''),
            default    => '',
        };

        if ($path === '') {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'No existe archivo para descargar.');
        }

        if (!File::exists($path)) {
            return redirect()->route('admin.sat.ops.rfcs.index')->with('error', 'El archivo no existe físicamente en el servidor.');
        }

        return response()->download($path);
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

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}