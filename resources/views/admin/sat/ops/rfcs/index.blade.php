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

    <section class="satAdmCard satAdmCard--toolbar">
        <div class="satAdmToolbar">
            <div class="satAdmToolbar__left">
                <div class="satAdmCard__title">Listado global de RFC</div>
                <div class="satAdmCard__sub">Vista compacta, operativa y administrable para muchos registros.</div>
            </div>

            <div class="satAdmToolbar__right">
                <button
                    type="button"
                    class="satAdmBtn satAdmBtn--primary satAdmBtn--icon"
                    onclick="document.getElementById('satAdmCreateModal').showModal()"
                >
                    <span class="satAdmBtn__icon" aria-hidden="true">＋</span>
                    <span>RFC</span>
                </button>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.sat.ops.rfcs.index') }}" class="satAdmInlineFilters">
            <div class="satAdmField satAdmField--search">
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

            <div class="satAdmField">
                <label for="account">Cuenta exacta</label>
                <input type="text" id="account" name="account" value="{{ $account }}" placeholder="UUID cuenta">
            </div>

            <div class="satAdmInlineFilters__actions">
                <button type="submit" class="satAdmBtn satAdmBtn--primary">Aplicar</button>
                <a href="{{ route('admin.sat.ops.rfcs.index') }}" class="satAdmBtn">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="satAdmCard satAdmCard--table">
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
                        @php
                            $opsModalId = 'rfcOpsModal_' . $row->id;
                        @endphp

                        <tr>
                            <td>
                                <div class="satAdmCellMain satAdmMono">{{ $row->rfc }}</div>
                            </td>

                            <td>
                                <div class="satAdmCellMain">{{ $row->razon_social !== '' ? $row->razon_social : '—' }}</div>
                            </td>

                            <td>
                                <div class="satAdmStack">
                                    <span class="satAdmMono satAdmCellMain">{{ $row->cuenta_id !== '' ? $row->cuenta_id : '—' }}</span>
                                    <small>{{ $row->account_id !== '' ? $row->account_id : '—' }}</small>
                                </div>
                            </td>

                            <td>
                                <span class="satAdmBadge satAdmBadge--neutral">{{ strtoupper($row->tipo_origen_ui) }}</span>
                            </td>

                            <td>
                                <span class="satAdmBadge {{ $row->has_fiel ? 'satAdmBadge--ok' : 'satAdmBadge--warn' }}">
                                    {{ $row->has_fiel ? 'CARGADA' : 'PENDIENTE' }}
                                </span>
                            </td>

                            <td>
                                <span class="satAdmBadge {{ $row->has_csd ? 'satAdmBadge--ok' : 'satAdmBadge--warn' }}">
                                    {{ $row->has_csd ? 'CARGADA' : 'OPCIONAL' }}
                                </span>
                            </td>

                            <td>
                                <span class="satAdmBadge {{ $row->sat_status_ui === 'validado' ? 'satAdmBadge--ok' : 'satAdmBadge--warn' }}">
                                    {{ strtoupper($row->sat_status_ui) }}
                                </span>
                            </td>

                            <td class="satAdmActionsCell">
                                <button
                                    type="button"
                                    class="satAdmIconBtn satAdmIconBtn--main"
                                    onclick="document.getElementById('{{ $opsModalId }}').showModal()"
                                    title="Abrir acciones"
                                    aria-label="Abrir acciones"
                                >
                                    <span aria-hidden="true">⋯</span>
                                </button>

                                <dialog id="{{ $opsModalId }}" class="satAdmModal satAdmModal--opsRedesign">
                                    <div class="satAdmModalShell">
                                        <div class="satAdmModalShell__header">
                                            <div class="satAdmModalShell__titleWrap">
                                                <div class="satAdmModal__eyebrow">OPERACIÓN RFC</div>
                                                <h3 class="satAdmModalShell__title">{{ $row->rfc }}</h3>
                                                <p class="satAdmModalShell__subtitle">{{ $row->razon_social !== '' ? $row->razon_social : 'Sin razón social registrada' }}</p>
                                            </div>

                                            <button
                                                type="button"
                                                class="satAdmModal__close"
                                                onclick="document.getElementById('{{ $opsModalId }}').close()"
                                                aria-label="Cerrar"
                                            >×</button>
                                        </div>

                                        <div class="satAdmModalShell__body">
                                            <section class="satAdmOpsTopCards">
                                                <div class="satAdmOpsMiniCard">
                                                    <span class="satAdmOpsMiniCard__label">Cuenta UUID</span>
                                                    <span class="satAdmOpsMiniCard__value satAdmMono">{{ $row->cuenta_id ?: '—' }}</span>
                                                </div>

                                                <div class="satAdmOpsMiniCard">
                                                    <span class="satAdmOpsMiniCard__label">Account ID</span>
                                                    <span class="satAdmOpsMiniCard__value satAdmMono">{{ $row->account_id ?: '—' }}</span>
                                                </div>

                                                <div class="satAdmOpsMiniCard">
                                                    <span class="satAdmOpsMiniCard__label">Origen</span>
                                                    <span class="satAdmOpsMiniCard__value">{{ strtoupper($row->tipo_origen_ui) }}</span>
                                                </div>

                                                <div class="satAdmOpsMiniCard">
                                                    <span class="satAdmOpsMiniCard__label">Estado SAT</span>
                                                    <span class="satAdmOpsMiniCard__value">{{ strtoupper($row->sat_status_ui) }}</span>
                                                </div>
                                            </section>

                                            <section class="satAdmOpsQuickPanel">
                                                <div class="satAdmOpsSection__title">Acciones rápidas</div>

                                                <div class="satAdmQuickActions satAdmQuickActions--compact">
                                                    <a
                                                        class="satAdmQuickAction"
                                                        href="{{ route('admin.sat.ops.rfcs.operational_data', ['id' => $row->id]) }}"
                                                        target="_blank"
                                                    >
                                                        <span class="satAdmQuickAction__icon">{ }</span>
                                                        <span class="satAdmQuickAction__text">
                                                            <strong>JSON operativo</strong>
                                                            <small>Abrir información operativa completa.</small>
                                                        </span>
                                                    </a>

                                                    <a
                                                        class="satAdmQuickAction"
                                                        href="{{ route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'fiel_cer']) }}"
                                                    >
                                                        <span class="satAdmQuickAction__icon">FC</span>
                                                        <span class="satAdmQuickAction__text">
                                                            <strong>FIEL CER</strong>
                                                            <small>Descargar certificado FIEL.</small>
                                                        </span>
                                                    </a>

                                                    <a
                                                        class="satAdmQuickAction"
                                                        href="{{ route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'fiel_key']) }}"
                                                    >
                                                        <span class="satAdmQuickAction__icon">FK</span>
                                                        <span class="satAdmQuickAction__text">
                                                            <strong>FIEL KEY</strong>
                                                            <small>Descargar llave FIEL.</small>
                                                        </span>
                                                    </a>

                                                    <a
                                                        class="satAdmQuickAction"
                                                        href="{{ route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'csd_cer']) }}"
                                                    >
                                                        <span class="satAdmQuickAction__icon">CC</span>
                                                        <span class="satAdmQuickAction__text">
                                                            <strong>CSD CER</strong>
                                                            <small>Descargar certificado CSD.</small>
                                                        </span>
                                                    </a>

                                                    <a
                                                        class="satAdmQuickAction"
                                                        href="{{ route('admin.sat.ops.rfcs.download', ['id' => $row->id, 'kind' => 'csd_key']) }}"
                                                    >
                                                        <span class="satAdmQuickAction__icon">CK</span>
                                                        <span class="satAdmQuickAction__text">
                                                            <strong>CSD KEY</strong>
                                                            <small>Descargar llave CSD.</small>
                                                        </span>
                                                    </a>
                                                </div>
                                            </section>

                                            <section class="satAdmOpsPanelsGrid">
                                                <section class="satAdmOpsPanel satAdmOpsPanel--detail">
                                                    <div class="satAdmOpsSection__title">Detalle general</div>

                                                    <div class="satAdmDetailGrid satAdmDetailGrid--modal">
                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">RFC</span>
                                                            <span class="satAdmDetailItem__value satAdmMono">{{ $row->rfc ?: '—' }}</span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">Razón social</span>
                                                            <span class="satAdmDetailItem__value">{{ $row->razon_social ?: '—' }}</span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">Etiqueta visual</span>
                                                            <span class="satAdmDetailItem__value">{{ $row->source_label ?: '—' }}</span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">Detalle origen</span>
                                                            <span class="satAdmDetailItem__value">{{ $row->origen_detalle ?: 'Sin detalle de origen' }}</span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">FIEL</span>
                                                            <span class="satAdmDetailItem__value">{{ $row->has_fiel ? 'Cargada' : 'Pendiente' }}</span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">CSD</span>
                                                            <span class="satAdmDetailItem__value">{{ $row->has_csd ? 'Cargada' : 'Opcional / no cargada' }}</span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">Contraseña FIEL</span>
                                                            <span class="satAdmDetailItem__value satAdmMono">
                                                                {{ $row->fiel_password_plain !== '' ? $row->fiel_password_plain : 'Sin contraseña registrada' }}
                                                            </span>
                                                        </div>

                                                        <div class="satAdmDetailItem">
                                                            <span class="satAdmDetailItem__label">Contraseña CSD</span>
                                                            <span class="satAdmDetailItem__value satAdmMono">
                                                                {{ $row->csd_password_plain !== '' ? $row->csd_password_plain : 'Sin contraseña registrada' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </section>

                                                <section class="satAdmOpsPanel satAdmOpsPanel--detail">
                                                    <div class="satAdmOpsSection__title">Rutas registradas</div>

                                                    <div class="satAdmDetailGrid satAdmDetailGrid--single">
                                                        <div class="satAdmDetailItem satAdmDetailItem--full">
                                                            <span class="satAdmDetailItem__label">Ruta FIEL CER</span>
                                                            <span class="satAdmDetailItem__value satAdmMono satAdmBreak">
                                                                {{ $row->fiel_cer_path !== '' ? $row->fiel_cer_path : 'No registrado' }}
                                                            </span>
                                                        </div>

                                                        <div class="satAdmDetailItem satAdmDetailItem--full">
                                                            <span class="satAdmDetailItem__label">Ruta FIEL KEY</span>
                                                            <span class="satAdmDetailItem__value satAdmMono satAdmBreak">
                                                                {{ $row->fiel_key_path !== '' ? $row->fiel_key_path : 'No registrado' }}
                                                            </span>
                                                        </div>

                                                        <div class="satAdmDetailItem satAdmDetailItem--full">
                                                            <span class="satAdmDetailItem__label">Ruta CSD CER</span>
                                                            <span class="satAdmDetailItem__value satAdmMono satAdmBreak">
                                                                {{ $row->csd_cer_path !== '' ? $row->csd_cer_path : 'No registrado' }}
                                                            </span>
                                                        </div>

                                                        <div class="satAdmDetailItem satAdmDetailItem--full">
                                                            <span class="satAdmDetailItem__label">Ruta CSD KEY</span>
                                                            <span class="satAdmDetailItem__value satAdmMono satAdmBreak">
                                                                {{ $row->csd_key_path !== '' ? $row->csd_key_path : 'No registrado' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </section>
                                            </section>

                                            <details class="satAdmEditCollapse">
                                                <summary class="satAdmEditCollapse__summary">Editar RFC</summary>

                                                <div class="satAdmEditCollapse__body">
                                                    <form method="POST" action="{{ route('admin.sat.ops.rfcs.update', ['id' => $row->id]) }}" class="satAdmForm" enctype="multipart/form-data">
                                                        @csrf

                                                        <div class="satAdmForm__grid satAdmForm__grid--modal">
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

                                                            <div class="satAdmField satAdmField--span2">
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

                                                            <div class="satAdmField">
                                                                <label>Contraseña operativa FIEL</label>
                                                                <input type="text" name="fiel_password_plain" value="{{ $row->fiel_password_plain }}">
                                                            </div>

                                                            <div class="satAdmField">
                                                                <label>Contraseña operativa CSD</label>
                                                                <input type="text" name="csd_password_plain" value="{{ $row->csd_password_plain }}">
                                                            </div>

                                                            <div class="satAdmField">
                                                                <label>Reemplazar FIEL CER</label>
                                                                <input type="file" name="fiel_cer" accept=".cer">
                                                            </div>

                                                            <div class="satAdmField">
                                                                <label>Reemplazar FIEL KEY</label>
                                                                <input type="file" name="fiel_key" accept=".key">
                                                            </div>

                                                            <div class="satAdmField">
                                                                <label>Reemplazar CSD CER</label>
                                                                <input type="file" name="csd_cer" accept=".cer">
                                                            </div>

                                                            <div class="satAdmField">
                                                                <label>Reemplazar CSD KEY</label>
                                                                <input type="file" name="csd_key" accept=".key">
                                                            </div>
                                                        </div>

                                                        <div class="satAdmModal__actions">
                                                            <button
                                                                type="submit"
                                                                formaction="{{ route('admin.sat.ops.rfcs.delete', ['id' => $row->id]) }}"
                                                                formmethod="POST"
                                                                onclick="return confirm('¿Deseas dar de baja este RFC?');"
                                                                class="satAdmBtn satAdmBtn--danger"
                                                            >
                                                                Dar de baja
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="satAdmBtn"
                                                                onclick="document.getElementById('{{ $opsModalId }}').close()"
                                                            >
                                                                Cerrar
                                                            </button>

                                                            <button type="submit" class="satAdmBtn satAdmBtn--primary">Guardar cambios</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </dialog>
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

    <dialog id="satAdmCreateModal" class="satAdmModal">
        <div class="satAdmModal__box satAdmModal__box--create">
            <div class="satAdmModal__head">
                <div>
                    <div class="satAdmModal__eyebrow">ALTA RÁPIDA</div>
                    <h3 class="satAdmModal__title">Nuevo RFC</h3>
                </div>

                <button
                    type="button"
                    class="satAdmModal__close"
                    onclick="document.getElementById('satAdmCreateModal').close()"
                    aria-label="Cerrar"
                >×</button>
            </div>

            <form method="POST" action="{{ route('admin.sat.ops.rfcs.store') }}" class="satAdmForm" enctype="multipart/form-data">
                @csrf

                <div class="satAdmForm__grid satAdmForm__grid--modal">
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

                    <div class="satAdmField satAdmField--span2">
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

                    <div class="satAdmField">
                        <label for="fiel_password_plain">Contraseña operativa FIEL</label>
                        <input type="text" id="fiel_password_plain" name="fiel_password_plain" value="{{ old('fiel_password_plain') }}">
                    </div>

                    <div class="satAdmField">
                        <label for="csd_password_plain">Contraseña operativa CSD</label>
                        <input type="text" id="csd_password_plain" name="csd_password_plain" value="{{ old('csd_password_plain') }}">
                    </div>

                    <div class="satAdmField">
                        <label for="fiel_cer">FIEL CER</label>
                        <input type="file" id="fiel_cer" name="fiel_cer" accept=".cer">
                    </div>

                    <div class="satAdmField">
                        <label for="fiel_key">FIEL KEY</label>
                        <input type="file" id="fiel_key" name="fiel_key" accept=".key">
                    </div>

                    <div class="satAdmField">
                        <label for="csd_cer">CSD CER</label>
                        <input type="file" id="csd_cer" name="csd_cer" accept=".cer">
                    </div>

                    <div class="satAdmField">
                        <label for="csd_key">CSD KEY</label>
                        <input type="file" id="csd_key" name="csd_key" accept=".key">
                    </div>
                </div>

                <div class="satAdmModal__actions">
                    <button
                        type="button"
                        class="satAdmBtn"
                        onclick="document.getElementById('satAdmCreateModal').close()"
                    >
                        Cancelar
                    </button>
                    <button type="submit" class="satAdmBtn satAdmBtn--primary">Guardar RFC</button>
                </div>
            </form>
        </div>
    </dialog>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/sat-ops-rfcs.css') }}">
@endpush