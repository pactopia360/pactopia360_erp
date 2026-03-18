<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Services\Billing\Facturotopia\FacturotopiaClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EmisoresController extends Controller
{
    private string $cli = 'mysql_clientes';
    private string $table = 'emisores';
    private string $cuentasTable = 'cuentas_cliente';

    public function index(Request $request): View
    {
        $this->abortIfTableMissing();

        $this->autoSyncFromFacturotopia();

        $q = trim((string) $request->get('q', ''));

        $qb = DB::connection($this->cli)
            ->table($this->table)
            ->whereNull('deleted_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('rfc', 'like', "%{$q}%")
                    ->orWhere('razon_social', 'like', "%{$q}%")
                    ->orWhere('nombre_comercial', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('regimen_fiscal', 'like', "%{$q}%")
                    ->orWhere('grupo', 'like', "%{$q}%")
                    ->orWhere('status', 'like', "%{$q}%")
                    ->orWhere('cuenta_id', 'like', "%{$q}%")
                    ->orWhere('ext_id', 'like', "%{$q}%");
            });
        }

        $rows = $qb->paginate(20)->withQueryString();

        $cuentas = $this->loadCuentasMap();

        $rows->getCollection()->transform(function ($row) use ($cuentas) {
            $row->cuenta_label = $cuentas[(string) ($row->cuenta_id ?? '')] ?? null;
            $row->direccion_decoded = $this->decodeJsonColumn($row->direccion ?? null);
            $row->certificados_decoded = $this->decodeJsonColumn($row->certificados ?? null);
            $row->series_decoded = $this->decodeJsonColumn($row->series ?? null);
            return $row;
        });

        return view('admin.billing.invoicing.emisores.index', [
            'rows' => $rows,
            'q'    => $q,
        ]);
    }

    public function create(): View
    {
        $this->abortIfTableMissing();

        return view('admin.billing.invoicing.emisores.create', [
            'cuentas' => $this->loadCuentasOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortIfTableMissing();

        $data = $this->validateData($request);

        /** @var FacturotopiaClient $facturotopia */
        $facturotopia = app(FacturotopiaClient::class);

        $payload = $this->buildPayload($data, true);

        if ($facturotopia->isApiReady()) {
            $remoteId = trim((string) ($payload['ext_id'] ?? ''));
            if ($remoteId === '') {
                $remoteId = $this->resolveRemoteEmisorId($payload);
                if (in_array('ext_id', $this->columns(), true)) {
                    $payload['ext_id'] = $remoteId;
                }
            }

            $remotePayload = $this->buildFacturotopiaEmisorPayload($payload, $remoteId, true);

            $remote = $facturotopia->createEmisor($remotePayload);

            if (!(bool) ($remote['ok'] ?? false) && $this->facturotopiaLooksLikeAlreadyExists($remote)) {
                $remote = $facturotopia->updateEmisor($remoteId, $this->buildFacturotopiaEmisorPayload($payload, $remoteId, false));
            }

            if (!(bool) ($remote['ok'] ?? false)) {
                return back()->withErrors([
                    'facturotopia' => 'No se pudo registrar el emisor en Facturotopia: ' . (string) ($remote['message'] ?? 'Error desconocido.'),
                ])->withInput();
            }

            $remoteData = (array) ($remote['data'] ?? []);
            $remoteIdResolved = trim((string) ($remoteData['id'] ?? $remoteId));

            if ($remoteIdResolved !== '' && in_array('ext_id', $this->columns(), true)) {
                $payload['ext_id'] = $remoteIdResolved;
            }
        }

        $newId = (int) DB::connection($this->cli)
            ->table($this->table)
            ->insertGetId($payload);

        $fresh = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $newId)
            ->first();

        if ($fresh && $facturotopia->isApiReady()) {
            $sync = $this->syncSingleEmisorStatusOnly($fresh);
            if (!($sync['ok'] ?? false)) {
                return redirect()
                    ->route('admin.billing.invoicing.emisores.index')
                    ->withErrors([
                        'facturotopia' => 'Emisor creado y sincronizado, pero falló el estado remoto: ' . (string) ($sync['message'] ?? 'Error desconocido.'),
                    ])
                    ->with('ok', 'Emisor creado correctamente.');
            }
        }

        return redirect()
            ->route('admin.billing.invoicing.emisores.index')
            ->with('ok', $facturotopia->isApiReady()
                ? 'Emisor creado y registrado en Facturotopia correctamente.'
                : 'Emisor creado correctamente. Facturotopia no estaba configurado, así que solo se guardó en local.');
    }

    public function edit(int $id): View
    {
        $this->abortIfTableMissing();

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($row, 404, 'Emisor no encontrado.');

        return view('admin.billing.invoicing.emisores.edit', [
            'row'     => $row,
            'cuentas' => $this->loadCuentasOptions(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->abortIfTableMissing();

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($row, 404, 'Emisor no encontrado.');

        $data = $this->validateData($request, $id);

        /** @var FacturotopiaClient $facturotopia */
        $facturotopia = app(FacturotopiaClient::class);

        $payload = $this->buildPayload($data, false);

        $existingExtId = trim((string) ($row->ext_id ?? ''));
        $incomingExtId = trim((string) ($payload['ext_id'] ?? ''));
        $remoteId = $incomingExtId !== '' ? $incomingExtId : $existingExtId;

        if ($remoteId === '') {
            $remoteId = $this->resolveRemoteEmisorId(array_merge((array) $row, $payload));
            if (in_array('ext_id', $this->columns(), true)) {
                $payload['ext_id'] = $remoteId;
            }
        }

        if ($facturotopia->isApiReady()) {
            $mergedForRemote = array_merge((array) $row, $payload);
            $remotePayload = $this->buildFacturotopiaEmisorPayload($mergedForRemote, $remoteId, false);

            $remote = $facturotopia->updateEmisor($remoteId, $remotePayload);

            if (!(bool) ($remote['ok'] ?? false) && $this->facturotopiaLooksLikeNotFound($remote)) {
                $remote = $facturotopia->createEmisor($this->buildFacturotopiaEmisorPayload($mergedForRemote, $remoteId, true));
            }

            if (!(bool) ($remote['ok'] ?? false)) {
                return back()->withErrors([
                    'facturotopia' => 'No se pudo actualizar el emisor en Facturotopia: ' . (string) ($remote['message'] ?? 'Error desconocido.'),
                ])->withInput();
            }

            $remoteData = (array) ($remote['data'] ?? []);
            $remoteIdResolved = trim((string) ($remoteData['id'] ?? $remoteId));
            if ($remoteIdResolved !== '' && in_array('ext_id', $this->columns(), true)) {
                $payload['ext_id'] = $remoteIdResolved;
                $remoteId = $remoteIdResolved;
            }
        }

        DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->update($payload);

        $fresh = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->first();

        if ($fresh && $facturotopia->isApiReady()) {
            $sync = $this->syncSingleEmisorStatusOnly($fresh);
            if (!($sync['ok'] ?? false)) {
                return redirect()
                    ->route('admin.billing.invoicing.emisores.index')
                    ->withErrors([
                        'facturotopia' => 'Emisor actualizado, pero falló el estado remoto: ' . (string) ($sync['message'] ?? 'Error desconocido.'),
                    ])
                    ->with('ok', 'Emisor actualizado correctamente.');
            }
        }

        return redirect()
            ->route('admin.billing.invoicing.emisores.index')
            ->with('ok', $facturotopia->isApiReady()
                ? 'Emisor actualizado y sincronizado con Facturotopia correctamente.'
                : 'Emisor actualizado correctamente. Facturotopia no estaba configurado, así que solo se actualizó en local.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->abortIfTableMissing();

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($row, 404, 'Emisor no encontrado.');

        $cols = $this->columns();

        if (in_array('deleted_at', $cols, true)) {
            $update = [
                'deleted_at' => now(),
            ];

            if (in_array('updated_at', $cols, true)) {
                $update['updated_at'] = now();
            }

            DB::connection($this->cli)
                ->table($this->table)
                ->where('id', $id)
                ->update($update);
        } else {
            DB::connection($this->cli)
                ->table($this->table)
                ->where('id', $id)
                ->delete();
        }

        return redirect()
            ->route('admin.billing.invoicing.emisores.index')
            ->with('ok', 'Emisor eliminado correctamente en local. Facturotopia no expone aquí un endpoint de eliminación, así que solo quedó baja local.');
    }

    public function syncFacturotopia(Request $request): RedirectResponse
    {
        $this->abortIfTableMissing();

        $sync = $this->runAutomaticSync(true);

        if (!($sync['ok'] ?? false)) {
            return redirect()
                ->route('admin.billing.invoicing.emisores.index')
                ->withErrors([
                    'facturotopia' => (string) ($sync['message'] ?? 'No se pudo sincronizar con Facturotopía.'),
                ]);
        }

        return redirect()
            ->route('admin.billing.invoicing.emisores.index')
            ->with('ok', (string) ($sync['message'] ?? 'Sincronización completada correctamente.'));
    }

        public function runAutomaticSync(bool $force = false): array
    {
        /** @var FacturotopiaClient $facturotopia */
        $facturotopia = app(FacturotopiaClient::class);

        if (!$facturotopia->isApiReady()) {
            return [
                'ok' => false,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        $cooldownSeconds = 300;
        $stampKey = 'p360:facturotopia:emisores:last_sync_at';
        $lockKey  = 'p360:facturotopia:emisores:sync_lock';

        try {
            $lastSyncAt = (int) Cache::get($stampKey, 0);

            if (!$force && $lastSyncAt > 0 && (time() - $lastSyncAt) < $cooldownSeconds) {
                return [
                    'ok' => true,
                    'message' => 'Emisores ya sincronizados recientemente.',
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                ];
            }

            $lock = Cache::lock($lockKey, 120);

            if (!$lock->get()) {
                return [
                    'ok' => true,
                    'message' => 'Ya hay una sincronización automática de emisores en proceso.',
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                ];
            }

            try {
                $created = 0;
                $updated = 0;
                $skipped = 0;
                $page    = 1;
                $limit   = 250;
                $guard   = 0;

                do {
                    $guard++;

                    $response = $facturotopia->listEmisores($limit, (string) $page);

                    if (!($response['ok'] ?? false)) {
                        return [
                            'ok' => false,
                            'message' => 'No se pudo consultar el listado de emisores en Facturotopía: ' . (string) ($response['message'] ?? 'Error desconocido.'),
                            'created' => $created,
                            'updated' => $updated,
                            'skipped' => $skipped,
                        ];
                    }

                    $items = (array) ($response['data'] ?? []);
                    if (empty($items)) {
                        break;
                    }

                    foreach ($items as $item) {
                        try {
                            $remote = is_array($item) ? $item : [];
                            $remoteId = trim((string) ($remote['id'] ?? ''));

                            if ($remoteId !== '') {
                                $detail = $facturotopia->getEmisor($remoteId);
                                if (($detail['ok'] ?? false) && is_array($detail['data'] ?? null)) {
                                    $remote = array_merge($remote, (array) $detail['data']);
                                }
                            }

                            $result = $this->upsertEmisorFromFacturotopia($remote);

                            if ($result === 'created') {
                                $created++;
                            } elseif ($result === 'updated') {
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        } catch (Throwable $e) {
                            $skipped++;

                            Log::warning('[FACTUROTOPIA][EMISORES] autoSync row failed', [
                                'error' => $e->getMessage(),
                                'row'   => $item,
                            ]);
                        }
                    }

                    $page++;
                    if (count($items) < $limit) {
                        break;
                    }
                } while ($guard < 100);

                Cache::put($stampKey, time(), now()->addMinutes(15));

                return [
                    'ok' => true,
                    'message' => "Emisores sincronizados automáticamente. creados={$created}, actualizados={$updated}, omitidos={$skipped}.",
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                ];
            } finally {
                optional($lock)->release();
            }
        } catch (Throwable $e) {
            Log::error('[FACTUROTOPIA][EMISORES] autoSync failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Falló la sincronización automática de emisores: ' . $e->getMessage(),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }
    }

    private function autoSyncFromFacturotopia(): void
    {
        try {
            $this->runAutomaticSync(false);
        } catch (Throwable $e) {
            Log::warning('[FACTUROTOPIA][EMISORES] autoSync index warning', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function upsertEmisorFromFacturotopia(array $remote): string
    {
        $cols = $this->columns();

        $remoteId = trim((string) ($remote['id'] ?? ''));
        $rfc      = strtoupper(trim((string) ($remote['rfc'] ?? '')));

        if ($remoteId === '' && $rfc === '') {
            return 'skipped';
        }

        $existing = null;

        if ($remoteId !== '' && in_array('ext_id', $cols, true)) {
            $existing = DB::connection($this->cli)
                ->table($this->table)
                ->where('ext_id', $remoteId)
                ->first();
        }

        if (!$existing && $rfc !== '') {
            $existing = DB::connection($this->cli)
                ->table($this->table)
                ->where('rfc', $rfc)
                ->first();
        }

        $direccion = is_array($remote['direccion'] ?? null) ? $remote['direccion'] : null;

        $payload = [];
        $assign = function (string $key, mixed $value) use (&$payload, $cols): void {
            if (in_array($key, $cols, true)) {
                $payload[$key] = $value;
            }
        };

        if ($existing) {
            $assign('cuenta_id', $existing->cuenta_id ?? null);
        }

        $assign('rfc', $rfc !== '' ? $rfc : ($existing->rfc ?? null));
        $assign('razon_social', $this->nullIfEmpty($remote['razon_social'] ?? ($existing->razon_social ?? null)));
        $assign('nombre_comercial', $this->nullIfEmpty($existing->nombre_comercial ?? null));
        $assign('email', $this->nullIfEmpty($remote['email'] ?? ($existing->email ?? null)));
        $assign('regimen_fiscal', $this->nullIfEmpty($remote['regimen'] ?? ($existing->regimen_fiscal ?? null)));
        $assign('grupo', $this->nullIfEmpty($remote['grupo'] ?? ($existing->grupo ?? null)));
        $assign('status', $this->normalizeLocalStatus($remote['status'] ?? ($existing->status ?? 'active')));
        $assign('ext_id', $remoteId !== '' ? $remoteId : ($existing->ext_id ?? null));

        if ($direccion !== null) {
            $assign('direccion', json_encode($direccion, JSON_UNESCAPED_UNICODE));
        } elseif ($existing && isset($existing->direccion)) {
            $assign('direccion', $existing->direccion);
        }

        if ($existing && isset($existing->certificados)) {
            $assign('certificados', $existing->certificados);
        }

        if ($existing && isset($existing->series)) {
            $assign('series', $existing->series);
        }

        if ($existing && isset($existing->csd_serie)) {
            $assign('csd_serie', $existing->csd_serie);
        }

        if ($existing && isset($existing->csd_vigencia_hasta)) {
            $assign('csd_vigencia_hasta', $existing->csd_vigencia_hasta);
        }

        if (in_array('updated_at', $cols, true)) {
            $payload['updated_at'] = now();
        }

        if ($existing) {
            DB::connection($this->cli)
                ->table($this->table)
                ->where('id', (int) $existing->id)
                ->update($payload);

            return 'updated';
        }

        if (in_array('created_at', $cols, true)) {
            $payload['created_at'] = now();
        }

        DB::connection($this->cli)
            ->table($this->table)
            ->insert($payload);

        return 'created';
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'cuenta_id'           => 'nullable|integer',
            'rfc'                 => 'required|string|max:13',
            'razon_social'        => 'required|string|max:190',
            'nombre_comercial'    => 'nullable|string|max:190',
            'email'               => 'nullable|email|max:190',
            'regimen_fiscal'      => 'nullable|string|max:10',
            'grupo'               => 'nullable|string|max:50',
            'status'              => 'nullable|string|max:20',
            'direccion_json'      => 'nullable|string',
            'certificados_json'   => 'nullable|string',
            'series_json'         => 'nullable|string',
            'csd_serie'           => 'nullable|string|max:100',
            'csd_vigencia_hasta'  => 'nullable|date',
            'ext_id'              => 'nullable|string|max:36',
        ], [
            'rfc.required'            => 'El RFC es obligatorio.',
            'razon_social.required'   => 'La razón social es obligatoria.',
            'email.email'             => 'El email no es válido.',
            'csd_vigencia_hasta.date' => 'La vigencia CSD debe ser una fecha válida.',
        ]);

        $this->validateJsonFields($data);

        return $data;
    }

    private function buildPayload(array $data, bool $creating): array
    {
        $cols = $this->columns();
        $payload = [];

        $assign = function (string $key, mixed $value) use (&$payload, $cols): void {
            if (in_array($key, $cols, true)) {
                $payload[$key] = $value;
            }
        };

        $assign('cuenta_id', $data['cuenta_id'] !== null && $data['cuenta_id'] !== '' ? (int) $data['cuenta_id'] : null);
        $assign('rfc', strtoupper(trim((string) $data['rfc'])));
        $assign('razon_social', trim((string) $data['razon_social']));
        $assign('nombre_comercial', $this->nullIfEmpty($data['nombre_comercial'] ?? null));
        $assign('email', $this->nullIfEmpty($data['email'] ?? null));
        $assign('regimen_fiscal', $this->nullIfEmpty($data['regimen_fiscal'] ?? null));
        $assign('grupo', $this->nullIfEmpty($data['grupo'] ?? null));
        $assign('status', $this->normalizeLocalStatus($data['status'] ?? null));
        $assign('csd_serie', $this->nullIfEmpty($data['csd_serie'] ?? null));
        $assign('csd_vigencia_hasta', $this->nullIfEmpty($data['csd_vigencia_hasta'] ?? null));
        $assign('ext_id', $this->nullIfEmpty($data['ext_id'] ?? null));

        $assign('direccion', $this->normalizeJsonText($data['direccion_json'] ?? null));
        $assign('certificados', $this->normalizeJsonText($data['certificados_json'] ?? null));
        $assign('series', $this->normalizeJsonText($data['series_json'] ?? null));

        if ($creating && in_array('created_at', $cols, true)) {
            $payload['created_at'] = now();
        }

        if (in_array('updated_at', $cols, true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function buildFacturotopiaEmisorPayload(array $payload, string $remoteId, bool $creating): array
    {
        $direccion = $this->decodeJsonColumn($payload['direccion'] ?? null) ?: [];
        $certificados = $this->decodeJsonColumn($payload['certificados'] ?? null) ?: [];
        $series = $this->decodeJsonColumn($payload['series'] ?? null) ?: [];

        $cp = trim((string) ($direccion['cp'] ?? ''));
        $direccionTxt = trim((string) ($direccion['direccion'] ?? ''));
        $ciudad = trim((string) ($direccion['ciudad'] ?? ''));
        $estado = trim((string) ($direccion['estado'] ?? ''));

        $apiPayload = [
            'id'           => $remoteId,
            'razon_social' => trim((string) ($payload['razon_social'] ?? '')),
            'grupo'        => trim((string) ($payload['grupo'] ?? '')),
            'rfc'          => strtoupper(trim((string) ($payload['rfc'] ?? ''))),
            'regimen'      => trim((string) ($payload['regimen_fiscal'] ?? '')),
            'email'        => trim((string) ($payload['email'] ?? '')),
        ];

        if ($cp !== '') {
            $apiPayload['cp'] = $cp;
        }

        if ($cp !== '' || $direccionTxt !== '' || $ciudad !== '' || $estado !== '') {
            $apiPayload['direccion'] = [
                'cp'        => $cp,
                'direccion' => $direccionTxt,
                'ciudad'    => $ciudad,
                'estado'    => $estado,
            ];
        }

        if (!empty($series)) {
            $apiPayload['series'] = array_values(array_map(function ($item) {
                $row = is_array($item) ? $item : [];

                return [
                    'tipo'  => trim((string) ($row['tipo'] ?? '')),
                    'serie' => trim((string) ($row['serie'] ?? '')),
                    'folio' => isset($row['folio']) && trim((string) $row['folio']) !== '' ? (int) $row['folio'] : 1,
                ];
            }, $series));
        }

        if ($creating || !empty($certificados)) {
            $faltantes = [];

            if (trim((string) ($apiPayload['id'] ?? '')) === '') {
                $faltantes[] = 'id';
            }
            if (trim((string) ($apiPayload['razon_social'] ?? '')) === '') {
                $faltantes[] = 'razon_social';
            }
            if (trim((string) ($apiPayload['rfc'] ?? '')) === '') {
                $faltantes[] = 'rfc';
            }
            if (trim((string) ($apiPayload['regimen'] ?? '')) === '') {
                $faltantes[] = 'regimen';
            }
            if (trim((string) ($apiPayload['email'] ?? '')) === '') {
                $faltantes[] = 'email';
            }
            if ($cp === '') {
                $faltantes[] = 'direccion.cp';
            }

            if ($creating) {
                foreach (['csd_key', 'csd_cer', 'csd_password', 'fiel_key', 'fiel_cer', 'fiel_password'] as $field) {
                    if (trim((string) ($certificados[$field] ?? '')) === '') {
                        $faltantes[] = 'certificados.' . $field;
                    }
                }
            }

            if ($creating && !empty($faltantes)) {
                throw ValidationException::withMessages([
                    'facturotopia' => 'Para registrar el emisor en Facturotopia faltan campos obligatorios: ' . implode(', ', $faltantes) . '.',
                ]);
            }

            if (!empty($certificados)) {
                $apiPayload['certificados'] = [
                    'csd_key'       => (string) ($certificados['csd_key'] ?? ''),
                    'csd_cer'       => (string) ($certificados['csd_cer'] ?? ''),
                    'csd_password'  => (string) ($certificados['csd_password'] ?? ''),
                    'fiel_key'      => (string) ($certificados['fiel_key'] ?? ''),
                    'fiel_cer'      => (string) ($certificados['fiel_cer'] ?? ''),
                    'fiel_password' => (string) ($certificados['fiel_password'] ?? ''),
                ];
            }
        }

        return $apiPayload;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validateJsonFields(array $data): void
    {
        foreach ([
            'direccion_json'    => 'Dirección',
            'certificados_json' => 'Certificados',
            'series_json'       => 'Series',
        ] as $field => $label) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            try {
                json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                throw ValidationException::withMessages([
                    $field => $label . ' no contiene un JSON válido.',
                ]);
            }
        }
    }

    private function normalizeJsonText(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'json' => 'Uno de los campos JSON del emisor no tiene formato válido.',
            ]);
        }
    }

    private function decodeJsonColumn(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function loadCuentasOptions(): array
    {
        $rows = CuentaCliente::query()
            ->orderBy('razon_social')
            ->get([
                'admin_account_id',
                'razon_social',
                'rfc',
                'email',
                'nombre_comercial',
                'regimen_fiscal',
                'cp',
                'pais',
                'estado',
                'municipio',
                'colonia',
                'calle',
                'no_ext',
                'no_int',
            ]);

        return $rows
            ->map(function ($c) {
                return [
                    'value' => (int) ($c->admin_account_id ?? 0),
                    'label' => trim((string) ($c->razon_social ?? '')) . ' · ' . trim((string) ($c->rfc ?? '')),
                    'meta'  => [
                        'rfc'              => (string) ($c->rfc ?? ''),
                        'razon_social'     => (string) ($c->razon_social ?? ''),
                        'nombre_comercial' => (string) ($c->nombre_comercial ?? ''),
                        'email'            => (string) ($c->email ?? ''),
                        'regimen_fiscal'   => (string) ($c->regimen_fiscal ?? ''),
                        'cp'               => (string) ($c->cp ?? ''),
                        'pais'             => (string) ($c->pais ?? 'MEX'),
                        'estado'           => (string) ($c->estado ?? ''),
                        'municipio'        => (string) ($c->municipio ?? ''),
                        'colonia'          => (string) ($c->colonia ?? ''),
                        'calle'            => (string) ($c->calle ?? ''),
                        'no_ext'           => (string) ($c->no_ext ?? ''),
                        'no_int'           => (string) ($c->no_int ?? ''),
                    ],
                ];
            })
            ->filter(fn ($x) => $x['value'] > 0)
            ->values()
            ->all();
    }

    private function loadCuentasMap(): array
    {
        $map = [];

        foreach ($this->loadCuentasOptions() as $item) {
            $map[(string) $item['value']] = $item['label'];
        }

        return $map;
    }

    private function columns(): array
    {
        return Schema::connection($this->cli)->getColumnListing($this->table);
    }

    private function abortIfTableMissing(): void
    {
        abort_unless(
            Schema::connection($this->cli)->hasTable($this->table),
            500,
            'No existe la tabla emisores en mysql_clientes.'
        );
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeLocalStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));

        if (in_array($status, ['1', 'active', 'activo'], true)) {
            return 'active';
        }

        if (in_array($status, ['0', 'inactive', 'inactivo', 'disabled'], true)) {
            return 'inactive';
        }

        if ($status === 'pending' || $status === 'pendiente') {
            return 'pending';
        }

        return 'active';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolveRemoteEmisorId(array $payload): string
    {
        $extId = trim((string) ($payload['ext_id'] ?? ''));
        if ($extId !== '') {
            return $extId;
        }

        $cuentaId = (int) ($payload['cuenta_id'] ?? 0);
        $rfc = strtoupper(trim((string) ($payload['rfc'] ?? '')));

        if ($cuentaId > 0 && $rfc !== '') {
            $candidate = 'emi_' . $cuentaId . '_' . $rfc;
        } elseif ($rfc !== '') {
            $candidate = 'emi_' . $rfc;
        } else {
            $candidate = (string) Str::uuid();
        }

        return mb_substr($candidate, 0, 36);
    }

    private function facturotopiaStatusValue(?string $status): string
    {
        $status = $this->normalizeLocalStatus($status);

        return $status === 'active' ? '1' : '0';
    }

    private function facturotopiaLooksLikeAlreadyExists(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);
        $msg = strtolower(trim((string) ($response['message'] ?? '')));

        if (in_array($status, [409, 422], true)) {
            return true;
        }

        return str_contains($msg, 'already exists')
            || str_contains($msg, 'ya existe')
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'duplicado')
            || str_contains($msg, 'exists');
    }

    private function facturotopiaLooksLikeNotFound(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);
        $msg = strtolower(trim((string) ($response['message'] ?? '')));

        if ($status === 404) {
            return true;
        }

        return str_contains($msg, 'not found')
            || str_contains($msg, 'page not found')
            || str_contains($msg, 'no encontrado');
    }

    /**
     * @return array{ok:bool,message:string,operation:?string,remote_id:?string}
     */
    private function syncSingleEmisorToFacturotopia(object $row): array
    {
        /** @var FacturotopiaClient $facturotopia */
        $facturotopia = app(FacturotopiaClient::class);

        if (!$facturotopia->isApiReady()) {
            return [
                'ok' => false,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'operation' => null,
                'remote_id' => null,
            ];
        }

        $payloadBase = (array) $row;
        $remoteId = trim((string) ($row->ext_id ?? ''));

        if ($remoteId === '') {
            $remoteId = $this->resolveRemoteEmisorId($payloadBase);
        }

        try {
            $payload = $this->buildFacturotopiaEmisorPayload($payloadBase, $remoteId, false);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'operation' => null,
                'remote_id' => $remoteId !== '' ? $remoteId : null,
            ];
        }

        $response = null;
        $operation = null;

        if (trim((string) ($row->ext_id ?? '')) !== '') {
            $response = $facturotopia->updateEmisor($remoteId, $payload);

            if ((bool) ($response['ok'] ?? false)) {
                $operation = 'updated';
            } elseif ($this->facturotopiaLooksLikeNotFound($response)) {
                $payloadCreate = $this->buildFacturotopiaEmisorPayload($payloadBase, $remoteId, true);
                $response = $facturotopia->createEmisor($payloadCreate);

                if ((bool) ($response['ok'] ?? false)) {
                    $operation = 'created';
                }
            }
        } else {
            try {
                $payloadCreate = $this->buildFacturotopiaEmisorPayload($payloadBase, $remoteId, true);
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'operation' => null,
                    'remote_id' => $remoteId !== '' ? $remoteId : null,
                ];
            }

            $response = $facturotopia->createEmisor($payloadCreate);

            if ((bool) ($response['ok'] ?? false)) {
                $operation = 'created';
            } elseif ($this->facturotopiaLooksLikeAlreadyExists($response)) {
                $response = $facturotopia->updateEmisor($remoteId, $payload);

                if ((bool) ($response['ok'] ?? false)) {
                    $operation = 'updated';
                }
            }
        }

        if (!is_array($response) || !((bool) ($response['ok'] ?? false))) {
            return [
                'ok' => false,
                'message' => (string) ($response['message'] ?? 'No se pudo sincronizar el emisor con Facturotopia.'),
                'operation' => null,
                'remote_id' => $remoteId !== '' ? $remoteId : null,
            ];
        }

        $remoteData = (array) ($response['data'] ?? []);
        $remoteIdResolved = trim((string) ($remoteData['id'] ?? $remoteId));

        DB::connection($this->cli)
            ->table($this->table)
            ->where('id', (int) $row->id)
            ->update([
                'ext_id'      => $remoteIdResolved !== '' ? $remoteIdResolved : $remoteId,
                'updated_at'  => now(),
            ]);

        $fresh = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', (int) $row->id)
            ->first();

        if ($fresh) {
            $statusSync = $this->syncSingleEmisorStatusOnly($fresh);
            if (!($statusSync['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => 'Emisor sincronizado, pero falló el estado remoto: ' . (string) ($statusSync['message'] ?? 'Error desconocido.'),
                    'operation' => $operation,
                    'remote_id' => $remoteIdResolved !== '' ? $remoteIdResolved : $remoteId,
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Sincronizado correctamente.',
            'operation' => $operation,
            'remote_id' => $remoteIdResolved !== '' ? $remoteIdResolved : $remoteId,
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function syncSingleEmisorStatusOnly(object $row): array
    {
        /** @var FacturotopiaClient $facturotopia */
        $facturotopia = app(FacturotopiaClient::class);

        if (!$facturotopia->isApiReady()) {
            return ['ok' => false, 'message' => 'Facturotopia no está configurado.'];
        }

        $remoteId = trim((string) ($row->ext_id ?? ''));
        if ($remoteId === '') {
            return ['ok' => false, 'message' => 'El emisor no tiene ext_id para actualizar estado.'];
        }

        $statusValue = $this->facturotopiaStatusValue((string) ($row->status ?? 'active'));

        $statusResponse = $facturotopia->updateEmisorStatus(
            $remoteId,
            $statusValue,
            'Actualización desde panel administrativo Pactopia360'
        );

        if (!(bool) ($statusResponse['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($statusResponse['message'] ?? 'No se pudo actualizar el estado del emisor.'),
            ];
        }

        return ['ok' => true, 'message' => 'OK'];
    }
}