<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Services\Sat\Client\SatClientContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SatRfcController extends Controller
{
    public function __construct(
        private readonly SatClientContext $ctx,
    ) {}

    private function user(): ?object
    {
        return $this->ctx->user();
    }

    private function cuentaId(): string
    {
        return trim((string) $this->ctx->cuentaId());
    }

    private function trace(): string
    {
        return $this->ctx->trace();
    }

    public function index(Request $request)
    {
        $user = $this->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        $cuentaId = $this->cuentaId();
        if ($cuentaId === '') {
            abort(403, 'No se pudo resolver la cuenta del cliente.');
        }

        $search = trim((string) $request->query('q', ''));
        $filterOrigin = trim((string) $request->query('origin', ''));
        $filterStatus = trim((string) $request->query('status', ''));

        $credentials = $this->loadCredentialRows($cuentaId, $search, $filterOrigin, $filterStatus);
        $externalRows = $this->loadExternalRows($cuentaId, $search);

        $stats = [
            'total' => $credentials->count(),
            'internos' => $credentials->where('tipo_origen_ui', 'interno')->count(),
            'externos' => $credentials->where('tipo_origen_ui', 'externo')->count(),
            'validados' => $credentials->where('sat_status_ui', 'validado')->count(),
            'pendientes' => $credentials->where('sat_status_ui', 'pendiente')->count(),
            'con_fiel' => $credentials->where('has_fiel', true)->count(),
            'con_csd' => $credentials->where('has_csd', true)->count(),
            'externos_staging' => $externalRows->count(),
        ];

        return view('cliente.sat.rfcs.index', [
            'pageTitle' => 'RFC SAT',
            'traceId' => $this->trace(),
            'search' => $search,
            'filterOrigin' => $filterOrigin,
            'filterStatus' => $filterStatus,
            'stats' => $stats,
            'credentials' => $credentials,
            'externalRows' => $externalRows,
            'cuentaId' => $cuentaId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        $cuentaId = $this->cuentaId();
        if ($cuentaId === '') {
            abort(403, 'No se pudo resolver la cuenta del cliente.');
        }

        $validated = $request->validate([
            'rfc'             => ['required', 'string', 'min:12', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'razon_social'    => ['nullable', 'string', 'max:190'],
            'tipo_origen'     => ['required', 'string', 'in:interno,externo'],
            'origen_detalle'  => ['nullable', 'string', 'max:40'],
            'source_label'    => ['nullable', 'string', 'max:120'],

            'fiel_cer'        => ['required', 'file', 'max:5120'],
            'fiel_key'        => ['required', 'file', 'max:5120'],
            'fiel_password'   => ['required', 'string', 'max:190'],

            'csd_cer'         => ['nullable', 'file', 'max:5120'],
            'csd_key'         => ['nullable', 'file', 'max:5120'],
            'csd_password'    => ['nullable', 'string', 'max:190'],
        ], [
            'rfc.required'           => 'El RFC es obligatorio.',
            'rfc.regex'              => 'El RFC no tiene un formato válido.',
            'tipo_origen.required'   => 'Debes indicar si el RFC es interno o externo.',
            'tipo_origen.in'         => 'El origen seleccionado no es válido.',
            'fiel_cer.required'      => 'El archivo .cer de la FIEL es obligatorio.',
            'fiel_key.required'      => 'El archivo .key de la FIEL es obligatorio.',
            'fiel_password.required' => 'La contraseña de la FIEL es obligatoria.',
        ]);

        $rfc = strtoupper(trim((string) $validated['rfc']));

        $credential = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->whereRaw('UPPER(rfc) = ?', [$rfc])
            ->first();

        if ($credential && !$this->isLogicallyDeleted($credential)) {
            return back()
                ->withInput()
                ->with('error', 'Ya existe un RFC activo con ese valor en esta cuenta.');
        }

        if (!$credential) {
            $credential = new SatCredential();
            $credential->setConnection('mysql_clientes');
            $credential->cuenta_id = $cuentaId;

            if ($this->hasColumn('sat_credentials', 'account_id')) {
                $credential->account_id = $cuentaId;
            }

            $credential->rfc = $rfc;
        }

        $this->persistCredentialFromRequest($credential, $request, requireFiel: true, allowRfcChange: true);

        return redirect()
            ->route('cliente.sat.rfcs.index')
            ->with('ok', 'RFC registrado correctamente en el módulo maestro SAT.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $user = $this->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        $cuentaId = $this->cuentaId();
        if ($cuentaId === '') {
            abort(403, 'No se pudo resolver la cuenta del cliente.');
        }

        /** @var SatCredential|null $credential */
        $credential = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->where('id', $id)
            ->first();

        if (!$credential || $this->isLogicallyDeleted($credential)) {
            return redirect()
                ->route('cliente.sat.rfcs.index')
                ->with('error', 'No se encontró el RFC solicitado para editar.');
        }

        $validated = $request->validate([
            'rfc'             => ['required', 'string', 'min:12', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'razon_social'    => ['nullable', 'string', 'max:190'],
            'tipo_origen'     => ['required', 'string', 'in:interno,externo'],
            'origen_detalle'  => ['nullable', 'string', 'max:40'],
            'source_label'    => ['nullable', 'string', 'max:120'],

            'fiel_cer'        => ['nullable', 'file', 'max:5120'],
            'fiel_key'        => ['nullable', 'file', 'max:5120'],
            'fiel_password'   => ['nullable', 'string', 'max:190'],

            'csd_cer'         => ['nullable', 'file', 'max:5120'],
            'csd_key'         => ['nullable', 'file', 'max:5120'],
            'csd_password'    => ['nullable', 'string', 'max:190'],
        ], [
            'rfc.required'         => 'El RFC es obligatorio.',
            'rfc.regex'            => 'El RFC no tiene un formato válido.',
            'tipo_origen.required' => 'Debes indicar si el RFC es interno o externo.',
            'tipo_origen.in'       => 'El origen seleccionado no es válido.',
        ]);

        $newRfc = strtoupper(trim((string) $validated['rfc']));
        $oldRfc = strtoupper(trim((string) $credential->rfc));

        if ($newRfc !== $oldRfc) {
            $exists = SatCredential::query()
                ->where(function ($q) use ($cuentaId) {
                    $q->where('cuenta_id', $cuentaId)
                        ->orWhere('account_id', $cuentaId);
                })
                ->where('id', '!=', $credential->id)
                ->whereRaw('UPPER(rfc) = ?', [$newRfc])
                ->first();

            if ($exists && !$this->isLogicallyDeleted($exists)) {
                return back()
                    ->withInput()
                    ->with('error', 'Ya existe otro RFC activo con ese valor en esta cuenta.');
            }
        }

        $this->persistCredentialFromRequest($credential, $request, requireFiel: false, allowRfcChange: true);

        return redirect()
            ->route('cliente.sat.rfcs.index')
            ->with('ok', 'RFC actualizado correctamente.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $user = $this->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        $cuentaId = $this->cuentaId();
        if ($cuentaId === '') {
            abort(403, 'No se pudo resolver la cuenta del cliente.');
        }

        /** @var SatCredential|null $credential */
        $credential = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->where('id', $id)
            ->first();

        if (!$credential) {
            return redirect()
                ->route('cliente.sat.rfcs.index')
                ->with('error', 'No se encontró el RFC solicitado para baja.');
        }

        $meta = is_array($credential->meta) ? $credential->meta : [];
        $meta['is_active'] = false;
        $meta['deleted_from'] = 'sat_rfc_master';
        $meta['deleted_at'] = now()->toDateTimeString();

        $credential->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $credential->estatus_operativo = 'inactive';
        }

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $credential->deleted_at = now();
        }

        $credential->save();

        return redirect()
            ->route('cliente.sat.rfcs.index')
            ->with('ok', 'RFC dado de baja correctamente.');
    }

    public function downloadAsset(Request $request, string $id, string $type)
    {
        $user = $this->user();
        if (!$user) {
            abort(401, 'Sesión expirada.');
        }

        $credential = $this->resolveOwnedCredential($id);
        if (!$credential) {
            abort(404, 'RFC no encontrado.');
        }

        $type = strtolower(trim($type));

        $map = [
            'fiel_cer' => [
                'column' => 'fiel_cer_path',
                'fallback_column' => 'cer_path',
                'download_name' => 'fiel',
                'extension' => 'cer',
            ],
            'fiel_key' => [
                'column' => 'fiel_key_path',
                'fallback_column' => 'key_path',
                'download_name' => 'fiel',
                'extension' => 'key',
            ],
            'csd_cer' => [
                'column' => 'csd_cer_path',
                'fallback_column' => null,
                'download_name' => 'csd',
                'extension' => 'cer',
            ],
            'csd_key' => [
                'column' => 'csd_key_path',
                'fallback_column' => null,
                'download_name' => 'csd',
                'extension' => 'key',
            ],
        ];

        if (!array_key_exists($type, $map)) {
            abort(404, 'Tipo de archivo no válido.');
        }

        $def = $map[$type];

        $storedPath = trim((string) ($credential->{$def['column']} ?? ''));
        if ($storedPath === '' && !empty($def['fallback_column'])) {
            $fallback = (string) $def['fallback_column'];
            $storedPath = trim((string) ($credential->{$fallback} ?? ''));
        }

        if ($storedPath === '') {
            abort(404, 'Archivo no configurado para este RFC.');
        }

        [$disk, $realPath] = $this->resolveStoredPath($storedPath);
        if ($disk === null || $realPath === null) {
            abort(404, 'Archivo no encontrado en almacenamiento.');
        }

        $rfc = strtoupper(trim((string) $credential->rfc));
        $downloadName = $def['download_name'] . '_' . $rfc . '.' . $def['extension'];

        try {
            $stream = Storage::disk($disk)->readStream($realPath);
            if (!$stream) {
                abort(404, 'No se pudo abrir el archivo.');
            }

            return response()->streamDownload(function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, $downloadName, [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT RFC] downloadAsset failed', [
                'trace_id' => $this->trace(),
                'id' => $id,
                'type' => $type,
                'disk' => $disk,
                'path' => $realPath,
                'err' => $e->getMessage(),
            ]);

            abort(500, 'No se pudo descargar el archivo.');
        }
    }

    public function revealPassword(Request $request, string $id, string $scope): JsonResponse
    {
        $user = $this->user();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado.'], 401);
        }

        $credential = $this->resolveOwnedCredential($id);
        if (!$credential) {
            return response()->json(['ok' => false, 'msg' => 'RFC no encontrado.'], 404);
        }

        $scope = strtolower(trim($scope));

        $encrypted = match ($scope) {
            'fiel' => $this->resolveEncryptedPasswordValue($credential, ['fiel_password_enc', 'key_password']),
            'csd'  => $this->resolveEncryptedPasswordValue($credential, ['csd_password_enc']),
            default => null,
        };

        if ($encrypted === null || trim($encrypted) === '') {
            return response()->json([
                'ok' => true,
                'password' => null,
                'msg' => 'No hay contraseña guardada para este bloque.',
            ], 200);
        }

        $plain = $this->decryptStoredValue($encrypted);

        if ($plain === null) {
            Log::warning('[SAT RFC] revealPassword decrypt failed', [
                'trace_id' => $this->trace(),
                'id' => $id,
                'scope' => $scope,
            ]);

            return response()->json([
                'ok' => false,
                'msg' => 'No se pudo descifrar la contraseña.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'password' => $plain,
        ], 200);
    }

    private function persistCredentialFromRequest(
        SatCredential $credential,
        Request $request,
        bool $requireFiel,
        bool $allowRfcChange
    ): void {
        $validated = [
            'rfc' => (string) $request->input('rfc', ''),
            'razon_social' => (string) $request->input('razon_social', ''),
            'tipo_origen' => (string) $request->input('tipo_origen', 'interno'),
            'origen_detalle' => (string) $request->input('origen_detalle', ''),
            'source_label' => (string) $request->input('source_label', ''),
            'fiel_password' => (string) $request->input('fiel_password', ''),
            'csd_password' => (string) $request->input('csd_password', ''),
        ];

        $rfc = strtoupper(trim((string) $validated['rfc']));
        $razonSocial = trim((string) ($validated['razon_social'] ?? ''));
        $tipoOrigen = trim((string) $validated['tipo_origen']);
        $origenDetalle = trim((string) ($validated['origen_detalle'] ?? ''));
        $sourceLabel = trim((string) ($validated['source_label'] ?? ''));

        /** @var UploadedFile|null $fielCer */
        $fielCer = $request->file('fiel_cer');
        /** @var UploadedFile|null $fielKey */
        $fielKey = $request->file('fiel_key');
        $fielPassword = trim((string) ($validated['fiel_password'] ?? ''));

        /** @var UploadedFile|null $csdCer */
        $csdCer = $request->file('csd_cer');
        /** @var UploadedFile|null $csdKey */
        $csdKey = $request->file('csd_key');
        $csdPassword = trim((string) ($validated['csd_password'] ?? ''));

        $currentHasFiel = (
            filled($credential->fiel_cer_path ?? null)
            && filled($credential->fiel_key_path ?? null)
            && filled($credential->fiel_password_enc ?? null)
        ) || (filled($credential->cer_path ?? null) && filled($credential->key_path ?? null));

        if ($requireFiel && (!$fielCer || !$fielKey || $fielPassword === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fiel_cer' => 'La FIEL completa es obligatoria.',
            ]);
        }

        if (($fielCer || $fielKey || $fielPassword !== '') && (!$fielCer || !$fielKey || $fielPassword === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fiel_cer' => 'Si vas a reemplazar FIEL, debes subir .cer, .key y contraseña.',
            ]);
        }

        if (!$requireFiel && !$currentHasFiel && (!$fielCer || !$fielKey || $fielPassword === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fiel_cer' => 'Este RFC no tiene FIEL completa. Debes subirla para continuar.',
            ]);
        }

        if ($fielCer) {
            $this->assertExtension($fielCer, ['cer'], 'El archivo FIEL .cer debe tener extensión .cer.');
        }

        if ($fielKey) {
            $this->assertExtension($fielKey, ['key'], 'El archivo FIEL .key debe tener extensión .key.');
        }

        $hasAnyCsdInput = $csdCer !== null || $csdKey !== null || $csdPassword !== '';
        if ($hasAnyCsdInput) {
            if (!$csdCer || !$csdKey || $csdPassword === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'csd_cer' => 'Si capturas CSD, debes subir .cer, .key y contraseña.',
                ]);
            }

            $this->assertExtension($csdCer, ['cer'], 'El archivo CSD .cer debe tener extensión .cer.');
            $this->assertExtension($csdKey, ['key'], 'El archivo CSD .key debe tener extensión .key.');
        }

        if ($allowRfcChange) {
            $credential->rfc = $rfc;
        }

        $credential->razon_social = $razonSocial !== '' ? $razonSocial : null;

        if ($this->hasColumn('sat_credentials', 'tipo_origen')) {
            $credential->tipo_origen = $tipoOrigen;
        }

        if ($this->hasColumn('sat_credentials', 'origen_detalle')) {
            $credential->origen_detalle = $origenDetalle !== '' ? $origenDetalle : ($tipoOrigen === 'externo' ? 'cliente_externo' : 'cliente_interno');
        }

        if ($this->hasColumn('sat_credentials', 'source_label')) {
            $credential->source_label = $sourceLabel !== '' ? $sourceLabel : ($tipoOrigen === 'externo' ? 'Registro externo' : 'Registro interno');
        }

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $credential->estatus_operativo = 'pending';
        }

        $baseDir = 'sat/rfcs/' . $this->cuentaId() . '/' . $credential->rfc;

        if ($fielCer && $fielKey && $fielPassword !== '') {
            $fielCerPath = $this->storeNamedFile('private', $baseDir . '/fiel', $fielCer, 'fiel', 'cer');
            $fielKeyPath = $this->storeNamedFile('private', $baseDir . '/fiel', $fielKey, 'fiel', 'key');

            if ($this->hasColumn('sat_credentials', 'fiel_cer_path')) {
                $credential->fiel_cer_path = $fielCerPath;
            }

            if ($this->hasColumn('sat_credentials', 'fiel_key_path')) {
                $credential->fiel_key_path = $fielKeyPath;
            }

            if ($this->hasColumn('sat_credentials', 'fiel_password_enc')) {
                $credential->fiel_password_enc = encrypt($fielPassword);
            }

            $credential->cer_path = $fielCerPath;
            $credential->key_path = $fielKeyPath;
            $credential->setEncryptedKeyPassword($fielPassword);
        }

        if ($hasAnyCsdInput && $csdCer && $csdKey) {
            $csdCerPath = $this->storeNamedFile('private', $baseDir . '/csd', $csdCer, 'csd', 'cer');
            $csdKeyPath = $this->storeNamedFile('private', $baseDir . '/csd', $csdKey, 'csd', 'key');

            if ($this->hasColumn('sat_credentials', 'csd_cer_path')) {
                $credential->csd_cer_path = $csdCerPath;
            }

            if ($this->hasColumn('sat_credentials', 'csd_key_path')) {
                $credential->csd_key_path = $csdKeyPath;
            }

            if ($this->hasColumn('sat_credentials', 'csd_password_enc')) {
                $credential->csd_password_enc = encrypt($csdPassword);
            }
        }

        $meta = is_array($credential->meta) ? $credential->meta : [];
        $meta['is_active'] = true;
        $meta['updated_from'] = 'sat_rfc_master';
        $meta['tipo_origen'] = $tipoOrigen;
        $meta['estatus_operativo'] = 'pending';
        $meta['deleted_at'] = null;

        if ($fielCer && $fielKey && $fielPassword !== '') {
            $meta['fiel'] = [
                'cer' => basename((string) ($credential->fiel_cer_path ?? $credential->cer_path ?? '')),
                'key' => basename((string) ($credential->fiel_key_path ?? $credential->key_path ?? '')),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        if ($hasAnyCsdInput) {
            $meta['csd'] = [
                'cer' => basename((string) ($credential->csd_cer_path ?? '')),
                'key' => basename((string) ($credential->csd_key_path ?? '')),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        $credential->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $credential->deleted_at = null;
        }

        $credential->save();
    }

    private function loadCredentialRows(
        string $cuentaId,
        string $search = '',
        string $filterOrigin = '',
        string $filterStatus = ''
    ): Collection {
        $query = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->orderBy('rfc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('rfc', 'like', '%' . $search . '%')
                    ->orWhere('razon_social', 'like', '%' . $search . '%');
            });
        }

        $rows = $query->get()
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

        if ($filterOrigin !== '') {
            $rows = $rows->filter(fn ($row) => $row->tipo_origen_ui === $filterOrigin)->values();
        }

        if ($filterStatus !== '') {
            $rows = $rows->filter(fn ($row) => $row->sat_status_ui === $filterStatus)->values();
        }

        return $rows->values();
    }

    private function loadExternalRows(string $cuentaId, string $search = ''): Collection
    {
        try {
            $query = DB::connection('mysql_clientes')
                ->table('external_fiel_uploads')
                ->where(function ($q) use ($cuentaId) {
                    $q->where('cuenta_id', $cuentaId)
                        ->orWhere('account_id', $cuentaId);
                })
                ->orderByDesc('id');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('rfc', 'like', '%' . $search . '%')
                        ->orWhere('razon_social', 'like', '%' . $search . '%')
                        ->orWhere('email_externo', 'like', '%' . $search . '%');
                });
            }

            return $query->limit(100)->get()->map(function ($row) {
                return (object) [
                    'id' => (int) ($row->id ?? 0),
                    'rfc' => strtoupper((string) ($row->rfc ?? '')),
                    'razon_social' => (string) ($row->razon_social ?? ''),
                    'email_externo' => (string) ($row->email_externo ?? ''),
                    'status' => strtolower((string) ($row->status ?? '')),
                    'file_name' => (string) ($row->file_name ?? ''),
                    'created_at' => $row->created_at ?? null,
                ];
            })->values();
        } catch (\Throwable $e) {
            Log::warning('[SAT RFC] No se pudieron cargar externos staging', [
                'cuenta_id' => $cuentaId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    private function resolveOwnedCredential(string $id): ?SatCredential
    {
        $cuentaId = $this->cuentaId();
        if ($cuentaId === '') {
            return null;
        }

        /** @var SatCredential|null $credential */
        $credential = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->where('id', $id)
            ->first();

        if (!$credential || $this->isLogicallyDeleted($credential)) {
            return null;
        }

        return $credential;
    }

    private function resolveStoredPath(string $storedPath): array
    {
        $storedPath = ltrim(trim($storedPath), '/');

        $candidates = array_values(array_unique(array_filter([
            $storedPath,
            str_starts_with($storedPath, 'private/') ? substr($storedPath, 8) : null,
            str_starts_with($storedPath, 'public/') ? substr($storedPath, 7) : null,
        ])));

        foreach (['private', 'public'] as $disk) {
            foreach ($candidates as $candidate) {
                try {
                    if (Storage::disk($disk)->exists($candidate)) {
                        return [$disk, $candidate];
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        return [null, null];
    }

    private function resolveEncryptedPasswordValue(SatCredential $credential, array $columns): ?string
    {
        foreach ($columns as $column) {
            try {
                $value = $credential->{$column} ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }

    private function decryptStoredValue(string $encrypted): ?string
    {
        $encrypted = trim($encrypted);
        if ($encrypted === '') {
            return null;
        }

        try {
            $value = decrypt($encrypted);
            return is_string($value) ? $value : null;
        } catch (\Throwable) {
            // continue
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            // continue
        }

        $decoded = base64_decode($encrypted, true);
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        return null;
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

    private function assertExtension(UploadedFile $file, array $allowed, string $message): void
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, $allowed, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => $message,
            ]);
        }
    }

    private function storeNamedFile(string $disk, string $dir, UploadedFile $file, string $prefix, string $ext): string
    {
        $name = $prefix . '_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8)) . '.' . $ext;
        return $file->storeAs($dir, $name, $disk);
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