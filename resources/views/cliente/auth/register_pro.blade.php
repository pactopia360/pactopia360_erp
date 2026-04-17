{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\auth\register_pro.blade.php --}}
@extends('layouts.guest')

@section('title', 'Registro PRO · Pactopia360')
@section('hide-brand', '1')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/client/css/register_pro.css') }}">
    <style>
        .field.field-required{
            position: relative;
        }

        .field.field-required .input{
            padding-right: 34px;
        }

        .field-required-mark{
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            color: #dc2626;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            pointer-events: none;
            z-index: 2;
        }
    </style>
@endpush

@section('content')
@php
    $priceMonthly = $price_monthly ?? config('services.stripe.display_price_monthly', 990.00);
    $priceAnnual  = $price_annual  ?? config('services.stripe.display_price_annual', 9990.00);

    $prefPlan  = old('plan', session('checkout_plan', 'mensual'));
    $isMensual = $prefPlan === 'mensual';
    $isAnual   = $prefPlan === 'anual';

    $recaptchaKey = config('services.recaptcha.site_key');
    $recaptchaOn  = (bool) config('services.recaptcha.enabled', false);
    $showRecaptcha = $recaptchaOn && !empty($recaptchaKey);
@endphp

<div class="wrap register-pro-wrap">
    <div class="register-pro-bg" aria-hidden="true">
        <span class="bg-orb bg-orb-1"></span>
        <span class="bg-orb bg-orb-2"></span>
        <span class="bg-orb bg-orb-3"></span>
        <span class="bg-grid"></span>
    </div>

    <section class="card register-pro-card" role="region" aria-label="Registro PRO">
        <button type="button" class="theme" id="rpThemeToggle" aria-label="Cambiar tema">
            <span id="rpThemeIcon">🌙</span>
        </button>

        <div class="brand">
            <img
                class="logo logo-main"
                src="{{ asset('assets/client/img/Pactopia - Letra AZUL.png') }}"
                alt="Pactopia360"
            >
        </div>

        <div class="title title-clean">
            <h1>Configura tu cuenta PRO</h1>
            <p>Recibirás acceso inmediato y pasos de pago para activar tu facturación ilimitada.</p>
        </div>

        @if (session('ok'))
            <div class="alert ok">{{ session('ok') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('cliente.registro.pro.do') }}" novalidate id="regProForm" class="form form-clean">
            @csrf

            <div class="hp" aria-hidden="true">
                <input type="text" name="hp_field" id="hp_field" tabindex="-1" autocomplete="off">
            </div>

            <div class="field field-required">
                <input
                    class="input @error('nombre') is-invalid @enderror"
                    type="text"
                    name="nombre"
                    id="nombre"
                    value="{{ old('nombre') }}"
                    required
                    maxlength="150"
                    placeholder="Nombre / Razón social"
                    aria-label="Nombre / Razón social"
                    aria-invalid="@error('nombre') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('nombre')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field field-required">
                <input
                    class="input @error('rfc') is-invalid @enderror"
                    type="text"
                    name="rfc"
                    id="rfc"
                    value="{{ old('rfc') }}"
                    required
                    maxlength="13"
                    placeholder="RFC"
                    oninput="this.value=this.value.toUpperCase()"
                    pattern="[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}"
                    aria-label="RFC"
                    aria-invalid="@error('rfc') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('rfc')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field field-required">
                <input
                    class="input @error('telefono') is-invalid @enderror"
                    type="tel"
                    name="telefono"
                    id="telefono"
                    value="{{ old('telefono') }}"
                    required
                    maxlength="25"
                    placeholder="Teléfono / WhatsApp"
                    aria-label="Teléfono / WhatsApp"
                    aria-invalid="@error('telefono') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('telefono')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field field-required">
                <input
                    class="input @error('email') is-invalid @enderror"
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="150"
                    autocomplete="email"
                    placeholder="Correo de contacto"
                    aria-label="Correo de contacto"
                    aria-invalid="@error('email') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('email')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field field-plan">
                <div class="plan-picker" role="radiogroup" aria-label="Selecciona un plan">
                    <label class="plan-opt {{ $isMensual ? 'is-selected' : '' }}" data-plan="mensual" aria-checked="{{ $isMensual ? 'true' : 'false' }}">
                        <input class="sr-only" type="radio" name="plan" value="mensual" @checked($isMensual)>
                        <div class="plan-head">
                            <span>Mensual</span>
                            <span class="plan-price">${{ number_format($priceMonthly, 2) }} MXN</span>
                        </div>
                        <div class="plan-desc">Pago mes a mes. Actívate hoy, factura hoy.</div>
                    </label>

                    <label class="plan-opt {{ $isAnual ? 'is-selected' : '' }}" data-plan="anual" aria-checked="{{ $isAnual ? 'true' : 'false' }}">
                        <input class="sr-only" type="radio" name="plan" value="anual" @checked($isAnual)>
                        <div class="plan-head">
                            <span>Anual</span>
                            <span class="plan-price">${{ number_format($priceAnnual, 2) }} MXN</span>
                        </div>
                        <div class="plan-desc">1 solo pago con ahorro y prioridad en soporte.</div>
                    </label>
                </div>
                @error('plan')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            <div class="terms terms-clean">
                <label for="terms">
                    <input
                        type="checkbox"
                        name="terms"
                        id="terms"
                        required
                        {{ old('terms') ? 'checked' : '' }}
                    >
                    <span>
                        Acepto los
                        <a href="{{ route('cliente.terminos') }}" target="_blank" rel="noopener">términos y condiciones</a>.
                    </span>
                </label>
                @error('terms')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            @if ($showRecaptcha)
                <div class="field recaptcha-wrap recaptcha-clean">
                    <div
                        class="g-recaptcha"
                        data-sitekey="{{ $recaptchaKey }}"
                        data-callback="rpCaptchaDone"
                        data-expired-callback="rpCaptchaExpired"
                        data-error-callback="rpCaptchaExpired"
                    ></div>
                    @error('g-recaptcha-response')
                        <div class="help error">{{ $message }}</div>
                    @enderror
                </div>
            @else
                <input type="hidden" name="g-recaptcha-response" value="local-bypass">
            @endif

            <div class="actions actions-clean">
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    Crear cuenta PRO
                </button>

                <a href="{{ route('cliente.registro.free') }}" class="btn-free">
                    ¿Mejor gratis?
                </a>
            </div>

            <div class="login-cta login-cta-clean">
                ¿Ya tienes cuenta?
                <a href="{{ route('cliente.login') }}">Inicia sesión</a>
            </div>
        </form>
    </section>
</div>

<div class="modal" id="rpModal" aria-hidden="true">
    <div class="modal-card">
        <h3 class="modal-title" id="rpModalTitle">Aviso</h3>
        <div class="modal-body" id="rpModalBody">Mensaje</div>
        <div class="modal-actions">
            <button class="btn-ghost" id="rpModalClose" type="button">Cerrar</button>
            <button class="btn btn-primary" id="rpModalOk" type="button">Entendido</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if ($showRecaptcha)
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const html = document.documentElement;
    const KEY  = 'p360-theme';
    const btn  = document.getElementById('rpThemeToggle');
    const ico  = document.getElementById('rpThemeIcon');
    const form = document.getElementById('regProForm');
    const submitBtn = document.getElementById('submitBtn');
    const planOptions = Array.from(document.querySelectorAll('.plan-opt'));
    const planInputs  = Array.from(document.querySelectorAll('.plan-opt input[type="radio"]'));

    const paint = () => {
        if (ico) {
            ico.textContent = (html.dataset.theme === 'dark') ? '☀️' : '🌙';
        }
    };

    const setTheme = (v) => {
        html.dataset.theme = v;
        localStorage.setItem(KEY, v);
        paint();
    };

    setTheme(localStorage.getItem(KEY) || 'light');

    btn?.addEventListener('click', () => {
        setTheme(html.dataset.theme === 'dark' ? 'light' : 'dark');
    });

    function syncPlanUi() {
        planOptions.forEach((opt) => {
            const input = opt.querySelector('input[type="radio"]');
            const checked = !!input?.checked;
            opt.classList.toggle('is-selected', checked);
            opt.setAttribute('aria-checked', checked ? 'true' : 'false');
        });
    }

    planOptions.forEach((opt) => {
        opt.addEventListener('click', function () {
            const input = this.querySelector('input[type="radio"]');
            if (input) {
                input.checked = true;
                syncPlanUi();
                validateCaptchaState();
            }
        });
    });

    planInputs.forEach((input) => {
        input.addEventListener('change', function () {
            syncPlanUi();
            validateCaptchaState();
        });
    });

    function hasRecaptchaResponse() {
        const response = document.querySelector('[name="g-recaptcha-response"]');
        return response && response.value && response.value.trim().length > 0;
    }

    function validateCaptchaState() {
        @if ($showRecaptcha)
            submitBtn.disabled = !hasRecaptchaResponse();
        @else
            submitBtn.disabled = false;
        @endif
    }

    window.rpCaptchaDone = function () {
        validateCaptchaState();
    };

    window.rpCaptchaExpired = function () {
        validateCaptchaState();
    };

    form?.addEventListener('submit', function () {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Procesando...';
        }
    });

    syncPlanUi();
    validateCaptchaState();
});
</script>
@endpush