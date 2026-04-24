<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\Cfdi;
use App\Models\Cliente\CfdiConcepto;
use App\Models\Cliente\Emisor;
use App\Models\Cliente\Receptor;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Cliente\SepomexCodigoPostal;

class FacturacionController extends Controller
{
    private const REGIMENES_FISCALES = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '610' => 'Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => 'Ingresos por Dividendos',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '620' => 'Sociedades Cooperativas de Producción',
        '621' => 'Incorporación Fiscal',
        '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => 'Opcional para Grupos de Sociedades',
        '624' => 'Coordinados',
        '625' => 'Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
    ];

    private const USOS_CFDI = [
        'G01' => 'Adquisición de mercancías',
        'G02' => 'Devoluciones, descuentos o bonificaciones',
        'G03' => 'Gastos en general',
        'I01' => 'Construcciones',
        'I02' => 'Mobiliario y equipo de oficina',
        'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de cómputo',
        'I05' => 'Dados, troqueles, moldes, matrices y herramental',
        'I06' => 'Comunicaciones telefónicas',
        'I07' => 'Comunicaciones satelitales',
        'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios',
        'D02' => 'Gastos médicos por incapacidad o discapacidad',
        'D03' => 'Gastos funerales',
        'D04' => 'Donativos',
        'D05' => 'Intereses reales pagados por créditos hipotecarios',
        'D06' => 'Aportaciones voluntarias al SAR',
        'D07' => 'Primas por seguros de gastos médicos',
        'D08' => 'Gastos de transportación escolar',
        'D09' => 'Depósitos en cuentas para el ahorro',
        'D10' => 'Pagos por servicios educativos',
        'CP01' => 'Pagos',
        'CN01' => 'Nómina',
        'S01' => 'Sin efectos fiscales',
    ];

    private const FORMAS_PAGO = [
        '01' => 'Efectivo',
        '02' => 'Cheque nominativo',
        '03' => 'Transferencia electrónica de fondos',
        '04' => 'Tarjeta de crédito',
        '05' => 'Monedero electrónico',
        '06' => 'Dinero electrónico',
        '08' => 'Vales de despensa',
        '12' => 'Dación en pago',
        '13' => 'Pago por subrogación',
        '14' => 'Pago por consignación',
        '15' => 'Condonación',
        '17' => 'Compensación',
        '23' => 'Novación',
        '24' => 'Confusión',
        '25' => 'Remisión de deuda',
        '26' => 'Prescripción o caducidad',
        '27' => 'A satisfacción del acreedor',
        '28' => 'Tarjeta de débito',
        '29' => 'Tarjeta de servicios',
        '30' => 'Aplicación de anticipos',
        '31' => 'Intermediario pagos',
        '99' => 'Por definir',
    ];

    private const METODOS_PAGO = [
        'PUE' => 'Pago en una sola exhibición',
        'PPD' => 'Pago en parcialidades o diferido',
    ];

    private const TIPOS_CFDI = [
        'I' => 'Ingreso',
        'E' => 'Egreso / Nota de crédito',
        'T' => 'Traslado',
        'P' => 'Pago / REP',
        'N' => 'Nómina',
    ];

    private const RELACIONES_CFDI = [
        '01' => 'Nota de crédito de los documentos relacionados',
        '02' => 'Nota de débito de los documentos relacionados',
        '03' => 'Devolución de mercancía sobre facturas o traslados previos',
        '04' => 'Sustitución de los CFDI previos',
        '05' => 'Traslados de mercancías facturados previamente',
        '06' => 'Factura generada por los traslados previos',
        '07' => 'CFDI por aplicación de anticipo',
    ];

    private const CP_DEMO = [
        '77500' => [
            'estado' => 'Quintana Roo',
            'municipio' => 'Benito Juárez',
            'colonias' => ['Centro', 'Supermanzana 2', 'Supermanzana 5', 'Zona Hotelera'],
        ],
        '03020' => [
            'estado' => 'Ciudad de México',
            'municipio' => 'Benito Juárez',
            'colonias' => ['Narvarte Poniente', 'Narvarte Oriente'],
        ],
        '64000' => [
            'estado' => 'Nuevo León',
            'municipio' => 'Monterrey',
            'colonias' => ['Centro', 'Obispado'],
        ],
        '44100' => [
            'estado' => 'Jalisco',
            'municipio' => 'Guadalajara',
            'colonias' => ['Centro', 'Americana'],
        ],
        '72000' => [
            'estado' => 'Puebla',
            'municipio' => 'Puebla',
            'colonias' => ['Centro', 'San Francisco'],
        ],
    ];

    protected function tableExists(string $table, string $conn): bool
    {
        try {
            return Schema::connection($conn)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function hasColumn(string $table, string $col, string $conn): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function firstConnWith(string $table, array $order = ['mysql', 'mysql_clientes']): ?string
    {
        foreach ($order as $conn) {
            if ($this->tableExists($table, $conn)) {
                return $conn;
            }
        }

        return null;
    }

    protected function cfdiConn(): string
    {
        try {
            return (new Cfdi)->getConnectionName() ?: 'mysql';
        } catch (\Throwable $e) {
            return 'mysql';
        }
    }

    protected function currentCuenta(): ?object
    {
        try {
            $user = Auth::guard('web')->user() ?: Auth::guard('cliente')->user();
        } catch (\Throwable $e) {
            $user = null;
        }

        $cuenta = $user?->cuenta ?? null;

        if (is_array($cuenta)) {
            $cuenta = (object) $cuenta;
        }

        if (!$cuenta || empty($cuenta->id)) {
            return null;
        }

        return $cuenta;
    }
    protected function fiscalCatalogs(): array
    {
        return [
            'regimenes_fiscales' => self::REGIMENES_FISCALES,
            'usos_cfdi' => self::USOS_CFDI,
            'formas_pago' => self::FORMAS_PAGO,
            'metodos_pago' => self::METODOS_PAGO,
            'tipos_cfdi' => self::TIPOS_CFDI,
            'relaciones_cfdi' => self::RELACIONES_CFDI,
        ];
    }

    protected function normalizeRfc(?string $rfc): string
    {
        return strtoupper(preg_replace('/[^A-ZÑ&0-9]/i', '', trim((string) $rfc)));
    }

    protected function personaTipoDesdeRfc(?string $rfc): string
    {
        $rfc = $this->normalizeRfc($rfc);

        return strlen($rfc) === 12 ? 'moral' : (strlen($rfc) === 13 ? 'fisica' : 'desconocida');
    }

    protected function suggestRegimenesForRfc(?string $rfc): array
    {
        return match ($this->personaTipoDesdeRfc($rfc)) {
            'moral' => ['601', '603', '620', '623', '624'],
            'fisica' => ['612', '626', '606', '605', '621', '625'],
            default => ['601', '612', '626'],
        };
    }

    protected function fiscalAssistant(array $payload): array
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];
        $smartDefaults = [];

        $rfc = $this->normalizeRfc($payload['rfc'] ?? $payload['receptor_rfc'] ?? '');
        $tipoPersona = $this->personaTipoDesdeRfc($rfc);
        $regimen = trim((string) ($payload['regimen_fiscal'] ?? $payload['regimen_receptor'] ?? ''));
        $cp = trim((string) ($payload['codigo_postal'] ?? $payload['cp_receptor'] ?? ''));
        $uso = trim((string) ($payload['uso_cfdi'] ?? ''));
        $metodo = strtoupper(trim((string) ($payload['metodo_pago'] ?? '')));
        $forma = strtoupper(trim((string) ($payload['forma_pago'] ?? '')));
        $tipoCfdi = strtoupper(trim((string) ($payload['tipo_documento'] ?? $payload['tipo_comprobante'] ?? 'I')));
        $total = (float) ($payload['total'] ?? 0);

        if ($rfc !== '' && !in_array(strlen($rfc), [12, 13], true)) {
            $errors[] = 'El RFC no tiene longitud válida SAT. Debe tener 12 caracteres para persona moral o 13 para persona física.';
        }

        if ($regimen !== '' && !array_key_exists($regimen, self::REGIMENES_FISCALES)) {
            $warnings[] = "El régimen fiscal {$regimen} no está dentro del catálogo fiscal cargado.";
        }

        if ($regimen === '') {
            $suggested = $this->suggestRegimenesForRfc($rfc);
            $smartDefaults['regimen_fiscal_sugerido'] = $suggested[0] ?? '';
            $suggestions[] = 'Selecciona el régimen fiscal del receptor antes de timbrar. El sistema puede sugerirlo por tipo de RFC.';
        }

        if ($cp !== '' && !preg_match('/^\d{5}$/', $cp)) {
            $errors[] = 'El código postal fiscal debe tener 5 dígitos.';
        }

        if ($cp === '') {
            $suggestions[] = 'Captura el código postal fiscal del receptor; CFDI 4.0 lo necesita para evitar rechazos.';
        }

        if ($uso === '') {
            $smartDefaults['uso_cfdi_sugerido'] = $tipoCfdi === 'P' ? 'CP01' : 'G03';
            $suggestions[] = 'Se sugiere uso CFDI G03 para operación general, o CP01 si estás generando complemento de pago.';
        }

        if ($tipoCfdi === 'P') {
            $smartDefaults['uso_cfdi_sugerido'] = 'CP01';
            $smartDefaults['metodo_pago_sugerido'] = 'PPD';
            $smartDefaults['forma_pago_sugerida'] = '99';
            $suggestions[] = 'Para CFDI de pago/REP, usa CP01 y relaciona facturas PPD con saldo pendiente.';
        }

        if ($metodo === 'PPD' && $forma !== '99') {
            $smartDefaults['forma_pago_sugerida'] = '99';
            $warnings[] = 'Cuando el método es PPD, la forma de pago debe quedar como 99 Por definir en la factura origen.';
        }

        if ($metodo === 'PUE' && $forma === '99') {
            $warnings[] = 'Si el método es PUE, evita usar forma de pago 99 salvo que realmente no se conozca al emitir.';
        }

        if ($tipoCfdi === 'E') {
            $suggestions[] = 'Para nota de crédito valida si debe relacionarse con el CFDI origen mediante relación 01.';
        }

        if ($tipoCfdi === 'I' && $total > 0 && $metodo === 'PPD') {
            $suggestions[] = 'Esta factura quedará pendiente de Complemento de Pago REP 2.0 cuando el cliente pague.';
        }

        $score = 100;
        $score -= count($errors) * 25;
        $score -= count($warnings) * 10;
        $score -= count($suggestions) * 3;
        $score = max(0, min(100, $score));

        return [
            'ok' => empty($errors),
            'score' => $score,
            'nivel' => $score >= 90 ? 'excelente' : ($score >= 75 ? 'bueno' : ($score >= 55 ? 'revisar' : 'riesgo')),
            'tipo_persona' => $tipoPersona,
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'suggestions' => array_values($suggestions),
            'smart_defaults' => $smartDefaults,
            'catalog_hint' => [
                'regimenes_sugeridos' => $this->suggestRegimenesForRfc($rfc),
            ],
        ];
    }

    protected function resolvePeriod(Request $request): array
    {
        $month = trim((string) $request->input('month', ''));
        $mes = $request->input('mes');
        $anio = $request->input('anio');
        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $f = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $t = $f->copy()->endOfMonth();

            return [$f->toDateTimeString(), $t->toDateTimeString()];
        }

        if (is_numeric($mes) && is_numeric($anio)) {
            $f = Carbon::createFromDate((int) $anio, (int) $mes, 1)->startOfMonth();
            $t = $f->copy()->endOfMonth();

            return [$f->toDateTimeString(), $t->toDateTimeString()];
        }

        if ($from !== '' && $to !== '') {
            return [
                Carbon::parse($from)->startOfDay()->toDateTimeString(),
                Carbon::parse($to)->endOfDay()->toDateTimeString(),
            ];
        }

        return [now()->startOfMonth()->toDateTimeString(), now()->endOfMonth()->toDateTimeString()];
    }

    protected function cfdiBaseQuery(Request $request)
    {
        $conn = $this->cfdiConn();
        $q = Cfdi::on($conn);
        $cuenta = $this->currentCuenta();

        $emiConn = $this->firstConnWith('emisores', ['mysql_clientes', 'mysql']) ?? $conn;

        if ($cuenta && $this->hasColumn('emisores', 'cuenta_id', $emiConn)) {
            try {
                $ids = Emisor::on($emiConn)
                    ->where('cuenta_id', $cuenta->id)
                    ->pluck('id')
                    ->all();
            } catch (\Throwable $e) {
                $ids = [];
            }

            $q->whereIn('cliente_id', empty($ids) ? [-1] : $ids);
        }

        return $q;
    }

    protected function applyFilters($q, Request $request)
    {
        $qStr = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $metodo = trim((string) $request->input('metodo_pago', ''));
        $tipo = trim((string) $request->input('tipo_documento', ''));

        if ($qStr !== '') {
            $q->where(function ($w) use ($qStr) {
                $w->where('uuid', 'like', "%{$qStr}%")
                    ->orWhere('serie', 'like', "%{$qStr}%")
                    ->orWhere('folio', 'like', "%{$qStr}%");
            });
        }

        if ($status !== '') {
            $q->where('estatus', $status);
        }

        if ($metodo !== '') {
            $q->where('metodo_pago', $metodo);
        }

        if ($tipo !== '') {
            $q->where(function ($w) use ($tipo) {
                $w->where('tipo_documento', $tipo)->orWhere('tipo_comprobante', $tipo);
            });
        }

        return $q;
    }

    protected function safeList(
        string $table,
        array $columns,
        ?string $orderBy = null,
        int $limit = 200,
        ?string $conn = null,
        ?callable $scope = null
    ): Collection {
        $connToUse = $conn ?: $this->firstConnWith($table);

        if (!$connToUse || !$this->tableExists($table, $connToUse)) {
            return collect();
        }

        try {
            $available = Schema::connection($connToUse)->getColumnListing($table);
            $columns = array_values(array_filter($columns, fn ($col) => in_array($col, $available, true)));

            $q = DB::connection($connToUse)->table($table);

            if ($scope) {
                $scope($q);
            }

            if ($orderBy) {
                $q->orderByRaw($orderBy);
            }

            if ($limit > 0) {
                $q->limit($limit);
            }

            return $q->get($columns ?: ['*']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function onlyExistingColumnsForInsert(string $table, string $conn, array $payload): array
    {
        try {
            $columns = Schema::connection($conn)->getColumnListing($table);
        } catch (\Throwable $e) {
            return $payload;
        }

        return collect($payload)
            ->filter(fn ($value, string $key) => in_array($key, $columns, true))
            ->all();
    }

    public function index(Request $request): View
    {
        [$from, $to] = $this->resolvePeriod($request);
        $base = $this->cfdiBaseQuery($request);

        $q = $this->applyFilters(
            (clone $base)->whereBetween('fecha', [$from, $to]),
            $request
        )->with([
            'cliente:id,razon_social,nombre_comercial,rfc',
            'conceptos:id,cfdi_id,descripcion',
        ]);

        $perPage = (int) $request->integer('per_page', 15);

        $cfdis = $q->orderByDesc('fecha')
            ->paginate($perPage, [
                'id',
                'uuid',
                'serie',
                'folio',
                'subtotal',
                'iva',
                'total',
                'fecha',
                'estatus',
                'cliente_id',
                'receptor_id',
                'metodo_pago',
                'forma_pago',
                'tipo_documento',
                'tipo_comprobante',
            ])
            ->withQueryString();

        $current = $cfdis->getCollection()->first();

        if (!$current) {
            $current = (object) [
                'id' => null,
                'uuid' => null,
                'serie' => null,
                'folio' => null,
                'subtotal' => 0,
                'iva' => 0,
                'total' => 0,
                'fecha' => null,
                'estatus' => null,
                'cliente_id' => null,
            ];
        }

        $kpis = $this->calcKpis($request, $from, $to);
        $series = $this->buildSeries($request, $from, $to);

        $summary = $this->accountSummarySafe();
        $isPro = (bool) ($summary['is_pro'] ?? false);
        $plan = $isPro ? 'PRO' : 'FREE';
        $planKey = $isPro ? 'pro' : 'free';

        $accountFeatures = [
            'is_pro' => $isPro,
            'blocked' => (bool) ($summary['blocked'] ?? false),
            'cfdi_manual' => true,
            'cfdi_emitidos' => true,
            'cfdi_descargas' => true,
            'cfdi_cancelacion' => true,
            'catalogos' => true,
            'asistente_fiscal' => true,
            'validacion_inteligente' => true,
            'autollenado_receptor' => true,
            'rep_control' => true,
            'cfdi_masivo' => $isPro,
            'excel_templates' => $isPro,
            'batch_processing' => $isPro,
            'nomina_masiva' => $isPro,
            'cfdi_nomina_pro' => $isPro,
            'rep_masivo' => $isPro,
            'carta_porte_masiva' => $isPro,
            'api_integrations' => $isPro,
            'automation_rules' => $isPro,
        ];

        return view('cliente.facturacion.index', [
            'summary' => $summary,
            'plan' => $plan,
            'planKey' => $planKey,
            'isPro' => $isPro,
            'accountFeatures' => $accountFeatures,
            'period_from' => $from,
            'period_to' => $to,
            'kpis' => $kpis,
            'series' => $series,
            'cfdis' => $cfdis,
            'cfdi' => $current,
            'filters' => [
                'q' => trim((string) $request->input('q', '')),
                'status' => trim((string) $request->input('status', '')),
                'month' => trim((string) $request->input('month', '')),
                'mes' => (int) Carbon::parse($from)->format('m'),
                'anio' => (int) Carbon::parse($from)->format('Y'),
            ],
        ]);
    }

    protected function accountSummarySafe(): array
    {
        try {
            $summary = app(HomeController::class)->buildAccountSummary();
        } catch (\Throwable $e) {
            $summary = [];
        }

        $cuenta = $this->currentCuenta();
        $raw = strtolower((string) ($summary['plan_raw'] ?? $summary['plan_norm'] ?? $summary['plan'] ?? $cuenta->plan_actual ?? $cuenta->plan ?? 'free'));
        $raw = str_replace([' ', '-'], '_', trim($raw));

        $isPro = (bool) ($summary['is_pro'] ?? false)
            || in_array($raw, ['pro', 'pro_mensual', 'pro_anual', 'premium', 'empresa', 'business'], true)
            || str_starts_with($raw, 'pro_');

        $summary['is_pro'] = $isPro;
        $summary['plan'] = $isPro ? 'PRO' : 'FREE';
        $summary['plan_norm'] = $isPro ? 'pro' : 'free';
        $summary['blocked'] = (bool) ($summary['blocked'] ?? ((int) ($cuenta->is_blocked ?? 0) === 1));
        $summary['timbres'] = (int) ($summary['timbres'] ?? $cuenta->timbres_disponibles ?? 0);

        return $summary;
    }

    public function create(Request $request): View
    {
        $cuenta = $this->currentCuenta();

        $emisores = collect();

        if ($cuenta) {
            try {
                $emisores = Emisor::query()
                    ->where('cuenta_id', $cuenta->id)
                    ->orderByRaw("COALESCE(nombre_comercial, razon_social, rfc, '') ASC")
                    ->get(['id', 'rfc', 'razon_social', 'nombre_comercial']);
            } catch (\Throwable $e) {
                $emisores = collect();
            }
        }

        $receptores = $this->safeList(
            'receptores',
            [
                'id',
                'rfc',
                'razon_social',
                'nombre_comercial',
                'uso_cfdi',
                'forma_pago',
                'metodo_pago',
                'regimen_fiscal',
                'codigo_postal',
                'pais',
                'estado',
                'municipio',
                'colonia',
                'calle',
                'no_ext',
                'no_int',
                'email',
                'telefono',
            ],
            "COALESCE(razon_social, nombre_comercial, rfc, '') ASC",
            400,
            null,
            function ($q) use ($cuenta) {
                $conn = $q->getConnection()->getName();

                if ($cuenta && $this->hasColumn('receptores', 'cuenta_id', $conn)) {
                    $q->where('cuenta_id', $cuenta->id);
                }
            }
        );

        $productos = $this->safeList(
            'productos',
            ['id', 'sku', 'descripcion', 'precio_unitario', 'iva_tasa', 'cuenta_id'],
            "descripcion ASC",
            400,
            null,
            function ($q) use ($cuenta) {
                $conn = $q->getConnection()->getName();

                if ($cuenta && $this->hasColumn('productos', 'cuenta_id', $conn)) {
                    $q->where('cuenta_id', $cuenta->id);
                }
            }
        );

        return view('cliente.facturacion.create', [
            'emisores' => $emisores,
            'receptores' => $receptores,
            'productos' => $productos,
            'fiscalCatalogs' => $this->fiscalCatalogs(),
            'fiscalAi' => [
                'enabled' => true,
                'features' => [
                    'regimen_por_rfc',
                    'cp_autollenado',
                    'validacion_ppd_rep',
                    'riesgo_cfdi',
                    'sugerencias_uso_cfdi',
                    'checklist_timbrado',
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => 'required|integer',
            'receptor_id' => 'required|integer',
            'version_cfdi' => 'nullable|string|max:10',
            'tipo_comprobante' => 'nullable|string|max:5',
            'tipo_documento' => 'nullable|string|max:5',
            'serie' => 'nullable|string|max:10',
            'folio' => 'nullable|string|max:20',
            'fecha' => 'nullable|date',
            'moneda' => 'nullable|string|max:10',
            'tipo_cambio' => 'nullable|numeric|min:0',
            'metodo_pago' => 'nullable|string|max:10',
            'forma_pago' => 'nullable|string|max:10',
            'condiciones_pago' => 'nullable|string|max:120',
            'uso_cfdi' => 'nullable|string|max:10',
            'regimen_receptor' => 'nullable|string|max:10',
            'cp_receptor' => 'nullable|string|max:10',
            'tipo_relacion' => 'nullable|string|max:10',
            'uuid_relacionado' => 'nullable|string|max:60',
            'observaciones' => 'nullable|string|max:2000',
            'conceptos' => 'required|array|min:1',
            'conceptos.*.producto_id' => 'nullable|integer',
            'conceptos.*.descripcion' => 'required|string|max:500',
            'conceptos.*.cantidad' => 'required|numeric|min:0.0001',
            'conceptos.*.precio_unitario' => 'required|numeric|min:0',
            'conceptos.*.iva_tasa' => 'nullable|numeric|min:0',
            'conceptos.*.descuento' => 'nullable|numeric|min:0',
            'conceptos.*.clave_producto_sat' => 'nullable|string|max:20',
            'conceptos.*.clave_unidad_sat' => 'nullable|string|max:20',
            'conceptos.*.objeto_impuesto' => 'nullable|string|max:10',
        ]);

        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()
                ->withInput()
                ->withErrors([
                    'cuenta' => 'No se pudo identificar la cuenta activa del cliente.',
                ]);
        }

        $receptor = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->where('id', $data['receptor_id'])
            ->first();

        if (!$receptor) {
            return back()
                ->withInput()
                ->withErrors([
                    'receptor_id' => 'El receptor seleccionado no pertenece a esta cuenta.',
                ]);
        }

        $metodoPago = strtoupper((string) ($data['metodo_pago'] ?? 'PUE'));
        $formaPago = strtoupper((string) ($data['forma_pago'] ?? '03'));
        $tipoDocumento = strtoupper((string) ($data['tipo_documento'] ?? $data['tipo_comprobante'] ?? 'I'));

        if ($metodoPago === 'PPD') {
            $formaPago = '99';
        }

        $subtotal = 0.0;
        $descuento = 0.0;
        $iva = 0.0;
        $total = 0.0;

        foreach ($data['conceptos'] as $c) {
            $cant = (float) $c['cantidad'];
            $ppu = (float) $c['precio_unitario'];
            $desc = (float) ($c['descuento'] ?? 0);
            $tasa = isset($c['iva_tasa']) ? (float) $c['iva_tasa'] : 0.16;

            $lineSubtotal = round($cant * $ppu, 4);
            $lineDesc = min($lineSubtotal, round($desc, 4));
            $base = max(0, $lineSubtotal - $lineDesc);
            $lineIva = round($base * $tasa, 4);
            $lineTotal = round($base + $lineIva, 4);

            $subtotal += $lineSubtotal;
            $descuento += $lineDesc;
            $iva += $lineIva;
            $total += $lineTotal;
        }

        $subtotal = round($subtotal, 2);
        $descuento = round($descuento, 2);
        $iva = round($iva, 2);
        $total = round($total, 2);

        $assistant = $this->fiscalAssistant([
            'rfc' => $receptor?->rfc,
            'regimen_receptor' => $data['regimen_receptor'] ?? $receptor?->regimen_fiscal,
            'cp_receptor' => $data['cp_receptor'] ?? $receptor?->codigo_postal,
            'uso_cfdi' => $data['uso_cfdi'] ?? $receptor?->uso_cfdi,
            'metodo_pago' => $metodoPago,
            'forma_pago' => $formaPago,
            'tipo_documento' => $tipoDocumento,
            'total' => $total,
        ]);

        if (!$assistant['ok']) {
            return back()
                ->withInput()
                ->withErrors([
                    'asistente_fiscal' => implode(' ', $assistant['errors']),
                ]);
        }

        $conn = $this->cfdiConn();
        $cfdiModel = new Cfdi;
        $conceptoModel = new CfdiConcepto;
        $cfdiTable = $cfdiModel->getTable();
        $conceptoTable = $conceptoModel->getTable();
        $uuidTemp = (string) Str::uuid();

        DB::connection($conn)->transaction(function () use (
            $conn,
            $cfdiTable,
            $conceptoTable,
            $data,
            $subtotal,
            $descuento,
            $iva,
            $total,
            $uuidTemp,
            $metodoPago,
            $formaPago,
            $tipoDocumento,
            $assistant
        ) {
            $now = now();

            $cfdiPayload = [
                'cliente_id' => $data['cliente_id'],
                'receptor_id' => $data['receptor_id'],
                'uuid' => $uuidTemp,
                'serie' => $data['serie'] ?? null,
                'folio' => $data['folio'] ?? null,
                'fecha' => $data['fecha'] ?? $now,
                'version_cfdi' => $data['version_cfdi'] ?? '4.0',
                'tipo_comprobante' => $tipoDocumento,
                'tipo_documento' => $tipoDocumento,
                'moneda' => $data['moneda'] ?? 'MXN',
                'tipo_cambio' => $data['tipo_cambio'] ?? null,
                'metodo_pago' => $metodoPago,
                'forma_pago' => $formaPago,
                'condiciones_pago' => $data['condiciones_pago'] ?? null,
                'uso_cfdi' => $data['uso_cfdi'] ?? 'G03',
                'regimen_receptor' => $data['regimen_receptor'] ?? null,
                'cp_receptor' => $data['cp_receptor'] ?? null,
                'tipo_relacion' => $data['tipo_relacion'] ?? null,
                'uuid_relacionado' => $data['uuid_relacionado'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'iva' => $iva,
                'total' => $total,
                'saldo_original' => $total,
                'saldo_pagado' => 0,
                'saldo_pendiente' => $metodoPago === 'PPD' ? $total : 0,
                'estatus' => 'borrador',
                'estado_pago' => $metodoPago === 'PPD' ? 'pendiente_rep' : 'no_requiere_rep',
                'ia_fiscal_score' => $assistant['score'],
                'ia_fiscal_nivel' => $assistant['nivel'],
                'ia_fiscal_snapshot' => json_encode($assistant, JSON_UNESCAPED_UNICODE),
                'observaciones' => $data['observaciones'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $cfdiPayload = $this->onlyExistingColumnsForInsert($cfdiTable, $conn, $cfdiPayload);

            $cfdiId = DB::connection($conn)->table($cfdiTable)->insertGetId($cfdiPayload);

            foreach ($data['conceptos'] as $c) {
                $cant = (float) $c['cantidad'];
                $ppu = (float) $c['precio_unitario'];
                $desc = (float) ($c['descuento'] ?? 0);
                $tasa = isset($c['iva_tasa']) ? (float) $c['iva_tasa'] : 0.16;

                $lineSubtotal = round($cant * $ppu, 4);
                $lineDesc = min($lineSubtotal, round($desc, 4));
                $base = max(0, $lineSubtotal - $lineDesc);
                $lineIva = round($base * $tasa, 4);
                $lineTotal = round($base + $lineIva, 4);

                $conceptoPayload = [
                    'cfdi_id' => $cfdiId,
                    'producto_id' => $c['producto_id'] ?? null,
                    'descripcion' => $c['descripcion'],
                    'cantidad' => $cant,
                    'precio_unitario' => $ppu,
                    'descuento' => round($lineDesc, 2),
                    'iva_tasa' => $tasa,
                    'subtotal' => round($lineSubtotal, 2),
                    'iva' => round($lineIva, 2),
                    'total' => round($lineTotal, 2),
                    'clave_producto_sat' => $c['clave_producto_sat'] ?? null,
                    'clave_unidad_sat' => $c['clave_unidad_sat'] ?? null,
                    'objeto_impuesto' => $c['objeto_impuesto'] ?? '02',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $conceptoPayload = $this->onlyExistingColumnsForInsert($conceptoTable, $conn, $conceptoPayload);

                DB::connection($conn)->table($conceptoTable)->insert($conceptoPayload);
            }
        });

        return redirect()
            ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
            ->with('ok', 'Borrador CFDI 4.0 creado correctamente con revisión fiscal inteligente.');
    }

    public function receptorShow(int $receptor): JsonResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo identificar la cuenta activa del cliente.',
            ], 403);
        }

        $item = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->where('id', $receptor)
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'receptor' => $this->receptorPayload($item),
        ]);
    }
    public function receptorStore(Request $request): JsonResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo identificar la cuenta del cliente.',
            ], 422);
        }

        $data = $this->validateReceptorPayload($request);
        $data['cuenta_id'] = $cuenta->id;
        $data = $this->normalizeReceptorData($data);

        $assistant = $this->fiscalAssistant($data);

        if (!$assistant['ok']) {
            return response()->json([
                'ok' => false,
                'message' => implode(' ', $assistant['errors']),
                'assistant' => $assistant,
            ], 422);
        }

        $receptor = Receptor::query()->create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Receptor agregado correctamente.',
            'assistant' => $assistant,
            'receptor' => $this->receptorPayload($receptor),
        ]);
    }

    public function receptorUpdate(Request $request, int $receptor): JsonResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo identificar la cuenta del cliente.',
            ], 422);
        }

        $item = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->where('id', $receptor)
            ->firstOrFail();

        $data = $this->normalizeReceptorData($this->validateReceptorPayload($request));
        $assistant = $this->fiscalAssistant($data);

        if (!$assistant['ok']) {
            return response()->json([
                'ok' => false,
                'message' => implode(' ', $assistant['errors']),
                'assistant' => $assistant,
            ], 422);
        }

        $item->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Receptor actualizado correctamente.',
            'assistant' => $assistant,
            'receptor' => $this->receptorPayload($item->fresh()),
        ]);
    }

    protected function validateReceptorPayload(Request $request): array
    {
        return $request->validate([
            'rfc' => 'required|string|min:12|max:13',
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'uso_cfdi' => 'nullable|string|max:10',
            'forma_pago' => 'nullable|string|max:10',
            'metodo_pago' => 'nullable|string|max:10',
            'regimen_fiscal' => 'nullable|string|max:10',
            'codigo_postal' => 'nullable|string|max:10',
            'pais' => 'nullable|string|max:3',
            'estado' => 'nullable|string|max:120',
            'municipio' => 'nullable|string|max:120',
            'colonia' => 'nullable|string|max:120',
            'calle' => 'nullable|string|max:180',
            'no_ext' => 'nullable|string|max:30',
            'no_int' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:180',
            'telefono' => 'nullable|string|max:40',
        ]);
    }

    protected function normalizeReceptorData(array $data): array
    {
        $data['rfc'] = $this->normalizeRfc($data['rfc'] ?? '');
        $data['razon_social'] = mb_strtoupper(trim((string) ($data['razon_social'] ?? '')), 'UTF-8');
        $data['nombre_comercial'] = trim((string) ($data['nombre_comercial'] ?? '')) ?: null;
        $data['pais'] = strtoupper(trim((string) ($data['pais'] ?? 'MEX'))) ?: 'MEX';
        $data['uso_cfdi'] = strtoupper(trim((string) ($data['uso_cfdi'] ?? 'G03'))) ?: 'G03';
        $data['metodo_pago'] = strtoupper(trim((string) ($data['metodo_pago'] ?? ''))) ?: null;
        $data['forma_pago'] = strtoupper(trim((string) ($data['forma_pago'] ?? ''))) ?: null;
        $data['regimen_fiscal'] = trim((string) ($data['regimen_fiscal'] ?? '')) ?: null;
        $data['codigo_postal'] = preg_replace('/\D+/', '', (string) ($data['codigo_postal'] ?? '')) ?: null;

        return $data;
    }

    protected function receptorPayload(Receptor $r): array
    {
        $assistant = $this->fiscalAssistant([
            'rfc' => $r->rfc,
            'regimen_fiscal' => $r->regimen_fiscal,
            'codigo_postal' => $r->codigo_postal,
            'uso_cfdi' => $r->uso_cfdi,
            'metodo_pago' => $r->metodo_pago,
            'forma_pago' => $r->forma_pago,
        ]);

        return [
            'id' => $r->id,
            'rfc' => $r->rfc,
            'razon_social' => $r->razon_social,
            'nombre_comercial' => $r->nombre_comercial,
            'uso_cfdi' => $r->uso_cfdi,
            'forma_pago' => $r->forma_pago,
            'metodo_pago' => $r->metodo_pago,
            'regimen_fiscal' => $r->regimen_fiscal,
            'regimen_fiscal_label' => $r->regimen_fiscal ? ($r->regimen_fiscal . ' · ' . (self::REGIMENES_FISCALES[$r->regimen_fiscal] ?? '')) : null,
            'codigo_postal' => $r->codigo_postal,
            'pais' => $r->pais,
            'estado' => $r->estado,
            'municipio' => $r->municipio,
            'colonia' => $r->colonia,
            'calle' => $r->calle,
            'no_ext' => $r->no_ext,
            'no_int' => $r->no_int,
            'email' => $r->email,
            'telefono' => $r->telefono,
            'label' => trim(($r->razon_social ?: $r->nombre_comercial ?: 'Receptor') . ' · ' . $r->rfc),
            'assistant' => $assistant,
        ];
    }

    public function assistant(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'assistant' => $this->fiscalAssistant($request->all()),
            'catalogs' => $this->fiscalCatalogs(),
        ]);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'catalogs' => $this->fiscalCatalogs(),
        ]);
    }

    public function postalCode(string $cp): JsonResponse
    {
        $cp = preg_replace('/\D+/', '', $cp);

        if (!preg_match('/^\d{5}$/', $cp)) {
            return response()->json([
                'ok' => false,
                'message' => 'Código postal inválido.',
            ], 422);
        }

        $rows = SepomexCodigoPostal::query()
            ->activo()
            ->cp($cp)
            ->orderBy('estado')
            ->orderBy('municipio')
            ->orderBy('colonia')
            ->get([
                'codigo_postal',
                'estado',
                'municipio',
                'ciudad',
                'colonia',
                'tipo_asentamiento',
                'estado_clave',
                'municipio_clave',
                'zona',
            ]);

        if ($rows->isEmpty()) {
            return response()->json([
                'ok' => true,
                'found' => false,
                'cp' => $cp,
                'message' => 'Código postal no encontrado en el catálogo SEPOMEX local.',
                'estado' => null,
                'municipio' => null,
                'ciudad' => null,
                'colonias' => [],
                'items' => [],
            ]);
        }

        $first = $rows->first();

        return response()->json([
            'ok' => true,
            'found' => true,
            'cp' => $cp,
            'estado' => $first->estado,
            'municipio' => $first->municipio,
            'ciudad' => $first->ciudad,
            'colonias' => $rows->pluck('colonia')->filter()->unique()->values(),
            'items' => $rows->map(fn ($row) => [
                'codigo_postal' => $row->codigo_postal,
                'estado' => $row->estado,
                'municipio' => $row->municipio,
                'ciudad' => $row->ciudad,
                'colonia' => $row->colonia,
                'tipo_asentamiento' => $row->tipo_asentamiento,
                'estado_clave' => $row->estado_clave,
                'municipio_clave' => $row->municipio_clave,
                'zona' => $row->zona,
            ])->values(),
        ]);
    }

    public function locationCountries(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'countries' => [
                [
                    'code' => 'MEX',
                    'name' => 'México',
                ],
            ],
        ]);
    }

    public function locationStates(): JsonResponse
    {
        $states = SepomexCodigoPostal::query()
            ->activo()
            ->select('estado')
            ->whereNotNull('estado')
            ->groupBy('estado')
            ->orderBy('estado')
            ->pluck('estado')
            ->values();

        return response()->json([
            'ok' => true,
            'states' => $states,
        ]);
    }

    public function locationMunicipalities(Request $request): JsonResponse
    {
        $estado = trim((string) $request->query('estado', ''));

        if ($estado === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Selecciona un estado.',
                'municipalities' => [],
            ], 422);
        }

        $municipalities = SepomexCodigoPostal::query()
            ->activo()
            ->where('estado', $estado)
            ->select('municipio')
            ->whereNotNull('municipio')
            ->groupBy('municipio')
            ->orderBy('municipio')
            ->pluck('municipio')
            ->values();

        return response()->json([
            'ok' => true,
            'estado' => $estado,
            'municipalities' => $municipalities,
        ]);
    }

    public function locationColonies(Request $request): JsonResponse
    {
        $cp = preg_replace('/\D+/', '', (string) $request->query('cp', ''));
        $estado = trim((string) $request->query('estado', ''));
        $municipio = trim((string) $request->query('municipio', ''));

        $query = SepomexCodigoPostal::query()
            ->activo()
            ->select([
                'codigo_postal',
                'estado',
                'municipio',
                'ciudad',
                'colonia',
                'tipo_asentamiento',
                'zona',
            ]);

        if ($cp !== '') {
            $query->where('codigo_postal', str_pad(substr($cp, 0, 5), 5, '0', STR_PAD_LEFT));
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        if ($municipio !== '') {
            $query->where('municipio', $municipio);
        }

        if ($cp === '' && $estado === '' && $municipio === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Indica código postal, estado o municipio.',
                'colonies' => [],
                'items' => [],
            ], 422);
        }

        $rows = $query
            ->orderBy('colonia')
            ->limit(500)
            ->get();

        return response()->json([
            'ok' => true,
            'colonies' => $rows->pluck('colonia')->filter()->unique()->values(),
            'items' => $rows->map(fn ($row) => [
                'codigo_postal' => $row->codigo_postal,
                'estado' => $row->estado,
                'municipio' => $row->municipio,
                'ciudad' => $row->ciudad,
                'colonia' => $row->colonia,
                'tipo_asentamiento' => $row->tipo_asentamiento,
                'zona' => $row->zona,
            ])->values(),
        ]);
    }

    public function kpis(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        return response()->json($this->calcKpis($request, $from, $to));
    }

    public function series(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        return response()->json($this->buildSeries($request, $from, $to));
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $base = $this->cfdiBaseQuery($request);

        $q = $this->applyFilters(
            (clone $base)->whereBetween('fecha', [$from, $to]),
            $request
        )->orderByDesc('fecha');

        $filename = 'cfdis_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'UUID',
                'Serie',
                'Folio',
                'Tipo',
                'Metodo',
                'Forma',
                'Subtotal',
                'IVA',
                'Total',
                'Fecha',
                'Estatus',
                'ClienteID',
                'ReceptorID',
            ]);

            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->uuid,
                        $r->serie,
                        $r->folio,
                        $r->tipo_documento ?? $r->tipo_comprobante ?? null,
                        $r->metodo_pago ?? null,
                        $r->forma_pago ?? null,
                        number_format((float) ($r->subtotal ?? 0), 2, '.', ''),
                        number_format((float) ($r->iva ?? 0), 2, '.', ''),
                        number_format((float) ($r->total ?? 0), 2, '.', ''),
                        optional($r->fecha)->format('Y-m-d H:i:s'),
                        $r->estatus,
                        $r->cliente_id,
                        $r->receptor_id ?? null,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function calcKpis(Request $request, string $from, string $to): array
    {
        $base = $this->cfdiBaseQuery($request);
        $period = (clone $base)->whereBetween('fecha', [$from, $to]);

        $totalPeriodo = (float) ((clone $period)->sum('total') ?? 0);
        $emitidos = (float) ((clone $period)->where('estatus', 'emitido')->sum('total') ?? 0);
        $cancelados = (float) ((clone $period)->where('estatus', 'cancelado')->sum('total') ?? 0);
        $borradores = (int) ((clone $period)->where('estatus', 'borrador')->count() ?? 0);
        $cfdiModel = new Cfdi;
        $cfdiTable = $cfdiModel->getTable();
        $cfdiConn = $this->cfdiConn();

        if ($this->hasColumn($cfdiTable, 'saldo_pendiente', $cfdiConn)) {
            $ppdPendientes = (float) ((clone $period)
                ->where('metodo_pago', 'PPD')
                ->sum('saldo_pendiente') ?? 0);
        } else {
            $ppdPendientes = (float) ((clone $period)
                ->where('metodo_pago', 'PPD')
                ->sum('total') ?? 0);
        }

        $fromC = Carbon::parse($from);
        $toC = Carbon::parse($to);
        $lenDays = $fromC->diffInDays($toC) + 1;
        $prevFrom = $fromC->copy()->subDays($lenDays);
        $prevTo = $toC->copy()->subDays($lenDays);

        $periodPrev = (clone $base)->whereBetween('fecha', [$prevFrom->toDateTimeString(), $prevTo->toDateTimeString()]);
        $totalPrev = (float) ((clone $periodPrev)->sum('total') ?? 0);

        return [
            'total_periodo' => round($totalPeriodo, 2),
            'emitidos' => round($emitidos, 2),
            'cancelados' => round($cancelados, 2),
            'borradores' => $borradores,
            'ppd_pendiente_rep' => round($ppdPendientes, 2),
            'period' => ['from' => $from, 'to' => $to],
            'prev_period' => ['from' => $prevFrom->toDateTimeString(), 'to' => $prevTo->toDateTimeString()],
            'delta_total' => $this->deltaPct($totalPrev, $totalPeriodo),
            'assistant' => [
                'alerts' => array_values(array_filter([
                    $ppdPendientes > 0 ? 'Hay facturas PPD con saldo pendiente de complemento de pago.' : null,
                    $borradores > 0 ? 'Hay borradores pendientes de revisar o timbrar.' : null,
                ])),
            ],
        ];
    }

    protected function buildSeries(Request $request, string $from, string $to): array
    {
        $conn = $this->cfdiConn();

        $sub = (clone $this->cfdiBaseQuery($request))
            ->withoutGlobalScopes()
            ->whereBetween('fecha', [$from, $to])
            ->reorder()
            ->selectRaw("
                DATE(fecha) AS d,
                SUM(CASE WHEN estatus='emitido' THEN 1 ELSE 0 END) AS cnt_emitidos,
                SUM(CASE WHEN estatus='cancelado' THEN 1 ELSE 0 END) AS cnt_cancelados,
                SUM(CASE WHEN estatus='borrador' THEN 1 ELSE 0 END) AS cnt_borradores,
                SUM(CASE WHEN estatus='emitido' THEN total ELSE 0 END) AS total_emitidos,
                SUM(CASE WHEN estatus='cancelado' THEN total ELSE 0 END) AS total_cancelados
            ")
            ->groupBy('d');

        $rows = DB::connection($conn)->query()
            ->fromSub($sub, 't')
            ->orderBy('d', 'asc')
            ->get();

        $labels = $emCnt = $caCnt = $boCnt = $emTot = $caTot = [];

        foreach ($rows as $r) {
            $labels[] = $r->d;
            $emCnt[] = (int) $r->cnt_emitidos;
            $caCnt[] = (int) $r->cnt_cancelados;
            $boCnt[] = (int) $r->cnt_borradores;
            $emTot[] = round((float) $r->total_emitidos, 2);
            $caTot[] = round((float) $r->total_cancelados, 2);
        }

        return [
            'labels' => $labels,
            'series' => [
                'emitidos_cnt' => $emCnt,
                'cancelados_cnt' => $caCnt,
                'borradores_cnt' => $boCnt,
                'emitidos_total' => $emTot,
                'cancelados_total' => $caTot,
            ],
        ];
    }

    protected function deltaPct(float $prev, float $now): float
    {
        if ($prev <= 0 && $now <= 0) {
            return 0.0;
        }

        if ($prev <= 0 && $now > 0) {
            return 100.0;
        }

        return round((($now - $prev) / max($prev, 0.00001)) * 100, 2);
    }
}