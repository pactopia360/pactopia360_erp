{{-- resources/views/cliente/sat/_partials/connections_clean.blade.php --}}
{{-- P360 SAT · Conexiones limpias · v4.0 --}}
{{-- Compatible con sat index nuevo + modal conexiones --}}
@php
    use Illuminate\Support\Str;

    $externalRfc      = strtoupper(trim((string)($externalRfc ?? '')));
    $externalVerified = (bool)($externalVerified ?? false);

    $credList = collect($credList ?? [])->map(fn ($c) => is_array($c) ? (object) $c : $c);

    $kValidated = (int) $credList->filter(function ($c) {
        $estatusRaw = strtolower((string) data_get($c, 'estatus', ''));
        return
            !empty(data_get($c, 'validado'))
            || !empty(data_get($c, 'validated_at'))
            || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);
    })->count();

    $kPending = max(0, (int) $credList->count() - $kValidated);

    $plan  = strtoupper((string)($plan ?? 'FREE'));
    $isPro = in_array(strtolower($plan), ['pro', 'premium', 'empresa', 'business'], true);

    $rtCsdStore       = (string)($rtCsdStore ?? '#');
    $rtAlias          = (string)($rtAlias ?? '#');
    $rtRfcReg         = (string)($rtRfcReg ?? '#');
    $rtRfcDelete      = (string)($rtRfcDelete ?? '#');
    $rtExternalInvite = $rtExternalInvite ?? null;

    $anchorTable = 'sat-rfcs-table';

    $rows = $credList->map(function ($c) {
        $rfc = strtoupper(trim((string) data_get($c, 'rfc', '')));
        $alias = trim((string) data_get($c, 'razon_social', data_get($c, 'alias', '')));
        $estatusRaw = strtolower((string) data_get($c, 'estatus', ''));

        $isValidated =
            !empty(data_get($c, 'validado'))
            || !empty(data_get($c, 'validated_at'))
            || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);

        $hasCsd =
            !empty(data_get($c, 'cer_path')) || !empty(data_get($c, 'key_path'))
            || !empty(data_get($c, 'cer')) || !empty(data_get($c, 'key'));

        return (object) [
            'rfc'         => $rfc,
            'alias'       => $alias,
            'isValidated' => $isValidated,
            'hasCsd'      => $hasCsd,
        ];
    })->values();
@endphp

@push('styles')
    @php
        $relA = 'assets/cliente/css/sat/connections_clean.css';
        $absA = public_path($relA);

        $relB = 'assets/client/css/sat/connections_clean.css';
        $absB = public_path($relB);

        $relPath = is_file($absA) ? $relA : (is_file($absB) ? $relB : $relA);
        $absPath = public_path($relPath);
        $ver     = is_file($absPath) ? (string) @filemtime($absPath) : '4.0';
    @endphp
    <link rel="stylesheet" href="{{ asset($relPath) }}?v={{ $ver }}">
@endpush

<section class="sat-section sat-conx" id="block-connections">
    <div class="sat-conx-shell">

        {{-- Header principal --}}
        <div class="sat-conx-head">
            <div class="sat-conx-title">
                <div class="sat-conx-ico" aria-hidden="true">
                    <svg class="sat-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16M4 12h16M4 18h10"></path>
                        <path d="M6 3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3Z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="sat-conx-h1">SAT · Conexiones</h3>
                    <p class="sat-conx-sub">
                        Administra RFCs, alias y credenciales necesarias para habilitar solicitudes SAT,
                        cotizaciones y descargas operativas.
                    </p>
                </div>
            </div>

            <div class="sat-conx-actions">
                @if(!empty($rtExternalInvite))
                    <button
                        type="button"
                        class="btn soft"
                        id="btnExternalInviteOpen"
                        data-url="{{ $rtExternalInvite }}"
                        data-open="modal-invite"
                    >
                        <span aria-hidden="true">↗</span> Invitar
                    </button>
                @endif

                <button type="button" class="btn primary" id="btnOpenAddRfc" data-open="modal-rfc">
                    <span aria-hidden="true">＋</span> Agregar RFC
                </button>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="sat-stats">
            <div class="sat-stat">
                <div class="sat-stat-top">
                    <div class="sat-stat-label">Plan</div>
                    <span class="sat-stat-ico" aria-hidden="true">
                        <span class="sat-dot {{ $isPro ? 'ok' : 'mute' }}"></span>
                    </span>
                </div>
                <div class="sat-stat-value">{{ $plan }}</div>
                <div class="sat-stat-sub">{{ $isPro ? 'Modo productivo' : 'Modo base' }}</div>
            </div>

            <div class="sat-stat">
                <div class="sat-stat-top">
                    <div class="sat-stat-label">Validados</div>
                    <span class="sat-stat-ico" aria-hidden="true">
                        <span class="sat-dot ok"></span>
                    </span>
                </div>
                <div class="sat-stat-value">{{ number_format($kValidated) }}</div>
                <div class="sat-stat-sub">Listos para operar</div>
            </div>

            <div class="sat-stat">
                <div class="sat-stat-top">
                    <div class="sat-stat-label">Pendientes</div>
                    <span class="sat-stat-ico" aria-hidden="true">
                        <span class="sat-dot warn"></span>
                    </span>
                </div>
                <div class="sat-stat-value">{{ number_format($kPending) }}</div>
                <div class="sat-stat-sub">Falta CSD o validación</div>
            </div>

            <div class="sat-stat">
                <div class="sat-stat-top">
                    <div class="sat-stat-label">RFC externo</div>
                    <span class="sat-stat-ico" aria-hidden="true">
                        <span class="sat-dot {{ ($externalRfc !== '' ? ($externalVerified ? 'ok' : 'warn') : 'mute') }}"></span>
                    </span>
                </div>
                <div class="sat-stat-value sat-rfc-mono sat-stat-rfc">{{ $externalRfc !== '' ? $externalRfc : '—' }}</div>
                <div class="sat-stat-sub">
                    {{ $externalRfc !== '' ? ($externalVerified ? 'Registro SAT OK' : 'Registro SAT pendiente') : 'Sin registro externo' }}
                </div>
            </div>
        </div>

        {{-- Layout principal --}}
        <div class="sat-grid">

            {{-- Panel RFC externo --}}
            <div class="sat-panel">
                <div class="sat-panel-head">
                    <div class="sat-panel-head-left">
                        <div class="sat-panel-title">
                            RFC externo
                            <span class="sat-badge mute">Referencia</span>
                        </div>
                        <div class="sat-panel-hint">
                            Este RFC proviene del registro externo/admin. Puedes integrarlo a tus RFCs operativos para usarlo en el flujo SAT.
                        </div>
                    </div>
                </div>

                <div class="sat-ext">
                    <div class="sat-badges">
                        @if($externalRfc !== '')
                            <span class="sat-badge ok">✓ Registro detectado</span>
                            <span class="sat-badge {{ $externalVerified ? 'ok' : 'warn' }}">
                                {{ $externalVerified ? '✓ SAT OK' : '⏳ SAT pendiente' }}
                            </span>
                        @else
                            <span class="sat-badge warn">⚠ Sin RFC externo</span>
                        @endif
                    </div>

                    <div class="sat-ext-main">
                        <div class="sat-ext-text">
                            <div class="sat-ext-rfc sat-rfc-mono">{{ $externalRfc !== '' ? $externalRfc : '—' }}</div>
                            <div class="sat-ext-desc">
                                @if($externalRfc !== '')
                                    Puedes agregar este RFC a tu lista interna para completar alias, CSD y validarlo para solicitudes SAT.
                                @else
                                    Si esperabas ver un RFC aquí, revisa el flujo de registro externo o usa “Invitar” para capturarlo.
                                @endif
                            </div>
                        </div>

                        <div class="sat-ext-actions">
                            <a class="btn soft" href="#{{ $anchorTable }}" style="text-decoration:none;">Ver RFCs</a>

                            <button
                                type="button"
                                class="btn"
                                id="btnExternalAddAsRfc"
                                data-rfc="{{ $externalRfc }}"
                                data-alias="Registro externo"
                                {{ $externalRfc !== '' ? '' : 'disabled' }}
                            >
                                <span aria-hidden="true">＋</span> Agregar como RFC
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sat-mini-note">
                    El estado de este bloque no reemplaza la validación operativa de credenciales. Para usarlo en solicitudes SAT debes completar su configuración.
                </div>
            </div>

            {{-- Panel tabla RFCs --}}
            <div class="sat-panel" id="{{ $anchorTable }}">
                <div class="sat-panel-head">
                    <div class="sat-panel-head-left">
                        <div class="sat-panel-title">
                            RFCs registrados
                            <span class="sat-badge mute">Gestión</span>
                        </div>
                        <div class="sat-panel-hint">
                            Solo los RFCs validados deben usarse en solicitudes, cotizaciones y descargas SAT.
                        </div>
                    </div>
                </div>

                <div class="sat-table-wrap">
                    <table class="sat-table">
                        <thead>
                            <tr>
                                <th style="text-align:center; width:64px;">#</th>
                                <th>RFC</th>
                                <th>Alias / razón social</th>
                                <th style="text-align:center; width:140px;">Credenciales</th>
                                <th style="text-align:center; width:120px;">SAT</th>
                                <th style="text-align:center; width:170px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $row)
                            @php
                                $satPill = $row->isValidated ? 'ok' : 'warn';
                                $satText = $row->isValidated ? 'OK' : 'PEND';
                            @endphp
                            <tr data-rfc="{{ $row->rfc }}">
                                <td style="text-align:center;" class="sat-rfc-mono">
                                    {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </td>

                                <td class="sat-rfc-mono">{{ $row->rfc !== '' ? $row->rfc : '—' }}</td>

                                <td>
                                    <div class="sat-strong">{{ $row->alias !== '' ? $row->alias : '—' }}</div>
                                    <div class="sat-row-meta">
                                        <span class="sat-badge mute">
                                            {{ $row->hasCsd ? 'CSD/E.FIRMA cargado' : 'Sin CSD' }}
                                        </span>
                                    </div>
                                </td>

                                <td style="text-align:center;">
                                    <span class="sat-badge {{ $row->hasCsd ? 'ok' : 'warn' }}">
                                        {{ $row->hasCsd ? 'OK' : 'PEND' }}
                                    </span>
                                </td>

                                <td style="text-align:center;">
                                    <span class="sat-badge {{ $satPill }}">
                                        <span class="sat-badge-dot {{ $satPill }}"></span>
                                        {{ $satText }}
                                    </span>
                                </td>

                                <td style="text-align:center;">
                                    <div class="sat-row-actions">
                                        <button
                                            type="button"
                                            class="sat-iconbtn"
                                            title="Subir/actualizar CSD"
                                            data-open="modal-rfc"
                                            data-prefill-rfc="{{ $row->rfc }}"
                                            data-prefill-alias="{{ $row->alias }}"
                                            {{ ($rtCsdStore !== '#' && $row->rfc !== '') ? '' : 'disabled' }}
                                        >
                                            <svg class="sat-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M12 3l7 4v5c0 5-3 9-7 9s-7-4-7-9V7l7-4Z"></path>
                                                <path d="M9 12l2 2 4-4"></path>
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            class="sat-iconbtn"
                                            title="Editar alias"
                                            data-action="alias"
                                            data-url="{{ $rtAlias }}"
                                            data-rfc="{{ $row->rfc }}"
                                            data-prefill-alias="{{ $row->alias }}"
                                            {{ ($rtAlias !== '#' && $row->rfc !== '') ? '' : 'disabled' }}
                                        >
                                            <svg class="sat-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            class="sat-iconbtn danger"
                                            title="Eliminar RFC"
                                            data-action="delete-rfc"
                                            data-url="{{ $rtRfcDelete }}"
                                            data-rfc="{{ $row->rfc }}"
                                            {{ ($rtRfcDelete !== '#' && $row->rfc !== '') ? '' : 'disabled' }}
                                        >
                                            <svg class="sat-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4h8v2"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6M14 11v6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center; opacity:.75; padding:18px;">
                                    Aún no tienes RFCs registrados. Usa “Agregar RFC” para iniciar.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sat-mini-note">
                    Recomendación: mantén al menos 1 RFC validado y con credenciales completas para operar correctamente en SAT.
                </div>
            </div>
        </div>
    </div>
</section>

{{-- MODAL INVITAR --}}
<div class="sat-modal-backdrop" id="modalInvite" style="display:none;">
    <div class="sat-modal sat-modal-lg">
        <div class="sat-modal-header sat-modal-header-simple">
            <div>
                <div class="sat-modal-kicker">Registro externo</div>
                <div class="sat-modal-title">Invitar por correo</div>
                <p class="sat-modal-sub">
                    Envía una invitación para registrar RFC/CSD y vincularlo a tu cuenta.
                </p>
            </div>
            <button type="button" class="sat-modal-close" data-close="modal-invite" aria-label="Cerrar">✕</button>
        </div>

        <form id="formInvite">
            @csrf
            <div class="sat-modal-body sat-modal-body-steps">
                <section class="sat-step-card">
                    <div class="sat-step-kicker">
                        <span>Datos</span>
                        <small>Correo del invitado</small>
                    </div>

                    <div class="sat-step-grid">
                        <div class="sat-field sat-field-full">
                            <div class="sat-field-label">Correo electrónico</div>
                            <input
                                class="input sat-input-pill"
                                type="email"
                                name="email"
                                id="inviteEmail"
                                placeholder="correo@dominio.com"
                                required
                            >
                        </div>

                        <div class="sat-field sat-field-full">
                            <div class="sat-field-label">Nota (opcional)</div>
                            <input
                                class="input sat-input-pill"
                                type="text"
                                name="note"
                                id="inviteNote"
                                maxlength="190"
                                placeholder="Ej. Registro de RFC para la cuenta..."
                            >
                        </div>
                    </div>

                    <div class="sat-mini-note" style="margin-top:10px;">
                        Se validará el correo al enviar. La liga de invitación se mostrará si el backend la devuelve.
                    </div>
                </section>
            </div>

            <div class="sat-modal-footer">
                <button type="button" class="btn" data-close="modal-invite">Cancelar</button>
                <button type="submit" class="btn primary">
                    <span aria-hidden="true">✉</span><span>Enviar invitación</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    function ready(fn) {
        try {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn, { once: true });
            } else {
                fn();
            }
        } catch (e) {
            try { fn(); } catch (_) {}
        }
    }

    function getCsrf() {
        try {
            if (window.P360_SAT && window.P360_SAT.csrf) return String(window.P360_SAT.csrf);
        } catch (e) {}
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function toast(msg, kind) {
        kind = kind || 'info';
        try {
            if (window.P360 && typeof window.P360.toast === 'function') {
                if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
                if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
                return window.P360.toast(msg);
            }
        } catch (e) {}
        alert(msg);
    }

    async function postForm(url, dataObj) {
        var csrf = getCsrf();
        var fd = new FormData();

        Object.keys(dataObj || {}).forEach(function (k) {
            if (dataObj[k] !== undefined && dataObj[k] !== null) {
                fd.append(k, String(dataObj[k]));
            }
        });

        var res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json, text/plain, */*'
            },
            body: fd,
            credentials: 'same-origin'
        });

        var txt = '';
        try { txt = await res.text(); } catch (e) {}

        var json = null;
        try { json = txt ? JSON.parse(txt) : null; } catch (e) {}

        if (!res.ok) {
            var msg =
                (json && (json.msg || json.message || json.error))
                    ? (json.msg || json.message || json.error)
                    : ('Error HTTP ' + res.status);
            throw new Error(msg);
        }

        return json || { ok: true };
    }

    function openInviteModal() {
        var el = document.getElementById('modalInvite');
        if (!el) return;
        el.style.display = 'flex';
        el.classList.add('is-open');
    }

    function closeInviteModal() {
        var el = document.getElementById('modalInvite');
        if (!el) return;
        el.style.display = 'none';
        el.classList.remove('is-open');
    }

    function openModalRfc() {
        var el = document.getElementById('modalRfc');
        if (!el) return;
        el.style.display = 'flex';
        el.classList.add('is-open');
    }

    function prefillModalRfc(rfc, alias) {
        try {
            var form = document.getElementById('formRfc');
            if (!form) return;

            var inRfc = form.querySelector('input[name="rfc"]');
            var inAli = form.querySelector('input[name="alias"]');

            if (inRfc && rfc) inRfc.value = String(rfc).toUpperCase().trim();
            if (inAli) inAli.value = String(alias || '').trim();
        } catch (e) {}
    }

    async function handleEditAlias(btn) {
        var url = btn.getAttribute('data-url') || '';
        var rfc = (btn.getAttribute('data-rfc') || '').toUpperCase().trim();

        if (!url || url === '#') return toast('Ruta de alias no configurada.', 'error');
        if (!rfc) return toast('RFC inválido.', 'error');

        var currentAlias = (btn.getAttribute('data-prefill-alias') || '').trim();
        if (!currentAlias) {
            try {
                var tr = btn.closest('tr');
                var aliasCell = tr ? tr.querySelector('td:nth-child(3) .sat-strong') : null;
                currentAlias = aliasCell ? String(aliasCell.textContent || '').trim() : '';
            } catch (e) {}
        }

        var newAlias = prompt('Editar alias para ' + rfc + ':', currentAlias || '');
        if (newAlias === null) return;
        newAlias = String(newAlias).trim();

        try {
            await postForm(url, { rfc: rfc, alias: newAlias });
            toast('Alias actualizado.', 'success');
            window.location.reload();
        } catch (e) {
            toast((e && e.message) ? e.message : 'No se pudo actualizar el alias.', 'error');
        }
    }

    async function handleDeleteRfc(btn) {
        var url = btn.getAttribute('data-url') || '';
        var rfc = (btn.getAttribute('data-rfc') || '').toUpperCase().trim();

        if (!url || url === '#') return toast('Ruta de eliminar RFC no configurada.', 'error');
        if (!rfc) return toast('RFC inválido.', 'error');

        if (!confirm('¿Eliminar RFC ' + rfc + '?\n\nEsto quitará la conexión y sus credenciales asociadas.')) return;

        try {
            await postForm(url, { rfc: rfc });
            toast('RFC eliminado.', 'success');
            window.location.reload();
        } catch (e) {
            toast((e && e.message) ? e.message : 'No se pudo eliminar el RFC.', 'error');
        }
    }

    async function handleInviteSubmit(form) {
        var opener = document.getElementById('btnExternalInviteOpen');
        var url = opener ? String(opener.getAttribute('data-url') || '').trim() : '';

        if (!url || url === '#') {
            toast('Ruta de invitación no configurada.', 'error');
            return;
        }

        var email = form.querySelector('#inviteEmail');
        var note  = form.querySelector('#inviteNote');
        var submit = form.querySelector('button[type="submit"]');

        var emailVal = email ? String(email.value || '').trim() : '';
        var noteVal  = note ? String(note.value || '').trim() : '';

        if (!emailVal) {
            toast('Captura un correo válido.', 'error');
            if (email) email.focus();
            return;
        }

        if (submit) submit.disabled = true;

        try {
            var res = await postForm(url, {
                email: emailVal,
                note: noteVal
            });

            toast((res && (res.msg || res.message)) ? (res.msg || res.message) : 'Invitación enviada.', 'success');
            closeInviteModal();
            form.reset();
        } catch (e) {
            toast((e && e.message) ? e.message : 'No se pudo enviar la invitación.', 'error');
        } finally {
            if (submit) submit.disabled = false;
        }
    }

    ready(function () {
        var btnAdd = document.getElementById('btnOpenAddRfc');
        if (btnAdd) btnAdd.addEventListener('click', openModalRfc);

        var btnInvite = document.getElementById('btnExternalInviteOpen');
        if (btnInvite) {
            btnInvite.addEventListener('click', function () {
                openInviteModal();
            });
        }

        var modalInvite = document.getElementById('modalInvite');
        if (modalInvite) {
            modalInvite.addEventListener('click', function (ev) {
                var closeBtn = ev.target.closest('[data-close="modal-invite"]');
                if (closeBtn || ev.target === modalInvite) {
                    closeInviteModal();
                }
            });
        }

        var formInvite = document.getElementById('formInvite');
        if (formInvite) {
            formInvite.addEventListener('submit', function (ev) {
                ev.preventDefault();
                handleInviteSubmit(formInvite);
            });
        }

        var btnExt = document.getElementById('btnExternalAddAsRfc');
        if (btnExt) {
            btnExt.addEventListener('click', function () {
                var rfc = btnExt.getAttribute('data-rfc') || '';
                var ali = btnExt.getAttribute('data-alias') || 'Registro externo';
                rfc = String(rfc).toUpperCase().trim();
                if (!rfc) return;

                prefillModalRfc(rfc, ali);
                openModalRfc();
            });
        }

        document.querySelectorAll('[data-open="modal-rfc"][data-prefill-rfc]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var rfc = btn.getAttribute('data-prefill-rfc') || '';
                var ali = btn.getAttribute('data-prefill-alias') || '';
                if (rfc) prefillModalRfc(rfc, ali);
                openModalRfc();
            });
        });

        document.addEventListener('click', function (ev) {
            var btn = ev.target && ev.target.closest ? ev.target.closest('button[data-action]') : null;
            if (!btn || btn.disabled) return;

            var action = (btn.getAttribute('data-action') || '').toLowerCase().trim();

            if (action === 'alias') {
                ev.preventDefault();
                handleEditAlias(btn);
                return;
            }

            if (action === 'delete-rfc') {
                ev.preventDefault();
                handleDeleteRfc(btn);
            }
        });
    });
})();
</script>
@endpush