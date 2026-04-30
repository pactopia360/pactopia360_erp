@extends('layouts.cliente')

@section('title', 'Empleados Nómina · Pactopia360')
@section('pageClass', 'page-sat page-sat-clean page-facturacion-360 page-rh-empleados')

@push('styles')
@php
    $SAT_CSS_REL = 'assets/client/css/sat/sat-portal-v1.css';
    $SAT_CSS_ABS = public_path($SAT_CSS_REL);
    $SAT_CSS_V   = is_file($SAT_CSS_ABS) ? (string) filemtime($SAT_CSS_ABS) : null;

    $FX360_CSS_REL = 'assets/client/css/pages/facturacion-360.css';
    $FX360_CSS_ABS = public_path($FX360_CSS_REL);
    $FX360_CSS_V   = is_file($FX360_CSS_ABS) ? (string) filemtime($FX360_CSS_ABS) : null;

    $RH360_CSS_REL = 'assets/client/css/pages/rh-empleados.css';
    $RH360_CSS_ABS = public_path($RH360_CSS_REL);
    $RH360_CSS_V   = is_file($RH360_CSS_ABS) ? (string) filemtime($RH360_CSS_ABS) : null;
@endphp

<link rel="stylesheet" href="{{ asset($SAT_CSS_REL) }}{{ $SAT_CSS_V ? ('?v='.$SAT_CSS_V) : '' }}">
<link rel="stylesheet" href="{{ asset($FX360_CSS_REL) }}{{ $FX360_CSS_V ? ('?v='.$FX360_CSS_V) : '' }}">
<link rel="stylesheet" href="{{ asset($RH360_CSS_REL) }}{{ $RH360_CSS_V ? ('?v='.$RH360_CSS_V) : '' }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $q = $q ?? request('q', '');

    $empleadosCollection = collect();

    if (isset($empleados)) {
        if ($empleados instanceof \Illuminate\Contracts\Pagination\Paginator) {
            $empleadosCollection = collect($empleados->items());
        } elseif ($empleados instanceof \Illuminate\Support\Collection) {
            $empleadosCollection = $empleados;
        } elseif (is_array($empleados)) {
            $empleadosCollection = collect($empleados);
        }
    }

    $totalEmpleados = method_exists($empleados, 'total') ? (int) $empleados->total() : $empleadosCollection->count();
    $activos = $empleadosCollection->filter(fn ($e) => (bool) ($e->activo ?? false))->count();
    $inactivos = max(0, $totalEmpleados - $activos);
    $sinCurp = $empleadosCollection->filter(fn ($e) => empty($e->curp))->count();

    $rtStore = Route::has('cliente.rh.empleados.store') ? route('cliente.rh.empleados.store') : '#';
    $rtIndex = Route::has('cliente.rh.empleados.index') ? route('cliente.rh.empleados.index') : '#';
    $rtCfdiNuevo = Route::has('cliente.facturacion.create') ? route('cliente.facturacion.create') : '#';
@endphp

<div class="sat-clean-shell fx360-shell rh360-shell">
    <div class="sat-clean-container">

        <section class="sat-clean-hero sat-clean-hero--portal sat-clean-hero--portal-simple" aria-label="Recursos Humanos">
            <div class="sat-clean-hero__content sat-clean-hero__content--portal sat-clean-hero__content--portal-simple">
                <div class="sat-clean-hero__main sat-clean-hero__main--portal-simple">
                    <div
                        aria-hidden="true"
                        style="
                            position:absolute;
                            inset:0;
                            border-radius:inherit;
                            background:
                                radial-gradient(circle at 18% 22%, rgba(255,255,255,.10), transparent 28%),
                                linear-gradient(90deg, rgba(7,20,54,.22) 0%, rgba(7,20,54,.10) 42%, rgba(255,255,255,0) 76%);
                            pointer-events:none;
                            z-index:1;
                        "
                    ></div>

                    <div style="min-width:0; max-width:100%; position:relative; z-index:2; padding:8px 0;">
                        <div
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:8px;
                                min-height:30px;
                                padding:0 14px;
                                border-radius:999px;
                                background:rgba(255,255,255,.16);
                                border:1px solid rgba(255,255,255,.18);
                                color:#f8fbff;
                                font-size:11px;
                                font-weight:800;
                                letter-spacing:.05em;
                                margin-bottom:16px;
                                backdrop-filter:blur(4px);
                                box-shadow:0 8px 20px rgba(10,24,64,.12);
                            "
                        >
                            <span
                                style="width:10px; height:10px; border-radius:999px; background:#ffffff; box-shadow:0 0 0 6px rgba(255,255,255,.12); display:inline-block;"
                                aria-hidden="true"
                            ></span>
                            <span>RH · NÓMINA 360</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:14px; margin:0 0 14px 0;">
                            <span
                                style="
                                    width:58px;
                                    height:58px;
                                    border-radius:18px;
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    background:rgba(255,255,255,.16);
                                    border:1px solid rgba(255,255,255,.18);
                                    box-shadow:0 14px 28px rgba(10,24,64,.20);
                                    flex:0 0 58px;
                                    color:#ffffff;
                                "
                                aria-hidden="true"
                            >
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                            </span>

                            <h1
                                class="sat-clean-hero__title sat-clean-hero__title--portal"
                                style="
                                    margin:0;
                                    color:#ffffff;
                                    font-size:clamp(36px, 3vw, 56px);
                                    line-height:.98;
                                    letter-spacing:-.05em;
                                    font-weight:900;
                                    text-shadow:0 10px 24px rgba(10,24,64,.24);
                                "
                            >
                                Empleados de nómina
                            </h1>
                        </div>

                        <p
                            class="sat-clean-hero__text sat-clean-hero__text--portal"
                            style="
                                max-width:760px;
                                margin:0 0 18px 0;
                                color:rgba(255,255,255,.96);
                                font-size:15px;
                                line-height:1.58;
                                font-weight:500;
                                text-shadow:0 6px 18px rgba(10,24,64,.18);
                            "
                        >
                            Administra empleados fiscales para CFDI tipo N, separados de receptores comerciales.
                            Este catálogo alimenta el flujo de nómina en Nuevo CFDI.
                        </p>

                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <span class="rh360-hero-pill">Empleados</span>
                            <span class="rh360-hero-pill">CFDI Nómina</span>
                            <span class="rh360-hero-pill">RFC</span>
                            <span class="rh360-hero-pill">CURP</span>
                            <span class="rh360-hero-pill">Datos laborales</span>
                        </div>
                    </div>

                    <div class="fx360-sidewrap">
                        <div class="fx360-sidecard">
                            <div class="fx360-sidegrid">
                                <div class="fx360-sidebox">
                                    <div class="fx360-sidebox__head">
                                        <div class="fx360-sidebox__label">EMPLEADOS</div>
                                        <span class="fx360-sidebox__icon fx360-sidebox__icon--blue" aria-hidden="true">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                                <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/>
                                            </svg>
                                        </span>
                                    </div>

                                    <div class="fx360-sidebox__value">{{ number_format($totalEmpleados) }}</div>
                                    <div class="fx360-sidebox__sub">Registrados</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if(session('ok'))
            <div class="fx360-flash fx360-flash--ok">{{ session('ok') }}</div>
        @endif

        @if($errors->any())
            <div class="fx360-flash fx360-flash--err">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="fx360-topgrid" aria-label="Indicadores nómina">
            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Total empleados</div>
                <div class="fx360-kpi__value">{{ number_format($totalEmpleados) }}</div>
                <div class="fx360-kpi__sub">Catálogo de nómina</div>
            </article>

            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Activos</div>
                <div class="fx360-kpi__value">{{ number_format($activos) }}</div>
                <div class="fx360-kpi__sub">Disponibles para CFDI N</div>
            </article>

            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Inactivos</div>
                <div class="fx360-kpi__value">{{ number_format($inactivos) }}</div>
                <div class="fx360-kpi__sub">No recomendados para timbrar</div>
            </article>

            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Pendientes</div>
                <div class="fx360-kpi__value" style="color:{{ $sinCurp > 0 ? '#b45309' : '#16a34a' }}">
                    {{ number_format($sinCurp) }}
                </div>
                <div class="fx360-kpi__sub">Sin CURP capturada</div>
            </article>
        </section>

        <section class="sat-clean-accordion" aria-label="Alta de empleado">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Alta de empleado</span>
                        <span class="sat-clean-accordion__bar-text">Captura fiscal y laboral para CFDI tipo Nómina.</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <button
                            type="button"
                            class="rh360-header-action"
                            data-open-dialog="rhCreateDialog"
                            data-tip="Nuevo empleado"
                            aria-label="Nuevo empleado"
                        >
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </button>

                        <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="fx360-tiles" style="padding-top:12px;">
                        <button type="button" class="fx360-tile rh360-tile-button" data-open-dialog="rhCreateDialog">
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Nuevo empleado</div>
                            <div class="fx360-tile__sub">Abre un emergente con los datos fiscales y laborales necesarios.</div>
                        </button>

                        <a href="{{ $rtCfdiNuevo }}" class="fx360-tile">
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 5h16v14H4z"/>
                                    <path d="M8 9h8M8 13h8M8 17h5"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Nuevo CFDI Nómina</div>
                            <div class="fx360-tile__sub">Abre el flujo de CFDI y selecciona tipo N · Nómina.</div>
                        </a>

                        <div class="fx360-tile">
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 8h.01M11 12h1v4h1"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Validación fiscal</div>
                            <div class="fx360-tile__sub">RFC, CURP, CP, régimen 605 y datos laborales alimentan el timbrado.</div>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="sat-clean-accordion" aria-label="Listado de empleados">
            <details class="sat-clean-accordion__item" open>
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Listado</span>
                        <span class="sat-clean-accordion__bar-text">{{ number_format($totalEmpleados) }} empleado{{ $totalEmpleados === 1 ? '' : 's' }} registrado{{ $totalEmpleados === 1 ? '' : 's' }}.</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="sat-clean-status-badge is-success">{{ number_format($activos) }} activo{{ $activos === 1 ? '' : 's' }}</span>

                        <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="fx360-panel" style="margin-top:12px;">
                        <form method="GET" action="{{ $rtIndex }}">
                            <div class="fx360-filters rh360-filters">
                                <div>
                                    <label class="fx360-label">Buscar</label>
                                    <input class="fx360-input" type="text" name="q" value="{{ $q }}" placeholder="Empleado, RFC, CURP, número, área...">
                                </div>

                                <div class="fx360-inline-actions">
                                    <button type="submit" class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact">
                                        Buscar
                                    </button>

                                    <a href="{{ $rtIndex }}" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact">
                                        Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="sat-clean-rfc-table-wrap sat-clean-rfc-table-wrap--minimal fx360-table-card">
                        <table class="sat-clean-rfc-table sat-clean-rfc-table--minimal">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>RFC / CURP</th>
                                    <th>Área</th>
                                    <th>Fiscal</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($empleados as $e)
                                    <tr>
                                        <td>
                                            <strong>{{ $e->nombre_completo ?: 'Empleado sin nombre' }}</strong>
                                            <div class="sat-clean-rfc-inline-text">
                                                {{ $e->numero_empleado ?: 'Sin número de empleado' }}
                                            </div>
                                        </td>

                                        <td>
                                            <strong>{{ $e->rfc ?: 'RFC pendiente' }}</strong>
                                            <div class="sat-clean-rfc-inline-text">
                                                {{ $e->curp ?: 'CURP pendiente' }}
                                            </div>
                                        </td>

                                        <td>
                                            <strong>{{ $e->departamento ?: 'Sin departamento' }}</strong>
                                            <div class="sat-clean-rfc-inline-text">
                                                {{ $e->puesto ?: 'Sin puesto' }}
                                            </div>
                                        </td>

                                        <td>
                                            <strong>Reg. {{ $e->regimen_fiscal ?: '605' }}</strong>
                                            <div class="sat-clean-rfc-inline-text">
                                                CP {{ $e->codigo_postal ?: 'pendiente' }}
                                            </div>
                                        </td>

                                        <td>
                                            @if($e->activo)
                                                <span class="sat-clean-status-badge is-success">Activo</span>
                                            @else
                                                <span class="sat-clean-status-badge is-muted">Inactivo</span>
                                            @endif
                                        </td>

                                        <td class="text-end">
                                            <div class="sat-clean-icon-actions fx360-actions-icons">
                                                <form method="POST" action="{{ route('cliente.rh.empleados.toggle', $e->id) }}" style="display:inline-flex; margin:0;">
                                                    @csrf
                                                    <button type="submit" class="fx360-action-icon" data-tip="{{ $e->activo ? 'Desactivar' : 'Activar' }}" aria-label="{{ $e->activo ? 'Desactivar' : 'Activar' }}">
                                                        @if($e->activo)
                                                            <svg viewBox="0 0 24 24" fill="none">
                                                                <path d="M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            </svg>
                                                        @else
                                                            <svg viewBox="0 0 24 24" fill="none">
                                                                <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        @endif
                                                    </button>
                                                </form>

                                                <button
                                                    type="button"
                                                    class="fx360-action-icon"
                                                    data-open-dialog="rhEditDialog{{ $e->id }}"
                                                    data-tip="Editar"
                                                    aria-label="Editar empleado"
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M4 20H8.6L19.25 9.35C20.05 8.55 20.05 7.25 19.25 6.45L17.55 4.75C16.75 3.95 15.45 3.95 14.65 4.75L4 15.4V20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                        <path d="M13.5 5.9L18.1 10.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    </svg>
                                                </button>

                                                <form method="POST" action="{{ route('cliente.rh.empleados.destroy', $e->id) }}" onsubmit="return confirm('¿Eliminar empleado?')" style="display:inline-flex; margin:0;">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit" class="fx360-action-icon fx360-action-icon--danger" data-tip="Eliminar" aria-label="Eliminar empleado">
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M6.5 7L7.3 20H16.7L17.5 7" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                            <path d="M9 7V4H15V7" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="sat-clean-empty-state sat-clean-empty-state--compact">
                                                <div class="sat-clean-empty-state__title">No hay empleados registrados</div>
                                                <div class="sat-clean-empty-state__text">Agrega empleados para poder emitir CFDI tipo N · Nómina.</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div style="padding-top:12px;">
                        {{ $empleados->onEachSide(1)->links() }}
                    </div>
                </div>
            </details>
        </section>
    </div>
</div>

<dialog class="rh360-dialog" id="rhCreateDialog">
    <div class="rh360-dialog__head">
        <div>
            <span>Alta de empleado</span>
            <strong>Nuevo empleado de nómina</strong>
        </div>
        <button type="button" class="rh360-dialog__close" data-close-dialog>×</button>
    </div>

    <form method="POST" action="{{ $rtStore }}" class="rh360-form">
        @csrf

        @include('cliente.rh.empleados._form_fields', ['empleado' => null])

        <div class="rh360-dialog__foot">
            <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-close-dialog>Cancelar</button>
            <button type="submit" class="sat-clean-btn sat-clean-btn--primary">Guardar empleado</button>
        </div>
    </form>
</dialog>

@foreach($empleados as $e)
    <dialog class="rh360-dialog" id="rhEditDialog{{ $e->id }}">
        <div class="rh360-dialog__head">
            <div>
                <span>Editar empleado</span>
                <strong>{{ $e->nombre_completo ?: 'Empleado' }}</strong>
            </div>
            <button type="button" class="rh360-dialog__close" data-close-dialog>×</button>
        </div>

        <form method="POST" action="{{ route('cliente.rh.empleados.update', $e->id) }}" class="rh360-form">
            @csrf
            @method('PUT')

            @include('cliente.rh.empleados._form_fields', ['empleado' => $e])

            <div class="rh360-dialog__foot">
                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-close-dialog>Cancelar</button>
                <button type="submit" class="sat-clean-btn sat-clean-btn--primary">Actualizar empleado</button>
            </div>
        </form>
    </dialog>
@endforeach


@endsection

@push('scripts')
@php
    $RH360_JS_REL = 'assets/client/js/pages/rh-empleados.js';
    $RH360_JS_ABS = public_path($RH360_JS_REL);
    $RH360_JS_V   = is_file($RH360_JS_ABS) ? (string) filemtime($RH360_JS_ABS) : null;
@endphp

<script src="{{ asset($RH360_JS_REL) }}{{ $RH360_JS_V ? ('?v='.$RH360_JS_V) : '' }}" defer></script>
@endpush