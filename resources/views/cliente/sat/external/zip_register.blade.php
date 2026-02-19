{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\sat\external\zip_register.blade.php --}}
{{-- P360 · SAT · External ZIP Register (Public/Signed) · UI match con registro individual --}}
@php
  $token   = isset($token) ? (string)$token : (string) request('token','');
  $postUrl = isset($postUrl) ? (string)$postUrl : route('cliente.sat.external.zip.register', ['token' => $token]);

  // Success sin sesión: ok=1&rid=...
  $ok = (string) request('ok','') === '1' || (bool) (session('ext_reg_success') ?? false);

  $rid = (string) request('rid','');
  $saved = session('ext_reg_saved') ?? null;

  // Mensaje corto de éxito
  $okMsg = 'Recibimos tu ZIP correctamente. Ya puedes cerrar esta ventana.';
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Pactopia360 · Carga ZIP FIEL</title>

  {{-- Reutiliza el mismo sistema visual del cliente --}}
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="{{ asset('assets/client/css/core-ui.css') }}">

  <style>
    /* ===== Page shell (match estilo del registro individual) ===== */
    :root{
      --p360-max: 980px;
      --p360-card: var(--card, #0f1726);
      --p360-bg: var(--bg, #0b111c);
      --p360-text: var(--text, #e6e9ef);
      --p360-muted: var(--muted, #9aa4b2);
      --p360-bd: var(--border, rgba(255,255,255,.10));
      --p360-r: 18px;
      --p360-shadow: 0 18px 60px rgba(2,6,23,.55);
    }

    body{
      background: var(--p360-bg);
      color: var(--p360-text);
      margin:0;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:22px;
    }

    .p360-wrap{
      width:100%;
      max-width: var(--p360-max);
    }

    .p360-top{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:12px;
      opacity:.98;
    }

    .p360-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border:1px solid var(--p360-bd);
      border-radius:999px;
      background: rgba(255,255,255,.03);
      color: var(--p360-muted);
      font-size:12px;
      letter-spacing:.2px;
      white-space:nowrap;
    }

    .p360-card{
      border:1px solid var(--p360-bd);
      border-radius: var(--p360-r);
      background: var(--p360-card);
      box-shadow: var(--p360-shadow);
      overflow:hidden;
    }

    .p360-head{
      padding:18px 18px 12px;
      border-bottom:1px solid var(--p360-bd);
      background: rgba(255,255,255,.02);
    }

    .p360-title{
      font-size:18px;
      font-weight:700;
      margin:0 0 6px;
      color: var(--p360-text);
    }

    .p360-sub{
      margin:0;
      color: var(--p360-muted);
      font-size:13px;
      line-height:1.45;
    }

    .p360-body{
      padding:16px 18px 18px;
    }

    .p360-grid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:12px;
    }
    @media (max-width: 820px){
      .p360-grid{ grid-template-columns: 1fr; }
    }

    .p360-field label{
      display:block;
      font-size:12px;
      color: var(--p360-muted);
      margin:0 0 6px;
    }

    .p360-field input,
    .p360-field textarea{
      width:100%;
      border:1px solid var(--p360-bd);
      background: rgba(255,255,255,.02);
      color: var(--p360-text);
      border-radius: 14px;
      padding:11px 12px;
      outline:none;
      font-size:14px;
    }

    .p360-field textarea{
      min-height: 100px;
      resize: vertical;
    }

    .p360-help{
      margin-top:7px;
      font-size:12px;
      color: var(--p360-muted);
      line-height:1.4;
    }

    .p360-actions{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      margin-top:14px;
      flex-wrap:wrap;
    }

    .p360-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      border:1px solid var(--p360-bd);
      background: rgba(255,255,255,.05);
      color: var(--p360-text);
      border-radius: 14px;
      padding:10px 14px;
      cursor:pointer;
      font-weight:700;
      font-size:14px;
      text-decoration:none;
      user-select:none;
      min-height:42px;
    }
    .p360-btn:hover{ background: rgba(255,255,255,.08); }

    .p360-btn.primary{
      border-color: rgba(56,189,248,.35);
      background: rgba(56,189,248,.12);
    }
    .p360-btn.primary:hover{ background: rgba(56,189,248,.16); }

    .p360-alert{
      border:1px solid var(--p360-bd);
      background: rgba(255,255,255,.03);
      border-radius: 14px;
      padding:12px 12px;
      margin-bottom:12px;
    }
    .p360-alert.ok{
      border-color: rgba(34,197,94,.28);
      background: rgba(34,197,94,.08);
    }
    .p360-alert.err{
      border-color: rgba(239,68,68,.28);
      background: rgba(239,68,68,.08);
    }

    .p360-alert h4{
      margin:0 0 6px;
      font-size:13px;
      font-weight:800;
    }
    .p360-alert p{
      margin:0;
      font-size:13px;
      color: var(--p360-text);
      opacity:.95;
      line-height:1.45;
    }

    .p360-mini{
      font-size:12px;
      color: var(--p360-muted);
    }

    .p360-code{
      display:inline-block;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size:12px;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid var(--p360-bd);
      background: rgba(255,255,255,.03);
      color: var(--p360-text);
    }

    /* File input tweak */
    input[type="file"]{
      padding:10px 12px;
    }
  </style>
</head>

<body>
  <div class="p360-wrap">

    <div class="p360-top">
      <div class="p360-badge">
        <span>🔒 Enlace seguro</span>
        @if($token !== '')
          <span class="p360-code">token: {{ \Illuminate\Support\Str::limit($token, 18, '…') }}</span>
        @endif
      </div>
      <div class="p360-badge">
        <span>📦 Carga ZIP FIEL</span>
        <span class="p360-mini">Pactopia360 · SAT</span>
      </div>
    </div>

    <div class="p360-card">
      <div class="p360-head">
        <h1 class="p360-title">Carga externa de ZIP (FIEL)</h1>
        <p class="p360-sub">
          Sube tu archivo ZIP con la FIEL. Este enlace es temporal y está protegido.
          Asegúrate de capturar el RFC y la contraseña correctamente.
        </p>
      </div>

      <div class="p360-body">

        {{-- ✅ ÉXITO --}}
        @if($ok)
          <div class="p360-alert ok">
            <h4>Listo</h4>
            <p>{{ $okMsg }}</p>
            @if($rid !== '')
              <p class="p360-mini" style="margin-top:6px;">Referencia: <span class="p360-code">{{ $rid }}</span></p>
            @endif
          </div>
        @endif

        {{-- ❌ ERRORES --}}
        @if($errors && $errors->any())
          <div class="p360-alert err">
            <h4>No se pudo enviar</h4>
            <p>{{ $errors->first() }}</p>
          </div>
        @endif

        <form method="POST" action="{{ $postUrl }}" enctype="multipart/form-data" novalidate>
          @csrf

          {{-- Por si el service lo quiere leer en body también --}}
          <input type="hidden" name="token" value="{{ $token }}">

          <div class="p360-grid">

            <div class="p360-field">
              <label>RFC</label>
              <input
                name="rfc"
                maxlength="13"
                required
                autocomplete="off"
                value="{{ old('rfc', '') }}"
                placeholder="Ej. ABCD010203XXX"
              >
              <div class="p360-help">Formato SAT: 12–13 caracteres (sin espacios).</div>
            </div>

            <div class="p360-field">
              <label>Razón social (opcional)</label>
              <input
                name="razon_social"
                maxlength="190"
                autocomplete="organization"
                value="{{ old('razon_social', '') }}"
                placeholder="Ej. Mi Empresa S.A. de C.V."
              >
              <div class="p360-help">Si no la sabes, puedes dejarla en blanco.</div>
            </div>

            <div class="p360-field">
              <label>Contraseña FIEL</label>
              <input
                type="password"
                name="fiel_password"
                maxlength="120"
                required
                autocomplete="new-password"
                placeholder="••••••••"
              >
              <div class="p360-help">Es la contraseña de la llave/certificado dentro del ZIP.</div>
            </div>

            <div class="p360-field">
              <label>Archivo ZIP</label>
              <input
                type="file"
                name="zip"
                accept=".zip"
                required
              >
              <div class="p360-help">Tamaño máximo: 50MB. Solo .zip.</div>
            </div>

            <div class="p360-field" style="grid-column:1 / -1;">
              <label>Notas (opcional)</label>
              <textarea
                name="notes"
                maxlength="800"
                placeholder="Escribe una nota si es necesario (opcional)."
              >{{ old('notes','') }}</textarea>
            </div>

          </div>

          <div class="p360-actions">
            <div class="p360-mini">
              Al enviar confirmas que tienes autorización para registrar/usar esta FIEL.
            </div>

            <button class="p360-btn primary" type="submit">
              Enviar ZIP
            </button>
          </div>

        </form>

      </div>
    </div>

    <div style="margin-top:12px;text-align:center;color:var(--p360-muted);font-size:12px;">
      © {{ date('Y') }} Pactopia360
    </div>

  </div>
</body>
</html>
