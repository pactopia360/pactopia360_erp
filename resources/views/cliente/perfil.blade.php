{{-- resources/views/cliente/perfil.blade.php --}}
@extends('layouts.client')
@section('title','Perfil · Pactopia360')

@push('styles')
<style>
  .page-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px }
  .page-title{ margin:0; font-size:clamp(18px,2.4vw,22px); font-weight:900; color:var(--text) }
  .muted{ color:var(--muted) }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px; box-shadow:var(--shadow) }
  .grid{ display:grid; gap:12px }
  @media (min-width: 1100px){ .grid{ grid-template-columns: 1fr 1fr } }
  .row{ display:grid; grid-template-columns: 160px 1fr; gap:8px; padding:8px 0; border-bottom:1px solid var(--border) }
  .row strong{ color:var(--muted) }
  .tools{ display:flex; align-items:center; gap:8px; flex-wrap:wrap }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 12px; border-radius:12px; border:1px solid var(--border); font-weight:900; text-decoration:none; cursor:pointer; background:transparent }
  .btn.primary{ background: linear-gradient(180deg, var(--accent), color-mix(in oklab, var(--accent) 85%, black)); color:#fff; border:0 }
  .btn.danger{ background: color-mix(in oklab, var(--err) 25%, var(--card)); border-color: color-mix(in oklab, var(--err) 40%, var(--border)); color: var(--text) }
  .btn.ghost{ background: transparent }
  .btn.disabled{ opacity:.55; pointer-events:none }
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--border); font-weight:900; font-size:12px }
  .badge.pro{ background: color-mix(in oklab, var(--accent-2) 18%, transparent) }
  .table{ width:100%; border-collapse:separate; border-spacing:0 }
  .table th, .table td{ padding:10px; border-bottom:1px solid var(--border); text-align:left; font-size:14px }
  .small{ font-size:12px; color:var(--muted) }

  /* modal */
  dialog.modal{ border:1px solid var(--border); border-radius:14px; padding:0; max-width:920px; width:96vw; }
  .modal header{ display:flex; justify-content:space-between; align-items:center; padding:14px; border-bottom:1px solid var(--border) }
  .modal .body{ padding:14px; max-height:70vh; overflow:auto }
  .modal .grid2{ display:grid; gap:12px }
  @media (min-width: 980px){ .modal .grid2{ grid-template-columns: 1fr 1fr } }
  .field{ display:grid; gap:6px; margin-bottom:10px }
  .lbl{ font-weight:800; color:var(--muted); font-size:12px }
  .input, .select, .number, textarea{
    width:100%; border:1px solid var(--border); border-radius:12px; background: color-mix(in oklab, var(--card) 96%, transparent);
    color:var(--text); padding:10px 12px; font-weight:700; resize:vertical
  }
  .hint{ font-size:12px; color:var(--muted) }
  .pill{ display:inline-flex; gap:6px; align-items:center; padding:4px 8px; border:1px dashed var(--border); border-radius:999px; font-size:12px; }
  .kvs{ display:grid; gap:6px; grid-template-columns: 1fr 1fr 1fr }
  .kvs .input{ padding:8px 10px }
</style>
@endpush

@section('content')
@php
  /** @var \App\Models\User $user */
  $user   = auth('web')->user();
  $cuenta = $user?->cuenta;
  $plan   = strtoupper($cuenta->plan_actual ?? 'FREE');
  $isPro  = ($plan === 'PRO');

  // Estas colecciones pueden venir desde el PerfilController
  /** @var \Illuminate\Support\Collection|\App\Models\Cliente\Emisor[] $emisores */
  $emisores = $emisores ?? collect();
@endphp

<div class="page-header">
  <div>
    <h1 class="page-title">Perfil de la cuenta</h1>
    <div class="muted">Datos y administración de tu usuario, organización y emisores.</div>
  </div>
  <div class="tools">
    <span class="badge {{ $isPro ? 'pro' : '' }}">Plan: {{ $plan }}</span>
  </div>
</div>

<div class="grid">
  {{-- ===== Panel Usuario ===== --}}
  <div class="card">
    <h3 style="margin:0 0 8px">Usuario</h3>
    <div class="row"><strong>Nombre</strong><div>{{ $user?->nombre ?? $user?->name ?? '—' }}</div></div>
    <div class="row"><strong>Email</strong><div>{{ $user?->email ?? '—' }}</div></div>
  </div>

  {{-- ===== Panel Organización ===== --}}
  <div class="card">
    <h3 style="margin:0 0 8px">Organización</h3>
    <div class="row"><strong>Razón social</strong><div>{{ $cuenta?->razon_social ?? $cuenta?->nombre_fiscal ?? '—' }}</div></div>
    <div class="row"><strong>Plan</strong><div>{{ $plan }}</div></div>
    <div class="row"><strong>Timbres</strong><div>{{ number_format((int)($cuenta->timbres_disponibles ?? 0)) }}</div></div>
  </div>
</div>

{{-- ===========================================================
   Emisores dentro de Perfil
=========================================================== --}}
<div class="card" style="margin-top:12px">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
    <div>
      <h3 style="margin:0">Emisores de la cuenta</h3>
      <div class="small">Administra todos tus emisores (ilimitados en FREE/PRO). Puedes usarlos al crear CFDI.</div>
    </div>

    <div class="tools">
      <button class="btn" type="button" onclick="openEmisorModal()">+ Nuevo emisor</button>

      {{-- Importación masiva (solo PRO) --}}
      @if ($isPro)
        <button class="btn" type="button" onclick="openImportModal()">Importar masivo</button>
      @else
        <button class="btn disabled" type="button" title="Solo en PRO">Importar masivo</button>
      @endif

      {{-- Ir a facturar con selección rápida --}}
      <a class="btn primary" href="{{ route('cliente.facturacion.nuevo') }}">Crear CFDI</a>
    </div>
  </div>

  <div style="margin-top:10px">
    @if($emisores->count() === 0)
      <div class="muted">Aún no tienes emisores. Crea el primero con “Nuevo emisor”.</div>
    @else
      <table class="table">
        <thead>
          <tr>
            <th>RFC</th>
            <th>Razón social</th>
            <th>Nombre comercial</th>
            <th>Email</th>
            <th>Régimen</th>
            <th>Grupo</th>
            <th style="width:260px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($emisores as $e)
          <tr>
            <td><code>{{ $e->rfc }}</code></td>
            <td>{{ $e->razon_social }}</td>
            <td>{{ $e->nombre_comercial ?? '—' }}</td>
            <td>{{ $e->email ?? '—' }}</td>
            <td>{{ $e->regimen_fiscal ?? '—' }}</td>
            <td>{{ $e->grupo ?? '—' }}</td>
            <td>
              <div class="tools">
                {{-- Editar (si hay route) --}}
                @php $canEdit = \Illuminate\Support\Facades\Route::has('cliente.emisores.edit'); @endphp
                <a class="btn {{ $canEdit ? '' : 'disabled' }}"
                   @if($canEdit) href="{{ route('cliente.emisores.edit', $e->id) }}" @endif>Editar</a>

                {{-- Usar al facturar (lleva al nuevo CFDI con emisor preseleccionado) --}}
                <a class="btn" href="{{ route('cliente.facturacion.nuevo', ['emisor_id' => $e->id]) }}">Usar al facturar</a>

                {{-- Eliminar (si hay route) --}}
                @php $canDel = \Illuminate\Support\Facades\Route::has('cliente.emisores.destroy'); @endphp
                <form method="POST"
                      @if($canDel) action="{{ route('cliente.emisores.destroy', $e->id) }}" @endif
                      onsubmit="return confirm('¿Eliminar emisor {{ $e->rfc }}?')"
                      style="display:inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn danger {{ $canDel ? '' : 'disabled' }}">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

{{-- ===========================================================
   MÁS SECCIONES DE PERFIL (placeholders para crecer)
=========================================================== --}}
<div class="grid" style="margin-top:12px">
  <div class="card">
    <h3 style="margin:0 0 8px">Compras</h3>
    <div class="muted small">Historial de compras (timbres, planes, addons). Próximamente filtros y descargas.</div>
    @php $hasBilling = \Illuminate\Support\Facades\Route::has('cliente.billing.statement'); @endphp
    <div style="margin-top:10px" class="tools">
      <a class="btn {{ $hasBilling ? '' : 'disabled' }}" @if($hasBilling) href="{{ route('cliente.billing.statement') }}" @endif>Ver estado de cuenta</a>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px">Pagos</h3>
    <div class="muted small">Métodos de pago, facturas de tus compras y recibos. (UI expandible)</div>
  </div>
</div>

{{-- ===========================================================
   MODAL: Nuevo emisor (con campos Waretek)
=========================================================== --}}
<dialog class="modal" id="emisorModal">
  <header>
    <strong>Nuevo emisor</strong>
    <button class="btn ghost" onclick="closeEmisorModal()">✕</button>
  </header>
  <div class="body">
    @php $canStore = \Illuminate\Support\Facades\Route::has('cliente.emisores.store'); @endphp
    <form id="emisorForm" method="POST" @if($canStore) action="{{ route('cliente.emisores.store') }}" @endif>
      @csrf

      <div class="grid" style="grid-template-columns: 1fr">
        <div class="card" style="padding:12px">
          <h4 style="margin:0 0 8px">Identificación</h4>
          <div class="grid">
            <div class="grid2">
              <div class="field">
                <span class="lbl">RFC (SAT)</span>
                <input class="input" type="text" name="rfc" maxlength="13" required>
              </div>
              <div class="field">
                <span class="lbl">Email</span>
                <input class="input" type="email" name="email" required>
              </div>
            </div>
            <div class="grid2">
              <div class="field">
                <span class="lbl">Razón social</span>
                <input class="input" type="text" name="razon_social" maxlength="190" required>
              </div>
              <div class="field">
                <span class="lbl">Nombre comercial</span>
                <input class="input" type="text" name="nombre_comercial" maxlength="190">
              </div>
            </div>
            <div class="grid2">
              <div class="field">
                <span class="lbl">Régimen fiscal (clave SAT)</span>
                <input class="input" type="text" name="regimen_fiscal" placeholder="601, 612, 603, …" required>
                <div class="hint">Equivale a <code>regimen</code> de Waretek.</div>
              </div>
              <div class="field">
                <span class="lbl">Grupo (opcional)</span>
                <input class="input" type="text" name="grupo" placeholder="restaurante, conductor, etc.">
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="padding:12px">
          <h4 style="margin:0 0 8px">Dirección (PDF)</h4>
          <div class="grid2">
            <div class="field">
              <span class="lbl">Código postal</span>
              <input class="input" type="text" name="direccion[cp]" maxlength="10" required>
            </div>
            <div class="field">
              <span class="lbl">Estado</span>
              <input class="input" type="text" name="direccion[estado]" maxlength="120">
            </div>
          </div>
          <div class="grid2">
            <div class="field">
              <span class="lbl">Ciudad / Delegación</span>
              <input class="input" type="text" name="direccion[ciudad]" maxlength="120">
            </div>
            <div class="field">
              <span class="lbl">Dirección</span>
              <input class="input" type="text" name="direccion[direccion]" maxlength="250">
            </div>
          </div>
        </div>

        <div class="card" style="padding:12px">
          <h4 style="margin:0 0 8px">Certificados (CSD & FIEL)</h4>
          <div class="grid2">
            <div>
              <div class="field">
                <span class="lbl">CSD (.cer)</span>
                <input class="input" type="file" id="csd_cer_file" accept=".cer" />
              </div>
              <div class="field">
                <span class="lbl">CSD (.key)</span>
                <input class="input" type="file" id="csd_key_file" accept=".key" />
              </div>
              <div class="field">
                <span class="lbl">Contraseña CSD</span>
                <input class="input" type="password" id="csd_password_input" />
              </div>
            </div>
            <div>
              <div class="field">
                <span class="lbl">FIEL (.cer)</span>
                <input class="input" type="file" id="fiel_cer_file" accept=".cer" />
              </div>
              <div class="field">
                <span class="lbl">FIEL (.key)</span>
                <input class="input" type="file" id="fiel_key_file" accept=".key" />
              </div>
              <div class="field">
                <span class="lbl">Contraseña FIEL</span>
                <input class="input" type="password" id="fiel_password_input" />
              </div>
            </div>
          </div>

          <div class="small" style="margin-top:6px">
            Los archivos se convierten a <strong>Base64 en tu navegador</strong> y se envían en JSON:
            <span class="pill">certificados.csd_cer</span>
            <span class="pill">certificados.csd_key</span>
            <span class="pill">certificados.csd_password</span>
            <span class="pill">certificados.fiel_cer</span>
            <span class="pill">certificados.fiel_key</span>
            <span class="pill">certificados.fiel_password</span>
          </div>

          {{-- inputs ocultos que se enviarán --}}
          <input type="hidden" name="certificados[csd_cer]" id="csd_cer_b64">
          <input type="hidden" name="certificados[csd_key]" id="csd_key_b64">
          <input type="hidden" name="certificados[csd_password]" id="csd_password_b64">
          <input type="hidden" name="certificados[fiel_cer]" id="fiel_cer_b64">
          <input type="hidden" name="certificados[fiel_key]" id="fiel_key_b64">
          <input type="hidden" name="certificados[fiel_password]" id="fiel_password_b64">
        </div>

        <div class="card" style="padding:12px">
          <h4 style="margin:0 0 8px">Series iniciales</h4>
          <div class="kvs" id="seriesBox">
            <div class="field">
              <span class="lbl">Tipo</span>
              <input class="input" type="text" placeholder="ingreso/egreso/pago/nomina" value="ingreso">
            </div>
            <div class="field">
              <span class="lbl">Serie</span>
              <input class="input" type="text" placeholder="IN" value="IN">
            </div>
            <div class="field">
              <span class="lbl">Folio</span>
              <input class="input" type="number" placeholder="1" value="1" min="0">
            </div>
          </div>
          <div class="tools" style="margin-top:8px">
            <button class="btn" type="button" onclick="addSerie()">+ Agregar serie</button>
          </div>
          {{-- payload oculto para series en JSON --}}
          <input type="hidden" name="series_json" id="series_json">
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px">
        <button type="button" class="btn ghost" onclick="closeEmisorModal()">Cancelar</button>
        <button type="submit" class="btn primary {{ $canStore ? '' : 'disabled' }}"
                @if(!$canStore) title="Falta definir route: cliente.emisores.store" @endif>
          Guardar emisor
        </button>
      </div>
    </form>
  </div>
</dialog>

{{-- ===========================================================
   MODAL: Importación masiva (PRO)
=========================================================== --}}
@if($isPro)
<dialog class="modal" id="importModal">
  <header>
    <strong>Importar emisores (PRO)</strong>
    <button class="btn ghost" onclick="closeImportModal()">✕</button>
  </header>
  <div class="body">
    @php $canImport = \Illuminate\Support\Facades\Route::has('cliente.emisores.import'); @endphp
    <form method="POST" @if($canImport) action="{{ route('cliente.emisores.import') }}" @endif enctype="multipart/form-data">
      @csrf
      <div class="field">
        <span class="lbl">Archivo</span>
        <input class="input" type="file" name="file" accept=".csv,.json" required>
        <div class="hint">Acepta CSV o JSON. Para certificados, usa columnas/base64 con nombres <code>csd_key</code>, <code>csd_cer</code>, <code>csd_password</code>, <code>fiel_key</code>, <code>fiel_cer</code>, <code>fiel_password</code>.</div>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px">
        <button type="button" class="btn ghost" onclick="closeImportModal()">Cancelar</button>
        <button type="submit" class="btn primary {{ $canImport ? '' : 'disabled' }}"
                @if(!$canImport) title="Falta definir route: cliente.emisores.import" @endif>
          Importar
        </button>
      </div>
    </form>
  </div>
</dialog>
@endif
@endsection

@push('scripts')
<script>
  // ===== Modal helpers
  const emisorModal = document.getElementById('emisorModal');
  function openEmisorModal(){ if(emisorModal) emisorModal.showModal(); }
  function closeEmisorModal(){ if(emisorModal) emisorModal.close(); }

  const importModal = document.getElementById('importModal');
  function openImportModal(){ if(importModal) importModal.showModal(); }
  function closeImportModal(){ if(importModal) importModal.close(); }

  // ===== Series payload
  const seriesBox  = document.getElementById('seriesBox');
  const seriesJson = document.getElementById('series_json');

  function collectSeries(){
    if(!seriesBox || !seriesJson) return;
    const rows = seriesBox.querySelectorAll('.field .input');
    const series = [];
    for(let i=0; i<rows.length; i+=3){
      const tipo = rows[i]?.value?.trim();
      const serie= rows[i+1]?.value?.trim();
      const folio= parseInt(rows[i+2]?.value||'0',10);
      if(tipo || serie || folio){
        series.push({tipo: tipo||'ingreso', serie: serie||'IN', folio: isNaN(folio)?1:folio});
      }
    }
    seriesJson.value = JSON.stringify(series);
  }
  function addSerie(){
    if(!seriesBox) return;
    const tpl = `
      <div class="field">
        <span class="lbl">Tipo</span>
        <input class="input" type="text" placeholder="ingreso/egreso/pago/nomina" value="">
      </div>
      <div class="field">
        <span class="lbl">Serie</span>
        <input class="input" type="text" placeholder="IN" value="">
      </div>
      <div class="field">
        <span class="lbl">Folio</span>
        <input class="input" type="number" placeholder="1" value="1" min="0">
      </div>
    `;
    seriesBox.insertAdjacentHTML('beforeend', tpl);
  }

  // ===== Base64 helpers (archivos cer/key)
  async function fileToBase64(file){
    if(!file) return null;
    return new Promise((resolve,reject)=>{
      const reader = new FileReader();
      reader.onload = () => resolve((reader.result||'').toString().split(',').pop());
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  const csdCerFile = document.getElementById('csd_cer_file');
  const csdKeyFile = document.getElementById('csd_key_file');
  const fielCerFile= document.getElementById('fiel_cer_file');
  const fielKeyFile= document.getElementById('fiel_key_file');

  const csdCerB64  = document.getElementById('csd_cer_b64');
  const csdKeyB64  = document.getElementById('csd_key_b64');
  const fielCerB64 = document.getElementById('fiel_cer_b64');
  const fielKeyB64 = document.getElementById('fiel_key_b64');

  const csdPassB64 = document.getElementById('csd_password_b64');
  const fielPassB64= document.getElementById('fiel_password_b64');

  const csdPassInp = document.getElementById('csd_password_input');
  const fielPassInp= document.getElementById('fiel_password_input');

  async function syncCertsToHidden(){
    if(csdCerFile?.files?.[0]) csdCerB64.value  = await fileToBase64(csdCerFile.files[0]);
    if(csdKeyFile?.files?.[0]) csdKeyB64.value  = await fileToBase64(csdKeyFile.files[0]);
    if(fielCerFile?.files?.[0])fielCerB64.value = await fileToBase64(fielCerFile.files[0]);
    if(fielKeyFile?.files?.[0])fielKeyB64.value = await fileToBase64(fielKeyFile.files[0]);

    if(csdPassInp)  csdPassB64.value  = csdPassInp.value || '';
    if(fielPassInp) fielPassB64.value = fielPassInp.value || '';
  }

  csdCerFile?.addEventListener('change', syncCertsToHidden);
  csdKeyFile?.addEventListener('change', syncCertsToHidden);
  fielCerFile?.addEventListener('change', syncCertsToHidden);
  fielKeyFile?.addEventListener('change', syncCertsToHidden);
  csdPassInp?.addEventListener('input',  syncCertsToHidden);
  fielPassInp?.addEventListener('input', syncCertsToHidden);

  // Al enviar, empaquetamos series y certificados
  const emisorForm = document.getElementById('emisorForm');
  emisorForm?.addEventListener('submit', async (e)=>{
    await syncCertsToHidden();
    collectSeries();
    // Si el botón está disabled por falta de route, prevenimos
    const btn = emisorForm.querySelector('button[type="submit"]');
    if(btn && btn.classList.contains('disabled')){
      e.preventDefault();
      alert('Falta definir la ruta backend para guardar emisores: cliente.emisores.store');
      return false;
    }
  });
</script>
@endpush
