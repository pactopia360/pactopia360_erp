{{-- resources/views/cliente/mi_cuenta/contract_show.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Contrato · Pactopia360')
@section('pageClass', 'page-mi-cuenta')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=2.0">
  <style>
    .ct-wrap{ display:grid; gap:16px; padding: 6px 0 24px; }
    .ct-doc{
      padding:18px 18px;
      color: var(--v-ink);
      font-size:13px;
      line-height:1.55;
    }
    .ct-doc h2{ margin:0 0 8px; font-size:16px; font-weight:900; }
    .ct-doc h3{ margin:18px 0 6px; font-size:13px; font-weight:900; }
    .ct-doc p{ margin:0 0 10px; color: color-mix(in oklab, var(--v-ink) 88%, transparent); }
    .ct-sep{ height:1px; background: var(--v-bd); margin:14px 0; }
    .sig-box{
      border:1px dashed var(--v-bd);
      border-radius:16px;
      background: linear-gradient(180deg, var(--v-soft), transparent);
      padding:14px;
      display:grid;
      gap:10px;
    }
    .sig-canvas{
      width:100%;
      height:180px;
      border-radius:12px;
      background: #fff;
      border:1px solid var(--v-bd);
      touch-action:none;
    }
    html.theme-dark .sig-canvas{ background: rgba(255,255,255,.92); }
    .sig-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
    .sig-left{ display:flex; gap:10px; flex-wrap:wrap; }
    .sig-note{ font-size:12px; color: var(--v-mut); font-weight:700; }
    .err{ color: var(--v-bad); font-weight:900; font-size:12px; }
  </style>
@endpush

@section('content')
@php
  $isSigned = (bool)($contract->status === 'signed' && $contract->signed_at);
  $razon = $cuenta?->razon_social ?? $cuenta?->nombre ?? '—';
  $accountId = (string)($cuenta?->id ?? '');
@endphp

<div class="ct-wrap">
  <section class="p360-card">
    <div class="p360-card-hd">
      <strong>{{ $contract->title }}</strong>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <span class="p360-pill">{{ $contract->version }}</span>
        <span class="p360-pill {{ $isSigned ? 'ok' : 'warn' }}">
          {{ $isSigned ? 'FIRMADO' : 'PENDIENTE' }}
        </span>
        <a class="p360-btn" href="{{ route('cliente.mi_cuenta') }}">Volver</a>
        @if($isSigned)
          <a class="p360-btn primary" href="{{ route('cliente.mi_cuenta.contratos.pdf', ['contract'=>$contract->id]) }}">Descargar PDF</a>
        @endif
      </div>
    </div>

    <div class="ct-doc">
      <h2>Contrato de aceptación de servicio</h2>
      <p><strong>Cuenta:</strong> {{ $razon }} <span style="color:var(--v-mut); font-weight:800;">({{ $accountId }})</span></p>
      <p><strong>Fecha de emisión:</strong> {{ $contract->created_at?->format('Y-m-d H:i') }}</p>

      <div class="ct-sep"></div>

      <h3>1. Objeto</h3>
      <p>El presente contrato regula la aceptación de los términos de uso del servicio Pactopia360 ERP, incluyendo el acceso a módulos, funcionalidades y soporte conforme al plan contratado.</p>

      <h3>2. Uso del servicio</h3>
      <p>La cuenta será utilizada para fines administrativos y contables del titular. El acceso y credenciales son responsabilidad del usuario y/o titular de la cuenta.</p>

      <h3>3. Pagos y estatus</h3>
      <p>El estatus del servicio (activa, bloqueada o falta de pago) se determina por la suscripción vigente y/o confirmación de pago conforme al flujo de cobro de la plataforma.</p>

      <h3>4. Aceptación</h3>
      <p>Al firmar, el titular acepta los términos y condiciones aplicables al uso de la plataforma, y consiente el registro de evidencia digital de aceptación (fecha/hora, usuario, hash y firma gráfica).</p>

      <div class="ct-sep"></div>

      <h3>Datos del firmante</h3>
      <p><strong>Nombre/Razón social:</strong> {{ $contract->signed_name ?? $razon }}</p>
      <p><strong>Correo:</strong> {{ $contract->signed_email ?? ($user?->email ?? '—') }}</p>

      <div class="ct-sep"></div>

      <h3>Firma</h3>

      @if($isSigned)
        <div class="sig-box">
          <div class="sig-note">
            Firmado el {{ $contract->signed_at->format('Y-m-d H:i') }} por {{ $contract->signed_email ?? '—' }}.
          </div>
          @if($contract->signature_png_base64)
            <img src="{{ $contract->signature_png_base64 }}" alt="Firma" style="max-width:420px; width:100%; border-radius:12px; border:1px solid var(--v-bd); background:#fff; padding:8px;">
          @endif
          <div class="sig-note">Hash: <span style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $contract->signature_hash ?? '—' }}</span></div>
        </div>
      @else
        @if($errors->any())
          <div class="err" style="margin-bottom:10px;">
            @foreach($errors->all() as $e) {{ $e }}<br>@endforeach
          </div>
        @endif

        <form id="signForm" method="POST" action="{{ route('cliente.mi_cuenta.contratos.sign', ['contract'=>$contract->id]) }}">
          @csrf

          <div class="sig-box">
            <div class="sig-note">
              Desliza para firmar. Esta firma quedará asociada a tu usuario y cuenta, con registro de fecha y hora.
            </div>

            <canvas id="sigCanvas" class="sig-canvas"></canvas>

            <input type="hidden" name="sigData" id="sigData">
            <input type="hidden" name="sigHash" id="sigHash">
            <input type="hidden" name="accept" value="1">

            <div class="sig-actions">
              <div class="sig-left">
                <button type="button" class="p360-btn" id="btnClear">Limpiar</button>
                <button type="button" class="p360-btn" id="btnPreview">Previsualizar</button>
              </div>
              <button type="submit" class="p360-btn primary" id="btnSign">Firmar contrato</button>
            </div>

            <label class="sig-note" style="display:flex; gap:10px; align-items:flex-start;">
              <input type="checkbox" id="chkAccept" checked style="margin-top:3px;">
              <span>Confirmo que he leído y acepto los términos. La firma es una evidencia digital de aceptación.</span>
            </label>

            <div id="sigErr" class="err" style="display:none;"></div>
          </div>
        </form>
      @endif
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const canvas = document.getElementById('sigCanvas');
  if(!canvas) return;

  const form = document.getElementById('signForm');
  const btnClear = document.getElementById('btnClear');
  const btnPreview = document.getElementById('btnPreview');
  const chkAccept = document.getElementById('chkAccept');
  const sigErr = document.getElementById('sigErr');
  const sigData = document.getElementById('sigData');
  const sigHash = document.getElementById('sigHash');

  const ctx = canvas.getContext('2d');
  let drawing = false;
  let hasInk = false;

  function resizeCanvas(){
    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.floor(rect.width * ratio);
    canvas.height = Math.floor(rect.height * ratio);
    ctx.setTransform(ratio,0,0,ratio,0,0);
    ctx.lineWidth = 2.4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0f172a';
  }

  function pointFromEvent(e){
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches && e.touches[0] ? e.touches[0] : null;
    const clientX = touch ? touch.clientX : e.clientX;
    const clientY = touch ? touch.clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
  }

  function start(e){
    e.preventDefault();
    drawing = true;
    const p = pointFromEvent(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
  }

  function move(e){
    if(!drawing) return;
    e.preventDefault();
    const p = pointFromEvent(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    hasInk = true;
  }

  function end(e){
    if(!drawing) return;
    e.preventDefault();
    drawing = false;
  }

  btnClear?.addEventListener('click', function(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    hasInk = false;
    sigErr.style.display = 'none';
  });

  btnPreview?.addEventListener('click', function(){
    if(!hasInk){
      sigErr.textContent = 'Primero dibuja tu firma.';
      sigErr.style.display = 'block';
      return;
    }
    const dataUrl = canvas.toDataURL('image/png');
    const w = window.open('', '_blank');
    w.document.write('<title>Firma</title><img style="max-width:100%;border:1px solid #eee;border-radius:12px" src="'+dataUrl+'">');
  });

  async function sha256(str){
    const buf = new TextEncoder().encode(str);
    const hashBuf = await crypto.subtle.digest('SHA-256', buf);
    const arr = Array.from(new Uint8Array(hashBuf));
    return arr.map(b => b.toString(16).padStart(2,'0')).join('');
  }

  form?.addEventListener('submit', async function(e){
    sigErr.style.display = 'none';

    if(!chkAccept.checked){
      e.preventDefault();
      sigErr.textContent = 'Debes confirmar la aceptación para firmar.';
      sigErr.style.display = 'block';
      return;
    }
    if(!hasInk){
      e.preventDefault();
      sigErr.textContent = 'Firma requerida. Dibuja tu firma antes de continuar.';
      sigErr.style.display = 'block';
      return;
    }

    const dataUrl = canvas.toDataURL('image/png');
    sigData.value = dataUrl;
    sigHash.value = await sha256(dataUrl);
  });

  // events
  window.addEventListener('resize', resizeCanvas, {passive:true});
  resizeCanvas();

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  canvas.addEventListener('mouseup', end);
  canvas.addEventListener('mouseleave', end);

  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove', move, {passive:false});
  canvas.addEventListener('touchend', end, {passive:false});
})();
</script>
@endpush
