{{-- resources/views/cliente/auth/success.blade.php --}}
<!doctype html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago confirmado · Pactopia360</title>
    <meta name="robots" content="noindex,nofollow">

    <style>
        :root{
            --bg:#f6f7fb;
            --card:#ffffff;
            --ink:#0f172a;
            --mut:#64748b;
            --line:rgba(15,23,42,.10);
            --shadow:0 18px 60px rgba(15,23,42,.10);
            --ok:#16a34a;
            --accent:#ef4444;
            --radius:18px;
        }

        /* Dark theme tokens (solo si el usuario cambia tema del sistema o con toggle) */
        html[data-theme="dark"]{
            --bg:#0b0f1a;
            --card:#0f172a;
            --ink:#e5e7eb;
            --mut:#94a3b8;
            --line:rgba(255,255,255,.10);
            --shadow:0 18px 60px rgba(0,0,0,.45);
        }

        *{box-sizing:border-box}
        body{
            margin:0; min-height:100vh; display:grid; place-items:center;
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
            color:var(--ink);
            background:
                radial-gradient(900px 520px at 15% 10%, rgba(239,68,68,.10), transparent 60%),
                radial-gradient(900px 520px at 85% 75%, rgba(99,102,241,.10), transparent 60%),
                var(--bg);
        }

        .wrap{width:min(760px, calc(100% - 32px));}

        .topbar{
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:14px;
        }

        .brand{
            display:flex; align-items:center; gap:10px;
            user-select:none;
        }

        .brand img{height:30px; width:auto; display:block}
        .brand .txt{font-weight:900; letter-spacing:.2px}

        .theme-btn{
            appearance:none; border:1px solid var(--line); background:rgba(255,255,255,.55);
            color:var(--ink);
            border-radius:12px; padding:9px 12px;
            font-weight:800; cursor:pointer;
            display:inline-flex; align-items:center; gap:8px;
        }
        html[data-theme="dark"] .theme-btn{background:rgba(15,23,42,.55)}

        .card{
            border-radius:var(--radius);
            background:linear-gradient(180deg, rgba(255,255,255,.70), rgba(255,255,255,.98));
            border:1px solid var(--line);
            box-shadow:var(--shadow);
            padding:22px;
        }
        html[data-theme="dark"] .card{
            background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
        }

        .head{
            display:flex; align-items:flex-start; gap:12px;
            padding-bottom:14px; border-bottom:1px solid var(--line);
            margin-bottom:14px;
        }
        .check{
            width:34px;height:34px;border-radius:12px;
            background:rgba(22,163,74,.12);
            border:1px solid rgba(22,163,74,.30);
            display:grid; place-items:center;
            flex:0 0 auto;
        }

        h1{margin:0;font-size:20px;font-weight:900}
        .sub{margin:4px 0 0 0;color:var(--mut);font-size:13px;line-height:1.45}

        .meta{margin-top:12px; display:flex; gap:10px; flex-wrap:wrap}
        .pill{
            border:1px solid var(--line);
            background:rgba(15,23,42,.03);
            border-radius:999px;
            padding:6px 10px;
            font-size:12px;
            color:var(--mut);
        }
        html[data-theme="dark"] .pill{background:rgba(15,23,42,.50); color:rgba(226,232,240,.88)}

        .actions{
            margin-top:16px;
            display:flex; gap:10px; flex-wrap:wrap;
        }
        .btn{
            display:inline-flex; align-items:center; justify-content:center;
            gap:8px; padding:10px 14px; border-radius:12px;
            font-weight:900; border:0; cursor:pointer; text-decoration:none;
        }
        .btn-primary{background:var(--accent); color:white;}
        .btn-ghost{
            background:transparent;
            border:1px solid var(--line);
            color:var(--ink);
        }

        .hint{
            margin-top:12px; color:var(--mut);
            font-size:12px; line-height:1.45;
        }
    </style>
</head>
<body>
@php
    $loginUrl = route('cliente.login');
    // redirect post-login a estado de cuenta/pagos
    $loginNextBilling = $loginUrl . '?next=' . urlencode('/cliente/billing/statement');

    // Mostrar solo algo útil al usuario (sin Stripe session)
    $cycle = strtolower((string)($plan ?? 'mensual'));
    $cycle = in_array($cycle, ['anual','annual','year'], true) ? 'anual' : 'mensual';
    $labelPlan = $cycle === 'anual' ? 'ANUAL' : 'MENSUAL';

    $acct = isset($accountId) ? (int)$accountId : 0;

    $logoBlack = asset('assets/client/p360-black.png');
    $logoWhite = asset('assets/client/p360-white.png');
@endphp

<div class="wrap">
    <div class="topbar">
        <div class="brand" aria-label="Pactopia360">
            <img id="brandLogo" src="{{ $logoBlack }}" alt="Pactopia360">
            <div class="txt">PACTOPIA360</div>
        </div>

        <button type="button" class="theme-btn" id="themeBtn" aria-label="Cambiar tema">
            <span id="themeIcon">☀</span>
            <span id="themeTxt">Claro</span>
        </button>
    </div>

    <div class="card">
        <div class="head">
            <div class="check" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M20 6L9 17l-5-5" stroke="rgba(22,163,74,1)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <h1>Pago confirmado</h1>
                <p class="sub">
                    Tu compra <strong>{{ $labelPlan }}</strong> fue registrada correctamente.
                    Para continuar, inicia sesión con tu correo o RFC y contraseña.
                </p>
            </div>
        </div>

        <div class="meta">
            @if($acct > 0)
                <span class="pill">Cuenta #{{ $acct }}</span>
            @endif
            <span class="pill">Activación automática por webhook</span>
        </div>

        <div class="actions">
            <a class="btn btn-primary" href="{{ $loginUrl }}">Iniciar sesión</a>
            <a class="btn btn-ghost" href="{{ $loginNextBilling }}">Ver mis pagos</a>
        </div>

        <div class="hint">
            Si acabas de pagar, espera unos segundos y luego inicia sesión.
            Si aún no te deja entrar, es porque el webhook está terminando de confirmar el pago.
        </div>
    </div>
</div>

<script>
(function () {
    const root   = document.documentElement;
    const btn    = document.getElementById('themeBtn');
    const icon   = document.getElementById('themeIcon');
    const txt    = document.getElementById('themeTxt');
    const logo   = document.getElementById('brandLogo');

    const logoBlack = @json($logoBlack);
    const logoWhite = @json($logoWhite);

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        const isDark = theme === 'dark';
        icon.textContent = isDark ? '🌙' : '☀';
        txt.textContent  = isDark ? 'Oscuro' : 'Claro';
        logo.src = isDark ? logoWhite : logoBlack;

        try { localStorage.setItem('p360_theme', theme); } catch(e) {}
    }

    // Default: claro, pero respeta preferencia guardada o sistema
    let saved = null;
    try { saved = localStorage.getItem('p360_theme'); } catch(e) {}

    if (saved === 'dark' || saved === 'light') {
        applyTheme(saved);
    } else {
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    btn.addEventListener('click', function(){
        const cur = root.getAttribute('data-theme') || 'light';
        applyTheme(cur === 'dark' ? 'light' : 'dark');
    });
})();
</script>
</body>
</html>
