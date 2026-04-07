@extends('layouts.cliente')

@section('title', 'SAT · RFCs')
@section('pageClass', 'page-sat-rfcs')

@push('styles')
<style>
    .satRfcWrap{
        display:grid;
        gap:18px;
    }

    .satRfcHero{
        display:grid;
        grid-template-columns:minmax(0,1.35fr) 360px;
        gap:18px;
        padding:22px;
        border-radius:26px;
        border:1px solid rgba(37,99,235,.14);
        background:linear-gradient(135deg,#0f2748 0%,#163d8f 54%,#3b82f6 100%);
        box-shadow:0 26px 80px rgba(2,6,23,.14);
        color:#fff;
    }

    .satRfcHero__kicker{
        display:inline-flex;
        align-items:center;
        gap:8px;
        font-size:12px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.06em;
        opacity:.92;
        margin-bottom:10px;
    }

    .satRfcHero__title{
        margin:0;
        font-size:32px;
        line-height:1.04;
        font-weight:950;
        letter-spacing:-.03em;
    }

    .satRfcHero__subtitle{
        margin:12px 0 0;
        max-width:760px;
        font-size:14px;
        line-height:1.6;
        font-weight:600;
        color:rgba(255,255,255,.86);
    }

    .satRfcHero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:16px;
    }

    .satRfcChip{
        display:inline-flex;
        align-items:center;
        min-height:34px;
        padding:0 14px;
        border-radius:999px;
        border:1px solid rgba(255,255,255,.16);
        background:rgba(255,255,255,.10);
        color:#fff;
        font-size:12px;
        font-weight:900;
    }

    .satRfcHero__side{
        display:grid;
        gap:12px;
        padding:16px;
        border-radius:22px;
        background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.14);
        backdrop-filter:blur(10px);
    }

    .satRfcHero__sideTitle{
        font-size:20px;
        font-weight:950;
        line-height:1.05;
    }

    .satRfcHero__sideSub{
        font-size:12px;
        color:rgba(255,255,255,.84);
    }

    .satRfcAction{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:42px;
        padding:0 16px;
        border-radius:14px;
        border:1px solid rgba(255,255,255,.18);
        background:#fff;
        color:#0f172a;
        text-decoration:none;
        font-size:13px;
        font-weight:900;
    }

    .satRfcGrid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:14px;
    }

    .satRfcCard{
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:22px;
        box-shadow:0 16px 40px rgba(15,23,42,.06);
    }

    .satRfcStat{
        padding:18px;
    }

    .satRfcStat__label{
        font-size:12px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.05em;
        color:#64748b;
    }

    .satRfcStat__value{
        margin-top:10px;
        font-size:28px;
        line-height:1;
        font-weight:950;
        color:#0f172a;
    }

    .satRfcStat__sub{
        margin-top:8px;
        font-size:13px;
        color:#64748b;
    }

    .satRfcToolbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:14px;
        padding:16px 18px;
    }

    .satRfcToolbar__title{
        font-size:18px;
        font-weight:950;
        color:#0f172a;
    }

    .satRfcToolbar__sub{
        margin-top:4px;
        font-size:13px;
        color:#64748b;
    }

    .satRfcFilters{
        padding:0 18px 18px;
        display:grid;
        grid-template-columns:minmax(0,1.3fr) repeat(2,220px) auto;
        gap:12px;
        align-items:end;
    }

    .satRfcField label{
        display:block;
        margin:0 0 6px;
        font-size:12px;
        font-weight:900;
        color:#64748b;
        text-transform:uppercase;
        letter-spacing:.04em;
    }

    .satRfcField input,
    .satRfcField select{
        width:100%;
        height:44px;
        border-radius:14px;
        border:1px solid #dbe3ee;
        background:#fff;
        padding:0 14px;
        font-size:14px;
        color:#0f172a;
        outline:none;
    }

    .satRfcBtn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:44px;
        padding:0 16px;
        border-radius:14px;
        border:1px solid #dbe3ee;
        background:#fff;
        color:#0f172a;
        text-decoration:none;
        font-size:13px;
        font-weight:900;
        cursor:pointer;
    }

    .satRfcBtn--primary{
        background:#2563eb;
        border-color:#2563eb;
        color:#fff;
    }

    .satRfcBtn--danger{
        background:#fff7ed;
        border-color:#fdba74;
        color:#c2410c;
    }

    .satRfcBtn--ghost{
        background:#f8fafc;
        border-color:#dbe3ee;
        color:#0f172a;
    }

    .satRfcTableWrap{
        padding:0 18px 18px;
        overflow:auto;
    }

    .satRfcTable{
        width:100%;
        border-collapse:separate;
        border-spacing:0;
        min-width:1160px;
    }

    .satRfcTable th{
        text-align:left;
        font-size:12px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.04em;
        color:#64748b;
        padding:14px 12px;
        border-bottom:1px solid #e5e7eb;
        background:#f8fafc;
    }

    .satRfcTable td{
        padding:14px 12px;
        border-bottom:1px solid #eef2f7;
        vertical-align:top;
        font-size:14px;
        color:#0f172a;
    }

    .satRfcMono{
        font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    }

    .satRfcBadge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:30px;
        padding:0 10px;
        border-radius:999px;
        font-size:11px;
        font-weight:900;
        border:1px solid transparent;
        white-space:nowrap;
    }

    .satRfcBadge--ok{
        background:#ecfdf3;
        color:#047857;
        border-color:#a7f3d0;
    }

    .satRfcBadge--warn{
        background:#fff7ed;
        color:#c2410c;
        border-color:#fdba74;
    }

    .satRfcBadge--neutral{
        background:#eff6ff;
        color:#1d4ed8;
        border-color:#bfdbfe;
    }

    .satRfcEmpty{
        padding:24px 18px;
        font-size:14px;
        color:#64748b;
    }

    .satRfcCols{
        display:grid;
        grid-template-columns:1.3fr 1fr;
        gap:18px;
    }

    .satRfcMiniTable{
        width:100%;
        border-collapse:separate;
        border-spacing:0;
    }

    .satRfcMiniTable th,
    .satRfcMiniTable td{
        padding:12px 14px;
        border-bottom:1px solid #eef2f7;
        font-size:13px;
        text-align:left;
    }

    .satRfcMiniTable th{
        background:#f8fafc;
        color:#64748b;
        font-weight:900;
        text-transform:uppercase;
        font-size:11px;
        letter-spacing:.04em;
    }

    .satRfcAlert{
        padding:14px 16px;
        border-radius:16px;
        font-size:14px;
        font-weight:700;
    }

    .satRfcAlert--ok{
        background:#ecfdf3;
        color:#047857;
        border:1px solid #a7f3d0;
    }

    .satRfcAlert--error{
        background:#fff7ed;
        color:#c2410c;
        border:1px solid #fdba74;
    }

    .satRfcRegister{
        display:grid;
        grid-template-columns:1.1fr .9fr;
        gap:18px;
    }

    .satRfcForm{
        padding:18px;
        display:grid;
        gap:14px;
    }

    .satRfcForm__title{
        font-size:18px;
        font-weight:950;
        color:#0f172a;
    }

    .satRfcForm__sub{
        font-size:13px;
        color:#64748b;
        margin-top:4px;
    }

    .satRfcFormGrid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:14px;
    }

    .satRfcFormGrid--files{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:14px;
    }

    .satRfcHint{
        padding:14px 16px;
        border-radius:16px;
        border:1px solid #dbeafe;
        background:#eff6ff;
        color:#1e3a8a;
        font-size:13px;
        line-height:1.55;
    }

    .satRfcField--full{
        grid-column:1 / -1;
    }

    .satRfcField input[type="file"]{
        padding:10px 12px;
        height:auto;
    }

    .satRfcCellActions{
        min-width:320px;
    }

    .satRfcActions{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        align-items:center;
    }

    .satRfcEdit{
        margin-top:12px;
        padding:14px;
        border:1px solid #e5e7eb;
        border-radius:18px;
        background:#f8fafc;
        display:grid;
        gap:12px;
    }

    .satRfcEdit summary{
        cursor:pointer;
        list-style:none;
        font-weight:900;
        color:#0f172a;
    }

    .satRfcEdit summary::-webkit-details-marker{
        display:none;
    }

    .satRfcEdit__grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }

    .satRfcEdit__files{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px;
    }

    .satRfcDeleteForm{
        display:inline;
    }

    @media (max-width: 1100px){
        .satRfcHero,
        .satRfcCols,
        .satRfcRegister{
            grid-template-columns:1fr;
        }

        .satRfcGrid{
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .satRfcFilters,
        .satRfcFormGrid,
        .satRfcFormGrid--files,
        .satRfcEdit__grid,
        .satRfcEdit__files{
            grid-template-columns:1fr;
        }
    }

    @media (max-width: 700px){
        .satRfcGrid,
        .satRfcFilters{
            grid-template-columns:1fr;
        }

        .satRfcHero{
            padding:18px;
        }

        .satRfcHero__title{
            font-size:26px;
        }
    }
</style>
@endpush

@section('content')
<div class="satRfcWrap">

    @if(session('ok'))
        <div class="satRfcAlert satRfcAlert--ok">{{ session('ok') }}</div>
    @endif

    @if(session('error'))
        <div class="satRfcAlert satRfcAlert--error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="satRfcAlert satRfcAlert--error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="satRfcHero">
        <div>
            <div class="satRfcHero__kicker">
                <span>SAT</span>
                <span>·</span>
                <span>Módulo maestro de RFC</span>
            </div>

            <h1 class="satRfcHero__title">Administración de RFC SAT</h1>

            <p class="satRfcHero__subtitle">
                Este módulo será la base del nuevo flujo SAT. Aquí se registrarán los RFC internos y externos,
                junto con su FIEL obligatoria, CSD opcional y su estado operativo para cotizaciones y descargas.
            </p>

            <div class="satRfcHero__chips">
                <span class="satRfcChip">RFC interno</span>
                <span class="satRfcChip">RFC externo</span>
                <span class="satRfcChip">FIEL obligatoria</span>
                <span class="satRfcChip">CSD opcional</span>
            </div>
        </div>

        <aside class="satRfcHero__side">
            <div>
                <div class="satRfcHero__sideTitle">Paso 4</div>
                <div class="satRfcHero__sideSub">Edición y baja lógica del RFC en el maestro SAT.</div>
            </div>

            <a href="{{ route('cliente.sat.index') }}" class="satRfcAction">
                Volver al portal SAT actual
            </a>

            <a href="#satRfcRegisterCard" class="satRfcAction">
                Registrar RFC ahora
            </a>
        </aside>
    </section>

    <section class="satRfcGrid">
        <article class="satRfcCard satRfcStat">
            <div class="satRfcStat__label">RFC registrados</div>
            <div class="satRfcStat__value">{{ number_format((int) ($stats['total'] ?? 0)) }}</div>
            <div class="satRfcStat__sub">Base maestra en sat_credentials.</div>
        </article>

        <article class="satRfcCard satRfcStat">
            <div class="satRfcStat__label">Validados</div>
            <div class="satRfcStat__value">{{ number_format((int) ($stats['validados'] ?? 0)) }}</div>
            <div class="satRfcStat__sub">Listos para operar en SAT.</div>
        </article>

        <article class="satRfcCard satRfcStat">
            <div class="satRfcStat__label">Con FIEL</div>
            <div class="satRfcStat__value">{{ number_format((int) ($stats['con_fiel'] ?? 0)) }}</div>
            <div class="satRfcStat__sub">FIEL detectada o heredada.</div>
        </article>

        <article class="satRfcCard satRfcStat">
            <div class="satRfcStat__label">Externos staging</div>
            <div class="satRfcStat__value">{{ number_format((int) ($stats['externos_staging'] ?? 0)) }}</div>
            <div class="satRfcStat__sub">Pendientes de consolidar al maestro.</div>
        </article>
    </section>

    <section class="satRfcCard">
        <div class="satRfcToolbar">
            <div>
                <div class="satRfcToolbar__title">Listado maestro de RFC</div>
                <div class="satRfcToolbar__sub">
                    Ahora ya incluye edición y baja lógica.
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('cliente.sat.rfcs.index') }}" class="satRfcFilters">
            <div class="satRfcField">
                <label for="q">Buscar</label>
                <input type="text" id="q" name="q" value="{{ $search }}" placeholder="RFC o razón social">
            </div>

            <div class="satRfcField">
                <label for="origin">Origen</label>
                <select id="origin" name="origin">
                    <option value="">Todos</option>
                    <option value="interno" {{ $filterOrigin === 'interno' ? 'selected' : '' }}>Interno</option>
                    <option value="externo" {{ $filterOrigin === 'externo' ? 'selected' : '' }}>Externo</option>
                    <option value="admin" {{ $filterOrigin === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>

            <div class="satRfcField">
                <label for="status">Estado</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <option value="validado" {{ $filterStatus === 'validado' ? 'selected' : '' }}>Validado</option>
                    <option value="pendiente" {{ $filterStatus === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                </select>
            </div>

            <div class="satRfcField">
                <label>&nbsp;</label>
                <button type="submit" class="satRfcBtn satRfcBtn--primary">Aplicar filtros</button>
            </div>
        </form>

        <div class="satRfcTableWrap">
            <table class="satRfcTable">
                <thead>
                    <tr>
                        <th>RFC</th>
                        <th>Razón social</th>
                        <th>Origen</th>
                        <th>FIEL</th>
                        <th>CSD</th>
                        <th>Estado SAT</th>
                        <th>Detalle</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($credentials as $row)
                        <tr>
                            <td class="satRfcMono">{{ $row->rfc }}</td>
                            <td>{{ $row->razon_social !== '' ? $row->razon_social : '—' }}</td>
                            <td>
                                <span class="satRfcBadge satRfcBadge--neutral">
                                    {{ strtoupper($row->tipo_origen_ui) }}
                                </span>
                            </td>
                            <td>
                                <span class="satRfcBadge {{ $row->has_fiel ? 'satRfcBadge--ok' : 'satRfcBadge--warn' }}">
                                    {{ $row->has_fiel ? 'CARGADA' : 'PENDIENTE' }}
                                </span>
                            </td>
                            <td>
                                <span class="satRfcBadge {{ $row->has_csd ? 'satRfcBadge--ok' : 'satRfcBadge--warn' }}">
                                    {{ $row->has_csd ? 'CARGADA' : 'OPCIONAL' }}
                                </span>
                            </td>
                            <td>
                                <span class="satRfcBadge {{ $row->sat_status_ui === 'validado' ? 'satRfcBadge--ok' : 'satRfcBadge--warn' }}">
                                    {{ strtoupper($row->sat_status_ui) }}
                                </span>
                            </td>
                            <td>
                                {{ $row->source_label !== '' ? $row->source_label : ($row->origen_detalle !== '' ? $row->origen_detalle : '—') }}
                            </td>
                            <td class="satRfcCellActions">
                                <div class="satRfcActions">
                                    <details class="satRfcEdit">
                                        <summary>Editar</summary>

                                        <form method="POST"
                                              action="{{ route('cliente.sat.rfcs.update', ['id' => $row->id]) }}"
                                              enctype="multipart/form-data"
                                              style="display:grid; gap:12px;">
                                            @csrf

                                            <div class="satRfcEdit__grid">
                                                <div class="satRfcField">
                                                    <label for="edit_tipo_origen_{{ $row->id }}">Origen</label>
                                                    <select id="edit_tipo_origen_{{ $row->id }}" name="tipo_origen" required>
                                                        <option value="interno" {{ $row->tipo_origen_ui === 'interno' ? 'selected' : '' }}>Interno</option>
                                                        <option value="externo" {{ $row->tipo_origen_ui === 'externo' ? 'selected' : '' }}>Externo</option>
                                                    </select>
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_rfc_{{ $row->id }}">RFC</label>
                                                    <input type="text"
                                                           id="edit_rfc_{{ $row->id }}"
                                                           name="rfc"
                                                           value="{{ $row->rfc }}"
                                                           maxlength="13"
                                                           required>
                                                </div>

                                                <div class="satRfcField satRfcField--full">
                                                    <label for="edit_razon_social_{{ $row->id }}">Razón social</label>
                                                    <input type="text"
                                                           id="edit_razon_social_{{ $row->id }}"
                                                           name="razon_social"
                                                           value="{{ $row->razon_social }}"
                                                           maxlength="190">
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_origen_detalle_{{ $row->id }}">Detalle origen</label>
                                                    <input type="text"
                                                           id="edit_origen_detalle_{{ $row->id }}"
                                                           name="origen_detalle"
                                                           value="{{ $row->origen_detalle }}"
                                                           maxlength="40">
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_source_label_{{ $row->id }}">Etiqueta visual</label>
                                                    <input type="text"
                                                           id="edit_source_label_{{ $row->id }}"
                                                           name="source_label"
                                                           value="{{ $row->source_label }}"
                                                           maxlength="120">
                                                </div>
                                            </div>

                                            <div class="satRfcHint">
                                                Deja vacíos los archivos FIEL/CSD si no quieres reemplazarlos. Si capturas una parte, debes completar el bloque.
                                            </div>

                                            <div class="satRfcEdit__files">
                                                <div class="satRfcField">
                                                    <label for="edit_fiel_cer_{{ $row->id }}">Reemplazar FIEL .cer</label>
                                                    <input type="file" id="edit_fiel_cer_{{ $row->id }}" name="fiel_cer">
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_fiel_key_{{ $row->id }}">Reemplazar FIEL .key</label>
                                                    <input type="file" id="edit_fiel_key_{{ $row->id }}" name="fiel_key">
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_fiel_password_{{ $row->id }}">Nueva contraseña FIEL</label>
                                                    <input type="password" id="edit_fiel_password_{{ $row->id }}" name="fiel_password" maxlength="190">
                                                </div>
                                            </div>

                                            <div class="satRfcEdit__files">
                                                <div class="satRfcField">
                                                    <label for="edit_csd_cer_{{ $row->id }}">Reemplazar CSD .cer</label>
                                                    <input type="file" id="edit_csd_cer_{{ $row->id }}" name="csd_cer">
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_csd_key_{{ $row->id }}">Reemplazar CSD .key</label>
                                                    <input type="file" id="edit_csd_key_{{ $row->id }}" name="csd_key">
                                                </div>

                                                <div class="satRfcField">
                                                    <label for="edit_csd_password_{{ $row->id }}">Nueva contraseña CSD</label>
                                                    <input type="password" id="edit_csd_password_{{ $row->id }}" name="csd_password" maxlength="190">
                                                </div>
                                            </div>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button type="submit" class="satRfcBtn satRfcBtn--primary">Guardar cambios</button>
                                            </div>
                                        </form>
                                    </details>

                                    <form method="POST"
                                          action="{{ route('cliente.sat.rfcs.delete', ['id' => $row->id]) }}"
                                          class="satRfcDeleteForm"
                                          onsubmit="return confirm('¿Deseas dar de baja este RFC?');">
                                        @csrf
                                        <button type="submit" class="satRfcBtn satRfcBtn--danger">
                                            Dar de baja
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="satRfcEmpty">
                                Aún no hay RFC SAT registrados en la base maestra.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="satRfcRegister" id="satRfcRegisterCard">
        <section class="satRfcCard">
            <form method="POST"
                  action="{{ route('cliente.sat.rfcs.store') }}"
                  enctype="multipart/form-data"
                  class="satRfcForm">
                @csrf

                <div>
                    <div class="satRfcForm__title">Alta de RFC SAT</div>
                    <div class="satRfcForm__sub">
                        Registra el RFC en la base maestra. La FIEL es obligatoria y el CSD es opcional.
                    </div>
                </div>

                <div class="satRfcFormGrid">
                    <div class="satRfcField">
                        <label for="tipo_origen">Origen</label>
                        <select id="tipo_origen" name="tipo_origen" required>
                            <option value="interno" {{ old('tipo_origen', 'interno') === 'interno' ? 'selected' : '' }}>Interno</option>
                            <option value="externo" {{ old('tipo_origen') === 'externo' ? 'selected' : '' }}>Externo</option>
                        </select>
                    </div>

                    <div class="satRfcField">
                        <label for="rfc">RFC</label>
                        <input type="text"
                               id="rfc"
                               name="rfc"
                               value="{{ old('rfc') }}"
                               maxlength="13"
                               required
                               placeholder="XAXX010101000">
                    </div>

                    <div class="satRfcField satRfcField--full">
                        <label for="razon_social">Razón social</label>
                        <input type="text"
                               id="razon_social"
                               name="razon_social"
                               value="{{ old('razon_social') }}"
                               maxlength="190"
                               placeholder="Nombre o razón social">
                    </div>

                    <div class="satRfcField">
                        <label for="origen_detalle">Detalle origen</label>
                        <input type="text"
                               id="origen_detalle"
                               name="origen_detalle"
                               value="{{ old('origen_detalle') }}"
                               maxlength="40"
                               placeholder="cliente_interno / cliente_externo">
                    </div>

                    <div class="satRfcField">
                        <label for="source_label">Etiqueta visual</label>
                        <input type="text"
                               id="source_label"
                               name="source_label"
                               value="{{ old('source_label') }}"
                               maxlength="120"
                               placeholder="Registro interno / Registro externo">
                    </div>
                </div>

                <div class="satRfcHint">
                    <b>FIEL obligatoria:</b> debes subir <b>.cer</b>, <b>.key</b> y contraseña.
                    <b>CSD opcional:</b> si la capturas, debes subir también <b>.cer</b>, <b>.key</b> y contraseña.
                </div>

                <div>
                    <div class="satRfcForm__title" style="font-size:16px;">FIEL obligatoria</div>
                </div>

                <div class="satRfcFormGrid--files">
                    <div class="satRfcField">
                        <label for="fiel_cer">FIEL .cer</label>
                        <input type="file" id="fiel_cer" name="fiel_cer" required>
                    </div>

                    <div class="satRfcField">
                        <label for="fiel_key">FIEL .key</label>
                        <input type="file" id="fiel_key" name="fiel_key" required>
                    </div>

                    <div class="satRfcField">
                        <label for="fiel_password">Contraseña FIEL</label>
                        <input type="password"
                               id="fiel_password"
                               name="fiel_password"
                               required
                               maxlength="190"
                               placeholder="Contraseña FIEL">
                    </div>
                </div>

                <div>
                    <div class="satRfcForm__title" style="font-size:16px;">CSD opcional</div>
                </div>

                <div class="satRfcFormGrid--files">
                    <div class="satRfcField">
                        <label for="csd_cer">CSD .cer</label>
                        <input type="file" id="csd_cer" name="csd_cer">
                    </div>

                    <div class="satRfcField">
                        <label for="csd_key">CSD .key</label>
                        <input type="file" id="csd_key" name="csd_key">
                    </div>

                    <div class="satRfcField">
                        <label for="csd_password">Contraseña CSD</label>
                        <input type="password"
                               id="csd_password"
                               name="csd_password"
                               maxlength="190"
                               placeholder="Contraseña CSD">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" class="satRfcBtn satRfcBtn--primary">
                        Guardar RFC
                    </button>
                </div>
            </form>
        </section>

        <section class="satRfcCard">
            <div class="satRfcForm">
                <div>
                    <div class="satRfcForm__title">Qué hace este paso</div>
                    <div class="satRfcForm__sub">
                        En este punto ya queda funcionando el alta, edición y baja lógica del RFC.
                    </div>
                </div>

                <div class="satRfcHint">
                    <b>Se guarda en:</b> <code>sat_credentials</code><br>
                    <b>Origen:</b> interno o externo<br>
                    <b>Estado inicial:</b> pending<br>
                    <b>Baja lógica:</b> se oculta del listado y no se elimina físicamente.
                </div>

                <div class="satRfcHint">
                    El siguiente paso será:
                    <br>1. acciones espejo en admin,
                    <br>2. consolidar staging externo,
                    <br>3. descarga/consulta de archivos,
                    <br>4. y enlace con cotizaciones.
                </div>
            </div>
        </section>
    </section>

    <section class="satRfcCols">
        <section class="satRfcCard">
            <div class="satRfcToolbar">
                <div>
                    <div class="satRfcToolbar__title">Resumen de origen</div>
                    <div class="satRfcToolbar__sub">Vista rápida del estado actual del maestro.</div>
                </div>
            </div>

            <div class="satRfcTableWrap">
                <table class="satRfcMiniTable">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Internos</td>
                            <td>{{ number_format((int) ($stats['internos'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td>Externos</td>
                            <td>{{ number_format((int) ($stats['externos'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td>Pendientes</td>
                            <td>{{ number_format((int) ($stats['pendientes'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td>Con CSD</td>
                            <td>{{ number_format((int) ($stats['con_csd'] ?? 0)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="satRfcCard">
            <div class="satRfcToolbar">
                <div>
                    <div class="satRfcToolbar__title">Externo staging</div>
                    <div class="satRfcToolbar__sub">Registros aún separados en external_fiel_uploads.</div>
                </div>
            </div>

            <div class="satRfcTableWrap">
                <table class="satRfcMiniTable">
                    <thead>
                        <tr>
                            <th>RFC</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($externalRows as $row)
                            <tr>
                                <td class="satRfcMono">{{ $row->rfc !== '' ? $row->rfc : '—' }}</td>
                                <td>{{ $row->email_externo !== '' ? $row->email_externo : '—' }}</td>
                                <td>{{ $row->status !== '' ? strtoupper($row->status) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="satRfcEmpty">
                                    No hay staging externo para esta cuenta.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>

</div>
@endsection