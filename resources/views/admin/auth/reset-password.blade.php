<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pactopia360 · Admin | Restablecer contraseña</title>
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

            window.P360_togglePwd = function (inputId, btnId) {
                const input = document.getElementById(inputId);
                const btn = document.getElementById(btnId);
                if (!input || !btn) return;

                const isPassword = (input.getAttribute('type') || '').toLowerCase() === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                btn.textContent = isPassword ? 'Ocultar' : 'Mostrar';
                btn.setAttribute('aria-pressed', String(isPassword));
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
            <form class="auth-card" method="POST" action="{{ route('admin.password.update') }}" novalidate autocomplete="on">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="auth-card__header">
                    <span class="eyebrow">Seguridad administrativa</span>
                    <h1 class="title">Crear nueva contraseña</h1>
                    <p class="subtitle">
                        Define una contraseña robusta para recuperar el acceso a tu cuenta administrativa.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="err" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>• {{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="field">
                    <label for="email_view">Correo electrónico</label>
                    <input
                        class="input"
                        id="email_view"
                        type="email"
                        value="{{ $email }}"
                        disabled>
                </div>

                <div class="field">
                    <label for="password">Nueva contraseña</label>
                    <div class="pwd-field">
                        <input
                            class="input"
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="Mínimo 8 caracteres">
                        <button
                            type="button"
                            class="toggle"
                            id="btnTogglePassword"
                            aria-controls="password"
                            aria-pressed="false"
                            onclick="window.P360_togglePwd('password','btnTogglePassword')">Mostrar</button>
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <div class="pwd-field">
                        <input
                            class="input"
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Repite tu nueva contraseña">
                        <button
                            type="button"
                            class="toggle"
                            id="btnTogglePassword2"
                            aria-controls="password_confirmation"
                            aria-pressed="false"
                            onclick="window.P360_togglePwd('password_confirmation','btnTogglePassword2')">Mostrar</button>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Actualizar contraseña</button>

                <div class="auth-actions">
                    <a class="link-back" href="{{ route('admin.login') }}">← Volver al inicio de sesión</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>