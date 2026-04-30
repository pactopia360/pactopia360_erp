<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\EmpleadoNomina;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NominaEmpleadosController extends Controller
{
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

        return $cuenta && !empty($cuenta->id) ? $cuenta : null;
    }

    public function index(Request $request): View
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            abort(403, 'No se pudo identificar la cuenta activa.');
        }

        $q = trim((string) $request->query('q', ''));

        $empleados = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nombre_completo', 'like', "%{$q}%")
                        ->orWhere('rfc', 'like', "%{$q}%")
                        ->orWhere('curp', 'like', "%{$q}%")
                        ->orWhere('numero_empleado', 'like', "%{$q}%")
                        ->orWhere('departamento', 'like', "%{$q}%")
                        ->orWhere('puesto', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('activo')
            ->orderBy('nombre_completo')
            ->paginate(15)
            ->withQueryString();

        return view('cliente.rh.empleados.index', [
            'empleados' => $empleados,
            'q' => $q,
            'catalogos' => $this->catalogosNomina(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()->withInput()->withErrors([
                'cuenta' => 'No se pudo identificar la cuenta activa.',
            ]);
        }

        $data = $this->validated($request);
        $data['cuenta_id'] = (string) $cuenta->id;

        EmpleadoNomina::create($data);

        return redirect()
            ->route('cliente.rh.empleados.index')
            ->with('ok', 'Empleado de nómina creado correctamente.');
    }

    public function update(Request $request, int $empleado): RedirectResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()->withInput()->withErrors([
                'cuenta' => 'No se pudo identificar la cuenta activa.',
            ]);
        }

        $item = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->where('id', $empleado)
            ->firstOrFail();

        $item->update($this->validated($request, $item->id));

        return redirect()
            ->route('cliente.rh.empleados.index')
            ->with('ok', 'Empleado de nómina actualizado correctamente.');
    }

    public function destroy(int $empleado): RedirectResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()->withErrors([
                'cuenta' => 'No se pudo identificar la cuenta activa.',
            ]);
        }

        $item = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->where('id', $empleado)
            ->firstOrFail();

        $item->delete();

        return redirect()
            ->route('cliente.rh.empleados.index')
            ->with('ok', 'Empleado eliminado correctamente.');
    }

    public function toggle(int $empleado): RedirectResponse
    {
        $cuenta = $this->currentCuenta();

        if (!$cuenta) {
            return back()->withErrors([
                'cuenta' => 'No se pudo identificar la cuenta activa.',
            ]);
        }

        $item = EmpleadoNomina::query()
            ->where('cuenta_id', (string) $cuenta->id)
            ->where('id', $empleado)
            ->firstOrFail();

        $item->update([
            'activo' => !$item->activo,
        ]);

        return redirect()
            ->route('cliente.rh.empleados.index')
            ->with('ok', $item->activo ? 'Empleado activado.' : 'Empleado desactivado.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $catalogos = $this->catalogosNomina();

        $data = $request->validate([
            'numero_empleado' => ['required', 'string', 'max:60'],
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
            'curp' => ['required', 'string', 'size:18'],
            'nss' => ['nullable', 'string', 'max:20'],

            'nombre' => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['required', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],

            'email' => ['nullable', 'email', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:40'],

            'codigo_postal' => ['required', 'regex:/^[0-9]{5}$/'],
            'regimen_fiscal' => ['required', Rule::in(['605'])],
            'uso_cfdi' => ['required', Rule::in(['CN01'])],

            'fecha_inicio_relacion_laboral' => ['nullable', 'date'],
            'tipo_contrato' => ['required', Rule::in(array_keys($catalogos['tipos_contrato']))],
            'tipo_jornada' => ['nullable', Rule::in(array_keys($catalogos['tipos_jornada']))],
            'tipo_regimen' => ['required', Rule::in(array_keys($catalogos['tipos_regimen']))],
            'periodicidad_pago' => ['required', Rule::in(array_keys($catalogos['periodicidades_pago']))],

            'departamento' => ['nullable', 'string', 'max:160'],
            'puesto' => ['nullable', 'string', 'max:160'],
            'riesgo_puesto' => ['nullable', Rule::in(array_keys($catalogos['riesgos_puesto']))],

            'salario_base_cot_apor' => ['nullable', 'numeric', 'min:0'],
            'salario_diario_integrado' => ['nullable', 'numeric', 'min:0'],
            'banco' => ['nullable', Rule::in(array_keys($catalogos['bancos']))],
            'cuenta_bancaria' => ['nullable', 'string', 'max:30'],

            'sindicalizado' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],

            'metodo_asistencia' => ['nullable', Rule::in(array_keys($catalogos['metodos_asistencia']))],
            'codigo_biometrico' => ['nullable', 'string', 'max:80'],
            'pin_asistencia' => ['nullable', 'string', 'max:20'],
            'telefono_whatsapp' => ['nullable', 'string', 'max:30'],
            'dispositivo_biometrico' => ['nullable', 'string', 'max:120'],
            'sincronizar_asistencia' => ['nullable', 'boolean'],
            'meta_asistencia' => ['nullable', 'array'],
        ], [
            'numero_empleado.required' => 'El número de empleado es obligatorio para CFDI de nómina.',
            'rfc.required' => 'El RFC del empleado es obligatorio.',
            'rfc.min' => 'El RFC debe tener 12 o 13 caracteres.',
            'rfc.max' => 'El RFC debe tener 12 o 13 caracteres.',
            'curp.required' => 'La CURP es obligatoria para CFDI de nómina.',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres.',
            'nombre.required' => 'El nombre del empleado es obligatorio.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio para formar el nombre fiscal.',
            'codigo_postal.required' => 'El CP fiscal es obligatorio para CFDI 4.0.',
            'codigo_postal.regex' => 'El CP fiscal debe tener exactamente 5 dígitos.',
            'regimen_fiscal.in' => 'Para empleados de nómina el régimen fiscal debe ser 605.',
            'uso_cfdi.in' => 'Para CFDI de nómina el uso CFDI debe ser CN01.',
            'tipo_contrato.required' => 'El tipo de contrato es obligatorio.',
            'tipo_contrato.in' => 'Selecciona un tipo de contrato válido.',
            'tipo_jornada.in' => 'Selecciona un tipo de jornada válido.',
            'tipo_regimen.required' => 'El tipo de régimen de nómina es obligatorio.',
            'tipo_regimen.in' => 'Selecciona un tipo de régimen válido.',
            'periodicidad_pago.required' => 'La periodicidad de pago es obligatoria.',
            'periodicidad_pago.in' => 'Selecciona una periodicidad válida.',
            'riesgo_puesto.in' => 'Selecciona un riesgo de puesto válido.',
            'banco.in' => 'Selecciona un banco válido.',
        ]);

        $data['rfc'] = strtoupper(preg_replace('/[^A-ZÑ&0-9]/i', '', (string) ($data['rfc'] ?? '')));
        $data['curp'] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($data['curp'] ?? '')));
        $data['codigo_postal'] = preg_replace('/\D+/', '', (string) ($data['codigo_postal'] ?? ''));
        $data['regimen_fiscal'] = '605';
        $data['uso_cfdi'] = 'CN01';
        $data['sindicalizado'] = (bool) ($data['sindicalizado'] ?? false);
        $data['activo'] = (bool) ($data['activo'] ?? true);

        $data['metodo_asistencia'] = $data['metodo_asistencia'] ?? 'manual';
        $data['telefono_whatsapp'] = !empty($data['telefono_whatsapp'])
            ? preg_replace('/\D+/', '', (string) $data['telefono_whatsapp'])
            : null;
        $data['codigo_biometrico'] = !empty($data['codigo_biometrico'])
            ? strtoupper(trim((string) $data['codigo_biometrico']))
            : null;
        $data['sincronizar_asistencia'] = (bool) ($data['sincronizar_asistencia'] ?? false);

        return $data;
    }

    protected function catalogosNomina(): array
    {
        return [
            'regimenes_fiscales' => [
                '605' => '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios',
            ],

            'usos_cfdi' => [
                'CN01' => 'CN01 - Nómina',
            ],

            'tipos_contrato' => [
                '01' => '01 - Contrato de trabajo por tiempo indeterminado',
                '02' => '02 - Contrato de trabajo para obra determinada',
                '03' => '03 - Contrato de trabajo por tiempo determinado',
                '04' => '04 - Contrato de trabajo por temporada',
                '05' => '05 - Contrato de trabajo sujeto a prueba',
                '06' => '06 - Contrato de trabajo con capacitación inicial',
                '07' => '07 - Modalidad de contratación por pago de hora laborada',
                '08' => '08 - Modalidad de trabajo por comisión laboral',
                '09' => '09 - Modalidades de contratación donde no existe relación de trabajo',
                '10' => '10 - Jubilación, pensión, retiro',
                '99' => '99 - Otro contrato',
            ],

            'tipos_jornada' => [
                '01' => '01 - Diurna',
                '02' => '02 - Nocturna',
                '03' => '03 - Mixta',
                '04' => '04 - Por hora',
                '05' => '05 - Reducida',
                '06' => '06 - Continuada',
                '07' => '07 - Partida',
                '08' => '08 - Por turnos',
                '99' => '99 - Otra jornada',
            ],

            'tipos_regimen' => [
                '02' => '02 - Sueldos',
                '03' => '03 - Jubilados',
                '04' => '04 - Pensionados',
                '05' => '05 - Asimilados miembros sociedades cooperativas producción',
                '06' => '06 - Asimilados integrantes sociedades asociaciones civiles',
                '07' => '07 - Asimilados miembros consejos',
                '08' => '08 - Asimilados comisionistas',
                '09' => '09 - Asimilados honorarios',
                '10' => '10 - Asimilados acciones',
                '11' => '11 - Asimilados otros',
                '12' => '12 - Jubilados o pensionados',
                '13' => '13 - Indemnización o separación',
                '99' => '99 - Otro régimen',
            ],

            'periodicidades_pago' => [
                '01' => '01 - Diario',
                '02' => '02 - Semanal',
                '03' => '03 - Catorcenal',
                '04' => '04 - Quincenal',
                '05' => '05 - Mensual',
                '06' => '06 - Bimestral',
                '07' => '07 - Unidad obra',
                '08' => '08 - Comisión',
                '09' => '09 - Precio alzado',
                '10' => '10 - Decenal',
                '99' => '99 - Otra periodicidad',
            ],

            'riesgos_puesto' => [
                '1' => '1 - Clase I',
                '2' => '2 - Clase II',
                '3' => '3 - Clase III',
                '4' => '4 - Clase IV',
                '5' => '5 - Clase V',
            ],

            'bancos' => [
                '002' => '002 - Banamex',
                '006' => '006 - Bancomext',
                '009' => '009 - Banobras',
                '012' => '012 - BBVA México',
                '014' => '014 - Santander',
                '019' => '019 - Banjército',
                '021' => '021 - HSBC',
                '030' => '030 - Bajío',
                '032' => '032 - IXE',
                '036' => '036 - Inbursa',
                '042' => '042 - Mifel',
                '044' => '044 - Scotiabank',
                '058' => '058 - Banregio',
                '059' => '059 - Invex',
                '060' => '060 - Bansi',
                '062' => '062 - Afirme',
                '072' => '072 - Banorte',
                '106' => '106 - Bank of America',
                '108' => '108 - MUFG',
                '110' => '110 - JP Morgan',
                '112' => '112 - BMONEX',
                '113' => '113 - Ve por Más',
                '126' => '126 - Credit Suisse',
                '127' => '127 - Azteca',
                '128' => '128 - Autofin',
                '129' => '129 - Barclays',
                '130' => '130 - Compartamos',
                '131' => '131 - Banco Famsa',
                '132' => '132 - Multiva',
                '133' => '133 - Actinver',
                '136' => '136 - Intercam',
                '137' => '137 - BanCoppel',
                '138' => '138 - ABC Capital',
                '140' => '140 - Consubanco',
                '143' => '143 - CIBanco',
                '145' => '145 - Bancrea',
                '147' => '147 - Bankaool',
                '148' => '148 - Pagatodo',
                '150' => '150 - Inmobiliario',
                '152' => '152 - Bancrea',
                '154' => '154 - Banco Covalto',
                '156' => '156 - Sabadell',
                '166' => '166 - Banco del Bienestar',
                '168' => '168 - Hipotecaria Federal',
            ],

            'metodos_asistencia' => [
                'manual' => 'Manual / Captura interna',
                'whatsapp' => 'WhatsApp',
                'biometrico' => 'Biométrico externo',
                'mixto' => 'Mixto: WhatsApp + biométrico',
            ],
        ];
    }
}