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
use App\Models\Cliente\EmpleadoNomina;


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

        $cuenta = $this->currentCuenta();

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

        $isPro = $this->resolvePortalIsProFromAdmin($cuenta, $summary);
        $plan = $isPro ? 'PRO' : 'FREE';
        $planKey = $isPro ? 'pro' : 'free';

        $summary['is_pro'] = $isPro;
        $summary['plan'] = $plan;
        $summary['plan_key'] = $planKey;

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
            'isProPlan' => $isPro,
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

        $empleadosNomina = collect();

if ($cuenta) {
    try {
        $empleadosNomina = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->where('activo', true)
            ->orderBy('nombre_completo')
            ->limit(500)
            ->get([
                'id',
                'numero_empleado',
                'rfc',
                'curp',
                'nss',
                'nombre',
                'apellido_paterno',
                'apellido_materno',
                'nombre_completo',
                'email',
                'codigo_postal',
                'regimen_fiscal',
                'uso_cfdi',
                'fecha_inicio_relacion_laboral',
                'tipo_contrato',
                'tipo_jornada',
                'tipo_regimen',
                'periodicidad_pago',
                'departamento',
                'puesto',
                'riesgo_puesto',
                'salario_base_cot_apor',
                'salario_diario_integrado',
                'banco',
                'cuenta_bancaria',
                'sindicalizado',
            ]);
    } catch (\Throwable $e) {
        $empleadosNomina = collect();
    }
}

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

        $summary = $this->accountSummarySafe();
        $isProPlan = (bool) ($summary['is_pro'] ?? false);

        return view('cliente.facturacion.nuevo', [
            'empleadosNomina' => $empleadosNomina,
            'emisores' => $emisores,
            'receptores' => $receptores,
            'productos' => $productos,
            'summary' => $summary,
            'isProPlan' => $isProPlan,
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

    public function store(Request $request, FacturotopiaService $facturotopia)
{
    $data = $request->validate([
        'accion_cfdi' => 'nullable|string|in:borrador,timbrar',
        'cliente_id' => ['required', 'string', 'max:80'],
        'receptor_id' => 'nullable|integer',
        'empleado_nomina_id' => 'nullable|integer',
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

        'conceptos' => 'nullable|array',
        'conceptos.*.producto_id' => 'nullable|integer',
        'conceptos.*.descripcion' => 'nullable|string|max:500',
        'conceptos.*.cantidad' => 'nullable|numeric|min:0.0001',
        'conceptos.*.precio_unitario' => 'nullable|numeric|min:0',
        'conceptos.*.iva_tasa' => 'nullable|numeric|min:0',
        'conceptos.*.descuento' => 'nullable|numeric|min:0',
        'conceptos.*.clave_producto_sat' => 'nullable|string|max:20',
        'conceptos.*.clave_unidad_sat' => 'nullable|string|max:20',
        'conceptos.*.objeto_impuesto' => 'nullable|string|max:10',

        'nomina_total_percepciones' => 'nullable|numeric|min:0',
        'nomina_total_deducciones' => 'nullable|numeric|min:0',

        'cfdi_relacionado_tipo' => 'nullable|string|max:10',
        'cfdi_relacionado_uuid' => 'nullable|string|max:60',

        'rep_fecha_pago' => 'nullable|date',
        'rep_forma_pago' => 'nullable|string|max:10',
        'rep_monto' => 'nullable|numeric|min:0',
        'rep_moneda' => 'nullable|string|max:10',
        'rep_num_operacion' => 'nullable|string|max:120',

        'rep_documentos' => 'nullable|array',
        'rep_documentos.*.cfdi_id' => 'nullable|integer',
        'rep_documentos.*.uuid' => 'nullable|string|max:60',
        'rep_documentos.*.serie' => 'nullable|string|max:40',
        'rep_documentos.*.folio' => 'nullable|string|max:40',
        'rep_documentos.*.moneda' => 'nullable|string|max:10',
        'rep_documentos.*.saldo_anterior' => 'nullable|numeric|min:0',
        'rep_documentos.*.monto_pagado' => 'nullable|numeric|min:0',
        'rep_documentos.*.saldo_insoluto' => 'nullable|numeric|min:0',
        'rep_documentos.*.num_parcialidad' => 'nullable|integer|min:1',

        'nomina_tipo' => 'nullable|string|in:O,E',
        'nomina_fecha_pago' => 'nullable|date',
        'nomina_fecha_inicial_pago' => 'nullable|date',
        'nomina_fecha_final_pago' => 'nullable|date',
        'nomina_dias_pagados' => 'nullable|numeric|min:0.001',
        'nomina_total_otros_pagos' => 'nullable|numeric|min:0',

        'carta_porte_origen' => 'nullable|string|max:255',
        'carta_porte_destino' => 'nullable|string|max:255',
        'carta_porte_transporte' => 'nullable|string|max:80',
        'carta_porte_peso_total' => 'nullable|numeric|min:0',

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

    $tipoDocumento = strtoupper((string) ($data['tipo_documento'] ?? $data['tipo_comprobante'] ?? 'I'));
    $tipoDocumento = in_array($tipoDocumento, ['I', 'E', 'T', 'P', 'N'], true) ? $tipoDocumento : 'I';

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

    $receptor = null;
    $empleadoNomina = null;

    $metodoPago = strtoupper((string) ($data['metodo_pago'] ?? 'PUE'));
    $formaPago = strtoupper((string) ($data['forma_pago'] ?? '03'));

    if (!empty($data['cfdi_relacionado_tipo']) && empty($data['tipo_relacion'])) {
    $data['tipo_relacion'] = $data['cfdi_relacionado_tipo'];
    }

    if (!empty($data['cfdi_relacionado_uuid']) && empty($data['uuid_relacionado'])) {
        $data['uuid_relacionado'] = $data['cfdi_relacionado_uuid'];
    }

    if ($tipoDocumento === 'N') {
        $empleadoNomina = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->where('activo', true)
            ->where('id', (int) ($data['empleado_nomina_id'] ?? 0))
            ->first();

        if (!$empleadoNomina) {
            return back()
                ->withInput()
                ->withErrors([
                    'empleado_nomina_id' => 'Selecciona un empleado activo para generar CFDI de nómina.',
                ]);
        }

        if (empty($empleadoNomina->codigo_postal) || !preg_match('/^\d{5}$/', (string) $empleadoNomina->codigo_postal)) {
            return back()
                ->withInput()
                ->withErrors([
                    'empleado_nomina_id' => 'El empleado seleccionado no tiene CP fiscal válido.',
                ]);
        }

        $percepciones = round((float) ($data['nomina_total_percepciones'] ?? 0), 2);
        $deducciones = round((float) ($data['nomina_total_deducciones'] ?? 0), 2);

        if ($percepciones <= 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'nomina_total_percepciones' => 'Captura el total de percepciones de la nómina.',
                ]);
        }

        if ($deducciones > $percepciones) {
            return back()
                ->withInput()
                ->withErrors([
                    'nomina_total_deducciones' => 'Las deducciones no pueden ser mayores que las percepciones.',
                ]);
        }

        $metodoPago = 'PUE';
        $formaPago = '99';

        $data['uso_cfdi'] = 'CN01';
        $data['regimen_receptor'] = $empleadoNomina->regimen_fiscal ?: '605';
        $data['cp_receptor'] = $empleadoNomina->codigo_postal;
        $data['adenda_activa'] = false;
        $data['adenda_tipo'] = null;
        $data['adenda'] = [];
        $data['tipo_relacion'] = null;
        $data['uuid_relacionado'] = null;

        $data['conceptos'] = [[
            'producto_id' => null,
            'descripcion' => 'Pago de nómina',
            'cantidad' => 1,
            'precio_unitario' => $percepciones,
            'iva_tasa' => 0,
            'descuento' => $deducciones,
            'clave_producto_sat' => '84111505',
            'clave_unidad_sat' => 'ACT',
            'objeto_impuesto' => '01',
        ]];
    } else {
        $receptor = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->where('id', (int) ($data['receptor_id'] ?? 0))
            ->first();

        if (!$receptor) {
            return back()
                ->withInput()
                ->withErrors([
                    'receptor_id' => 'El receptor seleccionado no pertenece a esta cuenta.',
                ]);
        }

        if ($tipoDocumento === 'P') {
            $repMonto = round((float) ($data['rep_monto'] ?? 0), 2);

            if (empty($data['rep_fecha_pago'])) {
                return back()->withInput()->withErrors([
                    'rep_fecha_pago' => 'Captura la fecha de pago del complemento REP.',
                ]);
            }

            if (empty($data['rep_forma_pago'])) {
                return back()->withInput()->withErrors([
                    'rep_forma_pago' => 'Selecciona la forma real de pago del complemento REP.',
                ]);
            }

            if ($repMonto <= 0) {
                return back()->withInput()->withErrors([
                    'rep_monto' => 'El monto pagado debe ser mayor a cero.',
                ]);
            }

            $metodoPago = 'PPD';
            $formaPago = '99';
            $data['uso_cfdi'] = 'CP01';
            $data['adenda_activa'] = false;
            $data['adenda_tipo'] = null;
            $data['adenda'] = [];

            $data['conceptos'] = [[
                'producto_id' => null,
                'descripcion' => 'Pago',
                'cantidad' => 1,
                'precio_unitario' => 0,
                'iva_tasa' => 0,
                'descuento' => 0,
                'clave_producto_sat' => '84111506',
                'clave_unidad_sat' => 'ACT',
                'objeto_impuesto' => '01',
            ]];
        } else {
            if (empty($data['conceptos']) || !is_array($data['conceptos'])) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'conceptos' => 'Agrega al menos un concepto para este CFDI.',
                    ]);
            }
        }

        foreach ($data['conceptos'] as $idx => $concepto) {
            if (empty($concepto['descripcion'])) {
                return back()->withInput()->withErrors([
                    "conceptos.{$idx}.descripcion" => 'La descripción del concepto es obligatoria.',
                ]);
            }

            if ((float) ($concepto['cantidad'] ?? 0) <= 0) {
                return back()->withInput()->withErrors([
                    "conceptos.{$idx}.cantidad" => 'La cantidad del concepto debe ser mayor a cero.',
                ]);
            }

            if ((float) ($concepto['precio_unitario'] ?? 0) < 0) {
                return back()->withInput()->withErrors([
                    "conceptos.{$idx}.precio_unitario" => 'El precio unitario no puede ser negativo.',
                ]);
            }
        }

        if ($metodoPago === 'PPD') {
            $formaPago = '99';
        }
    }

    $subtotal = 0.0;
    $descuento = 0.0;
    $iva = 0.0;
    $total = 0.0;

    foreach (($data['conceptos'] ?? []) as $c) {
        $cant = (float) ($c['cantidad'] ?? 0);
        $ppu = (float) ($c['precio_unitario'] ?? 0);
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

    $repJson = null;
$nominaJson = null;
$cartaPorteJson = null;

if ($tipoDocumento === 'P') {
    $documentosRep = collect($data['rep_documentos'] ?? [])
        ->map(function ($doc) {
            $saldoAnterior = round((float) ($doc['saldo_anterior'] ?? 0), 2);
            $montoPagado = round((float) ($doc['monto_pagado'] ?? 0), 2);
            $saldoInsoluto = round((float) ($doc['saldo_insoluto'] ?? max(0, $saldoAnterior - $montoPagado)), 2);

            $cfdiOrigen = null;

            if (!empty($doc['cfdi_id']) && is_numeric($doc['cfdi_id'])) {
                $cfdiOrigen = Cfdi::query()
                    ->where('id', (int) $doc['cfdi_id'])
                    ->first(['id', 'subtotal', 'iva', 'total']);
            }

            $origenIva = round((float) ($cfdiOrigen->iva ?? 0), 2);
            $objetoImp = $origenIva > 0 ? '02' : '01';

            $ivaBase = 0.0;
            $ivaImporte = 0.0;

            if ($objetoImp === '02' && $montoPagado > 0) {
                $ivaBase = round($montoPagado / 1.16, 2);
                $ivaImporte = round($montoPagado - $ivaBase, 2);
            }

            return [
                'cfdi_id' => $doc['cfdi_id'] ?? null,
                'uuid' => strtoupper(trim((string) ($doc['uuid'] ?? ''))),
                'serie' => $doc['serie'] ?? null,
                'folio' => $doc['folio'] ?? null,
                'moneda' => strtoupper((string) ($doc['moneda'] ?? 'MXN')),
                'saldo_anterior' => $saldoAnterior,
                'monto_pagado' => $montoPagado,
                'saldo_insoluto' => $saldoInsoluto,
                'num_parcialidad' => (int) ($doc['num_parcialidad'] ?? 1),
                'objeto_impuesto' => $objetoImp,
                'iva_base' => $ivaBase,
                'iva_importe' => $ivaImporte,
                'iva_tasa' => '0.160000',
            ];
        })
        ->filter(fn ($doc) => $doc['uuid'] !== '' && $doc['monto_pagado'] > 0)
        ->values()
        ->all();

    if (empty($documentosRep)) {
        return back()->withInput()->withErrors([
            'rep_documentos' => 'Selecciona al menos una factura PPD pendiente para relacionar el pago.',
        ]);
    }

    $totalDocumentos = round(array_sum(array_column($documentosRep, 'monto_pagado')), 2);
    $repMonto = round((float) ($data['rep_monto'] ?? 0), 2);

    if ($repMonto > $totalDocumentos) {
        return back()->withInput()->withErrors([
            'rep_monto' => 'El monto recibido no puede ser mayor al total aplicado a facturas PPD.',
        ]);
    }

    $repJson = [
        'fecha_pago' => $data['rep_fecha_pago'] ?? null,
        'forma_pago' => $data['rep_forma_pago'] ?? null,
        'moneda' => $data['rep_moneda'] ?? 'MXN',
        'monto' => $repMonto,
        'num_operacion' => $data['rep_num_operacion'] ?? null,
        'documentos_relacionados' => $documentosRep,
    ];
}

if ($tipoDocumento === 'N') {
    $nominaJson = [
        'tipo_nomina' => $data['nomina_tipo'] ?? 'O',
        'fecha_pago' => $data['nomina_fecha_pago'] ?? now()->toDateString(),
        'fecha_inicial_pago' => $data['nomina_fecha_inicial_pago'] ?? now()->startOfMonth()->toDateString(),
        'fecha_final_pago' => $data['nomina_fecha_final_pago'] ?? now()->endOfMonth()->toDateString(),
        'dias_pagados' => number_format((float) ($data['nomina_dias_pagados'] ?? 15), 3, '.', ''),
        'total_percepciones' => round((float) ($data['nomina_total_percepciones'] ?? 0), 2),
        'total_deducciones' => round((float) ($data['nomina_total_deducciones'] ?? 0), 2),
        'total_otros_pagos' => round((float) ($data['nomina_total_otros_pagos'] ?? 0), 2),
    ];
}

if ($tipoDocumento === 'T') {
    $cartaPorteJson = [
        'origen' => $data['carta_porte_origen'] ?? null,
        'destino' => $data['carta_porte_destino'] ?? null,
        'transporte' => $data['carta_porte_transporte'] ?? null,
        'peso_total' => round((float) ($data['carta_porte_peso_total'] ?? 0), 3),
    ];
}

    $adendaActiva = $tipoDocumento === 'N' ? false : (bool) ($data['adenda_activa'] ?? false);
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

    if ($tipoDocumento === 'P') {
        $data['uso_cfdi'] = 'CP01';
    } elseif ($tipoDocumento === 'N') {
        $data['uso_cfdi'] = 'CN01';
    } elseif (in_array(strtoupper((string) ($data['uso_cfdi'] ?? '')), ['CP01', 'CN01'], true)) {
        $data['uso_cfdi'] = 'G03';
    }

    $assistant = $this->fiscalAssistant([
        'rfc' => $tipoDocumento === 'N' ? $empleadoNomina?->rfc : $receptor?->rfc,
        'regimen_receptor' => $tipoDocumento === 'N'
            ? ($empleadoNomina?->regimen_fiscal ?: '605')
            : ($data['regimen_receptor'] ?? $receptor?->regimen_fiscal),
        'cp_receptor' => $tipoDocumento === 'N'
            ? $empleadoNomina?->codigo_postal
            : ($data['cp_receptor'] ?? $receptor?->codigo_postal),
        'uso_cfdi' => $tipoDocumento === 'N'
            ? 'CN01'
            : ($data['uso_cfdi'] ?? $receptor?->uso_cfdi),
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

    $accionCfdi = strtolower((string) ($data['accion_cfdi'] ?? $request->input('accion_cfdi', 'borrador')));
    $accionCfdi = $accionCfdi === 'timbrar' ? 'timbrar' : 'borrador';

    $cfdiId = null;

    DB::connection($conn)->transaction(function () use (
        &$cfdiId,
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
        $emisorCredential,
        $adendaTipo,
        $adendaData,
        $adendaActiva,
        $adendaXml,
        $empleadoNomina,
        $repJson,
        $nominaJson,
        $cartaPorteJson
    ) {
        $now = now();

        $cfdiPayload = [
            'cliente_id' => $clienteIdForCfdi,
            'cuenta_id' => $cuentaIdForCfdi,
            'emisor_credential_id' => $emisorCredential->id,
            'emisor_rfc' => $emisorCredential->rfc,
            'emisor_razon_social' => $emisorCredential->razon_social,
            'rfc_emisor' => $emisorCredential->rfc,
            'razon_emisor' => $emisorCredential->razon_social,

            'receptor_id' => $tipoDocumento === 'N' ? null : ($data['receptor_id'] ?? null),
            'empleado_nomina_id' => $tipoDocumento === 'N' ? $empleadoNomina?->id : null,
            'receptor_nomina_json' => $tipoDocumento === 'N' ? json_encode([
                'id' => $empleadoNomina?->id,
                'numero_empleado' => $empleadoNomina?->numero_empleado,
                'rfc' => $empleadoNomina?->rfc,
                'curp' => $empleadoNomina?->curp,
                'nss' => $empleadoNomina?->nss,
                'nombre_completo' => $empleadoNomina?->nombre_completo,
                'codigo_postal' => $empleadoNomina?->codigo_postal,
                'regimen_fiscal' => $empleadoNomina?->regimen_fiscal ?: '605',
                'uso_cfdi' => 'CN01',
                'departamento' => $empleadoNomina?->departamento,
                'puesto' => $empleadoNomina?->puesto,
                'tipo_contrato' => $empleadoNomina?->tipo_contrato,
                'tipo_jornada' => $empleadoNomina?->tipo_jornada,
                'tipo_regimen' => $empleadoNomina?->tipo_regimen,
                'periodicidad_pago' => $empleadoNomina?->periodicidad_pago,
                'fecha_inicio_relacion_laboral' => optional($empleadoNomina?->fecha_inicio_relacion_laboral)->toDateString(),
                'salario_base_cot_apor' => $empleadoNomina?->salario_base_cot_apor,
                'salario_diario_integrado' => $empleadoNomina?->salario_diario_integrado,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,

            'uuid' => $uuidTemp,
            'serie' => $data['serie'] ?? null,
            'folio' => $data['folio'] ?? null,
            'fecha' => $data['fecha'] ?? $now,
            'version_cfdi' => $data['version_cfdi'] ?? '4.0',
            'tipo_comprobante' => $tipoDocumento,
            'tipo_documento' => $tipoDocumento,
            'tipo_cambio' => isset($data['tipo_cambio']) && is_numeric($data['tipo_cambio'])
                ? (float) $data['tipo_cambio']
                : 1,

            'metodo_pago' => $metodoPago,
            'forma_pago' => $formaPago,
            'condiciones_pago' => $tipoDocumento === 'N' ? null : ($data['condiciones_pago'] ?? null),
            'uso_cfdi' => $tipoDocumento === 'N' ? 'CN01' : ($data['uso_cfdi'] ?? 'G03'),
            'regimen_receptor' => $tipoDocumento === 'N' ? ($empleadoNomina?->regimen_fiscal ?: '605') : ($data['regimen_receptor'] ?? null),
            'cp_receptor' => $tipoDocumento === 'N' ? $empleadoNomina?->codigo_postal : ($data['cp_receptor'] ?? null),
            'tipo_relacion' => $tipoDocumento === 'N' ? null : ($data['tipo_relacion'] ?? null),
            'uuid_relacionado' => $tipoDocumento === 'N' ? null : ($data['uuid_relacionado'] ?? null),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
            'saldo_original' => $total,
            'saldo_pagado' => $tipoDocumento === 'P' ? round((float) ($data['rep_monto'] ?? 0), 2) : 0,
            'saldo_pendiente' => $tipoDocumento === 'P' ? 0 : ($metodoPago === 'PPD' ? $total : 0),
            'estatus' => 'borrador',
            'estado_pago' => $metodoPago === 'PPD' ? 'pendiente_rep' : 'no_requiere_rep',
            'rep_json' => $repJson ? json_encode($repJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'nomina_json' => $nominaJson ? json_encode($nominaJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'carta_porte_json' => $cartaPorteJson ? json_encode($cartaPorteJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ia_fiscal_score' => $assistant['score'],
            'ia_fiscal_nivel' => $assistant['nivel'],
            'ia_fiscal_snapshot' => json_encode($assistant, JSON_UNESCAPED_UNICODE),
            'observaciones' => $data['observaciones'] ?? null,
            'adenda_tipo' => $tipoDocumento === 'N' ? null : $adendaTipo,
            'adenda_json' => ($tipoDocumento !== 'N' && $adendaActiva) ? json_encode([
                'tipo' => $adendaTipo,
                'datos' => $adendaData,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'adenda_xml' => $tipoDocumento === 'N' ? null : $adendaXml,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $cfdiPayload = $this->onlyExistingColumnsForInsert($cfdiTable, $conn, $cfdiPayload);

        $cfdiId = DB::connection($conn)->table($cfdiTable)->insertGetId($cfdiPayload);

        foreach (($data['conceptos'] ?? []) as $c) {
            $cant = (float) ($c['cantidad'] ?? 0);
            $ppu = (float) ($c['precio_unitario'] ?? 0);
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
                'descripcion' => $c['descripcion'] ?? 'Concepto',
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

    if ($accionCfdi === 'timbrar') {
        if (!$cfdiId) {
            return redirect()
                ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
                ->withErrors([
                    'timbrado' => 'El CFDI se guardó, pero no se pudo resolver el ID para timbrarlo.',
                ]);
        }

        return $this->timbrar((int) $cfdiId, $facturotopia);
    }

    return redirect()
        ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
        ->with('ok', 'Borrador CFDI 4.0 guardado correctamente.');
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

    foreach (['importe', 'iva_importe', 'subtotal', 'iva', 'total', 'descuento', 'iva_tasa', 'clave_producto_sat', 'clave_unidad_sat', 'objeto_impuesto'] as $col) {
        if ($this->hasColumn($conceptoTable, $col, $conn)) {
            $conceptoColumns[] = $col;
        }
    }

    return $this->cfdiBaseQuery(request())
        ->where('id', $cfdiId)
        ->with([
            'cliente:id,razon_social,nombre_comercial,rfc',
            'receptor:id,razon_social,nombre_comercial,rfc,uso_cfdi,forma_pago,metodo_pago,regimen_fiscal,codigo_postal,pais,estado,municipio,colonia,calle,no_ext,no_int,email,telefono',
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

    $xml = trim((string) ($item->xml_timbrado ?? ''));

    if ($xml === '') {
        return back()->withErrors([
            'xml' => 'Este CFDI está timbrado, pero no tiene XML timbrado guardado.',
        ]);
    }

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
    $fecha = optional($cfdi->fecha)->format('Y-m-d\TH:i:s') ?: now()->format('Y-m-d H:i:s');

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

    if (strtolower((string) ($item->estatus ?? '')) !== 'borrador') {
        return redirect()
            ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
            ->withErrors([
                'cfdi' => 'Solo se pueden eliminar CFDI en borrador.',
            ]);
    }

    $conn = $this->cfdiConn();
    $cfdiTable = (new Cfdi)->getTable();
    $conceptoTable = (new CfdiConcepto)->getTable();

    DB::connection($conn)->transaction(function () use ($conn, $cfdiTable, $conceptoTable, $item) {
        DB::connection($conn)
            ->table($conceptoTable)
            ->where('cfdi_id', $item->id)
            ->delete();

        DB::connection($conn)
            ->table($cfdiTable)
            ->where('id', $item->id)
            ->delete();
    });

    return redirect()
        ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
        ->with('ok', 'Borrador CFDI eliminado correctamente.');
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

        foreach (['Version', 'Fecha', 'TipoDeComprobante', 'Emisor', 'Receptor', 'Conceptos'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $payloadJson)) {
                DB::connection($conn)->table($table)->where('id', $item->id)->update($this->onlyExistingColumnsForInsert($table, $conn, [
                    'pac_env' => $env,
                    'pac_status' => 'payload_incompleto',
                    'json_enviado' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]));

                return back()->withErrors([
                    'timbrado' => 'Payload CFDI 4.0 incompleto. Falta: ' . $requiredKey . '. No se envió al PAC.',
                ]);
            }
        }

        \Log::warning('P360.cfdi.payload.debug', [
            'cfdi_id' => $item->id,
            'tipo' => $payloadJson['TipoDeComprobante'] ?? null,
            'version' => $payloadJson['Version'] ?? null,
            'fecha' => $payloadJson['Fecha'] ?? null,
            'uso_cfdi' => data_get($payloadJson, 'Receptor.UsoCFDI'),
            'keys' => array_keys($payloadJson),
            'payload_json' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

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

        // ✅ INYECTAR ADENDA AQUÍ
        $xmlTimbrado = $this->injectAdendaIntoXml($xmlTimbrado, $item->adenda_xml ?? null);
        $xmlBase64 = base64_encode($xmlTimbrado);

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

        // ✅ ENVÍO DE CORREO AUTOMÁTICO CON CFDI YA ACTUALIZADO
        try {
            $itemFresh = $this->findOwnedCfdi((int) $item->id);
            $receptor = $itemFresh->receptor;

            if (!empty($receptor->email)) {
                \Mail::raw('Adjunto su CFDI timbrado.', function ($mail) use ($receptor, $itemFresh) {
                    $serieFolio = trim((string) ($itemFresh->serie ?? '') . '-' . (string) ($itemFresh->folio ?? ''), '-');

                    $mail->to($receptor->email)
                        ->subject('CFDI ' . ($serieFolio !== '' ? $serieFolio : (string) $itemFresh->uuid));

                    if (!empty($itemFresh->xml_timbrado)) {
                        $mail->attachData($itemFresh->xml_timbrado, 'cfdi.xml', [
                            'mime' => 'application/xml',
                        ]);
                    }

                    if (!empty($itemFresh->pdf_base64)) {
                        $pdf = base64_decode((string) $itemFresh->pdf_base64, true);

                        if (is_string($pdf) && $pdf !== '') {
                            $mail->attachData($pdf, 'cfdi.pdf', [
                                'mime' => 'application/pdf',
                            ]);
                        }
                    }
                });
            }
        } catch (\Throwable $e) {
                report($e);
            }

            // ✅ DESCONTAR TIMBRE SOLO DESPUÉS DE TIMBRADO EXITOSO
            try {
                $this->consumeFacturotopiaStamp((int) $adminAccountId);
            } catch (\Throwable $e) {
                report($e);
            }

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
    $tipo = strtoupper((string) ($cfdi->tipo_comprobante ?: $cfdi->tipo_documento ?: 'I'));
    $tipo = in_array($tipo, ['I', 'E', 'T', 'P', 'N'], true) ? $tipo : 'I';

    $receptor = $cfdi->receptor;
    $nominaReceptor = [];

    if ($tipo === 'N' && !empty($cfdi->receptor_nomina_json)) {
        $decodedNomina = json_decode((string) $cfdi->receptor_nomina_json, true);
        $nominaReceptor = is_array($decodedNomina) ? $decodedNomina : [];
    }

    $conceptos = $cfdi->conceptos ?? collect();

    $emisorId = $this->resolveFacturotopiaEmisorId($cfdi);

    if ($emisorId === '') {
        throw new \RuntimeException('El RFC emisor no tiene emisor_id de Facturotopia. Sincroniza el RFC desde RFC / Emisores antes de timbrar.');
    }

    if ($tipo === 'N') {
        $rfcReceptor = strtoupper((string) data_get($nominaReceptor, 'rfc', ''));
        $nombreReceptor = strtoupper((string) data_get($nominaReceptor, 'nombre_completo', ''));
        $cp = preg_replace('/\D+/', '', (string) ($cfdi->cp_receptor ?: data_get($nominaReceptor, 'codigo_postal', '')));
        $regimen = trim((string) ($cfdi->regimen_receptor ?: data_get($nominaReceptor, 'regimen_fiscal', '605')));
        $usoCfdi = 'CN01';
    } else {
        $rfcReceptor = strtoupper((string) ($receptor->rfc ?? $cfdi->rfc_receptor ?? 'XAXX010101000'));
        $nombreReceptor = strtoupper((string) ($receptor->razon_social ?? $receptor->nombre_comercial ?? $cfdi->razon_receptor ?? 'PUBLICO GENERAL'));
        $cp = preg_replace('/\D+/', '', (string) ($cfdi->cp_receptor ?: ($receptor->codigo_postal ?? '')));
        $regimen = trim((string) ($cfdi->regimen_receptor ?: ($receptor->regimen_fiscal ?? '')));
        $usoCfdi = strtoupper(trim((string) ($cfdi->uso_cfdi ?: ($receptor->uso_cfdi ?? 'G03'))));
    }

    if ($tipo === 'P') {
        $usoCfdi = 'CP01';
    } elseif ($tipo === 'N') {
        $usoCfdi = 'CN01';
    } elseif (in_array($usoCfdi, ['CP01', 'CN01'], true)) {
        $usoCfdi = 'G03';
    }

    if ($rfcReceptor === '') {
        throw new \RuntimeException('El receptor no tiene RFC válido para timbrar.');
    }

    if ($nombreReceptor === '') {
        throw new \RuntimeException('El receptor no tiene nombre o razón social para timbrar.');
    }

    if ($cp === '' || !preg_match('/^\d{5}$/', $cp)) {
        throw new \RuntimeException('El receptor no tiene código postal fiscal válido.');
    }

    if ($regimen === '') {
        throw new \RuntimeException('El receptor no tiene régimen fiscal asignado.');
    }

    $formaPago = (string) ($cfdi->forma_pago ?: '03');
    $metodoPago = (string) ($cfdi->metodo_pago ?: 'PUE');
    $subtotal = (float) ($cfdi->subtotal ?? 0);
    $descuento = (float) ($cfdi->descuento ?? 0);
    $total = (float) ($cfdi->total ?? 0);
    $ivaTotal = (float) ($cfdi->iva ?? 0);

    $fecha = $cfdi->fecha
    ? Carbon::parse($cfdi->fecha)->format('Y-m-d H:i:s')
    : now()->format('Y-m-d H:i:s');

    $emisorRfc = strtoupper((string) ($cfdi->emisor_rfc ?: $cfdi->rfc_emisor));
    $emisorNombre = strtoupper((string) ($cfdi->emisor_razon_social ?: $cfdi->razon_emisor ?: $emisorRfc));

    $lugarExpedicion = preg_replace('/\D+/', '', (string) ($cfdi->cp_emisor ?? '55000'));
    $lugarExpedicion = preg_match('/^\d{5}$/', $lugarExpedicion) ? $lugarExpedicion : '55000';

    $payload = [
        'emisor_id' => $emisorId,
        'Version' => '4.0',
        'Serie' => (string) ($cfdi->serie ?? ''),
        'Folio' => (string) ($cfdi->folio ?? ''),
        'Fecha' => $fecha,
        'SubTotal' => number_format($tipo === 'P' ? 0 : $subtotal, 2, '.', ''),
        'Moneda' => $tipo === 'P' ? 'XXX' : strtoupper((string) ($cfdi->moneda ?: 'MXN')),
        'Total' => number_format($tipo === 'P' ? 0 : $total, 2, '.', ''),
        'TipoDeComprobante' => $tipo,
        'Exportacion' => '01',
        'LugarExpedicion' => $lugarExpedicion,
        'Emisor' => [
            'Rfc' => $emisorRfc,
            'Nombre' => $emisorNombre,
            'RegimenFiscal' => '601',
        ],
        'Receptor' => [
            'Rfc' => $rfcReceptor,
            'Nombre' => $nombreReceptor,
            'DomicilioFiscalReceptor' => $cp,
            'RegimenFiscalReceptor' => $regimen,
            'UsoCFDI' => $usoCfdi,
        ],
        'Conceptos' => [
            'Concepto' => [],
        ],
    ];

    if ($payload['Serie'] === '') {
    unset($payload['Serie']);
    }

    if ($payload['Folio'] === '') {
        unset($payload['Folio']);
    }

    if ($tipo !== 'P' && $formaPago !== '') {
        $payload['FormaPago'] = $formaPago;
    }

    if ($tipo !== 'P' && $metodoPago !== '') {
        $payload['MetodoPago'] = $metodoPago;
    }

    if ($tipo !== 'P' && strtoupper((string) ($cfdi->moneda ?: 'MXN')) !== 'MXN') {
        $payload['TipoCambio'] = number_format((float) ($cfdi->tipo_cambio ?: 1), 6, '.', '');
    }

    if ($tipo === 'P') {
    $rep = is_array($cfdi->rep_json)
        ? $cfdi->rep_json
        : (json_decode((string) ($cfdi->rep_json ?? ''), true) ?: []);

    $montoPago = round((float) data_get($rep, 'monto', $cfdi->saldo_pagado ?? 0), 2);
    $fechaPago = (string) data_get($rep, 'fecha_pago', optional($cfdi->fecha)->format('Y-m-d H:i:s') ?: now()->format('Y-m-d H:i:s'));
    $formaPagoRep = (string) data_get($rep, 'forma_pago', '03');
    $monedaPago = strtoupper((string) data_get($rep, 'moneda', $cfdi->moneda ?: 'MXN'));
    $numOperacion = trim((string) data_get($rep, 'num_operacion', ''));

    if ($montoPago <= 0) {
        throw new \RuntimeException('El complemento de pago no tiene monto válido.');
    }

    $payload['Conceptos']['Concepto'][] = [
        'ClaveProdServ' => '84111506',
        'Descripcion' => 'Pago',
        'Cantidad' => '1',
        'ClaveUnidad' => 'ACT',
        'Unidad' => 'Actividad',
        'ValorUnitario' => '0',
        'Importe' => '0',
        'ObjetoImp' => '01',
    ];

    $pago = [
        'FechaPago' => Carbon::parse($fechaPago)->format('Y-m-d\TH:i:s'),
        'FormaDePagoP' => $formaPagoRep,
        'MonedaP' => $monedaPago,
        'Monto' => number_format($montoPago, 2, '.', ''),
    ];

    if ($numOperacion !== '') {
        $pago['NumOperacion'] = $numOperacion;
    }

    $pago['DoctoRelacionado'] = [];

    $totalTrasladosBaseIva16 = 0.0;
    $totalTrasladosImpuestoIva16 = 0.0;

    foreach ((array) data_get($rep, 'documentos_relacionados', []) as $doc) {
        $uuidDoc = strtoupper(trim((string) data_get($doc, 'uuid', '')));

        if ($uuidDoc === '') {
            continue;
        }

        $saldoAnterior = round((float) data_get($doc, 'saldo_anterior', 0), 2);
        $importePagado = round((float) data_get($doc, 'monto_pagado', 0), 2);
        $saldoInsoluto = round((float) data_get($doc, 'saldo_insoluto', max(0, $saldoAnterior - $importePagado)), 2);

        if ($importePagado <= 0) {
            continue;
        }

        $docto = [
            'IdDocumento' => $uuidDoc,
            'MonedaDR' => strtoupper((string) data_get($doc, 'moneda', 'MXN')),
            'EquivalenciaDR' => '1',
            'NumParcialidad' => (string) data_get($doc, 'num_parcialidad', '1'),
            'ImpSaldoAnt' => number_format($saldoAnterior, 2, '.', ''),
            'ImpPagado' => number_format($importePagado, 2, '.', ''),
            'ImpSaldoInsoluto' => number_format($saldoInsoluto, 2, '.', ''),
            'ObjetoImpDR' => (string) data_get($doc, 'objeto_impuesto', '01'),
        ];

        if ((string) data_get($doc, 'objeto_impuesto', '01') === '02') {
            $ivaBaseDr = round((float) data_get($doc, 'iva_base', 0), 2);
            $ivaImporteDr = round((float) data_get($doc, 'iva_importe', 0), 2);

            if ($ivaBaseDr <= 0 && $importePagado > 0) {
                $ivaBaseDr = round($importePagado / 1.16, 2);
                $ivaImporteDr = round($importePagado - $ivaBaseDr, 2);
            }

            $docto['ObjetoImpDR'] = '02';
            $docto['ImpuestosDR'] = [
                'TrasladosDR' => [
                    'TrasladoDR' => [[
                        'BaseDR' => number_format($ivaBaseDr, 2, '.', ''),
                        'ImpuestoDR' => '002',
                        'TipoFactorDR' => 'Tasa',
                        'TasaOCuotaDR' => '0.160000',
                        'ImporteDR' => number_format($ivaImporteDr, 2, '.', ''),
                    ]],
                ],
            ];

            $totalTrasladosBaseIva16 += $ivaBaseDr;
            $totalTrasladosImpuestoIva16 += $ivaImporteDr;
        }

        $serieDoc = trim((string) data_get($doc, 'serie', ''));
        $folioDoc = trim((string) data_get($doc, 'folio', ''));

        if ($serieDoc !== '') {
            $docto['Serie'] = $serieDoc;
        }

        if ($folioDoc !== '') {
            $docto['Folio'] = $folioDoc;
        }

        $pago['DoctoRelacionado'][] = $docto;
    }

    if (empty($pago['DoctoRelacionado'])) {
        throw new \RuntimeException('El complemento de pago no tiene documentos relacionados PPD.');
    }

    if ($totalTrasladosBaseIva16 > 0) {
        $pago['ImpuestosP'] = [
            'TrasladosP' => [
                'TrasladoP' => [[
                    'BaseP' => number_format($totalTrasladosBaseIva16, 2, '.', ''),
                    'ImpuestoP' => '002',
                    'TipoFactorP' => 'Tasa',
                    'TasaOCuotaP' => '0.160000',
                    'ImporteP' => number_format($totalTrasladosImpuestoIva16, 2, '.', ''),
                ]],
            ],
        ];
    }

    $payload['Complemento'] = [
        'Pagos20' => [
            'Version' => '2.0',
            'Totales' => [
                'MontoTotalPagos' => number_format($montoPago, 2, '.', ''),
            ],
            'Pago' => [$pago],
        ],
    ];

    return $payload;
}

    if ($tipo === 'N') {
        $payload['Conceptos']['Concepto'][] = [
            'ClaveProdServ' => '84111505',
            'Descripcion' => 'Pago de nómina',
            'Cantidad' => '1',
            'ClaveUnidad' => 'ACT',
            'Unidad' => 'Actividad',
            'ValorUnitario' => number_format($subtotal, 2, '.', ''),
            'Importe' => number_format($subtotal, 2, '.', ''),
            'Descuento' => number_format($descuento, 2, '.', ''),
            'ObjetoImp' => '01',
        ];

        $fechaPago = optional($cfdi->fecha)->toDateString() ?: now()->toDateString();

        $payload['Complemento'] = [
            'Nomina12' => [
                'Version' => '1.2',
                'TipoNomina' => 'O',
                'FechaPago' => $fechaPago,
                'FechaInicialPago' => now()->startOfMonth()->toDateString(),
                'FechaFinalPago' => now()->endOfMonth()->toDateString(),
                'NumDiasPagados' => '15.000',
                'Receptor' => [
                    'Curp' => (string) data_get($nominaReceptor, 'curp', ''),
                    'NumSeguridadSocial' => (string) data_get($nominaReceptor, 'nss', ''),
                    'FechaInicioRelLaboral' => (string) data_get($nominaReceptor, 'fecha_inicio_relacion_laboral', ''),
                    'TipoContrato' => (string) data_get($nominaReceptor, 'tipo_contrato', ''),
                    'TipoJornada' => (string) data_get($nominaReceptor, 'tipo_jornada', ''),
                    'TipoRegimen' => (string) data_get($nominaReceptor, 'tipo_regimen', ''),
                    'NumEmpleado' => (string) data_get($nominaReceptor, 'numero_empleado', ''),
                    'Departamento' => (string) data_get($nominaReceptor, 'departamento', ''),
                    'Puesto' => (string) data_get($nominaReceptor, 'puesto', ''),
                    'PeriodicidadPago' => (string) data_get($nominaReceptor, 'periodicidad_pago', ''),
                    'SalarioBaseCotApor' => number_format((float) data_get($nominaReceptor, 'salario_base_cot_apor', 0), 2, '.', ''),
                    'SalarioDiarioIntegrado' => number_format((float) data_get($nominaReceptor, 'salario_diario_integrado', 0), 2, '.', ''),
                ],
            ],
        ];

        return $payload;
    }

    foreach ($conceptos as $concepto) {
        $cantidad = (float) ($concepto->cantidad ?? 1);
        $precio = (float) ($concepto->precio_unitario ?? 0);
        $importe = (float) ($concepto->subtotal ?? ($cantidad * $precio));
        $iva = (float) ($concepto->iva ?? 0);
        $objetoImp = (string) ($concepto->objeto_impuesto ?? ($iva > 0 ? '02' : '01'));

        $item = [
            'ClaveProdServ' => (string) ($concepto->clave_producto_sat ?? '01010101'),
            'Descripcion' => (string) ($concepto->descripcion ?? 'Concepto'),
            'Cantidad' => number_format($cantidad, 6, '.', ''),
            'ClaveUnidad' => (string) ($concepto->clave_unidad_sat ?? 'ACT'),
            'Unidad' => (string) ($concepto->unidad ?? 'Actividad'),
            'ValorUnitario' => number_format($precio, 2, '.', ''),
            'Importe' => number_format($importe, 2, '.', ''),
            'ObjetoImp' => $objetoImp,
        ];

        if ((float) ($concepto->descuento ?? 0) > 0) {
            $item['Descuento'] = number_format((float) $concepto->descuento, 2, '.', '');
        }

        if ($iva > 0 && $objetoImp === '02') {
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

    if ($ivaTotal > 0) {
        $payload['Impuestos'] = [
            'TotalImpuestosTrasladados' => number_format($ivaTotal, 2, '.', ''),
            'Traslados' => [
                'Traslado' => [[
                    'Base' => number_format(max(0, $subtotal - $descuento), 2, '.', ''),
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
    try {
        $cuenta = $this->currentCuenta();
        $cuentaId = $cuenta && !empty($cuenta->id) ? (string) $cuenta->id : '';

        $rfc = $this->normalizeRfc($cfdi->emisor_rfc ?? $cfdi->rfc_emisor ?? '');

        $credentialQuery = SatCredential::query();

        if (!empty($cfdi->emisor_credential_id)) {
            $credentialQuery->where('id', (string) $cfdi->emisor_credential_id);
        } elseif ($rfc !== '') {
            $credentialQuery->where('rfc', $rfc);
        } else {
            return '';
        }

        if ($cuentaId !== '') {
            $credentialQuery->where(function ($q) use ($cuentaId) {
                $q->where('cuenta_id', $cuentaId)
                    ->orWhere('account_id', $cuentaId);
            });
        }

        $credential = $credentialQuery->first();

        if (!$credential) {
            return '';
        }

        $meta = is_array($credential->meta ?? null)
            ? $credential->meta
            : (json_decode((string) ($credential->meta ?? ''), true) ?: []);

        $env = strtolower((string) request()->input(
            'facturotopia_env',
            data_get($meta, 'facturotopia.env', 'sandbox')
        ));

        $env = in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';

        $emisorId =
            data_get($meta, "facturotopia.{$env}.emisor_id")
            ?: data_get($meta, "facturotopia.{$env}.id")
            ?: data_get($meta, 'facturotopia.emisor_id')
            ?: data_get($meta, 'facturotopia.id')
            ?: data_get($meta, 'emisor_id')
            ?: data_get($meta, 'facturotopia_emisor_id');

        return $emisorId ? (string) $emisorId : '';
    } catch (\Throwable $e) {
        report($e);

        return '';
    }
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

    public function receptoresIndex(Request $request): View
    {
        $cuenta = $this->currentCuenta();

        if (! $cuenta) {
            abort(403, 'No se pudo identificar la cuenta activa del cliente.');
        }

        $receptores = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->when(trim((string) $request->query('q', '')) !== '', function ($query) use ($request) {
                $q = trim((string) $request->query('q', ''));

                $query->where(function ($w) use ($q) {
                    $w->where('rfc', 'like', "%{$q}%")
                        ->orWhere('razon_social', 'like', "%{$q}%")
                        ->orWhere('nombre_comercial', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderByRaw("COALESCE(razon_social, nombre_comercial, rfc, '') ASC")
            ->paginate(15)
            ->withQueryString();

        return view('cliente.facturacion.receptores.index', [
            'receptores' => $receptores,
            'fiscalCatalogs' => $this->fiscalCatalogs(),
            'q' => trim((string) $request->query('q', '')),
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

    public function receptorDestroy(int $receptor)
{
    $cuenta = $this->currentCuenta();

    if (! $cuenta) {
        return redirect()
            ->route('cliente.facturacion.receptores.index')
            ->withErrors([
                'receptor' => 'No se pudo identificar la cuenta del cliente.',
            ]);
    }

    $item = Receptor::query()
        ->where('cuenta_id', $cuenta->id)
        ->where('id', $receptor)
        ->firstOrFail();

    $item->delete();

    return redirect()
        ->route('cliente.facturacion.receptores.index')
        ->with('ok', 'Receptor eliminado correctamente.');
}

    public function actualizar(Request $request, int $cfdi)
{
    $item = $this->findOwnedCfdi($cfdi);

    if (strtolower((string) $item->estatus) !== 'borrador') {
        return back()->withErrors([
            'cfdi' => 'Solo se pueden actualizar CFDI en borrador.',
        ]);
    }

    $tipoDocumento = strtoupper((string) ($item->tipo_documento ?? $item->tipo_comprobante ?? $request->input('tipo_documento', 'I')));
    $tipoDocumento = in_array($tipoDocumento, ['I', 'E', 'T', 'P', 'N'], true) ? $tipoDocumento : 'I';

    $data = $request->validate([
        'accion_cfdi' => 'nullable|string|in:borrador,timbrar',
        'cliente_id' => ['required', 'string', 'max:80'],
        'receptor_id' => ['nullable', 'integer'],
        'empleado_nomina_id' => ['nullable', 'integer'],
        'serie' => ['nullable', 'string', 'max:10'],
        'folio' => ['nullable', 'string', 'max:20'],
        'fecha' => ['nullable', 'date'],
        'moneda' => ['nullable', 'string', 'max:10'],
        'metodo_pago' => ['nullable', 'string', 'max:10'],
        'forma_pago' => ['nullable', 'string', 'max:10'],
        'uso_cfdi' => ['nullable', 'string', 'max:10'],
        'regimen_receptor' => ['nullable', 'string', 'max:10'],
        'cp_receptor' => ['nullable', 'string', 'max:10'],
        'conceptos' => ['nullable', 'array'],
        'conceptos.*.producto_id' => ['nullable'],
        'conceptos.*.descripcion' => ['nullable', 'string', 'max:500'],
        'conceptos.*.cantidad' => ['nullable', 'numeric', 'min:0.0001'],
        'conceptos.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
        'conceptos.*.iva_tasa' => ['nullable', 'numeric', 'min:0'],
        'conceptos.*.descuento' => ['nullable', 'numeric', 'min:0'],
        'conceptos.*.clave_producto_sat' => ['nullable', 'string', 'max:20'],
        'conceptos.*.clave_unidad_sat' => ['nullable', 'string', 'max:20'],
        'conceptos.*.objeto_impuesto' => ['nullable', 'string', 'max:10'],
        'nomina_total_percepciones' => ['nullable', 'numeric', 'min:0'],
        'nomina_total_deducciones' => ['nullable', 'numeric', 'min:0'],
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

    $receptor = null;
    $empleadoNomina = null;

    $metodoPago = strtoupper((string) ($data['metodo_pago'] ?? 'PUE'));
    $formaPago = strtoupper((string) ($data['forma_pago'] ?? '03'));

    if ($tipoDocumento === 'N') {
        $empleadoNomina = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->where('activo', true)
            ->where('id', (int) ($data['empleado_nomina_id'] ?? $item->empleado_nomina_id ?? 0))
            ->first();

        if (!$empleadoNomina) {
            return back()->withInput()->withErrors([
                'empleado_nomina_id' => 'Selecciona un empleado activo para actualizar CFDI de nómina.',
            ]);
        }

        $percepciones = round((float) ($data['nomina_total_percepciones'] ?? $item->subtotal ?? 0), 2);
        $deducciones = round((float) ($data['nomina_total_deducciones'] ?? $item->descuento ?? 0), 2);

        if ($percepciones <= 0) {
            return back()->withInput()->withErrors([
                'nomina_total_percepciones' => 'Captura el total de percepciones de la nómina.',
            ]);
        }

        $metodoPago = 'PUE';
        $formaPago = '99';

        $data['uso_cfdi'] = 'CN01';
        $data['nomina_tipo'] = $data['nomina_tipo'] ?? 'O';
        $data['nomina_fecha_pago'] = $data['nomina_fecha_pago'] ?? now()->toDateString();
        $data['nomina_fecha_inicial_pago'] = $data['nomina_fecha_inicial_pago'] ?? now()->startOfMonth()->toDateString();
        $data['nomina_fecha_final_pago'] = $data['nomina_fecha_final_pago'] ?? now()->endOfMonth()->toDateString();
        $data['nomina_dias_pagados'] = $data['nomina_dias_pagados'] ?? 15;
        $data['nomina_total_otros_pagos'] = $data['nomina_total_otros_pagos'] ?? 0;
        $data['regimen_receptor'] = $empleadoNomina->regimen_fiscal ?: '605';
        $data['cp_receptor'] = $empleadoNomina->codigo_postal;

        $data['conceptos'] = [[
            'producto_id' => null,
            'descripcion' => 'Pago de nómina',
            'cantidad' => 1,
            'precio_unitario' => $percepciones,
            'iva_tasa' => 0,
            'descuento' => $deducciones,
            'clave_producto_sat' => '84111505',
            'clave_unidad_sat' => 'ACT',
            'objeto_impuesto' => '01',
        ]];
    } else {
        $receptor = Receptor::query()
            ->where('cuenta_id', $cuenta->id)
            ->where('id', (int) ($data['receptor_id'] ?? 0))
            ->first();

        if (!$receptor) {
            return back()->withInput()->withErrors([
                'receptor_id' => 'El receptor seleccionado no pertenece a esta cuenta.',
            ]);
        }

        if (empty($data['conceptos']) || !is_array($data['conceptos'])) {
            return back()->withInput()->withErrors([
                'conceptos' => 'Agrega al menos un concepto para este CFDI.',
            ]);
        }

        if ($metodoPago === 'PPD') {
            $formaPago = '99';
        }
    }

    $subtotal = 0.0;
    $descuento = 0.0;
    $iva = 0.0;
    $total = 0.0;

    foreach (($data['conceptos'] ?? []) as $concepto) {
        $cantidad = (float) ($concepto['cantidad'] ?? 0);
        $precio = (float) ($concepto['precio_unitario'] ?? 0);
        $desc = (float) ($concepto['descuento'] ?? 0);
        $tasa = isset($concepto['iva_tasa']) ? (float) $concepto['iva_tasa'] : 0.16;

        $lineSubtotal = round($cantidad * $precio, 4);
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
        'rfc' => $tipoDocumento === 'N' ? $empleadoNomina?->rfc : $receptor?->rfc,
        'regimen_receptor' => $tipoDocumento === 'N'
            ? ($empleadoNomina?->regimen_fiscal ?: '605')
            : ($data['regimen_receptor'] ?? $receptor?->regimen_fiscal),
        'cp_receptor' => $tipoDocumento === 'N'
            ? $empleadoNomina?->codigo_postal
            : ($data['cp_receptor'] ?? $receptor?->codigo_postal),
        'uso_cfdi' => $tipoDocumento === 'N'
            ? 'CN01'
            : ($data['uso_cfdi'] ?? $receptor?->uso_cfdi),
        'metodo_pago' => $metodoPago,
        'forma_pago' => $formaPago,
        'tipo_documento' => $tipoDocumento,
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
        $descuento,
        $iva,
        $total,
        $metodoPago,
        $formaPago,
        $tipoDocumento,
        $assistant,
        $emisorCredential,
        $empleadoNomina
    ) {
        $now = now();

        $cfdiPayload = [
            'cliente_id' => is_numeric($item->cliente_id) ? (int) $item->cliente_id : 0,
            'emisor_credential_id' => $emisorCredential->id,
            'emisor_rfc' => $emisorCredential->rfc,
            'emisor_razon_social' => $emisorCredential->razon_social,
            'rfc_emisor' => $emisorCredential->rfc,
            'razon_emisor' => $emisorCredential->razon_social,

            'receptor_id' => $tipoDocumento === 'N' ? null : ($data['receptor_id'] ?? null),
            'empleado_nomina_id' => $tipoDocumento === 'N' ? $empleadoNomina?->id : null,
            'receptor_nomina_json' => $tipoDocumento === 'N' ? json_encode([
                'id' => $empleadoNomina?->id,
                'numero_empleado' => $empleadoNomina?->numero_empleado,
                'rfc' => $empleadoNomina?->rfc,
                'curp' => $empleadoNomina?->curp,
                'nss' => $empleadoNomina?->nss,
                'nombre_completo' => $empleadoNomina?->nombre_completo,
                'codigo_postal' => $empleadoNomina?->codigo_postal,
                'regimen_fiscal' => $empleadoNomina?->regimen_fiscal ?: '605',
                'uso_cfdi' => 'CN01',
                'departamento' => $empleadoNomina?->departamento,
                'puesto' => $empleadoNomina?->puesto,
                'tipo_contrato' => $empleadoNomina?->tipo_contrato,
                'tipo_jornada' => $empleadoNomina?->tipo_jornada,
                'tipo_regimen' => $empleadoNomina?->tipo_regimen,
                'periodicidad_pago' => $empleadoNomina?->periodicidad_pago,
                'fecha_inicio_relacion_laboral' => optional($empleadoNomina?->fecha_inicio_relacion_laboral)->toDateString(),
                'salario_base_cot_apor' => $empleadoNomina?->salario_base_cot_apor,
                'salario_diario_integrado' => $empleadoNomina?->salario_diario_integrado,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,

            'serie' => $data['serie'] ?? null,
            'folio' => $data['folio'] ?? null,
            'fecha' => $data['fecha'] ?? $now,
            'moneda' => $data['moneda'] ?? 'MXN',
            'metodo_pago' => $metodoPago,
            'forma_pago' => $formaPago,
            'uso_cfdi' => $tipoDocumento === 'N' ? 'CN01' : ($data['uso_cfdi'] ?? 'G03'),
            'regimen_receptor' => $tipoDocumento === 'N' ? ($empleadoNomina?->regimen_fiscal ?: '605') : ($data['regimen_receptor'] ?? null),
            'cp_receptor' => $tipoDocumento === 'N' ? $empleadoNomina?->codigo_postal : ($data['cp_receptor'] ?? null),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
            'saldo_original' => $total,
            'saldo_pagado' => 0,
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

        foreach (($data['conceptos'] ?? []) as $concepto) {
            $cantidad = (float) ($concepto['cantidad'] ?? 0);
            $precio = (float) ($concepto['precio_unitario'] ?? 0);
            $desc = (float) ($concepto['descuento'] ?? 0);
            $tasa = isset($concepto['iva_tasa']) ? (float) $concepto['iva_tasa'] : 0.16;

            $lineSubtotal = round($cantidad * $precio, 4);
            $lineDesc = min($lineSubtotal, round($desc, 4));
            $base = max(0, $lineSubtotal - $lineDesc);
            $lineIva = round($base * $tasa, 4);
            $lineTotal = round($base + $lineIva, 4);

            $conceptoPayload = [
                'cfdi_id' => $item->id,
                'producto_id' => $concepto['producto_id'] ?? null,
                'descripcion' => $concepto['descripcion'] ?? 'Concepto',
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => round($lineDesc, 2),
                'iva_tasa' => $tasa,
                'subtotal' => round($lineSubtotal, 2),
                'iva' => round($lineIva, 2),
                'total' => round($lineTotal, 2),
                'clave_producto_sat' => $concepto['clave_producto_sat'] ?? null,
                'clave_unidad_sat' => $concepto['clave_unidad_sat'] ?? null,
                'objeto_impuesto' => $concepto['objeto_impuesto'] ?? '02',
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

protected function consumeFacturotopiaStamp(int $adminAccountId): void
{
    if ($adminAccountId <= 0) {
        return;
    }

    $conn = 'mysql_admin';
    $table = 'accounts';

    if (! Schema::connection($conn)->hasTable($table)) {
        return;
    }

    if (! Schema::connection($conn)->hasColumn($table, 'meta')) {
        return;
    }

    DB::connection($conn)->transaction(function () use ($conn, $table, $adminAccountId) {
        $row = DB::connection($conn)
            ->table($table)
            ->where('id', $adminAccountId)
            ->lockForUpdate()
            ->first(['id', 'meta']);

        if (! $row) {
            return;
        }

        $meta = [];

        if (is_string($row->meta ?? null) && trim((string) $row->meta) !== '') {
            $decoded = json_decode((string) $row->meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $asignados = (int) data_get($meta, 'facturotopia.timbres.asignados', 0);
        $consumidos = (int) data_get($meta, 'facturotopia.timbres.consumidos', 0);

        if ($asignados > 0 && $consumidos >= $asignados) {
            throw new \RuntimeException('La cuenta ya no tiene timbres disponibles.');
        }

        data_set($meta, 'facturotopia.timbres.consumidos', $consumidos + 1);
        data_set($meta, 'facturotopia.timbres.disponibles', max(0, $asignados - ($consumidos + 1)));
        data_set($meta, 'facturotopia.timbres.last_consumed_at', now()->toDateTimeString());

        DB::connection($conn)
            ->table($table)
            ->where('id', $adminAccountId)
            ->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    });
}

public function aiFill(Request $request): JsonResponse
{
    $tipo = strtoupper((string) $request->input('tipo_documento', 'I'));
    $descripcion = mb_strtolower(trim((string) $request->input('descripcion', '')), 'UTF-8');

    $claveProducto = '01010101';
    $claveUnidad = 'ACT';
    $objetoImp = '02';
    $ivaTasa = 0.16;

    if (str_contains($descripcion, 'honorario') || str_contains($descripcion, 'servicio')) {
        $claveProducto = '80101500';
        $claveUnidad = 'E48';
    }

    if (str_contains($descripcion, 'software') || str_contains($descripcion, 'sistema')) {
        $claveProducto = '81111500';
        $claveUnidad = 'E48';
    }

    if (str_contains($descripcion, 'renta') || str_contains($descripcion, 'arrendamiento')) {
        $claveProducto = '80131500';
        $claveUnidad = 'E48';
    }

    if ($tipo === 'P') {
        $claveProducto = '84111506';
        $claveUnidad = 'ACT';
        $objetoImp = '01';
        $ivaTasa = 0;
    }

    if ($tipo === 'N') {
        $claveProducto = '84111505';
        $claveUnidad = 'ACT';
        $objetoImp = '01';
        $ivaTasa = 0;
    }

    return response()->json([
        'ok' => true,
        'suggestion' => [
            'clave_producto_sat' => $claveProducto,
            'clave_unidad_sat' => $claveUnidad,
            'objeto_impuesto' => $objetoImp,
            'iva_tasa' => $ivaTasa,
            'descripcion_normalizada' => trim((string) $request->input('descripcion', '')),
        ],
        'message' => 'Sugerencia IA generada con catálogo fiscal local.',
    ]);
}
public function aiFiscal(Request $request): JsonResponse
{
    return response()->json([
        'ok' => true,
        'assistant' => $this->fiscalAssistant($request->all()),
        'catalogs' => $this->fiscalCatalogs(),
    ]);
}

public function downloadTemplate(string $tipo): StreamedResponse
{
    $tipo = strtolower(trim($tipo));

    $headersByType = [
        'conceptos' => [
            'descripcion',
            'cantidad',
            'precio_unitario',
            'descuento',
            'iva_tasa',
            'clave_producto_sat',
            'clave_unidad_sat',
            'objeto_impuesto',
        ],
        'nomina' => [
            'numero_empleado',
            'rfc',
            'curp',
            'nss',
            'nombre_completo',
            'tipo_nomina',
            'fecha_pago',
            'fecha_inicial_pago',
            'fecha_final_pago',
            'dias_pagados',
            'total_percepciones',
            'total_deducciones',
            'total_otros_pagos',
        ],
        'pagos' => [
            'uuid_factura_ppd',
            'serie',
            'folio',
            'moneda',
            'saldo_anterior',
            'monto_pagado',
            'saldo_insoluto',
            'fecha_pago',
            'forma_pago',
            'num_operacion',
        ],
        'carta_porte' => [
            'origen_rfc',
            'origen_cp',
            'destino_rfc',
            'destino_cp',
            'clave_producto_sat',
            'descripcion_mercancia',
            'cantidad',
            'unidad',
            'peso_kg',
            'valor_mercancia',
            'tipo_transporte',
        ],
        'receptores' => [
            'rfc',
            'razon_social',
            'nombre_comercial',
            'regimen_fiscal',
            'uso_cfdi',
            'codigo_postal',
            'email',
            'telefono',
        ],
        'productos' => [
            'sku',
            'descripcion',
            'precio_unitario',
            'iva_tasa',
            'clave_producto_sat',
            'clave_unidad_sat',
            'objeto_impuesto',
        ],
    ];

    $headers = $headersByType[$tipo] ?? $headersByType['conceptos'];
    $filename = 'plantilla_' . $tipo . '_pactopia360_' . now()->format('Ymd_His') . '.csv';

    return response()->streamDownload(function () use ($headers, $tipo) {
        $out = fopen('php://output', 'w');

        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, $headers);

        if ($tipo === 'conceptos') {
            fputcsv($out, [
                'Servicio profesional',
                '1',
                '1000.00',
                '0.00',
                '0.16',
                '80101500',
                'E48',
                '02',
            ]);
        }

        if ($tipo === 'nomina') {
            fputcsv($out, [
                'EMP001',
                'XAXX010101000',
                'CURP000000HDFXXX00',
                '00000000000',
                'EMPLEADO DEMO',
                'O',
                now()->toDateString(),
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
                '15.000',
                '10000.00',
                '1500.00',
                '0.00',
            ]);
        }

        if ($tipo === 'pagos') {
            fputcsv($out, [
                'UUID-FACTURA-PPD',
                'A',
                '100',
                'MXN',
                '1000.00',
                '1000.00',
                '0.00',
                now()->format('Y-m-d H:i:s'),
                '03',
                'OPERACION123',
            ]);
        }

        fclose($out);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

public function importExcel(Request $request): JsonResponse
{
    $request->validate([
        'tipo' => 'nullable|string|max:40',
        'excel_cfdi_import' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
    ]);

    return response()->json([
        'ok' => true,
        'message' => 'Archivo recibido. Falta procesador XLSX avanzado; por ahora la importación queda preparada para conectar parser.',
        'file' => [
            'name' => $request->file('excel_cfdi_import')?->getClientOriginalName(),
            'size' => $request->file('excel_cfdi_import')?->getSize(),
            'mime' => $request->file('excel_cfdi_import')?->getMimeType(),
        ],
    ]);
}

public function repPendientes(Request $request): JsonResponse
{
    $cuenta = $this->currentCuenta();

    if (!$cuenta) {
        return response()->json([
            'ok' => false,
            'message' => 'No se pudo identificar la cuenta activa.',
            'items' => [],
        ], 422);
    }

    $conn = $this->cfdiConn();
    $table = (new Cfdi)->getTable();

    $query = $this->cfdiBaseQuery($request)
        ->where('metodo_pago', 'PPD')
        ->whereIn('estatus', ['emitido', 'timbrado']);

    $receptorId = (int) $request->query('receptor_id', 0);

    if ($receptorId > 0 && $this->hasColumn($table, 'receptor_id', $conn)) {
        $query->where('receptor_id', $receptorId);
    }

    if ($this->hasColumn($table, 'saldo_pendiente', $conn)) {
        $query->where('saldo_pendiente', '>', 0);
    }

    $items = $query
        ->orderByDesc('fecha')
        ->limit(200)
        ->get([
            'id',
            'uuid',
            'serie',
            'folio',
            'fecha',
            'total',
            'saldo_original',
            'saldo_pagado',
            'saldo_pendiente',
            'receptor_id',
            'moneda',
        ])
        ->map(fn ($row) => [
            'id' => $row->id,
            'uuid' => $row->uuid,
            'serie' => $row->serie,
            'folio' => $row->folio,
            'fecha' => optional($row->fecha)->format('Y-m-d'),
            'moneda' => $row->moneda ?: 'MXN',
            'total' => round((float) ($row->total ?? 0), 2),
            'saldo_original' => round((float) ($row->saldo_original ?? $row->total ?? 0), 2),
            'saldo_pagado' => round((float) ($row->saldo_pagado ?? 0), 2),
            'saldo_pendiente' => round((float) ($row->saldo_pendiente ?? $row->total ?? 0), 2),
            'label' => trim(($row->serie ?: 'S') . '-' . ($row->folio ?: $row->id)),
        ])
        ->values();

    return response()->json([
        'ok' => true,
        'items' => $items,
    ]);
}

public function empleadosNomina(Request $request): JsonResponse
{
    $cuenta = $this->currentCuenta();

    if (!$cuenta) {
        return response()->json([
            'ok' => false,
            'message' => 'No se pudo identificar la cuenta activa.',
            'items' => [],
        ], 422);
    }

    $q = trim((string) $request->query('q', ''));

    $items = EmpleadoNomina::query()
        ->where('cuenta_id', (string) $cuenta->id)
        ->where('activo', true)
        ->when($q !== '', function ($query) use ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('nombre_completo', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('numero_empleado', 'like', "%{$q}%")
                    ->orWhere('curp', 'like', "%{$q}%");
            });
        })
        ->orderBy('nombre_completo')
        ->limit(200)
        ->get([
            'id',
            'numero_empleado',
            'rfc',
            'curp',
            'nss',
            'nombre_completo',
            'email',
            'codigo_postal',
            'regimen_fiscal',
            'uso_cfdi',
            'tipo_contrato',
            'tipo_jornada',
            'tipo_regimen',
            'periodicidad_pago',
            'departamento',
            'puesto',
            'riesgo_puesto',
            'salario_base_cot_apor',
            'salario_diario_integrado',
        ])
        ->map(fn ($row) => [
            'id' => $row->id,
            'label' => trim(($row->numero_empleado ?: 'SIN NUM') . ' · ' . ($row->nombre_completo ?: 'Empleado') . ' · ' . ($row->rfc ?: 'Sin RFC')),
            'numero_empleado' => $row->numero_empleado,
            'rfc' => $row->rfc,
            'curp' => $row->curp,
            'nss' => $row->nss,
            'nombre_completo' => $row->nombre_completo,
            'codigo_postal' => $row->codigo_postal,
            'regimen_fiscal' => $row->regimen_fiscal ?: '605',
            'uso_cfdi' => 'CN01',
            'tipo_contrato' => $row->tipo_contrato,
            'tipo_jornada' => $row->tipo_jornada,
            'tipo_regimen' => $row->tipo_regimen,
            'periodicidad_pago' => $row->periodicidad_pago,
            'departamento' => $row->departamento,
            'puesto' => $row->puesto,
            'riesgo_puesto' => $row->riesgo_puesto,
            'salario_base_cot_apor' => $row->salario_base_cot_apor,
            'salario_diario_integrado' => $row->salario_diario_integrado,
        ])
        ->values();

    return response()->json([
        'ok' => true,
        'items' => $items,
    ]);
}

public function catalogosPorTipo(string $tipo): JsonResponse
{
    $tipo = strtoupper($tipo);
    $tipo = in_array($tipo, ['I', 'E', 'T', 'P', 'N'], true) ? $tipo : 'I';

    $base = $this->fiscalCatalogs();

    $rules = [
        'I' => [
            'receptor_tipo' => 'receptor',
            'conceptos' => true,
            'adenda' => true,
            'complementos' => ['retenciones', 'comercio_ext'],
            'uso_default' => 'G03',
            'metodo_default' => 'PUE',
            'forma_default' => '03',
        ],
        'E' => [
            'receptor_tipo' => 'receptor',
            'conceptos' => true,
            'adenda' => true,
            'complementos' => [],
            'uso_default' => 'G02',
            'metodo_default' => 'PUE',
            'forma_default' => '03',
            'requiere_cfdi_relacionado' => true,
        ],
        'T' => [
            'receptor_tipo' => 'receptor',
            'conceptos' => false,
            'adenda' => false,
            'complementos' => ['carta_porte'],
            'uso_default' => 'S01',
            'metodo_default' => null,
            'forma_default' => null,
        ],
        'P' => [
            'receptor_tipo' => 'receptor',
            'conceptos' => false,
            'adenda' => false,
            'complementos' => ['pagos'],
            'uso_default' => 'CP01',
            'metodo_default' => 'PPD',
            'forma_default' => '99',
            'requiere_ppd' => true,
        ],
        'N' => [
            'receptor_tipo' => 'empleado',
            'conceptos' => false,
            'adenda' => false,
            'complementos' => ['nomina'],
            'uso_default' => 'CN01',
            'metodo_default' => 'PUE',
            'forma_default' => '99',
        ],
    ];

    return response()->json([
        'ok' => true,
        'tipo' => $tipo,
        'rules' => $rules[$tipo],
        'catalogs' => $base,
    ]);
}


protected function resolvePortalIsProFromAdmin(?object $cuenta, array $summary = []): bool
{
    $normalize = static function (?string $raw): bool {
        $p = strtolower(trim((string) $raw));
        $p = str_replace([' ', '-'], '_', $p);
        $p = preg_replace('/_+/', '_', $p) ?: '';

        foreach (['_mensual', '_anual', '_monthly', '_yearly', '_annual'] as $suffix) {
            if (str_ends_with($p, $suffix)) {
                $p = substr($p, 0, -strlen($suffix));
                break;
            }
        }

        return in_array($p, ['pro', 'premium', 'empresa', 'business', 'enterprise', 'empresarial'], true);
    };

    try {
        if ($cuenta && Schema::connection('mysql_admin')->hasTable('accounts')) {
            $q = DB::connection('mysql_admin')->table('accounts');

            if (! empty($cuenta->admin_account_id)) {
                $row = (clone $q)->where('id', (int) $cuenta->admin_account_id)->first();

                if ($row) {
                    return $normalize(($row->plan_actual ?? null) ?: ($row->plan ?? null));
                }
            }

            foreach (['rfc', 'rfc_padre'] as $field) {
                if (! empty($cuenta->{$field}) && Schema::connection('mysql_admin')->hasColumn('accounts', 'rfc')) {
                    $row = (clone $q)
                        ->whereRaw('UPPER(rfc) = ?', [strtoupper(trim((string) $cuenta->{$field}))])
                        ->first();

                    if ($row) {
                        return $normalize(($row->plan_actual ?? null) ?: ($row->plan ?? null));
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        report($e);
    }

    return (bool) ($summary['is_pro'] ?? false);
}

}