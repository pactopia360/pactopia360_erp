{{-- resources/views/cliente/auth/verify_phone.blade.php (v2 visual Pactopia360) --}}
@php
  /** @var object $account (id, phone) */
  /** @var string $phone_masked */
  /** @var string $state "otp"|"phone" */
  $aid = $account->id ?? session('verify.account_id');
  $logoLight = asset('assets/client/logop360light.png');
  $logoDark  = asset('assets/client/logop360dark.png');
@endphp

@extends('layouts.guest')
@section('hide-brand','1')
@section('title','Verificar teléfono · Pactopia360')

@push('styles')
<style>
  body{
    font-family:'Poppins',system-ui,sans-serif;
    background:#fff8f9;
    color:#0f172a;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
  }
  body::before{
    content:"";
    position:fixed;inset:-20%;
    z-index:-1;
    background:
      radial-gradient(40% 50% at 18% 20%, rgba(255,91,126,.35), transparent 60%),
      radial-gradient(40% 50% at 82% 75%, rgba(190,18,60,.35), transparent 60%);
    filter:blur(100px);
    animation:float 14s ease-in-out infinite alternate;
  }
  @keyframes float{0%{transform:translate3d(-3%,-2%,0)}100%{transform:translate3d(5%,5%,0)}}

  .card{
    width:min(640px,94vw);
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:22px;
    padding:32px 26px 28px;
    box-shadow:0 22px 64px rgba(0,0,0,.18);
    position:relative;
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:23px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  .logo{display:grid;place-items:center;margin-bottom:14px}
  .logo img{height:44px;object-fit:contain;filter:drop-shadow(0 6px 10px rgba(0,0,0,.25));}

  .kicker{text-align:center;color:#6b7280;font-size:12px;}
  .h1{text-align:center;font-weight:900;font-size:22px;color:#E11D48;margin:8px 0;}
  .sub{text-align:center;color:#475569;font-size:13px;margin-bottom:12px;}

  .alert{margin-top:12px;padding:10px 12px;border-radius:12px;font-size:13px;text-align:center;}
  .alert-ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;}
  .alert-warn{background:#fefce8;color:#b45309;border:1px solid #fde68a;}
  .alert-err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}

  .grid{display:grid;gap:12px;margin-top:10px;}
  .label{color:#6b7280;font-size:12px;font-weight:700;}
  .control{
    border:1px solid #e5e7eb;background:#fff;color:#0f172a;
    border-radius:12px;padding:12px 14px;font-size:15px;
    outline:none;transition:.15s border-color,.15s box-shadow;
  }
  .control:focus{border-color:#E11D48;box-shadow:0 0 0 3px rgba(225,29,72,.25);}

  .btn{
    display:inline-flex;align-items:center;justify-content:center;
    width:100%;padding:14px;border-radius:12px;font-weight:900;font-size:15px;
    border:0;cursor:pointer;transition:filter .15s ease;
    background:linear-gradient(90deg,#E11D48,#BE123C);color:#fff;
    box-shadow:0 18px 40px rgba(225,29,72,.25);
  }
  .btn:hover{filter:brightness(.97)}

  .otp{display:flex;gap:10px;justify-content:center;margin:20px 0;}
  .otp input{
    width:58px;height:60px;text-align:center;font-weight:900;font-size:22px;
    border-radius:12px;border:1px solid #e5e7eb;background:#fff;color:#0f172a;outline:none;
    box-shadow:inset 0 -2px 0 rgba(0,0,0,.04);
  }
  .otp input:focus{
    border-color:#E11D48;
    box-shadow:0 0 0 4px rgba(225,29,72,.25),inset 0 -2px 0 rgba(0,0,0,.04);
  }

  .resend{
    appearance:none;background:transparent;border:0;cursor:pointer;
    color:#E11D48;font-weight:700;font-size:12px;text-decoration:none;
    border-bottom:1px dashed currentColor;padding:0 0 2px;
  }
  .resend:hover{opacity:.9}

  .helper{text-align:center;color:#6b7280;font-size:12px;margin-top:12px;}
  .helper a{color:#E11D48;text-decoration:none;font-weight:800;}
  .helper a:hover{text-decoration:underline;}
</style>
@endpush

@section('content')
<div class="w-full grid place-items-center py-8">
  <div class="card" role="region" aria-labelledby="vh1">

    <div class="logo">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="{{ $logoDark }}">
        <img id="vhLogo" src="{{ $logoLight }}" alt="Pactopia360">
      </picture>
    </div>

    <div class="kicker">Verificación de identidad</div>
    <h1 id="vh1" class="h1">Seguridad de cuenta</h1>
    <div class="sub">Verificación en dos pasos (WhatsApp / SMS)</div>

    {{-- Alertas --}}
    @if (session('ok'))     <div class="alert alert-ok">{{ session('ok') }}</div> @endif
    @if (session('warning'))<div class="alert alert-warn">{{ session('warning') }}</div> @endif
    @if ($errors->any())
      <div class="alert alert-err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    {{-- Estado: Captura de teléfono --}}
    @if ($state === 'phone')
      <form method="POST" action="{{ route('cliente.verify.phone.update') }}" class="grid">
        @csrf
        <input type="hidden" name="account_id" value="{{ $aid }}">
        <label class="grid gap-1">
          <span class="label">Prefijo de país</span>
          <input class="control" name="country_code" value="52" maxlength="5" required>
        </label>
        <label class="grid gap-1">
          <span class="label">Teléfono (WhatsApp / SMS)</span>
          <input class="control" name="telefono" placeholder="5537747366" maxlength="25" required>
        </label>
        <button class="btn">Enviar código</button>
      </form>
    @else
      {{-- Estado: OTP --}}
      <div class="helper" style="margin-bottom:4px">
        Enviamos un código a <strong>{{ $phone_masked }}</strong>. Vence en 10 minutos.
      </div>

      <form id="otpForm" method="POST" action="{{ route('cliente.verify.phone.check') }}" class="grid">
        @csrf
        <input type="hidden" name="account_id" value="{{ $aid }}">
        <input type="hidden" id="code" name="code" value="">
        <div class="otp">
          @for ($i=0; $i<6; $i++)
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-i="{{ $i }}" autocomplete="one-time-code">
          @endfor
        </div>
        <button class="btn">Verificar</button>
      </form>

      <form id="resendForm" method="POST" action="{{ route('cliente.verify.phone.send') }}"
            style="display:grid;place-items:center;margin-top:10px">
        @csrf
        <input type="hidden" name="account_id" value="{{ $aid }}">
        <button type="submit" class="resend">Reenviar código</button>
      </form>
    @endif

    <div class="helper">
      ¿Ya tienes acceso?
      <a href="{{ route('cliente.login') }}">Inicia sesión</a>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // Logo dinámico según tema
  const root=document.documentElement;
  const logo=document.getElementById('vhLogo');
  const imgs={light:@json($logoLight),dark:@json($logoDark)};
  function applyLogo(){
    const mode=root.getAttribute('data-theme')||'light';
    if(logo)logo.src=mode==='dark'?imgs.dark:imgs.light;
  }
  applyLogo();
  new MutationObserver(applyLogo).observe(root,{attributes:true,attributeFilter:['data-theme']});

  // OTP UX
  const boxes=Array.from(document.querySelectorAll('.otp-box'));
  const hidden=document.getElementById('code');
  const form=document.getElementById('otpForm');

  function compose(){hidden.value=boxes.map(b=>(b.value||'').replace(/\D/g,'')).join('').slice(0,6);}

  boxes.forEach((box,idx)=>{
    box.addEventListener('input',()=>{
      box.value=box.value.replace(/\D/g,'').slice(0,1);
      compose();
      if(box.value&&idx<boxes.length-1)boxes[idx+1].focus();
    });
    box.addEventListener('keydown',e=>{
      if(e.key==='Backspace'&&!box.value&&idx>0)boxes[idx-1].focus();
      if((e.key??'').match(/^[0-9]$/))box.value='';
      setTimeout(compose,0);
    });
    box.addEventListener('paste',e=>{
      e.preventDefault();
      const d=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
      for(let i=0;i<boxes.length;i++)boxes[i].value=d[i]??'';
      compose();
      boxes[Math.max(0,(d.length?d.length-1:0))].focus();
    });
  });
  if(boxes.length)boxes[0].focus();

  form?.addEventListener('submit',e=>{
    compose();
    if(hidden.value.length!==6){
      e.preventDefault();
      boxes[hidden.value.length||0].focus();
    }
  });
})();
</script>
@endpush
