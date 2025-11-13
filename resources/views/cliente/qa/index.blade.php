{{-- resources/views/cliente/qa/index.blade.php (v1 visual Pactopia360 · Centro de ayuda / QA) --}}
@extends('layouts.client')
@section('title','Centro de ayuda · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  .page-header{
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;margin-bottom:22px;flex-wrap:wrap;
  }
  .page-header h1{
    margin:0;font-weight:900;font-size:22px;color:#E11D48;
  }

  .card{
    position:relative;
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:18px;
    padding:20px 22px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;opacity:.25;pointer-events:none;
  }

  .qa-list{display:grid;gap:18px;}
  details{background:#fff;border:1px solid #f3d5dc;border-radius:12px;padding:14px 18px;cursor:pointer;transition:all .25s;}
  summary{font-weight:800;font-size:15px;color:#E11D48;list-style:none;}
  details[open]{background:#fff0f3;}
  details[open] summary{color:#BE123C;}
  summary::marker{display:none;}
  details p{margin:10px 0 0;color:#0f172a;font-size:14px;line-height:1.55;}

  .btnx{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 14px;border-radius:12px;font-weight:800;font-size:14px;
    cursor:pointer;text-decoration:none;
  }
  .btnx.primary{
    background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;border:0;box-shadow:0 8px 20px rgba(225,29,72,.25);
  }
  .btnx.ghost{background:#fff;border:1px solid #f3d5dc;color:#E11D48;}
  .btnx.ghost:hover{background:#fff0f3;}
</style>
@endpush

@section('content')
<div class="page-header">
  <h1>Centro de ayuda</h1>
  <a href="{{ route('cliente.home') }}" class="btnx ghost">← Volver al inicio</a>
</div>

<div class="card">
  <h3 style="margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.25px;font-weight:800;color:#E11D48;">
    Preguntas frecuentes
  </h3>

  <div class="qa-list">
    <details>
      <summary>¿Cómo timbro un CFDI en Pactopia360?</summary>
      <p>
        Una vez creado tu borrador en el módulo <strong>Facturación</strong>, selecciona el documento y presiona
        <em>Timbrar</em>. Si tu cuenta es <strong>PRO</strong>, el timbre se genera automáticamente y podrás descargar
        el XML y PDF inmediatamente.
      </p>
    </details>

    <details>
      <summary>¿Cómo cancelo un CFDI?</summary>
      <p>
        Desde el detalle del CFDI, presiona <em>Cancelar</em>. Se enviará la solicitud al SAT y, si es aceptada,
        el documento quedará marcado como <strong>Cancelado</strong>. Solo los CFDIs timbrados pueden cancelarse.
      </p>
    </details>

    <details>
      <summary>¿Puedo usar Pactopia360 con varios RFC?</summary>
      <p>
        Sí. En el plan <strong>PRO</strong> puedes registrar múltiples emisores y usar distintos RFCs desde tu misma cuenta.
        Cada uno conserva sus series, folios y certificados.
      </p>
    </details>

    <details>
      <summary>¿Dónde veo mis pagos o suscripción?</summary>
      <p>
        En el menú lateral, abre <em>Mi cuenta → Pagos y suscripción</em>. Ahí podrás ver tus facturas, renovar o actualizar tu plan.
      </p>
    </details>

    <details>
      <summary>¿Qué hago si olvidé mi contraseña?</summary>
      <p>
        En la pantalla de inicio de sesión, selecciona <strong>¿Olvidaste tu contraseña?</strong> y sigue las instrucciones
        para restablecerla mediante correo o WhatsApp.
      </p>
    </details>
  </div>
</div>

<div class="card" style="margin-top:20px;text-align:center;">
  <p style="margin:0 0 12px;">¿No encontraste lo que buscabas?</p>
  <a href="mailto:soporte@pactopia360.com" class="btnx primary">Contactar soporte</a>
</div>
@endsection
