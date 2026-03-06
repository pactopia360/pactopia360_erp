<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pactopia360 · Admin | Iniciar sesión</title>
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

                if (icon) icon.textContent = mode === 'dark' ? '☀' : '☾';
                if (text) text.textContent = mode === 'dark' ? 'Modo claro' : 'Modo oscuro';
            }

            window.P360AdminThemeToggle = function () {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem(KEY, next);
                applyTheme(next);
            };

            window.P360_togglePwd = function (inputId, btnId) {
                const input = document.getElementById(inputId || 'password');
                const btn = document.getElementById(btnId || 'btnTogglePassword');
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
<body class="auth-page auth-page--pactopia">
    <div class="auth-bg">
        <div class="auth-glow auth-glow--1"></div>
        <div class="auth-glow auth-glow--2"></div>
        <div class="auth-grid-lines"></div>
    </div>

    <div class="theme-switch">
        <button type="button" class="theme-btn" id="themeToggle" onclick="window.P360AdminThemeToggle()" aria-pressed="false">
            <span class="theme-btn__icon">☾</span>
            <span class="theme-btn__text">Modo oscuro</span>
        </button>
    </div>

    <main class="auth-shell">
        <section class="login-wrap" aria-label="Acceso administrativo Pactopia360">
            <aside class="login-hero">
                <div class="login-hero__inner">
                    <div class="login-brand">
                        <img class="login-brand__logo login-brand__logo--dark" src="{{ asset('assets/admin/img/logo-pactopia360-white.png') }}" alt="Pactopia360">
                        <img class="login-brand__logo login-brand__logo--light" src="{{ asset('assets/admin/img/logo-pactopia360-dark.png') }}" alt="Pactopia360">
                    </div>

                    <span class="login-chip">ADMIN</span>

                    <h1 class="login-hero__title">Hola, bienvenido</h1>

                    <p class="login-hero__text">
                        Accede al panel administrativo de Pactopia360 para gestionar operación, clientes, facturación, SAT y finanzas.
                    </p>

                    <div class="login-hero__mini">
                        <span>Control centralizado</span>
                        <span>Acceso seguro</span>
                        <span>Operación crítica</span>
                    </div>

                    <div class="login-hero__footer">
                        © {{ date('Y') }} Pactopia360 · Panel administrativo
                    </div>
                </div>
            </aside>

            <section class="login-panel">
                <form class="login-card" method="POST" action="{{ route('admin.login.do') }}" id="loginForm" novalidate autocomplete="on">
                    @csrf

                    <div class="login-card__header">
                        <p class="login-card__eyebrow">Acceso administrativo</p>
                        <h2 class="login-card__title">Iniciar sesión</h2>
                        <p class="login-card__subtitle">
                            Ingresa con tus credenciales para continuar al entorno de administración.
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="ok" role="status">{{ session('status') }}</div>
                    @endif

                    @if (session('warn'))
                        <div class="err" role="alert">{{ session('warn') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="err" role="alert">
                            @foreach ($errors->all() as $error)
                                <div>• {{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="field">
                        <label for="email">Correo electrónico o usuario</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M20 7L12.9 12.05C12.35 12.44 11.65 12.44 11.1 12.05L4 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                            </span>
                            <input
                                class="input"
                                id="email"
                                type="text"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                inputmode="email"
                                placeholder="admin@pactopia360.com">
                        </div>
                    </div>

                    <div class="field">
                        <div class="field-head">
                            <label for="password">Contraseña</label>
                            <a class="link-muted" href="{{ route('admin.password.request') }}">¿Olvidaste tu contraseña?</a>
                        </div>

                        <div class="pwd-field input-wrap">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M8 10V7.5A4 4 0 0 1 12 3.5A4 4 0 0 1 16 7.5V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <rect x="5" y="10" width="14" height="10" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                            </span>
                            <input
                                class="input"
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Escribe tu contraseña"
                                minlength="6"
                                maxlength="200">
                            <button
                                type="button"
                                class="toggle"
                                id="btnTogglePassword"
                                aria-controls="password"
                                aria-pressed="false"
                                onclick="window.P360_togglePwd('password','btnTogglePassword')">Mostrar</button>
                        </div>
                    </div>

                    <div class="row">
                        <label class="remember">
                            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                            <span>Recordarme</span>
                        </label>
                    </div>

                    <button class="btn btn-primary" id="btnSubmit" type="submit">Entrar</button>

                    <div class="card-note">
                        Acceso exclusivo para administradores autorizados de Pactopia360.
                    </div>
                </form>
            </section>
        </section>
    </main>
</body>
</html>