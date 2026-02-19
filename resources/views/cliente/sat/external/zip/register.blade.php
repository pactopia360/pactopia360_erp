{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\sat\external\zip\register.blade.php --}}
{{-- P360 · SAT Carga externa ZIP (FIEL) · UI estilo Login (Light) · v3.0 --}}
@php
  use Illuminate\Support\Facades\File;

  $email     = $email ?? request()->query('email','');
  $cuenta_id = $cuenta_id ?? request()->query('cuenta_id', null);
  $token     = $token ?? request()->query('token','');

  // Fallbacks robustos: si no hay sesión (flash), usamos query ok/rid.
  $success = (bool)($success ?? session('ext_zip_success', false) ?: (request()->query('ok') == '1'));
  $rid     = (string)($rid ?? request()->query('rid',''));

  // Usa errores estándar de Laravel (preferente)
  $bag = $errors ?? null;
  $firstError = null;
  try { $firstError = $bag?->first() ?: null; } catch (\Throwable $e) { $firstError = null; }

  // Assets
  $theme = 'light';

  $viteManifest = public_path('build/manifest.json');
  $hasViteBuild = File::exists($viteManifest);

  $coreCss       = asset('assets/client/css/core-ui.css');
  $vaultThemeCss = asset('assets/client/css/p360-vault-theme.css');
  $fallbackCss   = asset('assets/client/css/app.css');
  $fallbackJs    = asset('assets/client/js/app.js');

  $logoUrl = '/assets/client/p360-black.png';

  // Mantener querystring firmado en POST
  $qs        = request()->getQueryString();
  $actionUrl = request()->url() . ($qs ? ('?'.$qs) : '');
@endphp

<!DOCTYPE html>
<html lang="es" class="theme-{{ $theme }}" data-theme="{{ $theme }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light dark">
  <title>Pactopia360 · Carga externa ZIP (FIEL)</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="{{ $coreCss }}">
  <link rel="stylesheet" href="{{ $vaultThemeCss }}?v=1.0">

  @if ($hasViteBuild)
    @vite(['resources/css/app.css','resources/js/app.js'])
  @else
    <link rel="stylesheet" href="{{ $fallbackCss }}">
    @if ($fallbackJs)
      <script src="{{ $fallbackJs }}" defer></script>
    @endif
  @endif

  <style>
    :root{
      --ink:#0f172a;
      --mut:#475569;
      --bd:#e5e7eb;

      --brand:#6D28D9;
      --brand-2:#7C3AED;
      --accent:#0EA5E9;

      --card:#ffffff;
      --soft:#f6f7fb;

      --r:18px;

      --max: 1060px;
      --pad: 24px;
    }

    html, body{
      height:100%;
      font-family:'Poppins', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      color: var(--ink);
      background: #fff;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
    }

    .bg{
      min-height:100%;
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 26px 18px;
      background:
        radial-gradient(1200px 600px at 25% 35%, rgba(124,58,237,.18), transparent 60%),
        radial-gradient(900px 520px at 70% 55%, rgba(14,165,233,.12), transparent 55%),
        radial-gradient(800px 520px at 55% 85%, rgba(225,29,72,.10), transparent 60%),
        linear-gradient(180deg, #ffffff 0%, #fbf8ff 55%, #fff 100%);
    }

    .auth-shell{
      width:100%;
      max-width: var(--max);
      background: rgba(255,255,255,.78);
      border: 1px solid rgba(229,231,235,.8);
      border-radius: 22px;
      box-shadow:
        0 28px 70px rgba(2, 8, 23, .12),
        0 2px 0 rgba(255,255,255,.7) inset;
      backdrop-filter: blur(8px);
      overflow:hidden;
    }

    .auth-grid{
      display:grid;
      grid-template-columns: 1.05fr .95fr;
      gap: 0;
      min-height: 520px;
    }
    @media (max-width: 980px){
      .auth-grid{ grid-template-columns: 1fr; }
    }

    .left{
      padding: 26px 26px 22px;
      background: #ffffff;
      border-right: 1px solid rgba(229,231,235,.9);
    }
    @media (max-width: 980px){
      .left{ border-right:none; border-bottom: 1px solid rgba(229,231,235,.9); }
    }

    .logo-row{ display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .logo-row img{ height:44px; width:auto; display:block; }

    .left h2{
      margin: 10px 0 12px;
      font-size: 18px;
      font-weight: 900;
      letter-spacing: -.01em;
    }

    .bullets{
      margin: 0;
      padding: 0 0 0 16px;
      color: var(--mut);
      font-size: 13px;
      line-height: 1.6;
    }
    .bullets li{ margin: 7px 0; }
    .bullets b{ color: var(--ink); font-weight: 900; }

    .mini-card{
      margin-top: 16px;
      border: 1px solid rgba(229,231,235,.9);
      border-radius: 16px;
      background: #fff;
      padding: 14px;
      box-shadow: 0 10px 24px rgba(2, 8, 23, .07);
    }
    .mini-card .k{ font-size: 12px; color: var(--mut); font-weight: 900; margin-bottom: 8px; }
    .mini-card .v{ font-size: 12px; color: var(--mut); line-height: 1.5; overflow-wrap:anywhere; }

    .right{ padding: 26px 26px 22px; background: #ffffff; }

    .right h1{
      margin: 0 0 10px;
      text-align:center;
      font-size: 20px;
      font-weight: 900;
      letter-spacing: -.01em;
    }

    .sub{
      text-align:center;
      color: var(--mut);
      font-size: 13px;
      margin-bottom: 14px;
      line-height: 1.5;
    }

    .alert{
      border-radius: 12px;
      padding: 10px 12px;
      border: 1px solid rgba(229,231,235,.9);
      background: #fff;
      font-size: 13px;
      margin-bottom: 10px;
    }
    .alert-danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.06); color: #7f1d1d; }
    .alert-success{ border-color: rgba(22,163,74,.35); background: rgba(22,163,74,.08); color: #14532d; }

    .form{ margin-top: 8px; }

    .field{ display:flex; flex-direction:column; gap: 6px; margin-bottom: 12px; }
    .label{
      font-size: 12px; color: var(--mut); font-weight: 900;
      display:flex; align-items:center; justify-content:space-between; gap: 10px;
    }
    .label .hint{ font-size: 11px; color: #64748b; font-weight: 800; }

    input[type="text"],
    input[type="password"],
    textarea{
      width:100%;
      border: 1px solid rgba(203,213,225,.9);
      background: #fff;
      border-radius: 12px;
      padding: 11px 12px;
      outline:none;
      font-size: 14px;
      color: var(--ink);
    }
    input[type="text"]:focus,
    input[type="password"]:focus,
    textarea:focus{
      border-color: rgba(109,40,217,.45);
      box-shadow: 0 0 0 4px rgba(109,40,217,.12);
    }

    input[type="file"]{
      width:100%;
      border: 1px dashed rgba(148,163,184,.9);
      background: #fff;
      border-radius: 12px;
      padding: 9px 12px;
      font-size: 13px;
      color: var(--mut);
    }

    .field-error{ color:#b91c1c; font-weight: 900; font-size: 12px; margin-top: 2px; }

    .check{
      display:flex; gap:10px; align-items:flex-start;
      margin: 10px 0 12px;
      padding: 10px 12px;
      border: 1px solid rgba(229,231,235,.9);
      border-radius: 12px;
      background: #fff;
    }
    .check input{ margin-top: 3px; }
    .check strong{ display:block; font-size: 12.5px; font-weight: 900; color: var(--ink); }
    .check span{ display:block; margin-top: 2px; font-size: 12px; color: var(--mut); line-height: 1.45; }

    .btn{
      width:100%;
      border: none;
      padding: 12px 14px;
      border-radius: 12px;
      font-weight: 900;
      font-size: 14px;
      cursor:pointer;
      user-select:none;
    }
    .btn-primary{
      background: linear-gradient(180deg, var(--brand-2), var(--brand));
      color:#fff;
      box-shadow: 0 18px 30px rgba(109,40,217,.22);
    }

    .btn-row{ display:flex; gap: 10px; align-items:center; margin-top: 10px; }

    .btn-ghost{
      width:100%;
      border: 1px solid rgba(203,213,225,.95);
      background:#fff;
      color: var(--ink);
      padding: 12px 14px;
      border-radius: 12px;
      font-weight: 900;
      font-size: 14px;
      cursor:pointer;
      text-align:center;
      text-decoration:none;
    }

    .fineprint{
      text-align:center;
      margin-top: 10px;
      font-size: 11.5px;
      color: #64748b;
      line-height: 1.45;
    }

    .footer{
      text-align:center;
      font-size: 11.5px;
      color: #94a3b8;
      padding: 12px 8px 14px;
      border-top: 1px solid rgba(229,231,235,.85);
      background: rgba(255,255,255,.6);
    }
  </style>
</head>

<body>
  <div class="bg">
    <div class="auth-shell" role="main">
      <div class="auth-grid">

        <section class="left" aria-label="Branding">
          <div class="logo-row">
            <img src="{{ $logoUrl }}" alt="PACTOPIA 360">
          </div>

          <h2>Carga segura de ZIP (FIEL)</h2>

          <ul class="bullets">
            <li><b>Sube un ZIP</b> con tu e.firma en un solo archivo.</li>
            <li><b>Captura RFC</b> y la contraseña correcta.</li>
            <li><b>Enlace protegido</b> por token/expiración.</li>
            <li><b>Seguridad reforzada</b> por liga firmada.</li>
          </ul>

          <div class="mini-card">
            <div class="k">Detalles de la invitación</div>
            <div class="v"><b>Correo:</b> {{ $email !== '' ? $email : '—' }}</div>
            <div class="v"><b>Cuenta:</b> {{ $cuenta_id ?: '—' }}</div>
            <div class="v"><b>Ruta:</b> {{ request()->url() }}</div>
            <div class="v"><b>Token:</b> {{ $token !== '' ? $token : '—' }}</div>
            <div class="v" style="margin-top:8px;">
              Importante: no compartas esta liga. Si necesitas una nueva invitación, solicita al dueño de la cuenta que genere otra.
            </div>
          </div>
        </section>

        <section class="right" aria-label="Formulario">
          <h1>Cargar ZIP (FIEL)</h1>
          <div class="sub">Captura <b>RFC</b>, contraseña y sube el archivo <b>.zip</b>.</div>

          @if($success)
            <div class="alert alert-success">
              <b>ZIP recibido.</b>
              @if($rid !== '')
                ID: <b>{{ $rid }}</b>
              @endif
              @if(old('rfc'))
                <span style="display:block;margin-top:4px;">RFC: <b>{{ old('rfc') }}</b></span>
              @endif
            </div>
          @endif

          @if($firstError)
            <div class="alert alert-danger">
              <b>No se pudo cargar:</b> {{ $firstError }}
            </div>
          @endif

          <form class="form" method="POST" action="{{ $actionUrl }}" enctype="multipart/form-data" novalidate>
            @csrf

            <div class="field">
              <div class="label">
                <span>RFC</span>
                <span class="hint">12–13 caracteres, mayúsculas</span>
              </div>
              <input type="text" name="rfc" maxlength="13" value="{{ old('rfc') }}" placeholder="ABC010203XXX" required>
              @if($bag?->has('rfc')) <div class="field-error">{{ $bag->first('rfc') }}</div> @endif
            </div>

            <div class="field">
              <div class="label">
                <span>Razón social (opcional)</span>
                <span class="hint">Opcional</span>
              </div>
              <input type="text" name="razon_social" maxlength="190" value="{{ old('razon_social') }}" placeholder="Ej. Mi Empresa S.A. de C.V.">
              @if($bag?->has('razon_social')) <div class="field-error">{{ $bag->first('razon_social') }}</div> @endif
            </div>

            <div class="field">
              <div class="label">
                <span>Contraseña FIEL</span>
                <span class="hint">Se cifra y no se muestra</span>
              </div>
              <input type="password" name="key_password" value="" placeholder="••••••••" required>
              @if($bag?->has('key_password')) <div class="field-error">{{ $bag->first('key_password') }}</div> @endif
            </div>

            <div class="field">
              <div class="label">
                <span>Archivo ZIP</span>
                <span class="hint">Solo .zip</span>
              </div>
              <input type="file" name="zip" accept=".zip" required>
              @if($bag?->has('zip')) <div class="field-error">{{ $bag->first('zip') }}</div> @endif
            </div>

            <div class="field">
              <div class="label">
                <span>Nota (opcional)</span>
                <span class="hint">Contexto</span>
              </div>
              <textarea name="note" rows="2" maxlength="500" placeholder="Ej. CFDI 2025 / Operación X">{{ old('note') }}</textarea>
              @if($bag?->has('note')) <div class="field-error">{{ $bag->first('note') }}</div> @endif
            </div>

            <label class="check">
              <input type="checkbox" name="accept" value="1" {{ old('accept') ? 'checked' : '' }}>
              <div>
                <strong>Confirmo que cuento con autorización para registrar/usar esta FIEL.</strong>
                <span>Esta información se usará únicamente para servicios de descarga/gestión de CFDI en Pactopia360.</span>
              </div>
            </label>
            @if($bag?->has('accept')) <div class="field-error" style="margin-top:-6px;margin-bottom:10px;">{{ $bag->first('accept') }}</div> @endif

            <button type="submit" class="btn btn-primary">Enviar ZIP</button>

            <div class="btn-row">
              <a class="btn-ghost" href="javascript:window.close();">Cerrar</a>
            </div>

            <div class="fineprint">
              Si la liga expiró o fue alterada, el sistema rechazará el acceso.
            </div>
          </form>
        </section>

      </div>

      <div class="footer">
        © {{ date('Y') }} Pactopia SAPI de CV. Todos los derechos reservados.
      </div>
    </div>
  </div>

  @if($success)
<script>
  (function () {
    try {
      setTimeout(function () {
        try { window.close(); } catch(e) {}
        setTimeout(function () {
          if (!window.closed) { location.href = '/cliente/sat'; }
        }, 400);
      }, 900);
    } catch(e) {}
  })();
</script>
@endif

</body>
</html>
