{{-- resources/views/cliente/auth/paywall.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activar cuenta · Pactopia360</title>
    <meta name="robots" content="noindex,nofollow">

    <style>
        :root{
            --bg:#0b0f1a;
            --card:#0f172a;
            --ink:#e5e7eb;
            --mut:#94a3b8;
            --accent:#ef4444;
            --ok:#22c55e;
            --warn:#f59e0b;
        }
        body{
            margin:0;
            min-height:100vh;
            display:grid;
            place-items:center;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
            background: radial-gradient(1000px 600px at 20% 10%, rgba(239,68,68,.18), transparent 60%),
                        radial-gradient(900px 600px at 80% 70%, rgba(99,102,241,.14), transparent 60%),
                        var(--bg);
            color:var(--ink);
        }
        .card{
            width:min(560px, calc(100% - 32px));
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
            border:1px solid rgba(255,255,255,.10);
            border-radius:18px;
            padding:22px 20px;
            box-shadow: 0 18px 60px rgba(0,0,0,.45);
        }
        .title{
            font-weight:800;
            font-size:18px;
            letter-spacing:.2px;
            margin:0 0 6px 0;
        }
        .sub{
            margin:0 0 14px 0;
            color:var(--mut);
            font-size:13px;
            line-height:1.45;
        }
        .row{
            display:flex;
            align-items:center;
            gap:12px;
            padding:12px 12px;
            background: rgba(15, 23, 42, .55);
            border: 1px solid rgba(255,255,255,.08);
            border-radius:14px;
        }
        .spinner{
            width:18px;height:18px;
            border-radius:999px;
            border: 2px solid rgba(255,255,255,.22);
            border-top-color: var(--accent);
            animation: spin .9s linear infinite;
            flex:0 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg);} }

        .alert{
            margin-top:12px;
            border-radius:14px;
            padding:12px 12px;
            border:1px solid rgba(239,68,68,.30);
            background: rgba(239,68,68,.10);
            color:#fecaca;
            font-size:13px;
            line-height:1.45;
        }
        .meta{
            margin-top:10px;
            font-size:12px;
            color:rgba(148,163,184,.92);
            background: rgba(2,6,23,.35);
            border:1px solid rgba(255,255,255,.08);
            border-radius:14px;
            padding:10px 12px;
            overflow:auto;
            max-height:160px;
        }
        .actions{
            margin-top:14px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn{
            appearance:none;
            border:0;
            border-radius:12px;
            padding:10px 12px;
            font-weight:800;
            cursor:pointer;
            color:white;
        }
        .btn-primary{ background: var(--accent); }
        .btn-secondary{
            background: rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.12);
        }
        .btn:disabled{
            opacity:.6;
            cursor:not-allowed;
        }
        .hint{
            margin-top:12px;
            color:rgba(148,163,184,.9);
            font-size:12px;
        }
    </style>
</head>
<body>
@php
    $err = session('paywall.error');
    $errMeta = session('paywall.error_meta');
    $auto = empty($err); // solo auto-submit si NO hay error previo
@endphp

    <div class="card">
        <h1 class="title">{{ $auto ? 'Activando tu cuenta…' : 'No se pudo iniciar el pago' }}</h1>
        <p class="sub">
            {{ $auto
                ? 'Te estamos redirigiendo al pago seguro para continuar.'
                : 'Detectamos un problema al crear la sesión de Stripe. Reintenta o ajusta la configuración de precios.' }}
        </p>

        <div class="row" aria-live="polite">
            @if($auto)
                <div class="spinner" aria-hidden="true"></div>
                <div>
                    <div style="font-weight:800; font-size:13px;">Redirección a Stripe Checkout</div>
                    <div style="color:var(--mut); font-size:12px;">No cierres esta ventana.</div>
                </div>
            @else
                <div style="width:18px;height:18px;border-radius:999px;background:rgba(239,68,68,.25);border:1px solid rgba(239,68,68,.35);"></div>
                <div>
                    <div style="font-weight:800; font-size:13px;">Stripe Checkout no disponible</div>
                    <div style="color:var(--mut); font-size:12px;">Corrige el Price ID (test/live) y reintenta.</div>
                </div>
            @endif
        </div>

        @if(!$auto)
            <div class="alert">
                {{ $err ?: 'Error desconocido.' }}
            </div>

            @if(!empty($errMeta))
                <div class="meta"><pre style="margin:0;white-space:pre-wrap;">{{ is_string($errMeta) ? $errMeta : json_encode($errMeta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></div>
            @endif

            <div class="actions">
                <form method="POST" action="{{ route('cliente.checkout.pro.monthly') }}">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $accountId }}">
                    @if(!empty($email)) <input type="hidden" name="email" value="{{ $email }}"> @endif
                    <button class="btn btn-primary" type="submit">Reintentar mensual</button>
                </form>

                <form method="POST" action="{{ route('cliente.checkout.pro.annual') }}">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $accountId }}">
                    @if(!empty($email)) <input type="hidden" name="email" value="{{ $email }}"> @endif
                    <button class="btn btn-secondary" type="submit">Probar anual</button>
                </form>

                <a class="btn btn-secondary" style="text-decoration:none;display:inline-flex;align-items:center;"
                   href="{{ route('cliente.login') }}">Volver a login</a>
            </div>
        @endif

        <form id="paywallForm" method="POST" action="{{ $action }}">
            @csrf
            <input type="hidden" name="account_id" value="{{ $accountId }}">
            @if(!empty($email))
                <input type="hidden" name="email" value="{{ $email }}">
            @endif
        </form>

        <div class="hint">
            @if($auto)
                Si no te redirige en unos segundos, es porque Stripe no pudo crear la sesión (revisa logs).
            @else
                Sugerencia: revisa que tu STRIPE_SECRET (test/live) coincida con los price_id en stripe_price_list.
            @endif
        </div>
    </div>

    @if($auto)
        <script>
            (function(){
                const f = document.getElementById('paywallForm');
                if (!f) return;
                setTimeout(() => f.submit(), 80);
            })();
        </script>
    @endif
</body>
</html>
