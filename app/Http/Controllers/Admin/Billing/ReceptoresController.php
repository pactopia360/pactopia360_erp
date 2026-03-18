<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Services\Billing\Facturotopia\FacturotopiaClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReceptoresController extends Controller
{
    private string $cli = 'mysql_clientes';
    private string $table = 'receptores';
    private string $cuentasTable = 'cuentas_cliente';

    public function index(Request $request): View
    {
        $this->abortIfTableMissing();
        $this->syncMirrorFromCuentasCliente();
        $this->autoSyncFromFacturotopia();

        $q = trim((string) $request->get('q', ''));

        $qb = DB::connection($this->cli)
            ->table($this->table)
            ->orderByDesc('id');

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('rfc', 'like', "%{$q}%")
                    ->orWhere('razon_social', 'like', "%{$q}%")
                    ->orWhere('nombre_comercial', 'like', "%{$q}%")
                    ->orWhere('uso_cfdi', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('cuenta_id', 'like', "%{$q}%");

                $cols = $this->columns();
                if (in_array('regimen_fiscal', $cols, true)) {
                    $w->orWhere('regimen_fiscal', 'like', "%{$q}%");
                }
                if (in_array('codigo_postal', $cols, true)) {
                    $w->orWhere('codigo_postal', 'like', "%{$q}%");
                }
                if (in_array('forma_pago', $cols, true)) {
                    $w->orWhere('forma_pago', 'like', "%{$q}%");
                }
                if (in_array('metodo_pago', $cols, true)) {
                    $w->orWhere('metodo_pago', 'like', "%{$q}%");
                }
            });
        }

        /** @var LengthAwarePaginator $rows */
        $rows = $qb->paginate(20)->withQueryString();

        $cuentas = $this->loadCuentasMap();

        $rows->getCollection()->transform(function ($row) use ($cuentas) {
            $row->cuenta_label = $cuentas[(string) ($row->cuenta_id ?? '')] ?? null;
            $row->is_mirror = $this->isMirrorRow($row);
            return $row;
        });

        return view('admin.billing.invoicing.receptores.index', [
            'rows' => $rows,
            'q'    => $q,
        ]);
    }

    public function create(): View
    {
        $this->abortIfTableMissing();

        return view('admin.billing.invoicing.receptores.create', [
            'cuentas'   => $this->loadCuentasOptions(),
            'catalogos' => $this->loadCatalogosForForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortIfTableMissing();

        $data = $this->validateData($request);
        $payload = $this->buildPayload($data, true);

        $newId = (int) DB::connection($this->cli)
            ->table($this->table)
            ->insertGetId($payload);

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $newId)
            ->first();

        if (!$row) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors(['receptor' => 'El receptor se intentó crear, pero no se pudo recargar el registro.']);
        }

        $sync = $this->syncSingleReceptorToFacturotopia($row);

        if (!($sync['ok'] ?? false)) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors([
                    'receptor' => 'Receptor guardado localmente, pero falló la sincronización con Facturotopia: ' . (string) ($sync['message'] ?? 'Error desconocido.'),
                ])
                ->with('ok', 'Receptor creado correctamente en local.');
        }

        return redirect()
            ->route('admin.billing.invoicing.receptores.index')
            ->with('ok', 'Receptor creado y sincronizado con Facturotopia correctamente.');
    }

    public function edit(int $id): View
    {
        $this->abortIfTableMissing();
        $this->syncMirrorFromCuentasCliente();

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->first();

        abort_unless($row, 404, 'Receptor no encontrado.');

        return view('admin.billing.invoicing.receptores.edit', [
            'row'            => $row,
            'cuentas'        => $this->loadCuentasOptions(),
            'catalogos'      => $this->loadCatalogosForForm(),
            'isMirrorRecord' => $this->isMirrorRow($row),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->abortIfTableMissing();
        $this->syncMirrorFromCuentasCliente();

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->first();

        abort_unless($row, 404, 'Receptor no encontrado.');

        if ($this->isMirrorRow($row)) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors([
                    'receptor' => 'Este receptor viene del perfil fiscal del cliente. Debes editarlo desde el perfil del cliente para mantener el espejo correcto.',
                ]);
        }

        $data = $this->validateData($request);
        $payload = $this->buildPayload($data, false);

        DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->update($payload);

        $freshRow = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->first();

        if (!$freshRow) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors(['receptor' => 'El receptor se actualizó, pero no se pudo recargar el registro.']);
        }

        $sync = $this->syncSingleReceptorToFacturotopia($freshRow);

        if (!($sync['ok'] ?? false)) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors([
                    'receptor' => 'Receptor actualizado localmente, pero falló la sincronización con Facturotopia: ' . (string) ($sync['message'] ?? 'Error desconocido.'),
                ])
                ->with('ok', 'Receptor actualizado correctamente en local.');
        }

        return redirect()
            ->route('admin.billing.invoicing.receptores.index')
            ->with('ok', 'Receptor actualizado y sincronizado con Facturotopia correctamente.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->abortIfTableMissing();
        $this->syncMirrorFromCuentasCliente();

        $row = DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->first();

        abort_unless($row, 404, 'Receptor no encontrado.');

        if ($this->isMirrorRow($row)) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors([
                    'receptor' => 'Este receptor viene del perfil fiscal del cliente. No se elimina aquí; modifica los datos fiscales en el perfil del cliente.',
                ]);
        }

        DB::connection($this->cli)
            ->table($this->table)
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.billing.invoicing.receptores.index')
            ->with('ok', 'Receptor eliminado correctamente.');
    }

    public function syncFacturotopia(Request $request): RedirectResponse
    {
        $this->abortIfTableMissing();
        $this->syncMirrorFromCuentasCliente();

        $sync = $this->runAutomaticSync(true);

        if (!($sync['ok'] ?? false)) {
            return redirect()
                ->route('admin.billing.invoicing.receptores.index')
                ->withErrors([
                    'receptor' => (string) ($sync['message'] ?? 'No se pudo sincronizar con Facturotopía.'),
                ]);
        }

        return redirect()
            ->route('admin.billing.invoicing.receptores.index')
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
        $stampKey = 'p360:facturotopia:receptores:last_sync_at';
        $lockKey  = 'p360:facturotopia:receptores:sync_lock';

        try {
            $lastSyncAt = (int) Cache::get($stampKey, 0);

            if (!$force && $lastSyncAt > 0 && (time() - $lastSyncAt) < $cooldownSeconds) {
                return [
                    'ok' => true,
                    'message' => 'Receptores ya sincronizados recientemente.',
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                ];
            }

            $lock = Cache::lock($lockKey, 120);

            if (!$lock->get()) {
                return [
                    'ok' => true,
                    'message' => 'Ya hay una sincronización automática de receptores en proceso.',
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

                    $response = $facturotopia->listReceptores($limit, (string) $page);

                    if (!($response['ok'] ?? false)) {
                        return [
                            'ok' => false,
                            'message' => 'No se pudo consultar el listado de receptores en Facturotopía: ' . (string) ($response['message'] ?? 'Error desconocido.'),
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
                                $detail = $facturotopia->getReceptor($remoteId);
                                if (($detail['ok'] ?? false) && is_array($detail['data'] ?? null)) {
                                    $remote = array_merge($remote, (array) $detail['data']);
                                }
                            }

                            $result = $this->upsertReceptorFromFacturotopia($remote);

                            if ($result === 'created') {
                                $created++;
                            } elseif ($result === 'updated') {
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        } catch (Throwable $e) {
                            $skipped++;

                            Log::warning('[FACTUROTOPIA][RECEPTORES] autoSync row failed', [
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
                    'message' => "Receptores sincronizados automáticamente. creados={$created}, actualizados={$updated}, omitidos={$skipped}.",
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                ];
            } finally {
                optional($lock)->release();
            }
        } catch (Throwable $e) {
            Log::error('[FACTUROTOPIA][RECEPTORES] autoSync failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Falló la sincronización automática de receptores: ' . $e->getMessage(),
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
            Log::warning('[FACTUROTOPIA][RECEPTORES] autoSync index warning', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function upsertReceptorFromFacturotopia(array $remote): string
    {
        $cols = $this->columns();

        $remoteId   = trim((string) ($remote['id'] ?? ''));
        $rfc        = $this->normalizeRfc((string) ($remote['rfc'] ?? ''));
        $cuentaId   = (int) trim((string) ($remote['grupo_id'] ?? '0'));
        $direccion  = is_array($remote['direccion'] ?? null) ? $remote['direccion'] : [];
        $regimen    = trim((string) ($remote['regimen'] ?? ''));

        if ($remoteId === '' && $rfc === '') {
            return 'skipped';
        }

        $existing = null;

        if ($remoteId !== '' && in_array('extras', $cols, true)) {
            $existing = DB::connection($this->cli)
                ->table($this->table)
                ->where('extras', 'like', '%"ext_id":"' . $remoteId . '"%')
                ->first();
        }

        if (!$existing && $cuentaId > 0 && $rfc !== '') {
            $existing = DB::connection($this->cli)
                ->table($this->table)
                ->where('cuenta_id', $cuentaId)
                ->where('rfc', $rfc)
                ->first();
        }

        if (!$existing && $rfc !== '') {
            $existing = DB::connection($this->cli)
                ->table($this->table)
                ->where('rfc', $rfc)
                ->orderByDesc('id')
                ->first();
        }

        $isMirror = $existing ? $this->isMirrorRow($existing) : false;
        $extras   = $existing ? $this->decodeExtras($existing->extras ?? null) : [];

        $payload = [];
        $assign = function (string $key, mixed $value) use (&$payload, $cols): void {
            if (in_array($key, $cols, true)) {
                $payload[$key] = $value;
            }
        };

        if (!$isMirror) {
            $assign('cuenta_id', $cuentaId > 0 ? $cuentaId : ($existing->cuenta_id ?? null));
            $assign('rfc', $rfc !== '' ? $rfc : ($existing->rfc ?? null));
            $assign('razon_social', $this->nullIfEmpty($remote['razon_social'] ?? ($existing->razon_social ?? null)));
            $assign('nombre_comercial', $this->nullIfEmpty($existing->nombre_comercial ?? null));
            $assign('uso_cfdi', $this->nullIfEmpty($existing->uso_cfdi ?? null));
            $assign('regimen_fiscal', $this->nullIfEmpty($regimen !== '' ? $regimen : ($existing->regimen_fiscal ?? null)));
            $assign('forma_pago', $this->nullIfEmpty($existing->forma_pago ?? null));
            $assign('metodo_pago', $this->nullIfEmpty($existing->metodo_pago ?? null));
            $assign('codigo_postal', $this->nullIfEmpty($direccion['cp'] ?? ($existing->codigo_postal ?? null)));
            $assign('pais', $this->normalizeStoragePais($direccion['pais'] ?? ($existing->pais ?? 'MEX')));
            $assign('estado', $this->nullIfEmpty($direccion['estado'] ?? ($existing->estado ?? null)));
            $assign('municipio', $this->nullIfEmpty($direccion['ciudad'] ?? ($existing->municipio ?? null)));
            $assign('colonia', $this->nullIfEmpty($existing->colonia ?? null));
            $assign('calle', $this->nullIfEmpty($direccion['direccion'] ?? ($existing->calle ?? null)));
            $assign('no_ext', $this->nullIfEmpty($existing->no_ext ?? null));
            $assign('no_int', $this->nullIfEmpty($existing->no_int ?? null));
            $assign('email', $this->nullIfEmpty($remote['email'] ?? ($existing->email ?? null)));
            $assign('telefono', $this->nullIfEmpty($existing->telefono ?? null));

            if (in_array('source', $cols, true)) {
                $payload['source'] = 'facturotopia';
            }

            if (in_array('origen', $cols, true)) {
                $payload['origen'] = 'facturotopia';
            }

            if (in_array('is_manual', $cols, true)) {
                $payload['is_manual'] = 0;
            }
        }

        $facturotopiaPayload = [
            'id'           => $remoteId !== '' ? $remoteId : null,
            'rfc'          => $rfc !== '' ? $rfc : null,
            'razon_social' => $this->nullIfEmpty($remote['razon_social'] ?? null),
            'grupo'        => $this->nullIfEmpty($remote['grupo'] ?? null),
            'grupo_id'     => $cuentaId > 0 ? (string) $cuentaId : null,
            'email'        => $this->nullIfEmpty($remote['email'] ?? null),
            'regimen'      => $this->nullIfEmpty($regimen),
            'status'       => $this->nullIfEmpty($remote['status'] ?? null),
            'direccion'    => $direccion,
            'synced_by'    => 'pull_auto',
        ];

        $extras = $this->mergeFacturotopiaExtras(
            $extras,
            $remoteId,
            $facturotopiaPayload,
            $isMirror
        );

        $assign('extras', json_encode($extras, JSON_UNESCAPED_UNICODE));

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

    private function validateData(Request $request): array
    {
        return $request->validate([
            'cuenta_id'         => 'nullable|integer',
            'rfc'               => 'required|string|max:13',
            'razon_social'      => 'required|string|max:255',
            'nombre_comercial'  => 'nullable|string|max:255',
            'uso_cfdi'          => 'nullable|string|max:10',
            'regimen_fiscal'    => 'nullable|string|max:10',
            'forma_pago'        => 'nullable|string|max:10',
            'metodo_pago'       => 'nullable|string|max:10',
            'codigo_postal'     => 'nullable|string|max:10',
            'pais'              => 'nullable|string|max:3',
            'estado'            => 'nullable|string|max:120',
            'municipio'         => 'nullable|string|max:120',
            'colonia'           => 'nullable|string|max:120',
            'calle'             => 'nullable|string|max:180',
            'no_ext'            => 'nullable|string|max:30',
            'no_int'            => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'telefono'          => 'nullable|string|max:40',
        ], [
            'rfc.required'          => 'El RFC es obligatorio.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'email.email'           => 'El email no es válido.',
        ]);
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
        $assign('rfc', $this->normalizeRfc((string) $data['rfc']));
        $assign('razon_social', trim((string) $data['razon_social']));
        $assign('nombre_comercial', $this->nullIfEmpty($data['nombre_comercial'] ?? null));

        $assign('uso_cfdi', $this->nullIfEmpty($data['uso_cfdi'] ?? null));
        $assign('regimen_fiscal', $this->nullIfEmpty($data['regimen_fiscal'] ?? null));
        $assign('forma_pago', $this->nullIfEmpty($data['forma_pago'] ?? null));
        $assign('metodo_pago', $this->nullIfEmpty($data['metodo_pago'] ?? null));
        $assign('codigo_postal', $this->nullIfEmpty($data['codigo_postal'] ?? null));
        $assign('pais', $this->normalizeStoragePais($data['pais'] ?? null));

        $assign('estado', $this->nullIfEmpty($data['estado'] ?? null));
        $assign('municipio', $this->nullIfEmpty($data['municipio'] ?? null));
        $assign('colonia', $this->nullIfEmpty($data['colonia'] ?? null));
        $assign('calle', $this->nullIfEmpty($data['calle'] ?? null));
        $assign('no_ext', $this->nullIfEmpty($data['no_ext'] ?? null));
        $assign('no_int', $this->nullIfEmpty($data['no_int'] ?? null));
        $assign('email', $this->nullIfEmpty($data['email'] ?? null));
        $assign('telefono', $this->nullIfEmpty($data['telefono'] ?? null));

        if ($creating && in_array('created_at', $cols, true)) {
            $payload['created_at'] = now();
        }

        if (in_array('updated_at', $cols, true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    private function syncMirrorFromCuentasCliente(): void
    {
        if (!Schema::connection($this->cli)->hasTable($this->cuentasTable)) {
            return;
        }

        $receptorCols = $this->columns();
        $cuentaCols   = Schema::connection($this->cli)->getColumnListing($this->cuentasTable);

        $hasCuentaCol = fn (string $col): bool => in_array($col, $cuentaCols, true);
        $hasRecCol    = fn (string $col): bool => in_array($col, $receptorCols, true);

        $select = array_values(array_filter([
            'admin_account_id',
            $hasCuentaCol('rfc') ? 'rfc' : null,
            $hasCuentaCol('razon_social') ? 'razon_social' : null,
            $hasCuentaCol('nombre_comercial') ? 'nombre_comercial' : null,
            $hasCuentaCol('uso_cfdi') ? 'uso_cfdi' : null,
            $hasCuentaCol('email') ? 'email' : null,
            $hasCuentaCol('regimen_fiscal') ? 'regimen_fiscal' : null,
            $hasCuentaCol('cp') ? 'cp' : null,
            $hasCuentaCol('forma_pago') ? 'forma_pago' : null,
            $hasCuentaCol('metodo_pago') ? 'metodo_pago' : null,
            $hasCuentaCol('telefono') ? 'telefono' : null,
            $hasCuentaCol('pais') ? 'pais' : null,
            $hasCuentaCol('calle') ? 'calle' : null,
            $hasCuentaCol('no_ext') ? 'no_ext' : null,
            $hasCuentaCol('no_int') ? 'no_int' : null,
            $hasCuentaCol('colonia') ? 'colonia' : null,
            $hasCuentaCol('municipio') ? 'municipio' : null,
            $hasCuentaCol('estado') ? 'estado' : null,
            $hasCuentaCol('meta') ? 'meta' : null,
        ]));

        $cuentas = DB::connection($this->cli)
            ->table($this->cuentasTable)
            ->whereNotNull('admin_account_id')
            ->orderBy('admin_account_id')
            ->get($select);

        foreach ($cuentas as $cuenta) {
            $cuentaId = (int) ($cuenta->admin_account_id ?? 0);
            $rfc = strtoupper(trim((string) ($cuenta->rfc ?? '')));
            $razonSocial = trim((string) ($cuenta->razon_social ?? ''));

            if ($cuentaId <= 0 || $rfc === '' || $razonSocial === '') {
                continue;
            }

            $payload = [];
            $assign = function (string $key, mixed $value) use (&$payload, $hasRecCol): void {
                if ($hasRecCol($key)) {
                    $payload[$key] = $value;
                }
            };

            $assign('cuenta_id', $cuentaId);
            $assign('rfc', $rfc);
            $assign('razon_social', $razonSocial);
            $assign('nombre_comercial', $this->nullIfEmpty($cuenta->nombre_comercial ?? null));
            $assign('uso_cfdi', $this->nullIfEmpty($cuenta->uso_cfdi ?? null) ?: 'G03');
            $assign('email', $this->nullIfEmpty($cuenta->email ?? null));
            $assign('regimen_fiscal', $this->nullIfEmpty($cuenta->regimen_fiscal ?? null));
            $assign('codigo_postal', $this->nullIfEmpty($cuenta->cp ?? null));
            $assign('forma_pago', $this->nullIfEmpty($cuenta->forma_pago ?? null));
            $assign('metodo_pago', $this->nullIfEmpty($cuenta->metodo_pago ?? null));
            $assign('telefono', $this->nullIfEmpty($cuenta->telefono ?? null));
            $assign('pais', $this->normalizeStoragePais($cuenta->pais ?? null));
            $assign('calle', $this->nullIfEmpty($cuenta->calle ?? null));
            $assign('no_ext', $this->nullIfEmpty($cuenta->no_ext ?? null));
            $assign('no_int', $this->nullIfEmpty($cuenta->no_int ?? null));
            $assign('colonia', $this->nullIfEmpty($cuenta->colonia ?? null));
            $assign('municipio', $this->nullIfEmpty($cuenta->municipio ?? null));
            $assign('estado', $this->nullIfEmpty($cuenta->estado ?? null));

            if ($hasRecCol('source')) {
                $payload['source'] = 'cliente_perfil';
            }

            if ($hasRecCol('origen')) {
                $payload['origen'] = 'cliente_perfil';
            }

            if ($hasRecCol('is_manual')) {
                $payload['is_manual'] = 0;
            }

            if ($hasRecCol('meta')) {
                $payload['meta'] = json_encode([
                    'mirror' => [
                        'type' => 'cliente_perfil',
                        'admin_account_id' => $cuentaId,
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }

            if ($hasRecCol('updated_at')) {
                $payload['updated_at'] = now();
            }

            $existing = DB::connection($this->cli)
                ->table($this->table)
                ->where('cuenta_id', $cuentaId)
                ->where('rfc', $rfc)
                ->first();

            if ($existing) {
                DB::connection($this->cli)
                    ->table($this->table)
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                if ($hasRecCol('created_at')) {
                    $payload['created_at'] = now();
                }

                DB::connection($this->cli)
                    ->table($this->table)
                    ->insert($payload);
            }
        }
    }

    private function isMirrorRow(object $row): bool
    {
        $cuentaId = (int) ($row->cuenta_id ?? 0);
        $rfc      = strtoupper(trim((string) ($row->rfc ?? '')));

        if ($cuentaId <= 0 || $rfc === '') {
            return false;
        }

        if (!Schema::connection($this->cli)->hasTable($this->cuentasTable)) {
            return false;
        }

        try {
            $q = DB::connection($this->cli)
                ->table($this->cuentasTable)
                ->where('admin_account_id', $cuentaId);

            if (Schema::connection($this->cli)->hasColumn($this->cuentasTable, 'rfc')) {
                $q->where('rfc', $rfc);
            }

            return $q->exists();
        } catch (Throwable $e) {
            return false;
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
                'telefono',
                'uso_cfdi',
                'regimen_fiscal',
                'cp',
                'forma_pago',
                'metodo_pago',
                'pais',
                'estado',
                'municipio',
                'colonia',
                'calle',
                'no_ext',
                'no_int',
                'nombre_comercial',
            ]);

        return $rows
            ->map(function ($c) {
                return [
                    'value'            => (int) ($c->admin_account_id ?? 0),
                    'label'            => trim((string) ($c->razon_social ?? '')) . ' · ' . trim((string) ($c->rfc ?? '')),
                    'email'            => (string) ($c->email ?? ''),
                    'telefono'         => (string) ($c->telefono ?? ''),
                    'rfc'              => (string) ($c->rfc ?? ''),
                    'razon_social'     => (string) ($c->razon_social ?? ''),
                    'nombre_comercial' => (string) ($c->nombre_comercial ?? ''),
                    'uso_cfdi'         => (string) ($c->uso_cfdi ?? ''),
                    'regimen_fiscal'   => (string) ($c->regimen_fiscal ?? ''),
                    'codigo_postal'    => (string) ($c->cp ?? ''),
                    'forma_pago'       => (string) ($c->forma_pago ?? ''),
                    'metodo_pago'      => (string) ($c->metodo_pago ?? ''),
                    'pais'             => (string) ($this->normalizeStoragePais($c->pais ?? 'MEX') ?? 'MEX'),
                    'estado'           => (string) ($c->estado ?? ''),
                    'municipio'        => (string) ($c->municipio ?? ''),
                    'colonia'          => (string) ($c->colonia ?? ''),
                    'calle'            => (string) ($c->calle ?? ''),
                    'no_ext'           => (string) ($c->no_ext ?? ''),
                    'no_int'           => (string) ($c->no_int ?? ''),
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

    /**
     * @return array<string,mixed>
     */
    private function loadCatalogosForForm(): array
    {
        return [
            'regimenes'    => $this->loadSatRegimenes(),
            'usos_cfdi'    => $this->loadSatUsosCfdi(),
            'formas_pago'  => $this->loadSatFormasPago(),
            'metodos_pago' => $this->loadSatMetodosPago(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadSatRegimenes(): array
    {
        if (!Schema::connection($this->cli)->hasTable('sat_regimenes_fiscales')) {
            return [];
        }

        return DB::connection($this->cli)
            ->table('sat_regimenes_fiscales')
            ->orderBy('clave')
            ->get()
            ->map(function ($r) {
                return [
                    'clave'         => (string) ($r->clave ?? ''),
                    'descripcion'   => (string) ($r->descripcion ?? ''),
                    'aplica_fisica' => (bool) ($r->aplica_fisica ?? false),
                    'aplica_moral'  => (bool) ($r->aplica_moral ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadSatUsosCfdi(): array
    {
        if (!Schema::connection($this->cli)->hasTable('sat_usos_cfdi')) {
            return [];
        }

        return DB::connection($this->cli)
            ->table('sat_usos_cfdi')
            ->orderBy('clave')
            ->get()
            ->map(function ($r) {
                $permitidos = [];

                $raw = $r->regimenes_permitidos ?? null;
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $permitidos = array_values(array_map('strval', $decoded));
                    }
                } elseif (is_array($raw)) {
                    $permitidos = array_values(array_map('strval', $raw));
                }

                return [
                    'clave'                => (string) ($r->clave ?? ''),
                    'descripcion'          => (string) ($r->descripcion ?? ''),
                    'aplica_fisica'        => (bool) ($r->aplica_fisica ?? false),
                    'aplica_moral'         => (bool) ($r->aplica_moral ?? false),
                    'regimenes_permitidos' => $permitidos,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadSatFormasPago(): array
    {
        if (!Schema::connection($this->cli)->hasTable('sat_formas_pago')) {
            return [];
        }

        return DB::connection($this->cli)
            ->table('sat_formas_pago')
            ->orderBy('clave')
            ->get()
            ->map(fn ($r) => [
                'clave'       => (string) ($r->clave ?? ''),
                'descripcion' => (string) ($r->descripcion ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadSatMetodosPago(): array
    {
        if (!Schema::connection($this->cli)->hasTable('sat_metodos_pago')) {
            return [];
        }

        return DB::connection($this->cli)
            ->table('sat_metodos_pago')
            ->orderBy('clave')
            ->get()
            ->map(fn ($r) => [
                'clave'       => (string) ($r->clave ?? ''),
                'descripcion' => (string) ($r->descripcion ?? ''),
            ])
            ->values()
            ->all();
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
            'No existe la tabla receptores en mysql_clientes.'
        );
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeRfc(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', '', $value) ?: '';
        return $value;
    }

    private function normalizeStoragePais(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $upper = mb_strtoupper($value, 'UTF-8');

        $map = [
            'MX'            => 'MEX',
            'MEX'           => 'MEX',
            'MEXICO'        => 'MEX',
            'MÉXICO'        => 'MEX',
            'USA'           => 'USA',
            'US'            => 'USA',
            'EEUU'          => 'USA',
            'EUA'           => 'USA',
            'UNITED STATES' => 'USA',
        ];

        if (isset($map[$upper])) {
            return $map[$upper];
        }

        return mb_substr($upper, 0, 3, 'UTF-8');
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeExtras(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $extras
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function mergeFacturotopiaExtras(array $extras, string $remoteId, array $payload, bool $isMirror): array
    {
        $extras['facturotopia'] = [
            'ext_id'    => $remoteId !== '' ? $remoteId : null,
            'synced_at' => now()->toDateTimeString(),
            'source'    => $isMirror ? 'cliente_perfil' : 'manual',
            'payload'   => $payload,
        ];

        return $extras;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildFacturotopiaReceptorPayload(object $row): ?array
    {
        $rfc = $this->normalizeRfc((string) ($row->rfc ?? ''));
        $razonSocial = trim((string) ($row->razon_social ?? ''));

        if ($rfc === '' || $razonSocial === '') {
            return null;
        }

        $remoteId = $this->resolveRemoteReceptorId($row);
        $grupo = 'pactopia360';
        $grupoId = (string) ((int) ($row->cuenta_id ?? 0));
        $fiscal = $this->resolveFacturotopiaFiscalData($row);

        $payload = [
            'id'           => $remoteId,
            'grupo'        => $grupo,
            'grupo_id'     => $grupoId !== '0' ? $grupoId : null,
            'razon_social' => $razonSocial,
            'rfc'          => $rfc,
            'email'        => $this->nullIfEmpty($row->email ?? null),
            'regimen'      => $this->nullIfEmpty($fiscal['regimen'] ?? null),
        ];

        $direccion = [];

        if (!empty($fiscal['cp'])) {
            $direccion['cp'] = (string) $fiscal['cp'];
        }

        if (!empty($fiscal['calle'])) {
            $direccion['direccion'] = (string) $fiscal['calle'];
        }

        $ciudad = trim((string) ($fiscal['municipio'] ?? ''));
        $estado = trim((string) ($fiscal['estado'] ?? ''));

        if ($ciudad === '' && $estado !== '') {
            $ciudad = $estado;
        }

        if ($ciudad !== '') {
            $direccion['ciudad'] = $ciudad;
        }

        if ($estado !== '') {
            $direccion['estado'] = $estado;
        }

        if (!empty($fiscal['pais'])) {
            $direccion['pais'] = (string) $fiscal['pais'];
        }

        if (!empty($direccion)) {
            $payload['direccion'] = $direccion;
        }

        return $payload;
    }

    private function resolveRemoteReceptorId(object $row): string
    {
        $extras = $this->decodeExtras($row->extras ?? null);
        $existing = trim((string) data_get($extras, 'facturotopia.ext_id', ''));

        if ($existing !== '') {
            return $existing;
        }

        $rfc = $this->normalizeRfc((string) ($row->rfc ?? ''));
        if ($rfc === '') {
            return 'rec_' . Str::upper(Str::random(12));
        }

        $reused = $this->findExistingRemoteReceptorIdByRfc($rfc, (int) ($row->id ?? 0));
        if ($reused !== '') {
            return $reused;
        }

        return 'rec_' . $rfc;
    }

    private function findExistingRemoteReceptorIdByRfc(string $rfc, int $ignoreId = 0): string
    {
        $rfc = $this->normalizeRfc($rfc);
        if ($rfc === '') {
            return '';
        }

        try {
            $query = DB::connection($this->cli)
                ->table($this->table)
                ->where('rfc', $rfc)
                ->orderByDesc('id');

            if ($ignoreId > 0) {
                $query->where('id', '<>', $ignoreId);
            }

            $candidates = $query->get(['id', 'rfc', 'cuenta_id', 'extras']);

            foreach ($candidates as $candidate) {
                $candidateExtras = $this->decodeExtras($candidate->extras ?? null);
                $candidateExtId = trim((string) data_get($candidateExtras, 'facturotopia.ext_id', ''));

                if ($candidateExtId !== '') {
                    return $candidateExtId;
                }
            }
        } catch (Throwable $e) {
            return '';
        }

        return '';
    }

    private function isPersonaFisicaRfc(string $rfc): bool
    {
        $rfc = $this->normalizeRfc($rfc);
        return strlen($rfc) === 13;
    }

    private function normalizeFacturotopiaPais(?string $pais): string
    {
        $pais = trim((string) $pais);
        if ($pais === '') {
            return 'MEX';
        }

        $upper = mb_strtoupper($pais, 'UTF-8');

        $map = [
            'MEX'    => 'MEX',
            'MX'     => 'MEX',
            'MEXICO' => 'MEX',
            'MÉXICO' => 'MEX',
        ];

        return $map[$upper] ?? $upper;
    }

    private function normalizeFacturotopiaRegimen(string $rfc, ?string $regimen): ?string
    {
        $regimen = trim((string) $regimen);
        if ($regimen === '') {
            return $this->isPersonaFisicaRfc($rfc) ? '612' : '601';
        }

        if ($this->isPersonaFisicaRfc($rfc)) {
            $allowedFisica = [
                '605', '606', '607', '608', '610', '611',
                '612', '614', '615', '616', '621', '625',
                '626',
            ];

            if (!in_array($regimen, $allowedFisica, true)) {
                return '612';
            }
        }

        return $regimen;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadCuentaFiscalSnapshot(int $cuentaId): array
    {
        if ($cuentaId <= 0) {
            return [];
        }

        if (!Schema::connection($this->cli)->hasTable($this->cuentasTable)) {
            return [];
        }

        try {
            $cols = Schema::connection($this->cli)->getColumnListing($this->cuentasTable);
            $has  = fn (string $col): bool => in_array($col, $cols, true);

            $select = array_values(array_filter([
                'admin_account_id',
                $has('rfc') ? 'rfc' : null,
                $has('razon_social') ? 'razon_social' : null,
                $has('nombre_comercial') ? 'nombre_comercial' : null,
                $has('uso_cfdi') ? 'uso_cfdi' : null,
                $has('regimen_fiscal') ? 'regimen_fiscal' : null,
                $has('cp') ? 'cp' : null,
                $has('codigo_postal') ? 'codigo_postal' : null,
                $has('forma_pago') ? 'forma_pago' : null,
                $has('metodo_pago') ? 'metodo_pago' : null,
                $has('telefono') ? 'telefono' : null,
                $has('email') ? 'email' : null,
                $has('pais') ? 'pais' : null,
                $has('estado') ? 'estado' : null,
                $has('municipio') ? 'municipio' : null,
                $has('colonia') ? 'colonia' : null,
                $has('calle') ? 'calle' : null,
                $has('no_ext') ? 'no_ext' : null,
                $has('no_int') ? 'no_int' : null,
                $has('meta') ? 'meta' : null,
            ]));

            $row = DB::connection($this->cli)
                ->table($this->cuentasTable)
                ->where('admin_account_id', $cuentaId)
                ->first($select);

            if (!$row) {
                return [];
            }

            $meta = [];
            try {
                $meta = is_string($row->meta ?? null)
                    ? (json_decode((string) $row->meta, true) ?: [])
                    : (array) ($row->meta ?? []);
            } catch (Throwable $e) {
                $meta = [];
            }

            $billing = is_array(data_get($meta, 'billing')) ? data_get($meta, 'billing') : [];
            $company = is_array(data_get($meta, 'company')) ? data_get($meta, 'company') : [];

            return [
                'rfc'             => $row->rfc ?? data_get($billing, 'rfc') ?? data_get($company, 'rfc'),
                'razon_social'    => $row->razon_social ?? data_get($billing, 'razon_social') ?? data_get($company, 'razon_social'),
                'uso_cfdi'        => $row->uso_cfdi ?? data_get($billing, 'uso_cfdi'),
                'regimen_fiscal'  => $row->regimen_fiscal ?? data_get($billing, 'regimen_fiscal'),
                'codigo_postal'   => $row->cp ?? ($row->codigo_postal ?? data_get($billing, 'cp') ?? data_get($billing, 'codigo_postal')),
                'forma_pago'      => $row->forma_pago ?? data_get($billing, 'forma_pago'),
                'metodo_pago'     => $row->metodo_pago ?? data_get($billing, 'metodo_pago'),
                'telefono'        => $row->telefono ?? data_get($billing, 'telefono'),
                'email'           => $row->email ?? data_get($billing, 'email'),
                'pais'            => $row->pais ?? data_get($billing, 'pais') ?? 'MEX',
                'estado'          => $row->estado ?? data_get($billing, 'estado'),
                'municipio'       => $row->municipio ?? data_get($billing, 'municipio'),
                'colonia'         => $row->colonia ?? data_get($billing, 'colonia'),
                'calle'           => $row->calle ?? data_get($billing, 'calle'),
                'no_ext'          => $row->no_ext ?? data_get($billing, 'no_ext'),
                'no_int'          => $row->no_int ?? data_get($billing, 'no_int'),
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveFacturotopiaFiscalData(object $row): array
    {
        $extras = $this->decodeExtras($row->extras ?? null);
        $direccionExtras = is_array(data_get($extras, 'direccion')) ? data_get($extras, 'direccion') : [];
        $cuentaId = (int) ($row->cuenta_id ?? 0);
        $cuenta = $this->loadCuentaFiscalSnapshot($cuentaId);

        $pick = function (...$values): ?string {
            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
            return null;
        };

        $regimen = $pick(
            $row->regimen_fiscal ?? null,
            data_get($extras, 'regimen_fiscal'),
            data_get($extras, 'regimen'),
            $cuenta['regimen_fiscal'] ?? null
        );

        $cp = $pick(
            $row->codigo_postal ?? null,
            data_get($extras, 'codigo_postal'),
            data_get($extras, 'cp'),
            data_get($direccionExtras, 'cp'),
            $cuenta['codigo_postal'] ?? null
        );

        $calle = $pick(
            $row->calle ?? null,
            data_get($extras, 'calle'),
            data_get($direccionExtras, 'direccion'),
            $cuenta['calle'] ?? null
        );

        $municipio = $pick(
            $row->municipio ?? null,
            data_get($extras, 'municipio'),
            data_get($direccionExtras, 'ciudad'),
            $cuenta['municipio'] ?? null
        );

        $estado = $pick(
            $row->estado ?? null,
            data_get($extras, 'estado'),
            data_get($direccionExtras, 'estado'),
            $cuenta['estado'] ?? null
        );

        $pais = strtoupper($pick(
            $row->pais ?? null,
            data_get($extras, 'pais'),
            $cuenta['pais'] ?? null,
            'MEX'
        ) ?? 'MEX');

        return [
            'regimen'   => $this->normalizeFacturotopiaRegimen((string) ($row->rfc ?? ''), $regimen),
            'cp'        => $cp,
            'calle'     => $calle,
            'municipio' => $municipio,
            'estado'    => $estado,
            'pais'      => $this->normalizeFacturotopiaPais($pais),
        ];
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

    /**
     * @return array{ok:bool,message:string,operation:?string,remote_id:?string}
     */
    private function syncSingleReceptorToFacturotopia(object $row): array
    {
        /** @var FacturotopiaClient $facturotopia */
        $facturotopia = app(FacturotopiaClient::class);

        if (!$facturotopia->isApiReady()) {
            return [
                'ok' => false,
                'message' => 'Facturotopia no tiene base/token configurados.',
                'operation' => null,
                'remote_id' => null,
            ];
        }

        $payload = $this->buildFacturotopiaReceptorPayload($row);

        if ($payload === null) {
            return [
                'ok' => false,
                'message' => 'El receptor no tiene RFC o razón social válidos para sincronizar.',
                'operation' => null,
                'remote_id' => null,
            ];
        }

        $missing = [];
        if (trim((string) ($payload['regimen'] ?? '')) === '') {
            $missing[] = 'regimen';
        }
        if (trim((string) data_get($payload, 'direccion.cp', '')) === '') {
            $missing[] = 'direccion.cp';
        }

        if (!empty($missing)) {
            return [
                'ok' => false,
                'message' => 'Faltan campos obligatorios para Facturotopia: ' . implode(', ', $missing) . '.',
                'operation' => null,
                'remote_id' => null,
            ];
        }

        $extras = $this->decodeExtras($row->extras ?? null);
        $remoteId = $this->resolveRemoteReceptorId($row);

        $payload['id'] = $remoteId;

        $response = null;
        $operation = null;

        if (trim((string) data_get($extras, 'facturotopia.ext_id', '')) !== '') {
            $response = $facturotopia->updateReceptor($remoteId, $payload);

            if ((bool) ($response['ok'] ?? false)) {
                $operation = 'updated';
            } elseif ($this->facturotopiaLooksLikeNotFound($response)) {
                $response = $facturotopia->createReceptor($payload);

                if ((bool) ($response['ok'] ?? false)) {
                    $operation = 'created';
                }
            }
        } else {
            $response = $facturotopia->createReceptor($payload);

            if ((bool) ($response['ok'] ?? false)) {
                $operation = 'created';
            } elseif ($this->facturotopiaLooksLikeAlreadyExists($response)) {
                $response = $facturotopia->updateReceptor($remoteId, $payload);

                if ((bool) ($response['ok'] ?? false)) {
                    $operation = 'updated';
                }
            }
        }

        if (!is_array($response) || !((bool) ($response['ok'] ?? false))) {
            return [
                'ok' => false,
                'message' => (string) ($response['message'] ?? 'No se pudo sincronizar el receptor con Facturotopia.'),
                'operation' => null,
                'remote_id' => $remoteId !== '' ? $remoteId : null,
            ];
        }

        $remoteData = (array) ($response['data'] ?? []);
        $remoteIdResolved = trim((string) ($remoteData['id'] ?? $remoteId));

        $extras = $this->mergeFacturotopiaExtras(
            $extras,
            $remoteIdResolved !== '' ? $remoteIdResolved : $remoteId,
            $payload,
            $this->isMirrorRow($row)
        );

        DB::connection($this->cli)
            ->table($this->table)
            ->where('id', (int) $row->id)
            ->update([
                'extras'     => json_encode($extras, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        return [
            'ok' => true,
            'message' => 'Sincronizado correctamente.',
            'operation' => $operation,
            'remote_id' => $remoteIdResolved !== '' ? $remoteIdResolved : $remoteId,
        ];
    }
}