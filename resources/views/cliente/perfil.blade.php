{{-- resources/views/cliente/perfil.blade.php (v7 · Free vs Pro claramente diferenciados) --}}
@extends('layouts.cliente')
@section('title','Perfil · Pactopia360')

@push('styles')
<style>
/* ============================================================
   PACTOPIA360 · Perfil (FREE / PRO)
   ============================================================ */
.perfil{
  font-family:'Poppins',system-ui,sans-serif;
  --rose:#E11D48;--rose-dark:#BE123C;--mut:#6b7280;
  --card:#fff;--border:#f3d5dc;--bg:#fff8f9;
  display:grid;gap:20px;
  padding:24px 20px 32px;
  color:#0f172a;
  max-width:1200px;
  margin:0 auto;
}

html[data-theme="dark"] .perfil{
  --card:#0b1220;--border:#2b2f36;--bg:#0e172a;--mut:#a5adbb;color:#e5e7eb;
}

/* Header */
.page-header{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;padding:20px 24px;border-radius:16px;
  box-shadow:0 8px 22px rgba(225,29,72,.25);
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
}
.page-title{margin:0;font-weight:900;font-size:22px;}
.page-header .muted{color:rgba(255,255,255,.85);font-size:13px;}
.badge{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;
  background:rgba(255,255,255,.9);color:#BE123C;
}
.badge.pro{background:#ecfdf5;color:#047857;}
.badge.free{background:#fef3c7;color:#92400e;}

/* KPIs PRO */
.kpi-grid{
  display:grid;
  grid-template-columns:repeat(5,minmax(0,1fr));
  gap:10px;
  margin-top:14px;
}
@media(max-width:1100px){
  .kpi-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
}
@media(max-width:800px){
  .kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
}
.kpi-card{
  background:var(--card);
  border-radius:14px;
  padding:10px 12px;
  border:1px solid rgba(255,255,255,.55);
  box-shadow:0 6px 16px rgba(15,23,42,.14);
}
.kpi-label{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.06em;
  color:rgba(15,23,42,.8);
}
.kpi-value{
  font-weight:900;
  font-size:17px;
  margin-top:2px;
}
.kpi-sub{
  font-size:11px;
  opacity:.9;
}

/* Avatar */
.avatar{
  width:68px;height:68px;border-radius:50%;
  background:linear-gradient(145deg,#E11D48,#BE123C);
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-size:28px;font-weight:900;box-shadow:0 6px 16px rgba(225,29,72,.25);
  flex-shrink:0;user-select:none;
}

/* Cards */
.card{
  background:var(--card);border:1px solid var(--border);
  border-radius:18px;padding:20px 22px;
  box-shadow:0 8px 28px rgba(225,29,72,.08);
}
.card h3{margin:0 0 8px;font-size:16px;font-weight:900;color:#E11D48;}
.card h4{margin:0 0 8px;font-size:14px;font-weight:800;color:#BE123C;}
.muted{color:var(--mut);}
.grid{display:grid;gap:16px;}
@media(min-width:1000px){.grid{grid-template-columns:1fr 1fr;}}
.row{display:grid;grid-template-columns:160px 1fr;gap:8px;padding:6px 0;
  border-bottom:1px solid var(--border);font-size:14px;}
.row strong{color:var(--mut);}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:10px 14px;border-radius:12px;font-weight:800;font-size:13px;
  border:1px solid var(--border);cursor:pointer;text-decoration:none;
  background:#fff;transition:.2s all ease;
}
.btn.primary{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;box-shadow:0 6px 18px rgba(225,29,72,.25);border:0;
}
.btn.primary:hover{filter:brightness(.96);}
.btn.danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.btn.ghost{background:transparent;}
.btn.disabled{opacity:.55;pointer-events:none;}
.tools{display:flex;flex-wrap:wrap;gap:8px;}
.small{font-size:12px;color:var(--mut);}
.table{width:100%;border-collapse:collapse;font-size:13px;}
.table th,.table td{padding:8px 10px;border-bottom:1px solid var(--border);}
.table th{color:#BE123C;text-align:left;font-weight:800;}
.table tr:hover td{background:#fffafc;}

/* Modal */
dialog.modal{
  border:1px solid var(--border);border-radius:16px;padding:0;
  width:96vw;max-width:960px;background:var(--card);
  box-shadow:0 8px 24px rgba(225,29,72,.12);
}
.modal header{
  display:flex;justify-content:space-between;align-items:center;
  padding:14px 18px;border-bottom:1px solid var(--border);
  font-weight:800;color:#BE123C;
}
.modal .body{padding:18px;max-height:70vh;overflow:auto;}
.modal .grid2{display:grid;gap:12px;}
@media(min-width:900px){.modal .grid2{grid-template-columns:1fr 1fr;}}
.field{display:grid;gap:4px;margin-bottom:10px;}
.lbl{font-weight:700;font-size:12px;color:var(--mut);}
.input,.select,.number,textarea{
  width:100%;border:1px solid var(--border);border-radius:12px;
  padding:10px 12px;font-weight:700;color:var(--text);
  background:color-mix(in oklab,var(--card)96%,transparent);
}
.hint{font-size:12px;color:var(--mut);}
.pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;
  border:1px dashed var(--border);border-radius:999px;font-size:12px;}
.kvs{display:grid;gap:6px;grid-template-columns:1fr 1fr 1fr;}
.kvs .input{padding:8px 10px;}
</style>
@endpush

@section('content')
@php
  // Datos desde el controlador
  $user              = $user ?? auth('web')->user();
  $cuenta            = $cuenta ?? ($user?->cuenta);
  $plan              = strtoupper($plan ?? ($cuenta->plan_actual ?? 'FREE'));
  $isPro             = $isPro ?? ($plan === 'PRO');
  $emisores          = $emisores          ?? collect();
  $kpis              = $kpis              ?? [];
  $facturasResumen   = $facturasResumen   ?? [];
  $facturasRecientes = $facturasRecientes ?? collect();
  $estadoCuenta      = $estadoCuenta      ?? ['saldo'=>0,'movimientos_recientes'=>collect()];
  $compras           = $compras           ?? collect();

  // Inicial avatar
  $ini = strtoupper(mb_substr(trim((string)($user?->nombre ?? $user?->name ?? 'U')),0,1));

  $hasBillingRoute   = \Illuminate\Support\Facades\Route::has('cliente.billing.statement');
  $hasUpgradeRoute   = \Illuminate\Support\Facades\Route::has('cliente.billing.plans');
@endphp

<div class="perfil">
  {{-- Header --}}
  <div class="page-header">
    <div style="flex:1 1 auto;min-width:220px;">
      <h1 class="page-title">Perfil de la cuenta</h1>

      @if($isPro)
        <div class="muted">
          Estás usando <strong>Pactopia360 PRO</strong>. Acceso completo a emisores, KPIs y compras.
        </div>
      @else
        <div class="muted">
          Estás en la versión <strong>FREE</strong>. Algunas funciones aparecen bloqueadas como “Solo PRO”.
        </div>
      @endif

      {{-- KPIs solo PRO --}}
      @if($isPro && !empty($kpis))
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Plan</div>
            <div class="kpi-value">{{ $kpis['plan'] ?? $plan }}</div>
            <div class="kpi-sub">Licencia actual</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Timbres disponibles</div>
            <div class="kpi-value">{{ number_format((int)($kpis['timbres_disponibles'] ?? 0)) }}</div>
            <div class="kpi-sub">En tu cuenta</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Emisores</div>
            <div class="kpi-value">{{ (int)($kpis['emisores'] ?? 0) }}</div>
            <div class="kpi-sub">Registrados</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">CFDI este mes</div>
            <div class="kpi-value">{{ (int)($kpis['facturas_mes'] ?? 0) }}</div>
            <div class="kpi-sub">Emitidos</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Saldo</div>
            <div class="kpi-value">
              ${{ number_format((float)($kpis['saldo'] ?? 0),2) }}
            </div>
            <div class="kpi-sub">Cuenta Pactopia360</div>
          </div>
        </div>
      @endif
    </div>

    <div class="tools">
      <span class="badge {{ $isPro ? 'pro' : 'free' }}">
        Plan: {{ $plan }}
      </span>
      @if(!$isPro)
        <a class="btn primary {{ $hasBillingRoute || $hasUpgradeRoute ? '' : 'disabled' }}"
           @if($hasUpgradeRoute)
             href="{{ route('cliente.billing.plans') }}"
           @elseif($hasBillingRoute)
             href="{{ route('cliente.billing.statement') }}"
           @endif>
          Activar PRO
        </a>
      @endif
    </div>
  </div>

  {{-- Usuario + Organización --}}
  <div class="grid">
    <div class="card">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;position:relative;">
        {{-- Avatar --}}
        <div class="avatar" id="avatarBox" title="Cambiar foto"
             style="cursor:pointer;position:relative;">
          @if($user?->avatar_url)
            <img src="{{ $user->avatar_url }}" alt="Avatar"
                 style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
          @else
            {{ $ini }}
          @endif
          <div id="avatarOverlay"
               style="position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.35);
               display:none;align-items:center;justify-content:center;color:#fff;
               font-size:12px;font-weight:700;">
            Subir
          </div>
        </div>
        <div>
          <h3 style="margin:0;color:#E11D48;">{{ $user?->nombre ?? $user?->name ?? 'Usuario' }}</h3>
          <div class="small">{{ $user?->email ?? 'Sin correo' }}</div>
        </div>
      </div>

      <div class="muted small" style="margin-top:6px;">
        Tu perfil contiene la información principal de acceso a la plataforma.
      </div>
    </div>

    <div class="card">
      <h3>Organización</h3>
      <div class="row">
        <strong>Razón social</strong>
        <div>{{ $cuenta?->razon_social ?? $cuenta?->nombre_fiscal ?? '—' }}</div>
      </div>
      <div class="row">
        <strong>Plan</strong>
        <div>{{ $plan }}</div>
      </div>
      <div class="row">
        <strong>Timbres</strong>
        <div>{{ number_format((int)($cuenta->timbres_disponibles ?? 0)) }}</div>
      </div>
    </div>
  </div>

  {{-- Emisores --}}
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div>
        <h3>Emisores de la cuenta</h3>
        @if($isPro)
          <div class="small">
            Administra todos tus emisores. Puedes usarlos al crear CFDI y en automatizaciones.
          </div>
        @else
          <div class="small">
            En FREE puedes crear emisores manualmente. La importación masiva está disponible en PRO.
          </div>
        @endif
      </div>
      <div class="tools">
        <button class="btn" type="button" onclick="openEmisorModal()">+ Nuevo emisor</button>
        @if($isPro)
          <button class="btn" type="button" onclick="openImportModal()">Importar masivo</button>
        @else
          <button class="btn disabled" title="Solo disponible en PRO">Importar masivo · Solo PRO</button>
        @endif
        @php $canNewCfdi = \Illuminate\Support\Facades\Route::has('cliente.facturacion.nuevo'); @endphp
        <a class="btn primary {{ $canNewCfdi ? '' : 'disabled' }}"
           @if($canNewCfdi) href="{{ route('cliente.facturacion.nuevo') }}" @endif>
          Crear CFDI
        </a>
      </div>
    </div>

    <div style="margin-top:10px;">
      @if($emisores->isEmpty())
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
            <th style="width:240px">Acciones</th>
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
                  @php $canEdit = Route::has('cliente.emisores.edit'); @endphp
                  <a class="btn {{ $canEdit ? '' : 'disabled' }}"
                     @if($canEdit) href="{{ route('cliente.emisores.edit',$e->id) }}" @endif>
                    Editar
                  </a>
                  @php $canNewCfdi = Route::has('cliente.facturacion.nuevo'); @endphp
                  <a class="btn {{ $canNewCfdi ? '' : 'disabled' }}"
                     @if($canNewCfdi) href="{{ route('cliente.facturacion.nuevo',['emisor_id'=>$e->id]) }}" @endif>
                    Usar al facturar
                  </a>
                  @php $canDel = Route::has('cliente.emisores.destroy'); @endphp
                  <form method="POST"
                        @if($canDel) action="{{ route('cliente.emisores.destroy',$e->id) }}" @endif
                        onsubmit="return confirm('¿Eliminar emisor {{ $e->rfc }}?')"
                        style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn danger {{ $canDel ? '' : 'disabled' }}">
                      Eliminar
                    </button>
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

  {{-- Compras y Pagos --}}
  <div class="grid">
    {{-- Compras --}}
    <div class="card">
      <h3>Compras</h3>

      @if($isPro)
        <div class="small muted">
          Historial de compras de timbres, planes y addons de tu cuenta.
        </div>

        @if($compras->isEmpty())
          <div class="muted small" style="margin-top:8px;">
            Aún no registras compras en tu cuenta.
          </div>
        @else
          <div style="margin-top:10px;overflow-x:auto;">
            <table class="table">
              <thead>
              <tr>
                <th>Fecha</th>
                <th>Descripción</th>
                <th>Folio</th>
                <th>Moneda</th>
                <th>Total</th>
                <th>Estatus</th>
              </tr>
              </thead>
              <tbody>
              @foreach($compras as $o)
                <tr>
                  <td>
                    @if($o->created_at instanceof \Carbon\Carbon)
                      {{ $o->created_at->format('d/m/Y H:i') }}
                    @else
                      {{ $o->created_at ?? '—' }}
                    @endif
                  </td>
                  <td>{{ $o->descripcion ?? '—' }}</td>
                  <td>{{ $o->folio ?? '—' }}</td>
                  <td>{{ $o->moneda ?? 'MXN' }}</td>
                  <td>${{ number_format((float)($o->total ?? 0),2) }}</td>
                  <td>{{ strtoupper($o->status ?? $o->estatus ?? '—') }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @endif

        <div class="tools" style="margin-top:10px;">
          <a class="btn {{ $hasBillingRoute ? '' : 'disabled' }}"
             @if($hasBillingRoute) href="{{ route('cliente.billing.statement') }}" @endif>
            Ver estado de cuenta
          </a>
        </div>
      @else
        <div class="muted small">
          En FREE no se muestra el historial detallado de compras.
          Al activar PRO podrás ver aquí tus órdenes, facturas y estado de cuenta.
        </div>
        <div class="tools" style="margin-top:12px;">
          <a class="btn primary {{ $hasUpgradeRoute ? '' : 'disabled' }}"
             @if($hasUpgradeRoute) href="{{ route('cliente.billing.plans') }}" @endif>
            Ver planes PRO
          </a>
        </div>
      @endif
    </div>

    {{-- Pagos / Estado de cuenta --}}
    <div class="card">
      <h3>Pagos y saldo</h3>

      @if($isPro)
        <div class="row">
          <strong>Saldo actual</strong>
          <div>${{ number_format((float)($estadoCuenta['saldo'] ?? 0),2) }}</div>
        </div>

        <h4 style="margin-top:14px;">Movimientos recientes</h4>
        @php
          $movs = $estadoCuenta['movimientos_recientes'] ?? collect();
        @endphp

        @if(!$movs || $movs->isEmpty())
          <div class="muted small">No hay movimientos recientes en tu cuenta.</div>
        @else
          <div style="margin-top:6px;max-height:210px;overflow:auto;">
            <table class="table">
              <thead>
              <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Tipo</th>
                <th>Monto</th>
                @if(isset($movs[0]) && property_exists($movs[0],'saldo'))
                  <th>Saldo</th>
                @endif
              </tr>
              </thead>
              <tbody>
              @foreach($movs as $m)
                <tr>
                  <td>{{ $m->fecha ?? '—' }}</td>
                  <td>{{ $m->concepto ?? '—' }}</td>
                  <td>{{ strtoupper($m->tipo ?? '—') }}</td>
                  <td>${{ number_format((float)($m->monto ?? 0),2) }}</td>
                  @if(property_exists($m,'saldo'))
                    <td>${{ number_format((float)($m->saldo ?? 0),2) }}</td>
                  @endif
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @endif

        <div class="muted small" style="margin-top:10px;">
          Próximamente podrás gestionar métodos de pago y facturas desde aquí.
        </div>
      @else
        <div class="muted small">
          El detalle de saldo y movimientos forma parte de las herramientas PRO.
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ===========================================================
   MODAL: Subir foto de perfil
=========================================================== --}}
<dialog class="modal" id="avatarModal">
  <header>
    <strong>Actualizar foto de perfil</strong>
    <button class="btn ghost" type="button" onclick="closeAvatarModal()">✕</button>
  </header>
  <div class="body">
    @php $canUpload = \Illuminate\Support\Facades\Route::has('cliente.perfil.avatar'); @endphp
    <form id="avatarForm" method="POST" enctype="multipart/form-data"
          @if($canUpload) action="{{ route('cliente.perfil.avatar') }}" @endif>
      @csrf
      <div class="field">
        <span class="lbl">Seleccionar imagen</span>
        <input class="input" type="file" name="avatar" id="avatarInput" accept="image/*" required>
        <div class="hint">
          Formatos aceptados: JPG, PNG, WEBP. Tamaño recomendado 400×400 px.
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
        <button type="button" class="btn ghost" onclick="closeAvatarModal()">Cancelar</button>
        <button type="submit" class="btn primary {{ $canUpload ? '' : 'disabled' }}"
                @if(!$canUpload) title="Falta definir route: cliente.perfil.avatar" @endif>
          Subir foto
        </button>
      </div>
    </form>
  </div>
</dialog>

<script>
  // ===== Avatar interaction =====
  const avatarBox     = document.getElementById('avatarBox');
  const avatarOverlay = document.getElementById('avatarOverlay');
  const avatarModal   = document.getElementById('avatarModal');

  if (avatarBox) {
    avatarBox.addEventListener('mouseenter', ()=>avatarOverlay.style.display='flex');
    avatarBox.addEventListener('mouseleave', ()=>avatarOverlay.style.display='none');
    avatarBox.addEventListener('click', ()=>openAvatarModal());
  }

  function openAvatarModal(){ avatarModal && avatarModal.showModal(); }
  function closeAvatarModal(){ avatarModal && avatarModal.close(); }

  // ===== Preview instantáneo antes de subir =====
  const avatarInput = document.getElementById('avatarInput');
  avatarInput && avatarInput.addEventListener('change', (e)=>{
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ()=>{
      const img = document.createElement('img');
      img.src = reader.result;
      img.style.cssText = "width:100%;height:100%;border-radius:50%;object-fit:cover;";
      avatarBox.innerHTML = '';
      avatarBox.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
</script>

{{-- Modales adicionales del perfil (emisores, etc.) --}}
@include('cliente._partials.perfil_modales')
@endsection
