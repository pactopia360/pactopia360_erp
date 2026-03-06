<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pactopia360 · Admin | Recuperar contraseña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/login.css') }}?v={{ @filemtime(public_path('assets/admin/css/login.css')) }}">

    <script>
        (function () {
            const KEY = 'p360-theme-admin';
            const saved = localStorage.getItem(KEY);
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const initial = saved || (prefersDark ? 'dark' : 'light');

            function applyTheme(mode) {
                document.documentElement.setAttribute('data-theme', mode);
                document.body.classList.toggle('theme-dark', mode === 'dark');
                document.body.classList.toggle('theme-light', mode !== 'dark');

                const btn = document.getElementById('themeToggle');
                if (!btn) return;

                btn.setAttribute('aria-pressed', String(mode === 'dark'));

                const icon = btn.querySelector('.theme-btn__icon');
                const text = btn.querySelector('.theme-btn__text');

                if (icon) icon.textContent = mode === 'dark' ? '☀️' : '🌙';
                if (text) text.textContent = mode === 'dark' ? 'Modo claro' : 'Modo oscuro';
            }

            window.P360AdminThemeToggle = function () {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem(KEY, next);
                applyTheme(next);
            };

            document.addEventListener('DOMContentLoaded', function () {
                applyTheme(initial);
            });
        })();
    </script>
</head>
<body class="auth-page">
    <div class="auth-bg">
        <div class="grid-orb orb-1"></div>
        <div class="grid-orb orb-2"></div>
        <div class="grid-orb orb-3"></div>
    </div>

    <div class="theme-switch">
        <button type="button" class="theme-btn" id="themeToggle" onclick="window.P360AdminThemeToggle()" aria-pressed="false">
            <span class="theme-btn__icon">🌙</span>
            <span class="theme-btn__text">Modo oscuro</span>
        </button>
    </div>

    <main class="auth-shell auth-shell--narrow">
        <section class="auth-panel auth-panel--single">
            <form class="auth-card" method="POST" action="{{ route('admin.password.email') }}" novalidate autocomplete="on">
                @csrf

                <div class="auth-card__header">
                    <span class="eyebrow">Recuperación de acceso</span>
                    <h1 class="title">¿Olvidaste tu contraseña?</h1>
                    <p class="subtitle">
                        Escribe tu correo administrativo y te enviaremos un enlace seguro para restablecer tu contraseña.
                    </p>
                </div>

                @if (session('status'))
                    <div class="ok" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="err" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>• {{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="field">
                    <label for="email">Correo electrónico</label>
                    <input
                        class="input"
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="admin@pactopia360.com">
                </div>

                <button class="btn btn-primary" type="submit">Enviar enlace de recuperación</button>

                <div class="auth-actions">
                    <a class="link-back" href="{{ route('admin.login') }}">← Volver al inicio de sesión</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>