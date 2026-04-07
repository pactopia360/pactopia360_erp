{{-- resources/views/admin/sat/ops/payments/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Pagos · v1.0 nuevo desde cero --}}

@extends('layouts.admin')

@section('title', $title ?? 'SAT · Operación · Pagos')
@section('pageClass', 'page-admin-sat-ops page-admin-sat-ops-payments')

@php
    use Illuminate\Support\Facades\Route;

    $backUrl = Route::has('admin.sat.ops.index') ? route('admin.sat.ops.index') : url('/admin');

    $status = request('status', '');
    $type   = request('type', '');
    $range  = request('range', '');
    $q      = request('q', '');

    $rows = collect([
        [
            'id' => 'PAY-1001',
            'cliente' => 'Grupo Empresarial Delta',
            'rfc' => 'GED9402158A1',
            'tipo' => 'Suscripción SAT',
            'monto' => 2499.00,
            'moneda' => 'MXN',
            'metodo' => 'Stripe',
            'estatus' => 'Pagado',
            'fecha' => '2026-04-01 10:22',
            'referencia' => 'pi_3QZ01ABC91X',
            'usuario' => 'sistema',
        ],
        [
            'id' => 'PAY-1002',
            'cliente' => 'Comercializadora Nova',
            'rfc' => 'CNO030818TQ2',
            'tipo' => 'Renovación',
            'monto' => 1499.00,
            'moneda' => 'MXN',
            'metodo' => 'Transferencia',
            'estatus' => 'Pendiente',
            'fecha' => '2026-04-03 14:05',
            'referencia' => 'TRX-884120',
            'usuario' => 'marco.admin',
        ],
        [
            'id' => 'PAY-1003',
            'cliente' => 'Servicios Integrales Orion',
            'rfc' => 'SIO120909LK9',
            'tipo' => 'Pago manual',
            'monto' => 3299.00,
            'moneda' => 'MXN',
            'metodo' => 'Manual',
            'estatus' => 'Aplicado',
            'fecha' => '2026-04-05 09:40',
            'referencia' => 'MAN-20260405-01',
            'usuario' => 'operaciones',
        ],
        [
            'id' => 'PAY-1004',
            'cliente' => 'Logística Axis',
            'rfc' => 'LAX150210PR4',
            'tipo' => 'Complemento',
            'monto' => 899.00,
            'moneda' => 'MXN',
            'metodo' => 'Stripe',
            'estatus' => 'Fallido',
            'fecha' => '2026-04-06 18:11',
            'referencia' => 'pi_3QZ09ERR12A',
            'usuario' => 'webhook',
        ],
    ]);

    if ($status !== '') {
        $rows = $rows->filter(fn ($row) => mb_strtolower($row['estatus']) === mb_strtolower($status));
    }

    if ($type !== '') {
        $rows = $rows->filter(fn ($row) => mb_strtolower($row['tipo']) === mb_strtolower($type));
    }

    if ($q !== '') {
        $needle = mb_strtolower($q);
        $rows = $rows->filter(function ($row) use ($needle) {
            return str_contains(mb_strtolower($row['id']), $needle)
                || str_contains(mb_strtolower($row['cliente']), $needle)
                || str_contains(mb_strtolower($row['rfc']), $needle)
                || str_contains(mb_strtolower($row['referencia']), $needle)
                || str_contains(mb_strtolower($row['metodo']), $needle);
        });
    }

    $rows = $rows->values();

    $totalMonto = $rows->sum('monto');
    $kpiTotal = $rows->count();
    $kpiPagados = $rows->where('estatus', 'Pagado')->count();
    $kpiPendientes = $rows->where('estatus', 'Pendiente')->count();
    $kpiFallidos = $rows->where('estatus', 'Fallido')->count();

    $statusClass = function (string $status): string {
        return match (mb_strtolower($status)) {
            'pagado' => 'is-paid',
            'aplicado' => 'is-applied',
            'pendiente' => 'is-pending',
            'fallido' => 'is-failed',
            default => 'is-neutral',
        };
    };
@endphp

@section('page-header')
    <div class="p360-ph p360-ph-payments">
        <div class="p360-ph-left">
            <div class="p360-ph-kicker">ADMIN · SAT OPS</div>
            <h1 class="p360-ph-title">Pagos SAT</h1>
            <div class="p360-ph-sub">
                Control operativo de pagos, referencias, estatus y conciliación desde una sola vista.
            </div>
        </div>

        <div class="p360-ph-right">
            <a class="p360-btn" href="{{ $backUrl }}">← Volver</a>
            <button type="button" class="p360-btn" onclick="location.reload()">Refrescar</button>
        </div>
    </div>
@endsection

@section('content')
<div class="pay-wrap" id="p360SatOpsPayments">
    <section class="pay-hero">
        <div class="pay-hero__main">
            <div class="pay-hero__eyebrow">Panel operativo</div>
            <h2 class="pay-hero__title">Movimientos y aplicación de pagos</h2>
            <p class="pay-hero__text">
                Filtra pagos por estatus, tipo o búsqueda rápida y revisa abajo la tabla operativa compacta.
            </p>
        </div>

        <div class="pay-kpis">
            <article class="pay-kpi">
                <span class="pay-kpi__label">Movimientos</span>
                <strong class="pay-kpi__value">{{ number_format($kpiTotal) }}</strong>
            </article>
            <article class="pay-kpi">
                <span class="pay-kpi__label">Pagados</span>
                <strong class="pay-kpi__value">{{ number_format($kpiPagados) }}</strong>
            </article>
            <article class="pay-kpi">
                <span class="pay-kpi__label">Pendientes</span>
                <strong class="pay-kpi__value">{{ number_format($kpiPendientes) }}</strong>
            </article>
            <article class="pay-kpi">
                <span class="pay-kpi__label">Fallidos</span>
                <strong class="pay-kpi__value">{{ number_format($kpiFallidos) }}</strong>
            </article>
            <article class="pay-kpi">
                <span class="pay-kpi__label">Monto visible</span>
                <strong class="pay-kpi__value">${{ number_format($totalMonto, 2) }}</strong>
            </article>
        </div>
    </section>

    <section class="pay-panel">
        <form class="pay-filterbar" method="GET" action="{{ url()->current() }}">
            <div class="pay-filterbar__search">
                <label class="pay-label pay-label--sr" for="paySearch">Buscar</label>
                <div class="pay-inputwrap pay-inputwrap--search">
                    <span class="pay-inputwrap__icon">⌕</span>
                    <input
                        id="paySearch"
                        name="q"
                        class="pay-input"
                        type="search"
                        value="{{ $q }}"
                        placeholder="ID, cliente, RFC, referencia, método..."
                        autocomplete="off">
                </div>
            </div>

            <div class="pay-filterbar__field">
                <label class="pay-label" for="payStatus">Estatus</label>
                <select id="payStatus" name="status" class="pay-select">
                    <option value="" @selected($status==='')>Todos</option>
                    <option value="Pagado" @selected($status==='Pagado')>Pagado</option>
                    <option value="Aplicado" @selected($status==='Aplicado')>Aplicado</option>
                    <option value="Pendiente" @selected($status==='Pendiente')>Pendiente</option>
                    <option value="Fallido" @selected($status==='Fallido')>Fallido</option>
                </select>
            </div>

            <div class="pay-filterbar__field">
                <label class="pay-label" for="payType">Tipo</label>
                <select id="payType" name="type" class="pay-select">
                    <option value="" @selected($type==='')>Todos</option>
                    <option value="Suscripción SAT" @selected($type==='Suscripción SAT')>Suscripción SAT</option>
                    <option value="Renovación" @selected($type==='Renovación')>Renovación</option>
                    <option value="Pago manual" @selected($type==='Pago manual')>Pago manual</option>
                    <option value="Complemento" @selected($type==='Complemento')>Complemento</option>
                </select>
            </div>

            <div class="pay-filterbar__field">
                <label class="pay-label" for="payRange">Rango</label>
                <select id="payRange" name="range" class="pay-select">
                    <option value="" @selected($range==='')>Todo</option>
                    <option value="today" @selected($range==='today')>Hoy</option>
                    <option value="7d" @selected($range==='7d')>7 días</option>
                    <option value="30d" @selected($range==='30d')>30 días</option>
                </select>
            </div>

            <div class="pay-filterbar__actions">
                <button class="p360-btn p360-btn-primary" type="submit">Aplicar</button>
                <a class="p360-btn" href="{{ url()->current() }}">Limpiar</a>
            </div>
        </form>

        <div class="pay-strip">
            <div class="pay-strip__left">
                <div class="pay-strip__title">Tabla operativa de pagos</div>
                <div class="pay-strip__sub">
                    {{ $rows->count() }} registro(s) visibles en esta vista
                </div>
            </div>

            <div class="pay-strip__right">
                <span class="chip">Ruta <span class="mono">/admin/sat/ops/payments</span></span>
                <span class="chip">Módulo <span class="mono">SAT Ops Payments</span></span>
            </div>
        </div>

        <div class="pay-table-card">
            <div class="pay-table-scroll">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>ID / Cliente</th>
                            <th>RFC</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Estatus</th>
                            <th>Referencia</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th class="th-actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td data-label="ID / Cliente">
                                <div class="pay-cell-main">
                                    <div class="pay-cell-main__id mono">{{ $row['id'] }}</div>
                                    <div class="pay-cell-main__name">{{ $row['cliente'] }}</div>
                                </div>
                            </td>
                            <td data-label="RFC">
                                <span class="mono">{{ $row['rfc'] }}</span>
                            </td>
                            <td data-label="Tipo">
                                <span class="pay-badge is-neutral">{{ $row['tipo'] }}</span>
                            </td>
                            <td data-label="Monto">
                                <strong>${{ number_format((float)$row['monto'], 2) }}</strong>
                                <div class="pay-sub">{{ $row['moneda'] }}</div>
                            </td>
                            <td data-label="Método">
                                <span class="pay-badge is-method">{{ $row['metodo'] }}</span>
                            </td>
                            <td data-label="Estatus">
                                <span class="pay-badge {{ $statusClass($row['estatus']) }}">{{ $row['estatus'] }}</span>
                            </td>
                            <td data-label="Referencia">
                                <span class="mono pay-ref">{{ $row['referencia'] }}</span>
                            </td>
                            <td data-label="Fecha">
                                <span>{{ $row['fecha'] }}</span>
                            </td>
                            <td data-label="Usuario">
                                <span>{{ $row['usuario'] }}</span>
                            </td>
                            <td data-label="Acciones" class="td-actions">
                                <div class="pay-actions">
                                    <button type="button" class="pay-action-btn" data-copy="{{ $row['id'] }}">Copiar ID</button>
                                    <button type="button" class="pay-action-btn" data-copy="{{ $row['referencia'] }}">Copiar ref</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="pay-empty">
                                    <div class="pay-empty__icon">🧾</div>
                                    <div class="pay-empty__title">Sin movimientos</div>
                                    <div class="pay-empty__sub">No hay pagos que coincidan con los filtros actuales.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/sat-ops-payments.css') }}?v={{ date('YmdHis') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/admin/js/sat-ops-payments.js') }}?v={{ date('YmdHis') }}" defer></script>
@endpush