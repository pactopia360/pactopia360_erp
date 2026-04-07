@extends('layouts.admin')

@section('title','SAT · RFC Maestro')
@section('pageClass','page-admin-sat-rfcs')
@section('contentLayout','full')

@section('page-header')
<div class="satAdmHero">
    <div class="satAdmHero__main">
        <div class="satAdmHero__eyebrow">ADMIN · SAT · RFC MAESTRO</div>
        <h1 class="satAdmHero__title">RFC maestro SAT</h1>
        <p class="satAdmHero__subtitle">
            Espejo administrativo del módulo cliente. Aquí podrás consultar, crear, editar y dar de baja
            los RFC registrados por cuenta para preparar cotizaciones y descargas SAT.
        </p>
    </div>

    <div class="satAdmHero__side">
        <a class="satAdmBtn satAdmBtn--primary" href="{{ route('admin.sat.ops.index') }}">Volver al dashboard SAT</a>
    </div>
</div>
@endsection

@section('content')
<div class="satAdmWrap">

    @if(session('ok'))
        <div class="satAdmAlert satAdmAlert--ok">{{ session('ok') }}</div>
    @endif

    @if(session('error'))
        <div class="satAdmAlert satAdmAlert--error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="satAdmAlert satAdmAlert--error">{{ $errors->first() }}</div>
    @endif

    <section class="satAdmKpis">
        <article class="satAdmKpi">
            <div class="satAdmKpi__label">Total</div>
            <div class="satAdmKpi__value">{{ number_format((int) ($stats['total'] ?? 0)) }}</div>
            <div class="satAdmKpi__sub">RFC activos visibles</div>
        </article>
        <article class="satAdmKpi">
            <div class="satAdmKpi__label">Internos</div>
            <div class="satAdmKpi__value">{{ number_format((int) ($stats['internos'] ?? 0)) }}</div>
            <div class="satAdmKpi__sub">Origen interno</div>
        </article>
        <article class="satAdmKpi">
            <div class="satAdmKpi__label">Externos</div>
            <div class="satAdmKpi__value">{{ number_format((int) ($stats['externos'] ?? 0)) }}</div>
            <div class="satAdmKpi__sub">Origen externo</div>
        </article>
        <article class="satAdmKpi">
            <div class="satAdmKpi__label">Validados</div>
            <div class="satAdmKpi__value">{{ number_format((int) ($stats['validados'] ?? 0)) }}</div>
            <div class="satAdmKpi__sub">Listos para operar</div>
        </article>
    </section>

    <section class="satAdmGrid">
        <section class="satAdmCard">
            <div class="satAdmCard__head">
                <div>
                    <div class="satAdmCard__title">Alta rápida desde admin</div>
                    <div class="satAdmCard__sub">Permite registrar RFC ligado o no ligado a una cuenta.</div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.sat.ops.rfcs.store') }}" class="satAdmForm">
                @csrf

                <div class="satAdmForm__grid">
                    <div class="satAdmField">
                        <label for="cuenta_id">Cuenta UUID</label>
                        <input type="text" id="cuenta_id" name="cuenta_id" value="{{ old('cuenta_id') }}" required>
                    </div>

                    <div class="satAdmField">
                        <label for="account_id">Account ID espejo</label>
                        <input type="text" id="account_id" name="account_id" value="{{ old('account_id') }}">
                    </div>

                    <div class="satAdmField">
                        <label for="rfc">RFC</label>
                        <input type="text" id="rfc" name="rfc" value="{{ old('rfc') }}" maxlength="13" required>
                    </div>

                    <div class="satAdmField">
                        <label for="tipo_origen">Origen</label>
                        <select id="tipo_origen" name="tipo_origen" required>
                            <option value="admin" {{ old('tipo_origen', 'admin') === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="interno" {{ old('tipo_origen') === 'interno' ? 'selected' : '' }}>Interno</option>
                            <option value="externo" {{ old('tipo_origen') === 'externo' ? 'selected' : '' }}>Externo</option>
                        </select>
                    </div>

                    <div class="satAdmField satAdmField--full">
                        <label for="razon_social">Razón social</label>
                        <input type="text" id="razon_social" name="razon_social" value="{{ old('razon_social') }}">
                    </div>

                    <div class="satAdmField">
                        <label for="origen_detalle">Detalle origen</label>
                        <input type="text" id="origen_detalle" name="origen_detalle" value="{{ old('origen_detalle') }}">
                    </div>

                    <div class="satAdmField">
                        <label for="source_label">Etiqueta visual</label>
                        <input type="text" id="source_label" name="source_label" value="{{ old('source_label') }}">
                    </div>
                </div>

                <div class="satAdmForm__actions">
                    <button type="submit" class="satAdmBtn satAdmBtn--primary">Guardar RFC</button>
                </div>
            </form>
        </section>

        <section class="satAdmCard">
            <div class="satAdmCard__head">
                <div>
                    <div class="satAdmCard__title">Filtros</div>
                    <div class="satAdmCard__sub">Consulta global del espejo SAT.</div>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.sat.ops.rfcs.index') }}" class="satAdmForm">
                <div class="satAdmForm__grid">
                    <div class="satAdmField satAdmField--full">
                        <label for="q">Buscar</label>
                        <input type="text" id="q" name="q" value="{{ $q }}" placeholder="RFC, razón social o cuenta">
                    </div>

                    <div class="satAdmField">
                        <label for="origin">Origen</label>
                        <select id="origin" name="origin">
                            <option value="">Todos</option>
                            <option value="interno" {{ $origin === 'interno' ? 'selected' : '' }}>Interno</option>
                            <option value="externo" {{ $origin === 'externo' ? 'selected' : '' }}>Externo</option>
                            <option value="admin" {{ $origin === 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                    </div>

                    <div class="satAdmField">
                        <label for="status">Estado</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <option value="validado" {{ $status === 'validado' ? 'selected' : '' }}>Validado</option>
                            <option value="pendiente" {{ $status === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                        </select>
                    </div>

                    <div class="satAdmField satAdmField--full">
                        <label for="account">Cuenta exacta</label>
                        <input type="text" id="account" name="account" value="{{ $account }}">
                    </div>
                </div>

                <div class="satAdmForm__actions">
                    <button type="submit" class="satAdmBtn satAdmBtn--primary">Aplicar filtros</button>
                </div>
            </form>
        </section>
    </section>

    <section class="satAdmCard">
        <div class="satAdmCard__head">
            <div>
                <div class="satAdmCard__title">Listado global de RFC</div>
                <div class="satAdmCard__sub">Espejo administrativo del maestro SAT del cliente.</div>
            </div>
        </div>

        <div class="satAdmTableWrap">
            <table class="satAdmTable">
                <thead>
                    <tr>
                        <th>RFC</th>
                        <th>Razón social</th>
                        <th>Cuenta</th>
                        <th>Origen</th>
                        <th>FIEL</th>
                        <th>CSD</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="satAdmMono">{{ $row->rfc }}</td>
                            <td>{{ $row->razon_social !== '' ? $row->razon_social : '—' }}</td>
                            <td>
                                <div class="satAdmStack">
                                    <span class="satAdmMono">{{ $row->cuenta_id !== '' ? $row->cuenta_id : '—' }}</span>
                                    <small>{{ $row->account_id !== '' ? $row->account_id : '—' }}</small>
                                </div>
                            </td>
                            <td><span class="satAdmBadge satAdmBadge--neutral">{{ strtoupper($row->tipo_origen_ui) }}</span></td>
                            <td><span class="satAdmBadge {{ $row->has_fiel ? 'satAdmBadge--ok' : 'satAdmBadge--warn' }}">{{ $row->has_fiel ? 'CARGADA' : 'PENDIENTE' }}</span></td>
                            <td><span class="satAdmBadge {{ $row->has_csd ? 'satAdmBadge--ok' : 'satAdmBadge--warn' }}">{{ $row->has_csd ? 'CARGADA' : 'OPCIONAL' }}</span></td>
                            <td><span class="satAdmBadge {{ $row->sat_status_ui === 'validado' ? 'satAdmBadge--ok' : 'satAdmBadge--warn' }}">{{ strtoupper($row->sat_status_ui) }}</span></td>
                            <td class="satAdmActionsCell">
                                <details class="satAdmEdit">
                                    <summary>Editar</summary>
                                    <form method="POST" action="{{ route('admin.sat.ops.rfcs.update', ['id' => $row->id]) }}" class="satAdmForm satAdmForm--compact">
                                        @csrf
                                        <div class="satAdmForm__grid">
                                            <div class="satAdmField">
                                                <label>Cuenta UUID</label>
                                                <input type="text" name="cuenta_id" value="{{ $row->cuenta_id }}" required>
                                            </div>
                                            <div class="satAdmField">
                                                <label>Account ID</label>
                                                <input type="text" name="account_id" value="{{ $row->account_id }}">
                                            </div>
                                            <div class="satAdmField">
                                                <label>RFC</label>
                                                <input type="text" name="rfc" value="{{ $row->rfc }}" required>
                                            </div>
                                            <div class="satAdmField">
                                                <label>Origen</label>
                                                <select name="tipo_origen" required>
                                                    <option value="admin" {{ $row->tipo_origen_ui === 'admin' ? 'selected' : '' }}>Admin</option>
                                                    <option value="interno" {{ $row->tipo_origen_ui === 'interno' ? 'selected' : '' }}>Interno</option>
                                                    <option value="externo" {{ $row->tipo_origen_ui === 'externo' ? 'selected' : '' }}>Externo</option>
                                                </select>
                                            </div>
                                            <div class="satAdmField satAdmField--full">
                                                <label>Razón social</label>
                                                <input type="text" name="razon_social" value="{{ $row->razon_social }}">
                                            </div>
                                            <div class="satAdmField">
                                                <label>Detalle origen</label>
                                                <input type="text" name="origen_detalle" value="{{ $row->origen_detalle }}">
                                            </div>
                                            <div class="satAdmField">
                                                <label>Etiqueta visual</label>
                                                <input type="text" name="source_label" value="{{ $row->source_label }}">
                                            </div>
                                        </div>

                                        <div class="satAdmForm__actions">
                                            <button type="submit" class="satAdmBtn satAdmBtn--primary">Guardar cambios</button>
                                        </div>
                                    </form>
                                </details>

                                <form method="POST" action="{{ route('admin.sat.ops.rfcs.delete', ['id' => $row->id]) }}" onsubmit="return confirm('¿Deseas dar de baja este RFC?');">
                                    @csrf
                                    <button type="submit" class="satAdmBtn satAdmBtn--danger">Dar de baja</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="satAdmEmpty">No hay RFC activos registrados en el maestro SAT.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection

@push('styles')
<style>
    .page-admin-sat-rfcs .page-container{ padding-top:12px; }
    .satAdmWrap{ display:grid; gap:16px; }
    .satAdmHero{
        display:grid;
        grid-template-columns:minmax(0,1.35fr) 280px;
        gap:16px;
        padding:18px 18px 14px;
    }
    .satAdmHero__main,.satAdmHero__side,.satAdmCard,.satAdmKpi{
        border:1px solid var(--bd);
        border-radius:20px;
        background:var(--card-bg);
        box-shadow:var(--shadow-1);
    }
    .satAdmHero__main{
        padding:22px;
        background:linear-gradient(135deg, rgba(14,42,59,.98) 0%, rgba(31,78,121,.96) 50%, rgba(37,99,235,.94) 100%);
        color:#fff;
        border-color:rgba(37,99,235,.18);
    }
    .satAdmHero__side{ padding:18px; display:grid; align-items:center; }
    .satAdmHero__eyebrow{
        font:900 11px/1 system-ui;
        letter-spacing:.12em;
        text-transform:uppercase;
        opacity:.84;
    }
    .satAdmHero__title{
        margin:10px 0 0;
        font:950 32px/1.03 system-ui;
        letter-spacing:-.03em;
    }
    .satAdmHero__subtitle{
        margin:12px 0 0;
        font:600 14px/1.6 system-ui;
        color:rgba(255,255,255,.86);
        max-width:840px;
    }

    .satAdmKpis{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
    }
    .satAdmKpi{ padding:16px; }
    .satAdmKpi__label{
        font:900 11px/1 system-ui;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:var(--muted);
    }
    .satAdmKpi__value{
        margin-top:10px;
        font:950 28px/1 system-ui;
        color:var(--text);
    }
    .satAdmKpi__sub{
        margin-top:8px;
        font:650 13px/1.45 system-ui;
        color:var(--muted);
    }

    .satAdmGrid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:16px;
    }

    .satAdmCard{ padding:16px; }
    .satAdmCard__head{
        display:flex;
        align-items:flex-end;
        justify-content:space-between;
        gap:10px;
        margin-bottom:14px;
    }
    .satAdmCard__title{
        font:950 18px/1.1 system-ui;
        color:var(--text);
    }
    .satAdmCard__sub{
        margin-top:5px;
        font:650 13px/1.4 system-ui;
        color:var(--muted);
    }

    .satAdmAlert{
        padding:14px 16px;
        border-radius:16px;
        font:700 14px/1.4 system-ui;
    }
    .satAdmAlert--ok{
        background:#ecfdf3;
        color:#047857;
        border:1px solid #a7f3d0;
    }
    .satAdmAlert--error{
        background:#fff7ed;
        color:#c2410c;
        border:1px solid #fdba74;
    }

    .satAdmForm{
        display:grid;
        gap:14px;
    }
    .satAdmForm--compact{
        margin-top:12px;
        padding:14px;
        border:1px solid var(--bd);
        border-radius:16px;
        background:color-mix(in oklab, var(--panel-bg) 74%, transparent);
    }
    .satAdmForm__grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .satAdmField--full{ grid-column:1 / -1; }

    .satAdmField label{
        display:block;
        margin:0 0 6px;
        font:900 12px/1 system-ui;
        letter-spacing:.04em;
        text-transform:uppercase;
        color:var(--muted);
    }
    .satAdmField input,
    .satAdmField select{
        width:100%;
        min-height:44px;
        border-radius:14px;
        border:1px solid var(--bd);
        background:#fff;
        color:#0f172a;
        padding:0 14px;
        font:600 14px/1.1 system-ui;
        outline:none;
    }
    html.theme-dark .satAdmField input,
    html.theme-dark .satAdmField select{
        background:#0f172a;
        color:#e5e7eb;
    }

    .satAdmForm__actions{
        display:flex;
        justify-content:flex-end;
        gap:8px;
    }

    .satAdmBtn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:44px;
        padding:0 16px;
        border-radius:14px;
        border:1px solid var(--bd);
        background:color-mix(in oklab, var(--card-bg) 88%, transparent);
        color:var(--text);
        text-decoration:none;
        font:850 13px/1 system-ui;
        cursor:pointer;
    }
    .satAdmBtn--primary{
        background:#2563eb;
        border-color:#2563eb;
        color:#fff;
    }
    .satAdmBtn--danger{
        background:#fff7ed;
        border-color:#fdba74;
        color:#c2410c;
    }

    .satAdmTableWrap{ overflow:auto; }
    .satAdmTable{
        width:100%;
        min-width:1200px;
        border-collapse:separate;
        border-spacing:0;
    }
    .satAdmTable th{
        text-align:left;
        padding:14px 12px;
        border-bottom:1px solid var(--bd);
        background:color-mix(in oklab, var(--panel-bg) 76%, transparent);
        font:900 12px/1 system-ui;
        letter-spacing:.04em;
        text-transform:uppercase;
        color:var(--muted);
    }
    .satAdmTable td{
        padding:14px 12px;
        border-bottom:1px solid color-mix(in oklab, var(--text) 8%, transparent);
        vertical-align:top;
        font:600 14px/1.5 system-ui;
        color:var(--text);
    }

    .satAdmMono{
        font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    }
    .satAdmStack{
        display:grid;
        gap:4px;
    }
    .satAdmStack small{
        color:var(--muted);
        font:600 12px/1.3 system-ui;
    }

    .satAdmBadge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:30px;
        padding:0 10px;
        border-radius:999px;
        font:900 11px/1 system-ui;
        border:1px solid transparent;
        white-space:nowrap;
    }
    .satAdmBadge--ok{
        background:#ecfdf3;
        color:#047857;
        border-color:#a7f3d0;
    }
    .satAdmBadge--warn{
        background:#fff7ed;
        color:#c2410c;
        border-color:#fdba74;
    }
    .satAdmBadge--neutral{
        background:#eff6ff;
        color:#1d4ed8;
        border-color:#bfdbfe;
    }

    .satAdmActionsCell{
        min-width:320px;
    }
    .satAdmEdit summary{
        cursor:pointer;
        font:900 13px/1 system-ui;
        color:var(--text);
        list-style:none;
    }
    .satAdmEdit summary::-webkit-details-marker{ display:none; }

    .satAdmEmpty{
        padding:24px 16px;
        color:var(--muted);
    }

    @media (max-width: 1180px){
        .satAdmHero,
        .satAdmGrid,
        .satAdmKpis{
            grid-template-columns:1fr;
        }
    }
</style>
@endpush