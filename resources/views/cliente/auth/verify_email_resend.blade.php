{{-- resources/views/cliente/auth/success.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.guest')

@section('title','Pago exitoso · Pactopia360')

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
      radial-gradient(45% 60% at 80% 75%, rgba(190,18,60,.38), transparent 60%);
    filter:blur(100px);
    animation:float 14s ease-in-out infinite alternate;
  }
  @keyframes float{0%{transform:translate3d(-3%,-2%,0)}100%{transform:translate3d(4%,5%,0)}}

  .success-wrap{
    text-align:center;
    max-width:480px;
    width:90%;
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:20px;
    padding:32px 26px 28px;
    box-shadow:0 22px 64px rgba(0,0,0,.18);
    position:relative;
  }
  .success-wrap::before{
    content:"";
    position:absolute;inset:-1px;border-radius:21px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  .emoji{
    font-size:54px;
    margin-bottom:10px;
    text-shadow:0 2px 10px rgba(225,29,72,.25);
  }
  .title{
    font-size:22px;
    font-weight:900;
    margin:0;
    color:#E11D48;
  }
  .subtitle{
    margin:8px 0 18px;
    color:#475569;
    font-size:14px;
  }

  .actions{
    display:flex;
    justify-content:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:8px;
  }
  .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:12px 18px;
    border-radius:12px;
    font-weight:800;
    font-size:14px;
    text-decoration:none;
    transition:filter .15s ease;
  }
  .btn.primary{
    color:#fff;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 10px 24px rgba(225,29,72,.25);
  }
  .btn.primary:hover{filter:brightness(.96);}
  .btn.secondary{
    color:#E11D48;
    background:#fff;
    border:1px solid #f3d5dc;
  }
  .btn.secondary:hover{filter:brightness(.97);}
</style>
@endpush

@section('content')
<div class="success-wrap">
  <div class="emoji">🎉</div>
  <h1 class="title">¡Pago confirmado!</h1>
  <p class="subtitle">Gracias por tu compra de <strong>Pactopia360 PRO</strong>.<br>Tu cuenta ha sido activada exitosamente.</p>

  <div class="actions">
    <a href="{{ route('cliente.login') }}" class="btn primary">Iniciar sesión</a>
    @if (Route::has('cliente.billing.statement'))
      <a href="{{ route('cliente.billing.statement') }}" class="btn secondary">Ver mis pagos</a>
    @endif
  </div>
</div>
@endsection
