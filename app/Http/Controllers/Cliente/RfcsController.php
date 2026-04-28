<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\Billing\FacturotopiaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RfcsController extends Controller
{
    public function index()
    {
        $cuentaId = $this->cuentaId();

        $emisores = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->orderBy('rfc')
            ->get()
            ->reject(fn (SatCredential $row) => $this->isLogicallyDeleted($row))
            ->values();

        $stats = [
            'total' => $emisores->count(),
            'activos' => $emisores->filter(fn ($e) => !$this->isLogicallyDeleted($e))->count(),
            'con_csd' => $emisores->filter(fn ($e) => $this->hasCsd($e))->count(),
            'con_series' => $emisores->filter(fn ($e) => count((array) data_get($e->meta, 'series', [])) > 0)->count(),
        ];

        return view('cliente.rfcs.index', compact('emisores', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $this->validateMain($request, true);

        $cuentaId = $this->cuentaId();
        $rfc = strtoupper(trim((string) $data['rfc']));

        $exists = SatCredential::query()
            ->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->whereRaw('UPPER(rfc) = ?', [$rfc])
            ->first();

        if ($exists && !$this->isLogicallyDeleted($exists)) {
            return back()->withInput()->withErrors(['rfc' => 'Ya existe un RFC activo con ese valor.']);
        }

        $credential = $exists ?: new SatCredential();
        $credential->setConnection('mysql_clientes');

        if ($this->hasColumn('sat_credentials', 'cuenta_id')) {
            $credential->cuenta_id = $cuentaId;
        }

        if ($this->hasColumn('sat_credentials', 'account_id')) {
            $credential->account_id = $cuentaId;
        }

        $credential->rfc = $rfc;

        $this->persistCredential($credential, $request, true);

        return redirect()->route('cliente.rfcs.index')->with('ok', 'RFC registrado correctamente.');
    }

    public function show($rfc)
    {
        return response()->json([
            'ok' => true,
            'emisor' => $this->findCredential($rfc),
        ]);
    }

    public function update(Request $request, $rfc)
    {
        $credential = $this->findCredential($rfc);

        $this->validateMain($request, false);
        $this->persistCredential($credential, $request, false);

        if (
            $request->hasFile('fiel_cer') ||
            $request->hasFile('fiel_key') ||
            $request->filled('fiel_password') ||
            $request->hasFile('csd_cer') ||
            $request->hasFile('csd_key') ||
            $request->filled('csd_password') ||
            $request->filled('csd_serie') ||
            $request->filled('csd_vigencia_hasta')
        ) {
            $request->validate([
                'fiel_cer' => ['nullable', 'file', 'max:5120'],
                'fiel_key' => ['nullable', 'file', 'max:5120'],
                'fiel_password' => ['nullable', 'string', 'max:190'],
                'csd_cer' => ['nullable', 'file', 'max:5120'],
                'csd_key' => ['nullable', 'file', 'max:5120'],
                'csd_password' => ['nullable', 'string', 'max:190'],
                'csd_serie' => ['nullable', 'string', 'max:80'],
                'csd_vigencia_hasta' => ['nullable', 'date'],
            ]);

            $this->persistCertificates($credential, $request);
            $credential->save();
        }

        return redirect()->route('cliente.rfcs.index')->with('ok', 'RFC actualizado correctamente.');
    }

    public function destroy($rfc)
    {
        $credential = $this->findCredential($rfc);

        $meta = $this->meta($credential);
        $meta['is_active'] = false;
        $meta['deleted_from'] = 'rfcs_module';
        $meta['deleted_at'] = now()->toDateTimeString();

        $credential->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $credential->estatus_operativo = 'inactive';
        }

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $credential->deleted_at = now();
        }

        $credential->save();

        return redirect()->route('cliente.rfcs.index')->with('ok', 'RFC dado de baja correctamente.');
    }

    public function toggle($rfc)
    {
        $credential = $this->findCredential($rfc);

        $meta = $this->meta($credential);
        $isActive = !($meta['is_active'] ?? true);

        $meta['is_active'] = $isActive;
        $meta['updated_from'] = 'rfcs_module';
        $meta['deleted_at'] = $isActive ? null : now()->toDateTimeString();

        $credential->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $credential->estatus_operativo = $isActive ? 'pending' : 'inactive';
        }

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $credential->deleted_at = $isActive ? null : now();
        }

        $credential->save();

        return back()->with('ok', 'Estado del RFC actualizado.');
    }

    public function storeCertificados(Request $request, $rfc)
    {
        $credential = $this->findCredential($rfc);

        $request->validate([
            'fiel_cer' => ['nullable', 'file', 'max:5120'],
            'fiel_key' => ['nullable', 'file', 'max:5120'],
            'fiel_password' => ['nullable', 'string', 'max:190'],
            'csd_cer' => ['nullable', 'file', 'max:5120'],
            'csd_key' => ['nullable', 'file', 'max:5120'],
            'csd_password' => ['nullable', 'string', 'max:190'],
            'csd_serie' => ['nullable', 'string', 'max:80'],
            'csd_vigencia_hasta' => ['nullable', 'date'],
        ]);

        $this->persistCertificates($credential, $request);
        $credential->save();

        return back()->with('ok', 'Certificados actualizados correctamente.');
    }

    public function storeSerie(Request $request, $rfc)
    {
        $credential = $this->findCredential($rfc);

        $data = $request->validate([
            'serie' => ['required', 'string', 'max:20'],
            'folio_actual' => ['required', 'integer', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:120'],
            'tipo_comprobante' => ['nullable', 'string', 'max:5'],
        ]);

        $meta = $this->meta($credential);
        $series = array_values((array) data_get($meta, 'series', []));

        $series[] = [
            'id' => (string) Str::uuid(),
            'serie' => strtoupper(trim($data['serie'])),
            'folio_actual' => (int) $data['folio_actual'],
            'descripcion' => $data['descripcion'] ?? null,
            'tipo_comprobante' => $data['tipo_comprobante'] ?? 'I',
            'is_default' => count($series) === 0,
            'created_at' => now()->toDateTimeString(),
        ];

        $meta['series'] = $series;
        $credential->meta = $meta;
        $credential->save();

        return back()->with('ok', 'Serie registrada correctamente.');
    }

    public function updateSerie(Request $request, $rfc, $serie)
    {
        $credential = $this->findCredential($rfc);

        $data = $request->validate([
            'serie' => ['required', 'string', 'max:20'],
            'folio_actual' => ['required', 'integer', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:120'],
            'tipo_comprobante' => ['nullable', 'string', 'max:5'],
            'is_default' => ['nullable'],
        ]);

        $meta = $this->meta($credential);
        $series = array_values((array) data_get($meta, 'series', []));

        foreach ($series as $idx => $item) {
            if ((string) ($item['id'] ?? $idx) === (string) $serie) {
                $series[$idx]['serie'] = strtoupper(trim($data['serie']));
                $series[$idx]['folio_actual'] = (int) $data['folio_actual'];
                $series[$idx]['descripcion'] = $data['descripcion'] ?? null;
                $series[$idx]['tipo_comprobante'] = $data['tipo_comprobante'] ?? 'I';
                $series[$idx]['is_default'] = $request->boolean('is_default');
                $series[$idx]['updated_at'] = now()->toDateTimeString();
            } elseif ($request->boolean('is_default')) {
                $series[$idx]['is_default'] = false;
            }
        }

        $meta['series'] = $series;
        $credential->meta = $meta;
        $credential->save();

        return back()->with('ok', 'Serie actualizada correctamente.');
    }

    public function destroySerie($rfc, $serie)
    {
        $credential = $this->findCredential($rfc);

        $meta = $this->meta($credential);
        $meta['series'] = collect((array) data_get($meta, 'series', []))
            ->reject(fn ($item, $idx) => (string) ($item['id'] ?? $idx) === (string) $serie)
            ->values()
            ->all();

        $credential->meta = $meta;
        $credential->save();

        return back()->with('ok', 'Serie eliminada correctamente.');
    }

    private function validateMain(Request $request, bool $creating): array
    {
        return $request->validate([
            'rfc' => [$creating ? 'required' : 'nullable', 'string', 'min:12', 'max:13'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'nombre_comercial' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'sitio_web' => ['nullable', 'string', 'max:190'],
            'regimen_fiscal' => ['nullable', 'string', 'max:10'],
            'tipo_origen' => ['nullable', 'string', 'in:interno,externo'],
            'origen_detalle' => ['nullable', 'string', 'max:80'],
            'source_label' => ['nullable', 'string', 'max:120'],

            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'estado' => ['nullable', 'string', 'max:120'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'colonia' => ['nullable', 'string', 'max:160'],
            'calle' => ['nullable', 'string', 'max:190'],
            'no_exterior' => ['nullable', 'string', 'max:50'],
            'no_interior' => ['nullable', 'string', 'max:50'],

            'logo' => ['nullable', 'image', 'max:4096'],
            'color_pdf' => ['nullable', 'string', 'max:20'],
            'plantilla_pdf' => ['nullable', 'string', 'max:50'],
            'leyenda_pdf' => ['nullable', 'string', 'max:500'],
            'notas_pdf' => ['nullable', 'string', 'max:1000'],

            'correo_facturacion' => ['nullable', 'email', 'max:190'],
            'correo_cc' => ['nullable', 'string', 'max:300'],
            'correo_bcc' => ['nullable', 'string', 'max:300'],
            'correo_asunto' => ['nullable', 'string', 'max:190'],
            'correo_mensaje' => ['nullable', 'string', 'max:1000'],

            'complementos' => ['nullable', 'array'],
            'complementos.*' => ['nullable', 'string', 'max:40'],
        ]);
    }

    private function persistCredential(SatCredential $credential, Request $request, bool $creating): void
    {
        if ($creating) {
            $credential->rfc = strtoupper(trim((string) $request->input('rfc')));
        }

        if ($this->hasColumn('sat_credentials', 'razon_social')) {
            $credential->razon_social = trim((string) $request->input('razon_social')) ?: null;
        }

        if ($this->hasColumn('sat_credentials', 'tipo_origen')) {
            $credential->tipo_origen = $request->input('tipo_origen', 'interno');
        }

        if ($this->hasColumn('sat_credentials', 'origen_detalle')) {
            $credential->origen_detalle = $request->input('origen_detalle') ?: 'cliente_interno';
        }

        if ($this->hasColumn('sat_credentials', 'source_label')) {
            $credential->source_label = $request->input('source_label') ?: 'Registro interno';
        }

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $credential->estatus_operativo = $credential->estatus_operativo ?: 'pending';
        }

        $meta = $this->meta($credential);
        $meta['is_active'] = true;
        $meta['updated_from'] = 'rfcs_module';
        $meta['updated_at'] = now()->toDateTimeString();
        $meta['deleted_at'] = null;

        $meta['config_fiscal'] = [
            'nombre_comercial' => $request->input('nombre_comercial'),
            'email' => $request->input('email'),
            'telefono' => $request->input('telefono'),
            'sitio_web' => $request->input('sitio_web'),
            'regimen_fiscal' => $request->input('regimen_fiscal'),
            'tipo_origen' => $request->input('tipo_origen', 'interno'),
            'origen_detalle' => $request->input('origen_detalle'),
            'source_label' => $request->input('source_label'),
        ];

        $meta['direccion'] = [
            'codigo_postal' => $request->input('codigo_postal'),
            'estado' => $request->input('estado'),
            'municipio' => $request->input('municipio'),
            'colonia' => $request->input('colonia'),
            'calle' => $request->input('calle'),
            'no_exterior' => $request->input('no_exterior'),
            'no_interior' => $request->input('no_interior'),
        ];

        $branding = (array) data_get($meta, 'branding', []);
        if ($request->hasFile('logo')) {
            $branding['logo_path'] = $request->file('logo')->storeAs(
                'sat/rfcs/'.$this->cuentaId().'/'.$credential->rfc.'/branding',
                'logo_'.now()->format('Ymd_His').'.'.$request->file('logo')->getClientOriginalExtension(),
                'public'
            );
        }

        $branding['color_pdf'] = $request->input('color_pdf', $branding['color_pdf'] ?? '#2563eb');
        $branding['plantilla_pdf'] = $request->input('plantilla_pdf', $branding['plantilla_pdf'] ?? 'moderna');
        $branding['leyenda_pdf'] = $request->input('leyenda_pdf', $branding['leyenda_pdf'] ?? null);
        $branding['notas_pdf'] = $request->input('notas_pdf', $branding['notas_pdf'] ?? null);
        $meta['branding'] = $branding;

        $meta['email'] = [
            'correo_facturacion' => $request->input('correo_facturacion'),
            'correo_cc' => $request->input('correo_cc'),
            'correo_bcc' => $request->input('correo_bcc'),
            'correo_asunto' => $request->input('correo_asunto', 'Envío de CFDI'),
            'correo_mensaje' => $request->input('correo_mensaje', 'Adjunto encontrará su CFDI en PDF y XML.'),
            'adjuntar_pdf' => $request->boolean('adjuntar_pdf', true),
            'adjuntar_xml' => $request->boolean('adjuntar_xml', true),
            'enviar_copia_emisor' => $request->boolean('enviar_copia_emisor'),
        ];

        $meta['complementos'] = array_values((array) $request->input('complementos', data_get($meta, 'complementos', [])));

        $meta['audit'] = [
            'last_updated_by' => optional(Auth::user())->email ?? optional(Auth::user())->id,
            'last_updated_at' => now()->toDateTimeString(),
        ];

        $credential->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'deleted_at')) {
            $credential->deleted_at = null;
        }

        $credential->save();
    }

    private function persistCertificates(SatCredential $credential, Request $request): void
    {
        $baseDir = 'sat/rfcs/'.$this->cuentaId().'/'.$credential->rfc;

        if ($request->hasFile('fiel_cer') && $request->hasFile('fiel_key') && $request->filled('fiel_password')) {
            $credential->fiel_cer_path = $request->file('fiel_cer')->storeAs($baseDir.'/fiel', 'fiel_'.now()->format('Ymd_His').'.cer', 'private');
            $credential->fiel_key_path = $request->file('fiel_key')->storeAs($baseDir.'/fiel', 'fiel_'.now()->format('Ymd_His').'.key', 'private');

            if ($this->hasColumn('sat_credentials', 'fiel_password_enc')) {
                $credential->fiel_password_enc = encrypt($request->input('fiel_password'));
            }

            if ($this->hasColumn('sat_credentials', 'cer_path')) {
                $credential->cer_path = $credential->fiel_cer_path;
            }

            if ($this->hasColumn('sat_credentials', 'key_path')) {
                $credential->key_path = $credential->fiel_key_path;
            }

            if (method_exists($credential, 'setEncryptedKeyPassword')) {
                $credential->setEncryptedKeyPassword($request->input('fiel_password'));
            }
        }

        if ($request->hasFile('csd_cer') && $request->hasFile('csd_key') && $request->filled('csd_password')) {
            $credential->csd_cer_path = $request->file('csd_cer')->storeAs($baseDir.'/csd', 'csd_'.now()->format('Ymd_His').'.cer', 'private');
            $credential->csd_key_path = $request->file('csd_key')->storeAs($baseDir.'/csd', 'csd_'.now()->format('Ymd_His').'.key', 'private');

            if ($this->hasColumn('sat_credentials', 'csd_password_enc')) {
                $credential->csd_password_enc = encrypt($request->input('csd_password'));
            }
        }

        $meta = $this->meta($credential);

        if ($request->filled('csd_serie')) {
            $meta['csd_serie'] = $request->input('csd_serie');
        }

        if ($request->filled('csd_vigencia_hasta')) {
            $meta['csd_vigencia_hasta'] = $request->input('csd_vigencia_hasta');
        }

        if (!empty($credential->fiel_cer_path) || !empty($credential->cer_path)) {
            $meta['fiel'] = [
                'cer' => basename((string) ($credential->fiel_cer_path ?? $credential->cer_path)),
                'key' => basename((string) ($credential->fiel_key_path ?? $credential->key_path)),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        if (!empty($credential->csd_cer_path) || !empty($credential->csd_key_path)) {
            $meta['csd'] = [
                'cer' => basename((string) $credential->csd_cer_path),
                'key' => basename((string) $credential->csd_key_path),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        $credential->meta = $meta;
    }

    private function findCredential($id): SatCredential
    {
        return SatCredential::query()
            ->where(function ($q) {
                $cuentaId = $this->cuentaId();
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            })
            ->where('id', $id)
            ->firstOrFail();
    }

    private function cuentaId(): string
    {
        $user = Auth::user();

        return (string) (
            $user->cuenta_id
            ?? session('cuenta_id')
            ?? session('cliente_cuenta_id')
            ?? $user->id
        );
    }

    private function hasCsd(SatCredential $row): bool
    {
        $meta = $this->meta($row);

        return (!empty($row->csd_cer_path ?? null) && !empty($row->csd_key_path ?? null))
            || isset($meta['csd']);
    }

    private function isLogicallyDeleted(SatCredential $credential): bool
    {
        $meta = $this->meta($credential);

        if (($meta['is_active'] ?? true) === false || ($meta['is_active'] ?? true) === '0') {
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

    private function meta(SatCredential $credential): array
    {
        return is_array($credential->meta) ? $credential->meta : [];
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    public function syncFacturotopia(Request $request, $rfc, FacturotopiaService $facturotopia)
{
    $credential = $this->findCredential($rfc);

    $env = strtolower((string) $request->input('env', 'sandbox'));
    $env = in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';

    $adminAccountId = $this->adminAccountId();

    if (!$adminAccountId) {
        return back()->withErrors([
            'facturotopia' => 'No se pudo resolver la cuenta admin para conectar Facturotopia.',
        ]);
    }

    $meta = $this->meta($credential);
    $facturotopiaMeta = (array) data_get($meta, 'facturotopia', []);
    $currentEmisorId = (string) data_get($facturotopiaMeta, "{$env}.emisor_id", data_get($facturotopiaMeta, 'emisor_id', ''));

    $payload = $this->buildFacturotopiaEmisorPayload($credential);

    try {
        $result = $currentEmisorId !== ''
            ? $facturotopia->actualizarEmisor($adminAccountId, $currentEmisorId, $payload, $env)
            : $facturotopia->registerEmisor($adminAccountId, $payload, $env);

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        if (!($result['ok'] ?? false)) {
            Log::warning('Rfcs.facturotopia.sync.error', [
                'rfc' => $credential->rfc,
                'env' => $env,
                'status' => $result['status'] ?? null,
                'body' => $result['body'] ?? null,
            ]);

            return back()->withErrors([
                'facturotopia' => 'Facturotopia rechazó el emisor. HTTP: ' . ($result['status'] ?? 'N/D') . '. ' . mb_substr((string) ($result['body'] ?? ''), 0, 250),
            ]);
        }

        $emisorId = (string) (
            data_get($data, 'id')
            ?: data_get($data, 'emisor.id')
            ?: data_get($data, 'data.id')
            ?: data_get($data, 'result.id')
            ?: $payload['id']
        );

        $facturotopiaMeta[$env] = array_merge((array) data_get($facturotopiaMeta, $env, []), [
            'emisor_id' => $emisorId,
            'synced_at' => now()->toDateTimeString(),
            'status' => 'synced',
            'last_http_status' => $result['status'] ?? null,
        ]);

        $facturotopiaMeta['emisor_id'] = $emisorId;
        $facturotopiaMeta['env'] = $env;
        $facturotopiaMeta['last_response'] = $data;

        $meta['facturotopia'] = $facturotopiaMeta;
        $meta['updated_at'] = now()->toDateTimeString();

        $credential->meta = $meta;

        if ($this->hasColumn('sat_credentials', 'estatus_operativo')) {
            $credential->estatus_operativo = 'active';
        }

        $credential->save();

        return back()->with('ok', 'RFC emisor sincronizado con Facturotopia correctamente.');
    } catch (\Throwable $e) {
        Log::error('Rfcs.facturotopia.sync.exception', [
            'rfc' => $credential->rfc,
            'env' => $env,
            'error' => $e->getMessage(),
        ]);

        return back()->withErrors([
            'facturotopia' => 'No se pudo sincronizar con Facturotopia: ' . $e->getMessage(),
        ]);
    }
}

private function buildFacturotopiaEmisorPayload(SatCredential $credential): array
{
    $meta = $this->meta($credential);
    $fiscal = (array) data_get($meta, 'config_fiscal', []);
    $direccion = (array) data_get($meta, 'direccion', []);

    $rfc = strtoupper((string) $credential->rfc);
    $razonSocial = trim((string) ($credential->razon_social ?: data_get($fiscal, 'nombre_comercial') ?: $rfc));
    $regimen = (string) (data_get($fiscal, 'regimen_fiscal') ?: '601');
    $email = (string) (data_get($fiscal, 'email') ?: data_get($meta, 'email.correo_facturacion') ?: 'soporte@pactopia.com');

    $payload = [
        'id' => (string) $credential->id,
        'razon_social' => $razonSocial,
        'grupo' => 'pactopia360',
        'rfc' => $rfc,
        'regimen' => is_numeric($regimen) ? (int) $regimen : $regimen,
        'email' => $email,
        'direccion' => [
            'cp' => (string) (data_get($direccion, 'codigo_postal') ?: '00000'),
            'direccion' => trim(implode(' ', array_filter([
                data_get($direccion, 'calle'),
                data_get($direccion, 'no_exterior'),
                data_get($direccion, 'no_interior') ? 'Int. ' . data_get($direccion, 'no_interior') : null,
                data_get($direccion, 'colonia') ? 'Col. ' . data_get($direccion, 'colonia') : null,
            ]))) ?: 'Dirección no configurada',
            'ciudad' => (string) (data_get($direccion, 'municipio') ?: 'Ciudad'),
            'estado' => (string) (data_get($direccion, 'estado') ?: 'Estado'),
        ],
    ];

    $certificados = $this->buildFacturotopiaCertificadosPayload($credential);

    if (!empty($certificados)) {
        $payload['certificados'] = $certificados;
    }

    return $payload;
}

private function buildFacturotopiaCertificadosPayload(SatCredential $credential): array
{
    $certificados = [];

    $csdCerPath = (string) ($credential->csd_cer_path ?? '');
    $csdKeyPath = (string) ($credential->csd_key_path ?? '');
    $csdPassword = '';

    if ($this->hasColumn('sat_credentials', 'csd_password_enc') && !empty($credential->csd_password_enc)) {
        try {
            $csdPassword = decrypt($credential->csd_password_enc);
        } catch (\Throwable $e) {
            $csdPassword = '';
        }
    }

    if ($csdCerPath !== '' && Storage::disk('private')->exists($csdCerPath)) {
        $certificados['csd_cer'] = base64_encode(Storage::disk('private')->get($csdCerPath));
    }

    if ($csdKeyPath !== '' && Storage::disk('private')->exists($csdKeyPath)) {
        $certificados['csd_key'] = base64_encode(Storage::disk('private')->get($csdKeyPath));
    }

    if ($csdPassword !== '') {
        $certificados['csd_password'] = $csdPassword;
    }

    return $certificados;
}

private function adminAccountId(): ?int
{
    $user = Auth::user();
    $cuenta = $user?->cuenta;

    if (!empty($cuenta?->admin_account_id)) {
        return (int) $cuenta->admin_account_id;
    }

    foreach ([
        'client.admin_account_id',
        'admin_account_id',
        'client.account_id',
        'account_id',
        'p360.account_id',
    ] as $key) {
        $value = session($key);
        if (!empty($value) && is_numeric($value)) {
            return (int) $value;
        }
    }

    $cuentaId = (string) ($cuenta->id ?? $this->cuentaId());

    if ($cuentaId === '') {
        return null;
    }

    try {
        if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            $row = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->first(['admin_account_id']);

            if ($row && !empty($row->admin_account_id)) {
                return (int) $row->admin_account_id;
            }
        }
    } catch (\Throwable $e) {
        Log::warning('Rfcs.adminAccountId.error', [
            'error' => $e->getMessage(),
            'cuenta_id' => $cuentaId,
        ]);
    }

    return null;
}
}