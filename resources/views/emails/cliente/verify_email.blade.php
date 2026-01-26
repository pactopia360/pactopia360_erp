{{-- resources/views/emails/cliente/verify_email.blade.php --}}
@php
  $producto     = $producto     ?? 'Pactopia360';
  $nombre       = $nombre       ?? 'Usuario';
  $email        = $email        ?? null;
  $rfc          = $rfc          ?? null;
  $tempPassword = $tempPassword ?? null;
  $is_pro       = isset($is_pro) ? (bool)$is_pro : null;

  // ✅ URL PRINCIPAL: confirmar correo (viene del controller como actionUrl)
  $actionUrl = $actionUrl ?? null;

  // Fallback a login (secundario)
  $loginUrl = $loginUrl ?? (\Illuminate\Support\Facades\Route::has('cliente.login')
              ? route('cliente.login')
              : url('/cliente/login'));

  $preheader = $preheader ?? 'Confirma tu correo para activar tu cuenta en Pactopia360.';
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirma tu correo · {{ $producto }}</title>
  <style>
    /* --- Paleta y tokens (modo oscuro amigable para email modernos) --- */
    :root{
      --brand:#ff2a2a; --brand-dark:#b91c1c;
      --bg:#0f172a; --card:#1e293b; --text:#f8fafc; --muted:#94a3b8;
      --radius:16px; --shadow:0 16px 40px rgba(0,0,0,.35);
      --bdCard:rgba(255,255,255,.06); --bdInfo:rgba(255,255,255,.08); --bdField:rgba(255,255,255,.12);
      --bgInfo:rgba(15,23,42,.55)
    }
    body{margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:linear-gradient(180deg,var(--bg),#020617);color:var(--text)}
    .wrapper{max-width:560px;margin:0 auto;padding:36px 16px}
    .card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid var(--bdCard);padding:32px 28px;position:relative;overflow:hidden}
    .card::before{content:"";position:absolute;inset:0;border-radius:var(--radius);
      background:radial-gradient(circle at 0 0, rgba(255,42,42,.38), transparent 70%),
                 radial-gradient(circle at 100% 100%, rgba(255,42,42,.22), transparent 60%);
      z-index:0}
    .content{position:relative;z-index:1}
    .logo{display:block;margin:0 auto 24px;width:160px;max-width:160px}
    h1{font-size:24px;font-weight:800;text-align:center;margin:0 0 12px;letter-spacing:-.5px;color:var(--text)}
    .subtitle{font-size:15px;line-height:1.6;color:var(--muted);text-align:center;margin:0 0 20px}
    .highlight-box{background:var(--bgInfo);border:1px solid var(--bdInfo);border-radius:14px;padding:16px 18px;margin-bottom:24px;color:var(--text)}
    .highlight-title{font-size:14px;font-weight:600;color:var(--text);margin:0 0 4px;line-height:1.4}
    .highlight-desc{margin:0;color:var(--muted);font-size:13px;line-height:1.5}
    .creds-box{border:1px solid var(--bdField);border-radius:12px;padding:14px 16px;font-size:13px;line-height:1.5;margin-top:12px;background:rgba(30,41,59,.5);color:var(--text)}
    .creds-label{display:block;color:var(--muted);font-size:12px;font-weight:600;margin-bottom:2px;text-transform:uppercase;letter-spacing:.03em}
    .creds-value{color:var(--text);font-weight:600;word-break:break-all}
    .btn-wrap{text-align:center;margin:28px 0 10px}
    .btn{display:inline-block;background:linear-gradient(180deg,var(--brand),var(--brand-dark));color:#fff !important;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:12px;box-shadow:0 12px 30px rgba(255,42,42,.35)}
    .btn-secondary{display:inline-block;margin-top:10px;background:transparent;color:var(--muted) !important;text-decoration:underline;font-weight:600;font-size:12px}
    .note-small{font-size:12px;color:var(--muted);line-height:1.5;text-align:center}
    .info-section{margin-top:22px}
    .info-headline{color:var(--text);font-weight:600;font-size:13px;margin:0 0 6px;text-align:left}
    .info-text{color:var(--muted);font-size:13px;line-height:1.5;margin:0 0 14px;text-align:left}
    .footer{margin-top:34px;text-align:center;font-size:12px;color:#64748b;line-height:1.5}
    .footer a{color:var(--muted);text-decoration:underline}
    .btn-fallback{display:block;text-align:center;margin-top:10px;font-size:12px;color:var(--muted)}
    @media (prefers-color-scheme: light){
      body{background:#f1f5f9;color:#0b1220}
      .card{background:#fff;border-color:#e5e7eb}
      .subtitle,.highlight-desc,.info-text,.footer{color:#475569}
      .creds-box{background:#f8fafc;border-color:#e5e7eb}
    }
  </style>
</head>
<body>

  {{-- Preheader (opcional) --}}
  @if(View::exists('emails.partials.preheader'))
    @include('emails.partials.preheader', ['text' => $preheader])
  @else
    <div style="display:none;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;color:transparent">
      {{ $preheader }}
    </div>
  @endif

  <div class="wrapper">
    <div class="card" role="article" aria-roledescription="email" aria-label="Verificación de correo">
      <div class="content">

        {{-- LOGO --}}
        <img src="{{ asset('assets/client/logop360light.png') }}" alt="{{ $producto }}" class="logo" width="160" height="40" style="height:auto">

        {{-- TITULAR --}}
        <h1>Confirma tu correo</h1>

        <p class="subtitle">
          Hola <strong style="color:#fff">{{ $nombre }}</strong>,<br>
          solo falta confirmar tu correo para activar tu cuenta en <strong style="color:#fff">{{ $producto }}</strong>.
        </p>

        {{-- RESUMEN --}}
        <div class="highlight-box">
          <p class="highlight-title">Paso 1 de 2: Verificación de correo</p>
          <p class="highlight-desc">
            Da clic en el botón para confirmar tu correo. Después podrás verificar tu teléfono y entrar al portal.
          </p>

          {{-- DATOS (si llegaron) --}}
          @if($email || $rfc)
            <div class="creds-box">
              @if($email)
                <div style="margin-bottom:10px">
                  <span class="creds-label">Correo</span>
                  <span class="creds-value">{{ $email }}</span>
                </div>
              @endif
              @if($rfc)
                <div style="margin-bottom:6px">
                  <span class="creds-label">RFC</span>
                  <span class="creds-value">{{ $rfc }}</span>
                </div>
              @endif
            </div>
          @endif
        </div>

        {{-- CTA CONFIRMAR CORREO --}}
        <div class="btn-wrap">
          @if($actionUrl)
            <a href="{{ $actionUrl }}" class="btn" target="_blank" rel="noopener">Confirmar correo</a>
            <div class="btn-fallback">
              Si el botón no funciona copia y pega este enlace:<br>
              <span style="word-break:break-all">{{ $actionUrl }}</span>
            </div>
          @else
            {{-- Fallback duro si por alguna razón no llegó actionUrl --}}
            <a href="{{ $loginUrl }}" class="btn" target="_blank" rel="noopener">Ir al portal</a>
            <div class="btn-fallback">
              No se generó el enlace de verificación. Entra al portal y solicita uno nuevo:<br>
              <span style="word-break:break-all">{{ $loginUrl }}</span>
            </div>
          @endif

          <a href="{{ $loginUrl }}" class="btn-secondary" target="_blank" rel="noopener">Ir al login</a>
        </div>

        <p class="note-small">
          Por seguridad, este enlace puede expirar. Si expiró, vuelve a solicitar un enlace de verificación.
        </p>

        {{-- INFO EXTRA --}}
        <div class="info-section">
          <p class="info-headline">Paso 2 de 2: Verificación de teléfono</p>
          <p class="info-text">
            Al confirmar tu correo, te pediremos verificar tu teléfono con un código OTP para terminar la activación.
          </p>

          @if(!is_null($is_pro))
            @if ($is_pro)
              <p class="info-headline">Plan PRO</p>
              <p class="info-text">
                Tienes prioridad en soporte, más almacenamiento y reportes avanzados.
              </p>
            @else
              <p class="info-headline">Plan FREE</p>
              <p class="info-text">
                Puedes activar PRO cuando lo necesites para ampliar timbres, almacenamiento y reportes.
              </p>
            @endif
          @endif
        </div>

        {{-- FOOTER --}}
        <div class="footer">
          ¿Dudas o necesitas ayuda?<br>
          Escríbenos a
          <a href="mailto:{{ $soporte ?? 'soporte@pactopia.com' }}">{{ $soporte ?? 'soporte@pactopia.com' }}</a>
          <br><br>
          © {{ date('Y') }} {{ $producto }} · Todos los derechos reservados
        </div>

      </div>
    </div>
  </div>
</body>
</html>
