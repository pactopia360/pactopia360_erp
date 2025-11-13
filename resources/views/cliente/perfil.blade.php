{{-- resources/views/cliente/perfil.blade.php (v4 con avatar dinámico Pactopia360) --}}
@extends('layouts.client')
@section('title','Perfil · Pactopia360')

@push('styles')
<style>
/* ============================================================
   PACTOPIA360 · Perfil (visual v4 + avatar dinámico)
   ============================================================ */
.perfil{
  font-family:'Poppins',system-ui,sans-serif;
  --rose:#E11D48;--rose-dark:#BE123C;--mut:#6b7280;
  --card:#fff;--border:#f3d5dc;--bg:#fff8f9;
  display:grid;gap:20px;padding:20px;color:#0f172a;
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
.badge{display:inline-flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;}
.badge.pro{background:#ecfdf5;color:#047857;}

/* Avatar circular */
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
.table{width:100%;border-collapse:collapse;font-size:14px;}
.table th,.table td{padding:10px;border-bottom:1px solid var(--border);}
.table th{color:#BE123C;text-align:left;font-weight:800;}
.table tr:hover td{background:#fffafc;}

/* Modal (igual que v4) */
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
  $user   = auth('web')->user();
  $cuenta = $user?->cuenta;
  $plan   = strtoupper($cuenta->plan_actual ?? 'FREE');
  $isPro  = ($plan === 'PRO');
  $emisores = $emisores ?? collect();

  // Inicial del avatar
  $ini = strtoupper(mb_substr(trim((string)($user?->nombre ?? $user?->name ?? 'U')),0,1));
@endphp

<div class="perfil">
  {{-- Header --}}
  <div class="page-header">
    <div>
      <h1 class="page-title">Perfil de la cuenta</h1>
      <div class="muted">Datos y administración de tu usuario, organización y emisores.</div>
    </div>
    <div class="tools">
      <span class="badge {{ $isPro?'pro':'' }}">Plan: {{ $plan }}</span>
    </div>
  </div>

  {{-- Usuario + Organización --}}
  <div class="grid">
    <div class="card">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;position:relative;">
        {{-- Avatar interactivo --}}
        <div class="avatar" id="avatarBox" title="Cambiar foto" style="cursor:pointer;position:relative;">
          @if($user?->avatar_url)
            <img src="{{ $user->avatar_url }}" alt="Avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
          @else
            {{ $ini }}
          @endif
          <div id="avatarOverlay" style="position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.35);
              display:none;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;">
            Subir
          </div>
        </div>
        <div>
          <h3 style="margin:0;color:#E11D48;">{{ $user?->nombre ?? $user?->name ?? 'Usuario' }}</h3>
          <div class="small">{{ $user?->email ?? 'Sin correo' }}</div>
        </div>
      </div>

      <div class="muted small" style="margin-top:6px;">
        Tu perfil de usuario contiene la información principal de acceso a la plataforma.
      </div>
    </div>

    <div class="card">
      <h3>Organización</h3>
      <div class="row"><strong>Razón social</strong><div>{{ $cuenta?->razon_social ?? $cuenta?->nombre_fiscal ?? '—' }}</div></div>
      <div class="row"><strong>Plan</strong><div>{{ $plan }}</div></div>
      <div class="row"><strong>Timbres</strong><div>{{ number_format((int)($cuenta->timbres_disponibles ?? 0)) }}</div></div>
    </div>
  </div>

  {{-- Emisores --}}
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div>
        <h3>Emisores de la cuenta</h3>
        <div class="small">Administra todos tus emisores (ilimitados en FREE/PRO). Puedes usarlos al crear CFDI.</div>
      </div>
      <div class="tools">
        <button class="btn" type="button" onclick="openEmisorModal()">+ Nuevo emisor</button>
        @if($isPro)
          <button class="btn" type="button" onclick="openImportModal()">Importar masivo</button>
        @else
          <button class="btn disabled" title="Solo en PRO">Importar masivo</button>
        @endif
        <a class="btn primary" href="{{ route('cliente.facturacion.nuevo') }}">Crear CFDI</a>
      </div>
    </div>

    <div style="margin-top:10px;">
      @if($emisores->isEmpty())
        <div class="muted">Aún no tienes emisores. Crea el primero con “Nuevo emisor”.</div>
      @else
        <table class="table">
          <thead>
            <tr><th>RFC</th><th>Razón social</th><th>Nombre comercial</th><th>Email</th><th>Régimen</th><th>Grupo</th><th style="width:240px">Acciones</th></tr>
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
                  <a class="btn {{ $canEdit?'':'disabled' }}" @if($canEdit) href="{{ route('cliente.emisores.edit',$e->id) }}" @endif>Editar</a>
                  <a class="btn" href="{{ route('cliente.facturacion.nuevo',['emisor_id'=>$e->id]) }}">Usar al facturar</a>
                  @php $canDel = Route::has('cliente.emisores.destroy'); @endphp
                  <form method="POST" @if($canDel) action="{{ route('cliente.emisores.destroy',$e->id) }}" @endif
                        onsubmit="return confirm('¿Eliminar emisor {{ $e->rfc }}?')" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn danger {{ $canDel?'':'disabled' }}">Eliminar</button>
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

  {{-- Placeholders --}}
  <div class="grid">
    <div class="card">
      <h3>Compras</h3>
      <div class="muted small">Historial de compras (timbres, planes, addons). Próximamente filtros y descargas.</div>
      @php $hasBilling = Route::has('cliente.billing.statement'); @endphp
      <div class="tools" style="margin-top:10px;">
        <a class="btn {{ $hasBilling?'':'disabled' }}" @if($hasBilling) href="{{ route('cliente.billing.statement') }}" @endif>Ver estado de cuenta</a>
      </div>
    </div>
    <div class="card">
      <h3>Pagos</h3>
      <div class="muted small">Métodos de pago, facturas y recibos. (Sección expandible próximamente)</div>
    </div>
  </div>
</div>

{{-- ===========================================================
   MODAL: Subir foto de perfil
=========================================================== --}}
<dialog class="modal" id="avatarModal">
  <header>
    <strong>Actualizar foto de perfil</strong>
    <button class="btn ghost" onclick="closeAvatarModal()">✕</button>
  </header>
  <div class="body">
    @php $canUpload = \Illuminate\Support\Facades\Route::has('cliente.perfil.avatar'); @endphp
    <form id="avatarForm" method="POST" enctype="multipart/form-data"
          @if($canUpload) action="{{ route('cliente.perfil.avatar') }}" @endif>
      @csrf
      <div class="field">
        <span class="lbl">Seleccionar imagen</span>
        <input class="input" type="file" name="avatar" id="avatarInput" accept="image/*" required>
        <div class="hint">Formatos aceptados: JPG, PNG, WEBP. Tamaño recomendado 400×400 px.</div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
        <button type="button" class="btn ghost" onclick="closeAvatarModal()">Cancelar</button>
        <button type="submit" class="btn primary {{ $canUpload?'':'disabled' }}"
                @if(!$canUpload) title="Falta definir route: cliente.perfil.avatar" @endif>
          Subir foto
        </button>
      </div>
    </form>
  </div>
</dialog>


<script>
  // ===== Avatar interaction =====
  const avatarBox = document.getElementById('avatarBox');
  const avatarOverlay = document.getElementById('avatarOverlay');
  const avatarModal = document.getElementById('avatarModal');

  if(avatarBox){
    avatarBox.addEventListener('mouseenter',()=>avatarOverlay.style.display='flex');
    avatarBox.addEventListener('mouseleave',()=>avatarOverlay.style.display='none');
    avatarBox.addEventListener('click',()=>openAvatarModal());
  }

  function openAvatarModal(){ avatarModal?.showModal(); }
  function closeAvatarModal(){ avatarModal?.close(); }

  // ===== Preview instantáneo antes de subir =====
  const avatarInput = document.getElementById('avatarInput');
  avatarInput?.addEventListener('change', (e)=>{
    const file = e.target.files?.[0];
    if(!file) return;
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


{{-- Se mantienen modales y scripts intactos --}}
@include('cliente._partials.perfil_modales')
@endsection
