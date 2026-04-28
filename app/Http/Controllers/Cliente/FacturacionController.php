<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\Cfdi;
use App\Models\Cliente\CfdiConcepto;
use App\Models\Cliente\Emisor;
use App\Models\Cliente\Receptor;
use App\Models\Cliente\SatCredential;
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
use App\Services\Billing\FacturotopiaService;
use Illuminate\Support\Facades\View as ViewFactory;


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

    protected function accountSummarySafe(): array
{
    $cuenta = $this->currentCuenta();

    $summary = [
        'id' => null,
        'nombre' => 'Cuenta cliente',
        'plan' => 'FREE',
        'plan_key' => 'free',
        'is_pro' => false,
        'blocked' => false,
        'estado_cuenta' => 'activa',
        'modo_cobro' => null,
        'monto' => 0,
        'moneda' => 'MXN',
    ];

    if (! $cuenta || empty($cuenta->id)) {
        return $summary;
    }

    $summary['id'] = $cuenta->id;

    foreach (['nombre_comercial', 'razon_social', 'nombre'] as $field) {
        if (! empty($cuenta->{$field})) {
            $summary['nombre'] = (string) $cuenta->{$field};
            break;
        }
    }

    foreach (['tipo_cuenta', 'plan', 'licencia', 'paquete'] as $field) {
        if (! empty($cuenta->{$field})) {
            $plan = strtoupper(trim((string) $cuenta->{$field}));
            $summary['plan'] = $plan;
            $summary['plan_key'] = strtolower($plan);
            $summary['is_pro'] = in_array(strtolower($plan), ['pro', 'premium', 'enterprise', 'empresarial'], true);
            break;
        }
    }

    foreach (['is_blocked', 'bloqueado', 'blocked'] as $field) {
        if (isset($cuenta->{$field})) {
            $summary['blocked'] = (bool) $cuenta->{$field};
            break;
        }
    }

    foreach (['estado_cuenta', 'estatus', 'status'] as $field) {
        if (! empty($cuenta->{$field})) {
            $summary['estado_cuenta'] = (string) $cuenta->{$field};
            break;
        }
    }

    foreach (['modo_cobro', 'ciclo_cobro', 'billing_cycle'] as $field) {
        if (! empty($cuenta->{$field})) {
            $summary['modo_cobro'] = (string) $cuenta->{$field};
            break;
        }
    }

    foreach (['monto_licencia', 'precio_licencia', 'monto', 'precio', 'monthly_price'] as $field) {
        if (isset($cuenta->{$field}) && is_numeric($cuenta->{$field})) {
            $summary['monto'] = (float) $cuenta->{$field};
            break;
        }
    }

    foreach (['moneda', 'currency'] as $field) {
        if (! empty($cuenta->{$field})) {
            $summary['moneda'] = strtoupper((string) $cuenta->{$field});
            break;
        }
    }

    try {
        $cuentaId = (string) $cuenta->id;

        $adminTables = [
            'cuentas',
            'clientes',
            'cuentas_cliente',
            'clientes_cuentas',
        ];

        foreach ($adminTables as $table) {
            if (! $this->tableExists($table, 'mysql')) {
                continue;
            }

            $query = DB::connection('mysql')->table($table);

            if ($this->hasColumn($table, 'id', 'mysql')) {
                $query->where('id', $cuentaId);
            } elseif ($this->hasColumn($table, 'cuenta_id', 'mysql')) {
                $query->where('cuenta_id', $cuentaId);
            } else {
                continue;
            }

            $adminCuenta = $query->first();

            if (! $adminCuenta) {
                continue;
            }

            foreach (['nombre_comercial', 'razon_social', 'nombre'] as $field) {
                if (! empty($adminCuenta->{$field})) {
                    $summary['nombre'] = (string) $adminCuenta->{$field};
                    break;
                }
            }

            foreach (['tipo_cuenta', 'plan', 'licencia', 'paquete'] as $field) {
                if (! empty($adminCuenta->{$field})) {
                    $plan = strtoupper(trim((string) $adminCuenta->{$field}));
                    $summary['plan'] = $plan;
                    $summary['plan_key'] = strtolower($plan);
                    $summary['is_pro'] = in_array(strtolower($plan), ['pro', 'premium', 'enterprise', 'empresarial'], true);
                    break;
                }
            }

            foreach (['is_blocked', 'bloqueado', 'blocked'] as $field) {
                if (isset($adminCuenta->{$field})) {
                    $summary['blocked'] = (bool) $adminCuenta->{$field};
                    break;
                }
            }

            foreach (['estado_cuenta', 'estatus', 'status'] as $field) {
                if (! empty($adminCuenta->{$field})) {
                    $summary['estado_cuenta'] = (string) $adminCuenta->{$field};
                    break;
                }
            }

            foreach (['modo_cobro', 'ciclo_cobro', 'billing_cycle'] as $field) {
                if (! empty($adminCuenta->{$field})) {
                    $summary['modo_cobro'] = (string) $adminCuenta->{$field};
                    break;
                }
            }

            foreach (['monto_licencia', 'precio_licencia', 'monto', 'precio', 'monthly_price'] as $field) {
                if (isset($adminCuenta->{$field}) && is_numeric($adminCuenta->{$field})) {
                    $summary['monto'] = (float) $adminCuenta->{$field};
                    break;
                }
            }

            foreach (['moneda', 'currency'] as $field) {
                if (! empty($adminCuenta->{$field})) {
                    $summary['moneda'] = strtoupper((string) $adminCuenta->{$field});
                    break;
                }
            }

            break;
        }
    } catch (\Throwable $e) {
        report($e);
    }

    return $summary;
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

        if (! $cuenta || empty($cuenta->id)) {
            return $q->whereRaw('1 = 0');
        }

        $cuentaId = (string) $cuenta->id;
        $cfdiTable = (new Cfdi)->getTable();

        $credentialIds = [];

        try {
            $credentialIds = SatCredential::query()
                ->where(function ($w) use ($cuentaId) {
                    $w->where('cuenta_id', $cuentaId)
                        ->orWhere('account_id', $cuentaId);
                })
                ->pluck('id')
                ->all();
        } catch (\Throwable $e) {
            $credentialIds = [];
        }

        $q->where(function ($w) use ($cfdiTable, $conn, $cuenta, $cuentaId, $credentialIds) {
            if ($this->hasColumn($cfdiTable, 'cuenta_id', $conn)) {
                $w->orWhere('cuenta_id', $cuentaId);
            }

            if ($this->hasColumn($cfdiTable, 'account_id', $conn)) {
                $w->orWhere('account_id', $cuentaId);
            }

            if ($this->hasColumn($cfdiTable, 'emisor_credential_id', $conn) && ! empty($credentialIds)) {
                $w->orWhereIn('emisor_credential_id', $credentialIds);
            }

            if (is_numeric($cuenta->id) && $this->hasColumn($cfdiTable, 'cliente_id', $conn)) {
                $w->orWhere('cliente_id', (int) $cuenta->id);
            }
        });

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

    $cfdiTable = (new Cfdi)->getTable();
    $conn = $this->cfdiConn();

    $baseColumns = [
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
    ];

    $optionalColumns = [
        'tipo_comprobante',
        'tipo_documento',
        'cuenta_id',
    ];

    $columns = $baseColumns;

    foreach ($optionalColumns as $col) {
        if ($this->hasColumn($cfdiTable, $col, $conn)) {
            $columns[] = $col;
        }
    }

    $conceptoTable = 'cfdi_conceptos';

    $conceptoColumns = [
        'id',
        'cfdi_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
    ];

    foreach (['importe', 'iva_importe', 'subtotal', 'iva', 'total'] as $col) {
        if ($this->hasColumn($conceptoTable, $col, $conn)) {
            $conceptoColumns[] = $col;
        }
    }

    $q = $this->applyFilters(
        (clone $base)->whereBetween('fecha', [$from, $to]),
        $request
    )->with([
        'cliente:id,razon_social,nombre_comercial,rfc',
        'receptor:id,razon_social,nombre_comercial,rfc',
        'conceptos' => function ($q) use ($conceptoColumns) {
            $q->select($conceptoColumns);
        },
    ]);

    $perPage = (int) $request->integer('per_page', 15);

    $cfdis = $q->orderByDesc('fecha')
        ->paginate($perPage, $columns)
        ->withQueryString();

    $current = $cfdis->getCollection()->first();

    if (! $current) {
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
    public function create(?Request $request = null): View
    {
        $request = $request ?: request();

        $cuenta = $this->currentCuenta();

        $emisores = collect();

        if ($cuenta) {
            try {
                $cuentaId = (string) $cuenta->id;

                $emisores = SatCredential::query()
                    ->where(function ($q) use ($cuentaId) {
                        $q->where('cuenta_id', $cuentaId)
                            ->orWhere('account_id', $cuentaId);
                    })
                    ->orderBy('rfc')
                    ->get()
                    ->reject(function ($row) {
                        $meta = is_array($row->meta ?? null) ? $row->meta : [];

                        return (($meta['is_active'] ?? true) === false)
                            || (($meta['is_active'] ?? true) === '0')
                            || !empty($row->deleted_at ?? null)
                            || strtolower((string) ($row->estatus_operativo ?? '')) === 'inactive';
                    })
                    ->map(function ($row) {
                        $meta = is_array($row->meta ?? null) ? $row->meta : [];
                        $config = (array) data_get($meta, 'config_fiscal', []);

                        return (object) [
                            'id' => $row->id,
                            'rfc' => strtoupper((string) $row->rfc),
                            'razon_social' => $row->razon_social,
                            'nombre_comercial' => data_get($config, 'nombre_comercial') ?: $row->razon_social ?: $row->rfc,
                            'regimen_fiscal' => data_get($config, 'regimen_fiscal'),
                            'codigo_postal' => data_get($meta, 'direccion.codigo_postal'),
                            'series' => data_get($meta, 'series', []),
                            'has_csd' => (!empty($row->csd_cer_path ?? null) && !empty($row->csd_key_path ?? null)) || !empty(data_get($meta, 'csd')),
                        ];
                    })
                    ->values();
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

        return view('cliente.facturacion.nuevo', [
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
            'cliente_id' => ['required', 'string', 'max:80'],
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
                        'adenda_activa' => 'nullable|boolean',
            'adenda_tipo' => 'nullable|string|max:80',
            'adenda' => 'nullable|array',
            'adenda.orden_compra' => 'nullable|string|max:120',
            'adenda.numero_proveedor' => 'nullable|string|max:120',
            'adenda.numero_tienda' => 'nullable|string|max:120',
            'adenda.gln' => 'nullable|string|max:120',
            'adenda.referencia_entrega' => 'nullable|string|max:160',
            'adenda.contrato' => 'nullable|string|max:160',
            'adenda.centro_costos' => 'nullable|string|max:160',
            'adenda.fecha_entrega' => 'nullable|date',
            'adenda.observaciones' => 'nullable|string|max:1000',
        ]);

        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()
                ->withInput()
                ->withErrors([
                    'cuenta' => 'No se pudo identificar la cuenta activa del cliente.',
                ]);
        }

        $emisorCredential = SatCredential::query()
        ->where(function ($q) use ($cuenta) {
            $q->where('cuenta_id', (string) $cuenta->id)
                ->orWhere('account_id', (string) $cuenta->id);
        })
        ->where('id', $data['cliente_id'])
        ->first();

        if (!$emisorCredential) {
        return back()
            ->withInput()
            ->withErrors([
                'cliente_id' => 'El RFC emisor seleccionado no pertenece a esta cuenta.',
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

        $adendaActiva = (bool) ($data['adenda_activa'] ?? false);
        $adendaTipo = $adendaActiva ? trim((string) ($data['adenda_tipo'] ?? '')) : null;
        $adendaData = $adendaActiva ? array_filter((array) ($data['adenda'] ?? []), fn ($v) => $v !== null && $v !== '') : [];

        if ($adendaActiva && $adendaTipo === '') {
            return back()
                ->withInput()
                ->withErrors([
                    'adenda_tipo' => 'Selecciona el tipo de adenda comercial.',
                ]);
        }

        if ($adendaActiva && in_array($adendaTipo, ['walmart', 'soriana', 'liverpool', 'chedraui', 'amazon', 'mercado_libre', 'oxxo_femsa'], true)) {
            if (empty($adendaData['orden_compra']) || empty($adendaData['numero_proveedor'])) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'adenda' => 'Para esta adenda captura orden de compra y número de proveedor.',
                    ]);
            }
        }

        $adendaXml = $adendaActiva ? $this->buildAdendaXml($adendaTipo, $adendaData) : null;

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
        $clienteIdForCfdi = is_numeric($cuenta->id) ? (int) $cuenta->id : 0;
        $cuentaIdForCfdi = (string) $cuenta->id;
        $uuidTemp = 'BORRADOR-' . strtoupper((string) Str::uuid());

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
                $assistant,
                $clienteIdForCfdi,
                $cuentaIdForCfdi,
                $emisorCredential
            ) {
            $now = now();

            $cfdiPayload = [
                'cliente_id' => $clienteIdForCfdi,
                'cuenta_id' => $cuentaIdForCfdi,
                'emisor_credential_id' => $emisorCredential->id,
                'emisor_rfc' => $emisorCredential->rfc,
                'emisor_razon_social' => $emisorCredential->razon_social,
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
                                'adenda_tipo' => $adendaTipo,
                'adenda_json' => $adendaActiva ? json_encode([
                    'tipo' => $adendaTipo,
                    'datos' => $adendaData,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'adenda_xml' => $adendaXml,
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

    protected function findOwnedCfdi(int $cfdiId)
{
    $conn = $this->cfdiConn();
    $conceptoTable = 'cfdi_conceptos';

    $conceptoColumns = [
        'id',
        'cfdi_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
    ];

    foreach (['importe', 'iva_importe', 'subtotal', 'iva', 'total'] as $col) {
        if ($this->hasColumn($conceptoTable, $col, $conn)) {
            $conceptoColumns[] = $col;
        }
    }

    return $this->cfdiBaseQuery(request())
        ->where('id', $cfdiId)
        ->with([
            'cliente:id,razon_social,nombre_comercial,rfc',
            'receptor:id,razon_social,nombre_comercial,rfc',
            'conceptos' => function ($q) use ($conceptoColumns) {
                $q->select($conceptoColumns);
            },
        ])
        ->firstOrFail();
}

public function edit(int $cfdi): View
{
    $item = $this->findOwnedCfdi($cfdi);

    if (strtolower((string) $item->estatus) !== 'borrador') {
        abort(403, 'Solo se pueden editar CFDI en borrador.');
    }

    $data = $this->create()->getData();

    return view('cliente.facturacion.edit', array_merge($data, [
        'cfdi' => $item,
        'modoEdicion' => true,
    ]));
}

public function show(int $cfdi): View
{
    $item = $this->findOwnedCfdi($cfdi);

    return view('cliente.facturacion.show', [
        'cfdi' => $item,
        'summary' => [],
    ]);
}

public function descargarXml(int $cfdi)
{
    $item = $this->findOwnedCfdi($cfdi);

    if (! in_array(strtolower((string) $item->estatus), ['emitido', 'timbrado'], true)) {
        return back()->withErrors([
            'xml' => 'El XML solo está disponible para CFDI timbrados.',
        ]);
    }

    $xml = $this->buildCfdiXmlFallback($item);

    $filename = 'CFDI_' . ($item->serie ?: 'S') . '_' . ($item->folio ?: $item->id) . '.xml';

    return response($xml, 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
}

public function verPdf(int $cfdi)
{
    $item = $this->findOwnedCfdi($cfdi);

    if (! in_array(strtolower((string) $item->estatus), ['emitido', 'timbrado'], true)) {
        return back()->withErrors([
            'pdf' => 'El PDF solo está disponible para CFDI timbrados.',
        ]);
    }

    return response()
        ->view('cliente.facturacion.pdf', [
            'cfdi' => $item,
            'brand' => $this->resolveCfdiBranding(),
            'isFallbackPdf' => true,
        ])
        ->header('Content-Type', 'text/html; charset=UTF-8');
}

protected function buildCfdiXmlFallback($cfdi): string
{
    $uuid = trim((string) ($cfdi->uuid ?? ''));

    if ($uuid === '' || str_starts_with($uuid, 'BORRADOR-')) {
        $uuid = strtoupper((string) Str::uuid());
    }

    $serie = htmlspecialchars((string) ($cfdi->serie ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $folio = htmlspecialchars((string) ($cfdi->folio ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $fecha = optional($cfdi->fecha)->format('Y-m-d\TH:i:s') ?: now()->format('Y-m-d\TH:i:s');

    $emisorRfc = htmlspecialchars((string) ($cfdi->emisor_rfc ?? 'XAXX010101000'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $emisorNombre = htmlspecialchars((string) ($cfdi->emisor_razon_social ?? $cfdi->emisor_nombre ?? 'EMISOR'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $receptor = $cfdi->receptor ?? null;
    $receptorRfc = htmlspecialchars((string) ($receptor->rfc ?? $cfdi->receptor_rfc ?? 'XAXX010101000'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $receptorNombre = htmlspecialchars((string) ($receptor->razon_social ?? $cfdi->receptor_nombre ?? 'PUBLICO EN GENERAL'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $subtotal = number_format((float) ($cfdi->subtotal ?? 0), 2, '.', '');
    $iva = number_format((float) ($cfdi->iva ?? 0), 2, '.', '');
    $total = number_format((float) ($cfdi->total ?? 0), 2, '.', '');

    $conceptosXml = '';

    foreach (($cfdi->conceptos ?? collect()) as $concepto) {
        $descripcion = htmlspecialchars((string) ($concepto->descripcion ?? 'Concepto'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cantidad = number_format((float) ($concepto->cantidad ?? 1), 2, '.', '');
        $valorUnitario = number_format((float) ($concepto->precio_unitario ?? 0), 2, '.', '');
        $importe = number_format((float) ($concepto->subtotal ?? (($concepto->cantidad ?? 1) * ($concepto->precio_unitario ?? 0))), 2, '.', '');

        $conceptosXml .= <<<XML
        <cfdi:Concepto ClaveProdServ="01010101" Cantidad="{$cantidad}" ClaveUnidad="ACT" Descripcion="{$descripcion}" ValorUnitario="{$valorUnitario}" Importe="{$importe}" ObjetoImp="02" />

XML;
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Serie="{$serie}" Folio="{$folio}" Fecha="{$fecha}" SubTotal="{$subtotal}" Moneda="MXN" Total="{$total}" TipoDeComprobante="I" Exportacion="01" LugarExpedicion="00000">
    <cfdi:Emisor Rfc="{$emisorRfc}" Nombre="{$emisorNombre}" RegimenFiscal="601" />
    <cfdi:Receptor Rfc="{$receptorRfc}" Nombre="{$receptorNombre}" DomicilioFiscalReceptor="00000" RegimenFiscalReceptor="601" UsoCFDI="G03" />
    <cfdi:Conceptos>
{$conceptosXml}    </cfdi:Conceptos>
    <cfdi:Impuestos TotalImpuestosTrasladados="{$iva}" />
    <cfdi:Complemento>
        <tfd:TimbreFiscalDigital Version="1.1" UUID="{$uuid}" FechaTimbrado="{$fecha}" RfcProvCertif="SIMULADO" />
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

protected function resolveCfdiBranding(): array
{
    $cuenta = $this->currentCuenta();

    $brand = [
        'logo_url' => null,
        'primary' => '#2458cf',
        'secondary' => '#173b78',
        'accent' => '#38bdf8',
        'nombre' => 'Pactopia360',
    ];

    if (! $cuenta) {
        return $brand;
    }

    foreach (['nombre_comercial', 'razon_social', 'nombre'] as $field) {
        if (! empty($cuenta->{$field})) {
            $brand['nombre'] = (string) $cuenta->{$field};
            break;
        }
    }

    foreach (['brand_primary', 'color_primary', 'primary_color'] as $field) {
        if (! empty($cuenta->{$field})) {
            $brand['primary'] = (string) $cuenta->{$field};
            break;
        }
    }

    foreach (['brand_secondary', 'color_secondary', 'secondary_color'] as $field) {
        if (! empty($cuenta->{$field})) {
            $brand['secondary'] = (string) $cuenta->{$field};
            break;
        }
    }

    foreach (['logo_url', 'logo', 'brand_logo'] as $field) {
        if (! empty($cuenta->{$field})) {
            $brand['logo_url'] = (string) $cuenta->{$field};
            break;
        }
    }

    return $brand;
}

public function destroy(int $cfdi)
{
    $item = $this->findOwnedCfdi($cfdi);

    DB::connection($this->cfdiConn())->table('cfdis')
        ->where('id', $item->id)
        ->delete();

    return response()->json([
        'ok' => true,
        'message' => 'CFDI eliminado correctamente.'
    ]);
}

public function descargarZip(int $cfdi)
{
    $item = $this->findOwnedCfdi($cfdi);

    if (!$item->xml_timbrado) {
        return back()->withErrors(['zip' => 'No hay XML disponible']);
    }

    $zipName = 'CFDI_'.$item->serie.'_'.$item->folio.'.zip';
    $zipPath = storage_path('app/temp/'.$zipName);

    if (!file_exists(dirname($zipPath))) {
        mkdir(dirname($zipPath), 0777, true);
    }

    $zip = new \ZipArchive();
    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    $zip->addFromString('cfdi.xml', $item->xml_timbrado);

    if ($item->pdf_base64) {
        $zip->addFromString('cfdi.pdf', base64_decode($item->pdf_base64));
    }

    $zip->close();

    return response()->download($zipPath)->deleteFileAfterSend();
}

    public function timbrar(int $cfdi, FacturotopiaService $facturotopia)
{
    $item = $this->findOwnedCfdi($cfdi);

    if (strtolower((string) $item->estatus) !== 'borrador') {
        return back()->withErrors([
            'cfdi' => 'Este CFDI ya no está en borrador.',
        ]);
    }

    $cuenta = $this->currentCuenta();

    if (! $cuenta || empty($cuenta->id)) {
        return back()->withErrors([
            'cuenta' => 'No se pudo identificar la cuenta activa del cliente.',
        ]);
    }

    $adminAccountId = $this->resolveAdminAccountIdFromCuenta($cuenta);

    if (! $adminAccountId) {
        return back()->withErrors([
            'timbres' => 'No se pudo resolver la cuenta admin para timbrar.',
        ]);
    }

    $env = strtolower((string) request()->input('facturotopia_env', 'sandbox'));
    $env = in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';

    $conn = $this->cfdiConn();
    $table = (new Cfdi)->getTable();

    try {
        $payloadJson = $this->buildFacturotopiaPayloadFromCfdi($item);

        $result = $facturotopia->timbrarCfdi($adminAccountId, $payloadJson, $env);

        if (! (bool) ($result['ok'] ?? false)) {
            DB::connection($conn)->table($table)->where('id', $item->id)->update($this->onlyExistingColumnsForInsert($table, $conn, [
                'pac_env' => $env,
                'pac_status' => 'error',
                'pac_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'json_enviado' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]));

            return back()->withErrors([
                'timbrado' => 'Facturotopia rechazó el timbrado. HTTP: ' . ($result['status'] ?? 'N/D') . '. Revisa la respuesta PAC.',
            ]);
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $xmlBase64 = $this->extractBase64FromPacResponse($data, $result['body'] ?? '', [
            'xml_base64',
            'XmlBase64',
            'XMLBase64',
            'xml',
            'Xml',
            'XML',
            'cfdi',
            'Cfdi',
            'comprobante',
            'Comprobante',
            'data.xml',
            'data.Xml',
            'data.xml_base64',
            'result.xml',
            'result.Xml',
            'result.xml_base64',
        ]);

        if ($xmlBase64 === '') {
            DB::connection($conn)->table($table)->where('id', $item->id)->update($this->onlyExistingColumnsForInsert($table, $conn, [
                'pac_env' => $env,
                'pac_status' => 'sin_xml',
                'pac_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'json_enviado' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]));

            return back()->withErrors([
                'timbrado' => 'El PAC respondió OK, pero no se encontró XML Base64 en la respuesta.',
            ]);
        }

        $xmlTimbrado = base64_decode($xmlBase64, true);

        if (! is_string($xmlTimbrado) || trim($xmlTimbrado) === '') {
            return back()->withErrors([
                'timbrado' => 'El XML Base64 recibido no se pudo decodificar.',
            ]);
        }

        $timbre = $this->extractCfdiTimbreData($xmlTimbrado);

        $pdfBase64 = $this->extractBase64FromPacResponse($data, $result['body'] ?? '', [
            'pdf_base64',
            'PdfBase64',
            'PDFBase64',
            'pdf',
            'Pdf',
            'PDF',
            'data.pdf',
            'data.Pdf',
            'data.pdf_base64',
            'result.pdf',
            'result.Pdf',
            'result.pdf_base64',
        ]);

        DB::connection($conn)->transaction(function () use (
            $conn,
            $table,
            $item,
            $env,
            $result,
            $payloadJson,
            $xmlBase64,
            $xmlTimbrado,
            $pdfBase64,
            $adendaTipo,
            $adendaData,
            $adendaActiva,
            $adendaXml,
            $timbre
        ) {
            DB::connection($conn)->table($table)->where('id', $item->id)->update($this->onlyExistingColumnsForInsert($table, $conn, [
                'estatus' => 'emitido',
                'uuid' => $timbre['uuid'] ?: $item->uuid,
                'pac_env' => $env,
                'pac_status' => 'timbrado',
                'pac_uuid' => $timbre['uuid'] ?: null,
                'pac_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'json_enviado' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'xml_base64' => $xmlBase64,
                'xml_timbrado' => $xmlTimbrado,
                'pdf_base64' => $pdfBase64 ?: null,
                'fecha_timbrado' => $timbre['fecha_timbrado'] ?: now(),
                'sello_cfd' => $timbre['sello_cfd'] ?: null,
                'sello_sat' => $timbre['sello_sat'] ?: null,
                'no_certificado_sat' => $timbre['no_certificado_sat'] ?: null,
                'no_certificado_cfd' => $timbre['no_certificado_cfd'] ?: null,
                'qr_url' => $timbre['qr_url'] ?: null,
                'cadena_original' => $timbre['cadena_original'] ?: null,
                'timbrado_por' => 'pactopia.com',
                'es_timbrado_real' => 1,
                'updated_at' => now(),
            ]));
        });

        return redirect()
            ->route('cliente.facturacion.show', $item->id)
            ->with('ok', 'CFDI timbrado correctamente por pactopia.com.');
    } catch (\Throwable $e) {
        report($e);

        return back()->withErrors([
            'timbrado' => 'No se pudo timbrar el CFDI: ' . $e->getMessage(),
        ]);
    }
}

protected function buildFacturotopiaPayloadFromCfdi($cfdi): array
{
    $receptor = $cfdi->receptor;
    $conceptos = $cfdi->conceptos ?? collect();

    $emisorId = $this->resolveFacturotopiaEmisorId($cfdi);

    $payload = [
        'Idx' => (string) Str::uuid(),
        'Version' => '4.0',
        'Fecha' => optional($cfdi->fecha)->format('Y-m-d H:i:s') ?: now()->format('Y-m-d H:i:s'),
        'FormaPago' => (string) ($cfdi->forma_pago ?: '03'),
        'MetodoPago' => (string) ($cfdi->metodo_pago ?: 'PUE'),
        'SubTotal' => number_format((float) ($cfdi->subtotal ?? 0), 2, '.', ''),
        'Moneda' => (string) ($cfdi->moneda ?: 'MXN'),
        'Total' => number_format((float) ($cfdi->total ?? 0), 2, '.', ''),
        'TipoDeComprobante' => (string) ($cfdi->tipo_comprobante ?: 'I'),
        'Exportacion' => '01',
        'Emisor' => [
            'Id' => $emisorId,
        ],
        'Receptor' => [
            'Rfc' => strtoupper((string) ($receptor->rfc ?? $cfdi->rfc_receptor ?? 'XAXX010101000')),
            'Nombre' => strtoupper((string) ($receptor->razon_social ?? $receptor->nombre_comercial ?? $cfdi->razon_receptor ?? 'PUBLICO GENERAL')),
            'UsoCFDI' => (string) ($receptor->uso_cfdi ?? $cfdi->uso_cfdi ?? 'G03'),
            'CP' => (string) ($receptor->codigo_postal ?? $cfdi->cp_receptor ?? '00000'),
            'Regimen' => (string) ($receptor->regimen_fiscal ?? $cfdi->regimen_receptor ?? '601'),
        ],
        'Conceptos' => [
            'Concepto' => [],
        ],
    ];

    if (! empty($cfdi->serie)) {
        $payload['Serie'] = (string) $cfdi->serie;
    }

    if (! empty($cfdi->folio)) {
        $payload['Folio'] = (string) $cfdi->folio;
    }

    foreach ($conceptos as $concepto) {
        $cantidad = (float) ($concepto->cantidad ?? 1);
        $precio = (float) ($concepto->precio_unitario ?? 0);
        $importe = (float) ($concepto->subtotal ?? ($cantidad * $precio));
        $iva = (float) ($concepto->iva ?? 0);

        $item = [
            'ClaveProdServ' => (string) ($concepto->clave_producto_sat ?? '01010101'),
            'Descripcion' => (string) ($concepto->descripcion ?? 'Concepto'),
            'Cantidad' => number_format($cantidad, 6, '.', ''),
            'ClaveUnidad' => (string) ($concepto->clave_unidad_sat ?? 'ACT'),
            'Unidad' => (string) ($concepto->unidad ?? 'Actividad'),
            'ValorUnitario' => number_format($precio, 2, '.', ''),
            'Importe' => number_format($importe, 2, '.', ''),
            'ObjetoImp' => $iva > 0 ? '02' : '01',
        ];

        if ($iva > 0) {
            $item['Impuestos'] = [
                'Traslados' => [
                    'Traslado' => [[
                        'Base' => number_format($importe, 2, '.', ''),
                        'Impuesto' => '002',
                        'TipoFactor' => 'Tasa',
                        'TasaOCuota' => '0.160000',
                        'Importe' => number_format($iva, 2, '.', ''),
                    ]],
                ],
            ];
        }

        $payload['Conceptos']['Concepto'][] = $item;
    }

    $ivaTotal = (float) ($cfdi->iva ?? 0);

    if ($ivaTotal > 0) {
        $payload['Impuestos'] = [
            'TotalImpuestosTrasladados' => number_format($ivaTotal, 2, '.', ''),
            'Traslados' => [
                'Traslado' => [[
                    'Base' => number_format((float) ($cfdi->subtotal ?? 0), 2, '.', ''),
                    'Impuesto' => '002',
                    'TipoFactor' => 'Tasa',
                    'TasaOCuota' => '0.160000',
                    'Importe' => number_format($ivaTotal, 2, '.', ''),
                ]],
            ],
        ];
    }

    return $payload;
}

protected function resolveFacturotopiaEmisorId($cfdi): string
{
    $credentialId = $cfdi->emisor_credential_id ?? null;

    if ($credentialId) {
        try {
            $credential = SatCredential::query()->where('id', $credentialId)->first();

            if ($credential) {
                $meta = is_array($credential->meta ?? null)
                    ? $credential->meta
                    : (json_decode((string) ($credential->meta ?? ''), true) ?: []);

                foreach ([
                    'facturotopia.id',
                    'facturotopia.emisor_id',
                    'facturotopiaEmisorId',
                    'pactopia.emisor_id',
                    'pactopia_id',
                    'emisor_id',
                ] as $key) {
                    $value = data_get($meta, $key);

                    if (! empty($value)) {
                        return (string) $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    return (string) $credentialId;
}

protected function extractBase64FromPacResponse(array $data, string $body, array $keys): string
{
    foreach ($keys as $key) {
        $value = data_get($data, $key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    if ($body !== '') {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            foreach ($keys as $key) {
                $value = data_get($decoded, $key);

                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        if (base64_decode($body, true) !== false) {
            return trim($body);
        }
    }

    return '';
}

protected function extractCfdiTimbreData(string $xml): array
{
    $out = [
        'uuid' => '',
        'fecha_timbrado' => null,
        'sello_cfd' => '',
        'sello_sat' => '',
        'no_certificado_sat' => '',
        'no_certificado_cfd' => '',
        'qr_url' => '',
        'cadena_original' => '',
    ];

    try {
        $sx = new \SimpleXMLElement($xml);

        $namespaces = $sx->getNamespaces(true);

        $out['sello_cfd'] = (string) ($sx['Sello'] ?? '');
        $out['no_certificado_cfd'] = (string) ($sx['NoCertificado'] ?? '');

        if (isset($namespaces['cfdi'])) {
            $sx->registerXPathNamespace('cfdi', $namespaces['cfdi']);
        }

        if (isset($namespaces['tfd'])) {
            $sx->registerXPathNamespace('tfd', $namespaces['tfd']);
        } else {
            $sx->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        }

        $timbres = $sx->xpath('//tfd:TimbreFiscalDigital');

        if ($timbres && isset($timbres[0])) {
            $tfd = $timbres[0];

            $out['uuid'] = strtoupper((string) ($tfd['UUID'] ?? ''));
            $out['fecha_timbrado'] = (string) ($tfd['FechaTimbrado'] ?? '') ?: null;
            $out['sello_sat'] = (string) ($tfd['SelloSAT'] ?? '');
            $out['no_certificado_sat'] = (string) ($tfd['NoCertificadoSAT'] ?? '');

            $total = (string) ($sx['Total'] ?? '0');
            $emisorRfc = '';
            $receptorRfc = '';

            $emisores = $sx->xpath('//cfdi:Emisor');
            if ($emisores && isset($emisores[0])) {
                $emisorRfc = (string) ($emisores[0]['Rfc'] ?? '');
            }

            $receptores = $sx->xpath('//cfdi:Receptor');
            if ($receptores && isset($receptores[0])) {
                $receptorRfc = (string) ($receptores[0]['Rfc'] ?? '');
            }

            $fe = substr($out['sello_cfd'], -8);

            $out['qr_url'] = 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx'
                . '?id=' . rawurlencode($out['uuid'])
                . '&re=' . rawurlencode($emisorRfc)
                . '&rr=' . rawurlencode($receptorRfc)
                . '&tt=' . rawurlencode($total)
                . '&fe=' . rawurlencode($fe);

            $out['cadena_original'] = '||1.1|'
                . $out['uuid'] . '|'
                . $out['fecha_timbrado'] . '|'
                . '|'
                . $out['sello_cfd'] . '|'
                . $out['no_certificado_sat'] . '||';
        }
    } catch (\Throwable $e) {
        report($e);
    }

    return $out;
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

    public function actualizar(Request $request, int $cfdi)
    {
        $item = $this->findOwnedCfdi($cfdi);

        if (strtolower((string) $item->estatus) !== 'borrador') {
            return back()->withErrors([
                'cfdi' => 'Solo se pueden actualizar CFDI en borrador.',
            ]);
        }

        $data = $request->validate([
            'cliente_id' => ['required', 'string', 'max:80'],
            'receptor_id' => ['required', 'integer'],
            'serie' => ['nullable', 'string', 'max:10'],
            'folio' => ['nullable', 'string', 'max:20'],
            'fecha' => ['nullable', 'date'],
            'moneda' => ['nullable', 'string', 'max:10'],
            'metodo_pago' => ['nullable', 'string', 'max:10'],
            'forma_pago' => ['nullable', 'string', 'max:10'],
            'conceptos' => ['required', 'array', 'min:1'],
            'conceptos.*.producto_id' => ['nullable'],
            'conceptos.*.descripcion' => ['required', 'string', 'max:500'],
            'conceptos.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'conceptos.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'conceptos.*.iva_tasa' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()->withInput()->withErrors([
                'cuenta' => 'No se pudo identificar la cuenta activa del cliente.',
            ]);
        }

        $emisorCredential = SatCredential::query()
            ->where(function ($q) use ($cuenta) {
                $q->where('cuenta_id', (string) $cuenta->id)
                    ->orWhere('account_id', (string) $cuenta->id);
            })
            ->where('id', $data['cliente_id'])
            ->first();

        if (!$emisorCredential) {
            return back()->withInput()->withErrors([
                'cliente_id' => 'El RFC emisor seleccionado no pertenece a esta cuenta.',
            ]);
        }

        $receptor = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->where('id', $data['receptor_id'])
            ->first();

        if (!$receptor) {
            return back()->withInput()->withErrors([
                'receptor_id' => 'El receptor seleccionado no pertenece a esta cuenta.',
            ]);
        }

        $metodoPago = strtoupper((string) ($data['metodo_pago'] ?? 'PUE'));
        $formaPago = strtoupper((string) ($data['forma_pago'] ?? '03'));

        if ($metodoPago === 'PPD') {
            $formaPago = '99';
        }

        $subtotal = 0.0;
        $iva = 0.0;
        $total = 0.0;

        foreach ($data['conceptos'] as $concepto) {
            $cantidad = (float) $concepto['cantidad'];
            $precio = (float) $concepto['precio_unitario'];
            $tasa = isset($concepto['iva_tasa']) ? (float) $concepto['iva_tasa'] : 0.16;

            $lineSubtotal = round($cantidad * $precio, 4);
            $lineIva = round($lineSubtotal * $tasa, 4);
            $lineTotal = round($lineSubtotal + $lineIva, 4);

            $subtotal += $lineSubtotal;
            $iva += $lineIva;
            $total += $lineTotal;
        }

        $subtotal = round($subtotal, 2);
        $iva = round($iva, 2);
        $total = round($total, 2);

        $assistant = $this->fiscalAssistant([
            'rfc' => $receptor->rfc,
            'regimen_receptor' => $receptor->regimen_fiscal,
            'cp_receptor' => $receptor->codigo_postal,
            'uso_cfdi' => $receptor->uso_cfdi,
            'metodo_pago' => $metodoPago,
            'forma_pago' => $formaPago,
            'tipo_documento' => $item->tipo_documento ?? $item->tipo_comprobante ?? 'I',
            'total' => $total,
        ]);

        if (!$assistant['ok']) {
            return back()->withInput()->withErrors([
                'asistente_fiscal' => implode(' ', $assistant['errors']),
            ]);
        }

        $conn = $this->cfdiConn();
        $cfdiTable = (new Cfdi)->getTable();
        $conceptoTable = (new CfdiConcepto)->getTable();

        DB::connection($conn)->transaction(function () use (
            $conn,
            $cfdiTable,
            $conceptoTable,
            $item,
            $data,
            $subtotal,
            $iva,
            $total,
            $metodoPago,
            $formaPago,
            $assistant,
            $emisorCredential
        ) {
            $now = now();

            $cfdiPayload = [
                'cliente_id' => is_numeric($item->cliente_id) ? (int) $item->cliente_id : 0,
                'emisor_credential_id' => $emisorCredential->id,
                'emisor_rfc' => $emisorCredential->rfc,
                'emisor_razon_social' => $emisorCredential->razon_social,
                'receptor_id' => $data['receptor_id'],
                'serie' => $data['serie'] ?? null,
                'folio' => $data['folio'] ?? null,
                'fecha' => $data['fecha'] ?? $now,
                'moneda' => $data['moneda'] ?? 'MXN',
                'metodo_pago' => $metodoPago,
                'forma_pago' => $formaPago,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'saldo_original' => $total,
                'saldo_pendiente' => $metodoPago === 'PPD' ? $total : 0,
                'estado_pago' => $metodoPago === 'PPD' ? 'pendiente_rep' : 'no_requiere_rep',
                'ia_fiscal_score' => $assistant['score'],
                'ia_fiscal_nivel' => $assistant['nivel'],
                'ia_fiscal_snapshot' => json_encode($assistant, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ];

            $cfdiPayload = $this->onlyExistingColumnsForInsert($cfdiTable, $conn, $cfdiPayload);

            DB::connection($conn)
                ->table($cfdiTable)
                ->where('id', $item->id)
                ->update($cfdiPayload);

            DB::connection($conn)
                ->table($conceptoTable)
                ->where('cfdi_id', $item->id)
                ->delete();

            foreach ($data['conceptos'] as $concepto) {
                $cantidad = (float) $concepto['cantidad'];
                $precio = (float) $concepto['precio_unitario'];
                $tasa = isset($concepto['iva_tasa']) ? (float) $concepto['iva_tasa'] : 0.16;

                $lineSubtotal = round($cantidad * $precio, 4);
                $lineIva = round($lineSubtotal * $tasa, 4);
                $lineTotal = round($lineSubtotal + $lineIva, 4);

                $conceptoPayload = [
                    'cfdi_id' => $item->id,
                    'producto_id' => $concepto['producto_id'] ?? null,
                    'descripcion' => $concepto['descripcion'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'iva_tasa' => $tasa,
                    'subtotal' => round($lineSubtotal, 2),
                    'iva' => round($lineIva, 2),
                    'total' => round($lineTotal, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $conceptoPayload = $this->onlyExistingColumnsForInsert($conceptoTable, $conn, $conceptoPayload);

                DB::connection($conn)->table($conceptoTable)->insert($conceptoPayload);
            }
        });

        return redirect()
            ->route('cliente.facturacion.show', $item->id)
            ->with('ok', 'CFDI actualizado correctamente.');
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

    protected function resolveAdminAccountIdFromCuenta(?object $cuenta): ?int
{
    if (! $cuenta || empty($cuenta->id)) {
        return null;
    }

    if (! empty($cuenta->admin_account_id)) {
        return (int) $cuenta->admin_account_id;
    }

    try {
        if (! Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            return null;
        }

        $row = DB::connection('mysql_clientes')
            ->table('cuentas_cliente')
            ->where('id', (string) $cuenta->id)
            ->first(['id', 'admin_account_id', 'rfc', 'rfc_padre']);

        if ($row && ! empty($row->admin_account_id)) {
            return (int) $row->admin_account_id;
        }
    } catch (\Throwable $e) {
        report($e);
    }

    return null;
}

public function facturotopiaTest(Request $request, FacturotopiaService $facturotopia): JsonResponse
{
    $cuenta = $this->currentCuenta();
    $adminAccountId = $this->resolveAdminAccountIdFromCuenta($cuenta);

    if (! $adminAccountId) {
        return response()->json([
            'ok' => false,
            'message' => 'No se pudo resolver la cuenta admin del cliente.',
        ], 422);
    }

    $env = strtolower((string) $request->input('env', 'sandbox'));
    $env = in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';

    try {
        $result = $facturotopia->testConnection($adminAccountId, $env);

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => ! empty($result['ok'])
                ? 'Conexión Facturotopia lista para Facturación.'
                : 'Faltan datos para conectar Facturotopia.',
            'env' => $result['env'] ?? $env,
            'base_url' => $result['base_url'] ?? '',
            'status' => $result['status'] ?? null,
            'response_ms' => $result['response_ms'] ?? 0,
            'has_api_key' => (bool) ($result['has_api_key'] ?? false),
            'has_user' => (bool) ($result['has_user'] ?? false),
            'has_password' => (bool) ($result['has_password'] ?? false),
            'customer_id' => $result['customer_id'] ?? '',
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'ok' => false,
            'message' => 'Error al probar Facturotopia: ' . $e->getMessage(),
        ], 500);
    }
}

protected function buildAdendaXml(?string $tipo, array $data): ?string
{
    $tipo = trim((string) $tipo);

    if ($tipo === '' || empty($data)) {
        return null;
    }

    $safeTipo = htmlspecialchars($tipo, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $nodes = '';

    foreach ($data as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $node = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $key);
        $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $nodes .= "        <p360:Campo nombre=\"{$node}\" valor=\"{$text}\" />\n";
    }

    return <<<XML
<cfdi:Addenda>
    <p360:AdendaComercial xmlns:p360="https://pactopia360.com/adendas" tipo="{$safeTipo}">
{$nodes}    </p360:AdendaComercial>
</cfdi:Addenda>
XML;
}

protected function injectAdendaIntoXml(string $xml, ?string $adendaXml): string
{
    $adendaXml = trim((string) $adendaXml);

    if ($xml === '' || $adendaXml === '') {
        return $xml;
    }

    if (str_contains($xml, '<cfdi:Addenda')) {
        return $xml;
    }

    return preg_replace('/<\/cfdi:Comprobante>\s*$/', $adendaXml . "\n</cfdi:Comprobante>", $xml) ?: $xml;
}
}