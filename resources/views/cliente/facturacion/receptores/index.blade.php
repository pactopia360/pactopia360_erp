{{-- resources/views/cliente/facturacion/receptores/index.blade.php --}}
@extends('layouts.cliente')

@section('title', 'RFC Receptores · Pactopia360')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/pages/rfcs.css') }}?v={{ time() }}">
@endpush

@section('content')
@php
    $rows = collect($receptores->items());

    $stats = [
        'total' => $receptores->total(),
        'activos' => $rows->filter(fn($r) => !empty($r->rfc) && !empty($r->razon_social))->count(),
        'con_regimen' => $rows->filter(fn($r) => !empty($r->regimen_fiscal))->count(),
        'con_cp' => $rows->filter(fn($r) => strlen((string) $r->codigo_postal) === 5)->count(),
    ];

    $rtNuevoCfdi = Route::has('cliente.facturacion.create')
        ? route('cliente.facturacion.create')
        : url('/cliente/facturacion/nuevo');

    $catalogRegimenes = $fiscalCatalogs['regimenes_fiscales'] ?? [];
    $catalogUsos = $fiscalCatalogs['usos_cfdi'] ?? [];
    $catalogFormas = $fiscalCatalogs['formas_pago'] ?? [];
    $catalogMetodos = $fiscalCatalogs['metodos_pago'] ?? [];
@endphp

<div class="rfcs-page">

    @if(session('ok'))
        <div class="rfcs-alert ok">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
        <div class="rfcs-alert error">{{ $errors->first() }}</div>
    @endif

    <section class="rfcs-hero">
        <div>
            <span class="rfcs-kicker">Catálogo fiscal</span>
            <h1>RFC Receptores</h1>
            <p>
                Centraliza clientes receptores para CFDI 4.0: RFC, razón social, régimen fiscal,
                código postal, uso CFDI, forma de pago y datos de contacto.
            </p>
        </div>

        <div class="rfcs-hero-actions">
            <a href="{{ route('cliente.facturacion.index') }}" class="rfcs-btn ghost">Volver a Facturación</a>
            <button type="button" class="rfcs-btn primary" data-open-receptor-modal="create">+ Nuevo receptor</button>
        </div>
    </section>

    <section class="rfcs-kpis">
        <div class="rfcs-kpi">
            <small>Total receptores</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Registrados</span>
        </div>

        <div class="rfcs-kpi">
            <small>Datos fiscales</small>
            <strong>{{ number_format($stats['activos']) }}</strong>
            <span>Con RFC y razón social</span>
        </div>

        <div class="rfcs-kpi">
            <small>Con régimen</small>
            <strong>{{ number_format($stats['con_regimen']) }}</strong>
            <span>Listos para CFDI 4.0</span>
        </div>

        <div class="rfcs-kpi">
            <small>CP fiscal</small>
            <strong>{{ number_format($stats['con_cp']) }}</strong>
            <span>Con código postal válido</span>
        </div>
    </section>

    <section class="rfcs-card">
        <div class="rfcs-card-head">
            <div>
                <h2>Receptores fiscales</h2>
                <p>Administra los clientes que recibirán CFDI dentro de Pactopia360.</p>
            </div>

            <form method="GET" action="{{ route('cliente.facturacion.receptores.index') }}" class="rfcs-tools">
                <input type="search" name="q" value="{{ $q }}" placeholder="Buscar RFC / razón social...">
                <button type="submit" class="rfcs-btn ghost">Buscar</button>
                <a href="{{ route('cliente.facturacion.receptores.index') }}" class="rfcs-btn ghost">Limpiar</a>
            </form>
        </div>

        <div class="rfcs-table-wrap">
            <table class="rfcs-table">
                <thead>
                    <tr>
                        <th>RFC</th>
                        <th>Razón social</th>
                        <th>Salud</th>
                        <th>Régimen / CP</th>
                        <th>Uso CFDI</th>
                        <th>Pago</th>
                        <th>Dirección</th>
                        <th>Contacto</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($receptores as $receptor)
                        @php
                            $rfc = strtoupper((string) ($receptor->rfc ?? ''));
                            $razonSocial = trim((string) ($receptor->razon_social ?? ''));
                            $nombreComercial = trim((string) ($receptor->nombre_comercial ?? ''));
                            $regimen = trim((string) ($receptor->regimen_fiscal ?? ''));
                            $cp = trim((string) ($receptor->codigo_postal ?? ''));
                            $uso = trim((string) ($receptor->uso_cfdi ?? 'G03'));
                            $formaPago = trim((string) ($receptor->forma_pago ?? ''));
                            $metodoPago = trim((string) ($receptor->metodo_pago ?? ''));
                            $email = trim((string) ($receptor->email ?? ''));
                            $telefono = trim((string) ($receptor->telefono ?? ''));

                            $healthScore = 0;
                            $healthScore += $rfc !== '' ? 25 : 0;
                            $healthScore += $razonSocial !== '' ? 20 : 0;
                            $healthScore += $regimen !== '' ? 20 : 0;
                            $healthScore += strlen($cp) === 5 ? 20 : 0;
                            $healthScore += $uso !== '' ? 10 : 0;
                            $healthScore += $email !== '' ? 5 : 0;

                            $ready = $rfc !== '' && $razonSocial !== '' && $regimen !== '' && strlen($cp) === 5 && $uso !== '';
                            $healthClass = $healthScore >= 80 ? 'ok' : ($healthScore >= 50 ? 'warn' : 'muted');

                            $payload = [
                                'id' => $receptor->id,
                                'rfc' => $rfc,
                                'razon_social' => $razonSocial,
                                'nombre_comercial' => $nombreComercial,
                                'uso_cfdi' => $uso,
                                'forma_pago' => $formaPago,
                                'metodo_pago' => $metodoPago,
                                'regimen_fiscal' => $regimen,
                                'codigo_postal' => $cp,
                                'pais' => $receptor->pais ?: 'MEX',
                                'estado' => $receptor->estado,
                                'municipio' => $receptor->municipio,
                                'colonia' => $receptor->colonia,
                                'calle' => $receptor->calle,
                                'no_ext' => $receptor->no_ext,
                                'no_int' => $receptor->no_int,
                                'email' => $email,
                                'telefono' => $telefono,
                            ];
                        @endphp

                        <tr data-receptor-row data-search="{{ e(strtolower($rfc.' '.$razonSocial.' '.$nombreComercial.' '.$email.' '.$regimen.' '.$cp)) }}">
                            <td>
                                <strong class="rfcs-rfc">{{ $rfc ?: 'Pendiente' }}</strong>
                                <span class="rfcs-muted">{{ $nombreComercial ?: 'Sin nombre comercial' }}</span>
                            </td>

                            <td>
                                <strong>{{ $razonSocial ?: 'Sin razón social registrada' }}</strong>
                                <span class="rfcs-muted">{{ $email ?: 'Sin correo principal' }}</span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $ready ? 'ok' : $healthClass }}">
                                    {{ $ready ? 'Listo' : ($healthScore . '%') }}
                                </span>
                            </td>

                            <td>
                                <strong>{{ $regimen ?: 'Pendiente' }}</strong>
                                <span class="rfcs-muted">CP: {{ $cp ?: 'Pendiente' }}</span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $uso ? 'ok' : 'warn' }}">
                                    {{ $uso ?: 'Pendiente' }}
                                </span>
                            </td>

                            <td>
                                <strong>{{ $metodoPago ?: 'Pendiente' }}</strong>
                                <span class="rfcs-muted">Forma: {{ $formaPago ?: 'Pendiente' }}</span>
                            </td>

                            <td>
                                <strong>{{ $receptor->estado ?: 'Sin estado' }}</strong>
                                <span class="rfcs-muted">
                                    {{ collect([$receptor->colonia, $receptor->municipio])->filter()->join(', ') ?: 'Sin dirección fiscal' }}
                                </span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $email ? 'ok' : 'warn' }}">
                                    {{ $email ? 'Configurado' : 'Pendiente' }}
                                </span>
                                <span class="rfcs-muted">{{ $telefono ?: 'Sin teléfono' }}</span>
                            </td>

                            <td class="text-end rfcs-actions-cell">
                                <div class="rfcs-action-menu">
                                    <button type="button" class="rfcs-dots-btn" data-rfcs-menu-toggle aria-label="Acciones">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <circle cx="5" cy="12" r="1.8" fill="currentColor"/>
                                            <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
                                            <circle cx="19" cy="12" r="1.8" fill="currentColor"/>
                                        </svg>
                                    </button>

                                    <div class="rfcs-floating-menu" hidden>
                                        <button type="button" class="rfcs-menu-item" data-open-receptor-modal="edit" data-receptor='@json($payload)'>
                                            Editar
                                        </button>

                                        <form method="POST"
                                              action="{{ route('cliente.facturacion.receptores.destroy', $receptor->id) }}"
                                              onsubmit="return confirm('¿Eliminar este receptor? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rfcs-menu-item danger">
                                                Borrar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="rfcs-empty">
                                    <strong>No hay receptores registrados</strong>
                                    <span>Agrega tu primer receptor para reutilizarlo al emitir CFDI.</span>
                                    <button type="button" class="rfcs-btn primary" data-open-receptor-modal="create">
                                        + Nuevo receptor
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding-top:14px;">
            {{ $receptores->links() }}
        </div>
    </section>
</div>

<div class="receptor-modal-backdrop" data-receptor-modal hidden>
    <div class="receptor-modal-panel">
        <div class="rfcs-modal-head">
            <div>
                <span class="rfcs-kicker" data-receptor-modal-kicker>Nuevo receptor</span>
                <h2 data-receptor-modal-title>Agregar receptor fiscal</h2>
                <p>Captura los datos fiscales CFDI 4.0 del receptor.</p>
                <div class="receptor-ai-panel" id="receptorAiPanel">
                    <div class="receptor-ai-head">
                        <div class="receptor-ai-bot">🤖</div>
                        <div>
                            <strong>IA Fiscal Pactopia360</strong>
                            <span>Valida RFC, régimen, CP, uso CFDI y forma de pago antes de timbrar.</span>
                        </div>
                        <button type="button" class="receptor-ai-btn" id="receptorAiCheck">
                            Verificar datos
                        </button>
                    </div>

                    <div class="receptor-ai-score" id="receptorAiScore">
                        <strong>Score fiscal: —</strong>
                        <span>Completa los datos y presiona verificar.</span>
                    </div>

                    <div class="receptor-ai-list" id="receptorAiList"></div>
                </div>
            </div>

            <button type="button" class="rfcs-modal-close" data-close-receptor-modal>×</button>
        </div>

        <form id="receptorForm">
            @csrf
            <input type="hidden" name="_method" value="POST" id="receptorMethod">
            <input type="hidden" id="receptorId">

            <div class="rfcs-form-grid">
                <label>
                    RFC
                    <input type="text" name="rfc" required maxlength="13">
                </label>

                <label>
                    Razón social
                    <input type="text" name="razon_social" required maxlength="255">
                </label>

                <label>
                    Nombre comercial
                    <input type="text" name="nombre_comercial" maxlength="255">
                </label>

                <label>
                    Régimen fiscal
                    <select name="regimen_fiscal">
                        <option value="">Seleccionar régimen</option>
                        @foreach($catalogRegimenes as $key => $label)
                            <option value="{{ $key }}">{{ $key }} · {{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Uso CFDI
                    <select name="uso_cfdi">
                        @foreach($catalogUsos as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'G03')>{{ $key }} · {{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Código postal fiscal
                    <input type="text" name="codigo_postal" maxlength="10">
                </label>

                <label>
                    Método de pago
                    <select name="metodo_pago">
                        <option value="">Seleccionar método</option>
                        @foreach($catalogMetodos as $key => $label)
                            <option value="{{ $key }}">{{ $key }} · {{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Forma de pago
                    <select name="forma_pago">
                        <option value="">Seleccionar forma</option>
                        @foreach($catalogFormas as $key => $label)
                            <option value="{{ $key }}">{{ $key }} · {{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    País
                    <input type="text" name="pais" value="MEX" maxlength="3">
                </label>

                <label>
                    Estado
                    <input type="text" name="estado" maxlength="120">
                </label>

                <label>
                    Municipio
                    <input type="text" name="municipio" maxlength="120">
                </label>

                <label>
                    Colonia
                    <input type="text" name="colonia" maxlength="120">
                </label>

                <label>
                    Calle
                    <input type="text" name="calle" maxlength="180">
                </label>

                <label>
                    No. ext
                    <input type="text" name="no_ext" maxlength="30">
                </label>

                <label>
                    No. int
                    <input type="text" name="no_int" maxlength="30">
                </label>

                <label>
                    Email
                    <input type="email" name="email" maxlength="180">
                </label>

                <label>
                    Teléfono
                    <input type="text" name="telefono" maxlength="40">
                </label>
            </div>

            <div class="rfcs-modal-actions">
                <button type="button" class="rfcs-btn ghost" data-close-receptor-modal>Cancelar</button>
                <button type="submit" class="rfcs-btn primary">Guardar receptor</button>
            </div>
        </form>
    </div>
</div>

<style>
    .receptor-modal-backdrop[hidden] {
        display: none !important;
    }

    .receptor-modal-backdrop {
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483647 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 24px !important;
        background: rgba(15, 23, 42, .62) !important;
        backdrop-filter: blur(7px) !important;
    }

    .receptor-modal-panel {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        transform: none !important;
        pointer-events: auto !important;
        width: min(980px, 100%) !important;
        max-height: calc(100vh - 48px) !important;
        overflow: auto !important;
        background: #ffffff !important;
        border: 1px solid #dbe7f5 !important;
        border-radius: 28px !important;
        box-shadow: 0 30px 90px rgba(15, 23, 42, .30) !important;
        padding: 24px !important;
        position: relative !important;
        z-index: 2147483647 !important;
    }

    .receptor-modal-panel .rfcs-modal-head {
        display: flex !important;
        justify-content: space-between !important;
        gap: 18px !important;
        align-items: flex-start !important;
        margin-bottom: 20px !important;
    }

    .receptor-modal-panel .rfcs-modal-head h2 {
        margin: 6px 0 4px !important;
        color: #10213f !important;
        font-size: 26px !important;
        font-weight: 950 !important;
    }

    .receptor-modal-panel .rfcs-modal-head p {
        margin: 0 !important;
        color: #64748b !important;
        font-size: 13px !important;
        font-weight: 700 !important;
    }

    .receptor-modal-panel .rfcs-modal-close {
        width: 42px !important;
        height: 42px !important;
        border: 1px solid #dbe7f5 !important;
        border-radius: 14px !important;
        background: #f8fbff !important;
        color: #1e3a8a !important;
        font-size: 24px !important;
        font-weight: 900 !important;
        cursor: pointer !important;
    }

    .receptor-modal-panel .rfcs-form-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 14px !important;
    }

    .receptor-modal-panel .rfcs-form-grid label {
        display: grid !important;
        gap: 7px !important;
        color: #60708a !important;
        font-size: 11px !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: .03em !important;
    }

    .receptor-modal-panel .rfcs-form-grid input,
    .receptor-modal-panel .rfcs-form-grid select {
        min-height: 46px !important;
        width: 100% !important;
        border: 1px solid #dbe7f5 !important;
        border-radius: 16px !important;
        background: #fff !important;
        color: #10213f !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        padding: 0 13px !important;
        outline: none !important;
    }

    .receptor-modal-panel .rfcs-modal-actions {
        display: flex !important;
        justify-content: flex-end !important;
        gap: 12px !important;
        margin-top: 22px !important;
    }

    @media (max-width: 900px) {
        .receptor-modal-panel .rfcs-form-grid {
            grid-template-columns: 1fr !important;
        }

        .receptor-modal-panel {
            border-radius: 22px !important;
            padding: 18px !important;
        }
    }

    .receptor-ai-panel{
    margin:16px 0 20px;
    padding:16px;
    border:1px solid #dbeafe;
    border-radius:22px;
    background:linear-gradient(135deg,#eff6ff 0%,#ffffff 62%,#f0f9ff 100%);
}

.receptor-ai-head{
    display:grid;
    grid-template-columns:auto minmax(0,1fr) auto;
    gap:12px;
    align-items:center;
}

.receptor-ai-bot{
    width:46px;
    height:46px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#2563eb;
    color:#fff;
    font-size:22px;
    box-shadow:0 14px 30px rgba(37,99,235,.22);
}

.receptor-ai-head strong,
.receptor-ai-head span{
    display:block;
}

.receptor-ai-head strong{
    color:#10213f;
    font-size:15px;
    font-weight:950;
}

.receptor-ai-head span{
    color:#64748b;
    font-size:12px;
    font-weight:800;
    margin-top:2px;
}

.receptor-ai-btn{
    min-height:40px;
    border:0;
    border-radius:14px;
    padding:0 14px;
    background:#0f5eff;
    color:#fff;
    font-size:12px;
    font-weight:950;
    cursor:pointer;
    box-shadow:0 12px 26px rgba(15,94,255,.22);
}

.receptor-ai-score{
    margin-top:14px;
    padding:12px 14px;
    border-radius:16px;
    background:#fff;
    border:1px solid #dbeafe;
}

.receptor-ai-score strong,
.receptor-ai-score span{
    display:block;
}

.receptor-ai-score strong{
    color:#10213f;
    font-size:13px;
    font-weight:950;
}

.receptor-ai-score span{
    color:#64748b;
    font-size:12px;
    font-weight:800;
    margin-top:3px;
}

.receptor-ai-list{
    display:grid;
    gap:8px;
    margin-top:10px;
}

.receptor-ai-item{
    padding:10px 12px;
    border-radius:14px;
    font-size:12px;
    font-weight:850;
    line-height:1.45;
}

.receptor-ai-item.ok{
    background:#ecfdf5;
    color:#166534;
    border:1px solid #bbf7d0;
}

.receptor-ai-item.warn{
    background:#fffbeb;
    color:#92400e;
    border:1px solid #fde68a;
}

.receptor-ai-item.error{
    background:#fef2f2;
    color:#991b1b;
    border:1px solid #fecaca;
}
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-receptor-modal]');
    const form = document.getElementById('receptorForm');
    const receptorId = document.getElementById('receptorId');
    const receptorMethod = document.getElementById('receptorMethod');
    const title = document.querySelector('[data-receptor-modal-title]');
    const kicker = document.querySelector('[data-receptor-modal-kicker]');

    function openModal(mode, data = {}) {
        form.reset();
        receptorId.value = '';
        receptorMethod.value = 'POST';

        title.textContent = mode === 'edit' ? 'Editar receptor fiscal' : 'Agregar receptor fiscal';
        kicker.textContent = mode === 'edit' ? 'Editar receptor' : 'Nuevo receptor';

        if (mode === 'edit') {
            receptorId.value = data.id || '';
            receptorMethod.value = 'PUT';

            Object.keys(data).forEach(function (key) {
                const input = form.querySelector('[name="' + key + '"]');
                if (input) input.value = data[key] || '';
            });
        } else {
            const pais = form.querySelector('[name="pais"]');
            const uso = form.querySelector('[name="uso_cfdi"]');
            if (pais) pais.value = 'MEX';
            if (uso) uso.value = 'G03';
        }

        modal.hidden = false;
    }

    function closeModal() {
        modal.hidden = true;
    }

    document.addEventListener('click', function (event) {
        const menuToggle = event.target.closest('[data-rfcs-menu-toggle]');

        document.querySelectorAll('.rfcs-floating-menu').forEach(function (menu) {
            if (!menuToggle || !menu.parentElement.contains(menuToggle)) {
                menu.hidden = true;
            }
        });

        if (menuToggle) {
            const menu = menuToggle.parentElement.querySelector('.rfcs-floating-menu');
            if (menu) menu.hidden = !menu.hidden;
        }

        const openBtn = event.target.closest('[data-open-receptor-modal]');
        if (openBtn) {
            const mode = openBtn.dataset.openReceptorModal || 'create';
            const data = openBtn.dataset.receptor ? JSON.parse(openBtn.dataset.receptor) : {};
            openModal(mode, data);
        }

        if (event.target.closest('[data-close-receptor-modal]')) {
            closeModal();
        }

        if (event.target === modal) {
            closeModal();
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const id = receptorId.value;
        const method = receptorMethod.value === 'PUT' ? 'PUT' : 'POST';
        const url = method === 'PUT'
            ? "{{ url('/cliente/facturacion/receptores') }}/" + encodeURIComponent(id)
            : "{{ route('cliente.facturacion.receptores.store') }}";

        const formData = new FormData(form);

        if (method === 'PUT') {
            formData.set('_method', 'PUT');
        }

        const response = await fetch(url, {
            method: method === 'PUT' ? 'POST' : 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value
            },
            body: formData
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok || !json.ok) {
            alert(json.message || 'No se pudo guardar el receptor.');
            return;
        }

        window.location.reload();
    });
});

const aiButton = document.getElementById('receptorAiCheck');
const aiScore = document.getElementById('receptorAiScore');
const aiList = document.getElementById('receptorAiList');

function receptorFormPayload() {
    const f = document.getElementById('receptorForm');

    if (!f) {
        console.error('Form no encontrado');
        return {};
    }

    return {
        rfc: f.querySelector('[name="rfc"]')?.value || '',
        razon_social: f.querySelector('[name="razon_social"]')?.value || '',
        regimen_fiscal: f.querySelector('[name="regimen_fiscal"]')?.value || '',
        codigo_postal: f.querySelector('[name="codigo_postal"]')?.value || '',
        uso_cfdi: f.querySelector('[name="uso_cfdi"]')?.value || '',
        metodo_pago: f.querySelector('[name="metodo_pago"]')?.value || '',
        forma_pago: f.querySelector('[name="forma_pago"]')?.value || '',
        total: 1
    };
}

function renderAiResult(json) {
    const assistant = json.assistant || {};
    const score = assistant.score ?? 0;
    const nivel = assistant.nivel || 'revisar';

    aiScore.innerHTML = `
        <strong>Score fiscal: ${score}/100 · ${nivel.toUpperCase()}</strong>
        <span>${assistant.ok ? 'Datos listos para CFDI 4.0.' : 'Hay datos fiscales que deben corregirse antes de timbrar.'}</span>
    `;

    const errors = assistant.errors || [];
    const warnings = assistant.warnings || [];
    const suggestions = assistant.suggestions || [];
    const defaults = assistant.smart_defaults || {};

    let items = [];

    errors.forEach(text => items.push({ type: 'error', text: 'Error: ' + text }));
    warnings.forEach(text => items.push({ type: 'warn', text: 'Alerta: ' + text }));
    suggestions.forEach(text => items.push({ type: 'warn', text: 'Sugerencia: ' + text }));

    Object.keys(defaults).forEach(key => {
        items.push({
            type: 'ok',
            text: 'IA sugiere ' + key.replaceAll('_', ' ') + ': ' + defaults[key]
        });
    });

    if (!items.length) {
        items.push({
            type: 'ok',
            text: 'Validación correcta. El receptor tiene estructura fiscal lista para facturación.'
        });
    }

    aiList.innerHTML = items.map(item => `
        <div class="receptor-ai-item ${item.type}">
            ${item.text}
        </div>
    `).join('');
}

if (aiButton) {
    aiButton.addEventListener('click', async function () {
        aiButton.disabled = true;
        aiButton.textContent = 'Verificando...';

        aiScore.innerHTML = `
            <strong>Score fiscal: calculando...</strong>
            <span>La IA está revisando consistencia SAT CFDI 4.0.</span>
        `;
        aiList.innerHTML = '';

        try {
            const response = await fetch("{{ route('cliente.facturacion.assistant') }}", {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify(receptorFormPayload())
            });

            const json = await response.json();
            renderAiResult(json);
        } catch (error) {
            aiScore.innerHTML = `
                <strong>Score fiscal: no disponible</strong>
                <span>No se pudo consultar la IA fiscal.</span>
            `;
            aiList.innerHTML = `
                <div class="receptor-ai-item error">
                    ${error.message}
                </div>
            `;
        } finally {
            aiButton.disabled = false;
            aiButton.textContent = 'Verificar datos';
        }
    });
}
</script>
@endpush