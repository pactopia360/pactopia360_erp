<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    | Define aquí las credenciales/ajustes de servicios externos.
    | Todas las llaves se leen desde .env para no exponer secretos.
    */

    // ===== Email providers =====
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // ===== Slack (notificaciones) =====
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ===== Stripe (pagos / suscripciones) =====
    'stripe' => [
        'secret'                 => env('STRIPE_SECRET'),
        'key'                    => env('STRIPE_KEY'),
        'webhook_secret'         => env('STRIPE_WEBHOOK_SECRET'),

        // IDs de precio en Stripe (price_xxx) para Checkout
        'price_monthly'          => env('STRIPE_PRICE_MONTHLY'),
        'price_annual'           => env('STRIPE_PRICE_ANNUAL'),

        // Precios visibles en UI (solo informativos)
        'display_price_monthly'  => (float) env('STRIPE_DISPLAY_PRICE_MONTHLY', 990.00),
        'display_price_annual'   => (float) env('STRIPE_DISPLAY_PRICE_ANNUAL', 9990.00),
    ],

    // ===== reCAPTCHA =====
    'recaptcha' => [
        'enabled'    => (bool) env('RECAPTCHA_ENABLED', false),
        'site_key'   => (string) env('RECAPTCHA_SITE_KEY', ''),
        'secret_key' => (string) env('RECAPTCHA_SECRET_KEY', ''),
    ],

    // ===== OTP =====
    'otp' => [
        'driver'  => env('OTP_DRIVER', 'whatsapp'),
        'channel' => env('OTP_CHANNEL', 'whatsapp'),

        'whatsapp' => [
            'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        ],
    ],

    // ===== Twilio =====
    'twilio' => [
        'sid'           => env('TWILIO_ACCOUNT_SID'),
        'token'         => env('TWILIO_AUTH_TOKEN'),
        'from'          => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    // ===== WhatsApp Cloud (Meta) =====
    'whatsapp_cloud' => [
        'token'           => env('WHATSAPP_CLOUD_TOKEN'),
        'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_ID'),
    ],

    // ===== SAT / servicios relacionados =====
    'sat' => [
        'download' => [
            'driver'         => env('SAT_DOWNLOAD_DRIVER', 'multi'),
            'providers'      => array_values(array_filter(array_map('trim', explode(',', env('SAT_DOWNLOAD_PROVIDERS', 'satws'))))),
            'failover'       => (bool) env('SAT_DOWNLOAD_FAILOVER', true),
            'http_timeout'   => (int) env('SAT_DOWNLOAD_HTTP_TIMEOUT', 60),
            'price_per_gb'   => (float) env('SAT_PRICE_PER_GB', 100),
            'ttl_hours'      => (int) env('SAT_DOWNLOAD_TTL_HOURS', 72),
            'demo_ttl_hours' => (int) env('SAT_DOWNLOAD_DEMO_TTL_HOURS', 24),
        ],

        'vault' => [
            'base_gb_pro'   => (float) env('SAT_VAULT_BASE_GB_PRO', 0),
            'force_enabled' => (bool) env('VAULT_FORCE_ENABLED', false),
        ],
    ],

    // ===== Facturotopia =====
    'facturotopia' => [
        // sandbox | production
        'mode' => (string) env('FACTUROTOPIA_MODE', 'sandbox'),

        // api_comprobantes
        'flow' => (string) env('FACTUROTOPIA_FLOW', 'api_comprobantes'),

        // bearer | apikey
        'auth_scheme' => strtolower((string) env('FACTUROTOPIA_AUTH_SCHEME', 'bearer')),

        // multi-tenant
        'tenancy'        => (string) env('FACTUROTOPIA_TENANCY', ''),
        'tenancy_header' => (string) env('FACTUROTOPIA_TENANCY_HEADER', 'X-Tenancy'),

        // emisor por defecto para comprobantes
        'emisor_id' => (string) env('FACTUROTOPIA_EMISOR_ID', ''),

        // Entorno sandbox/demo
        'sandbox' => [
            'base'  => rtrim((string) env('FT_BASE', env('FACTUROTOPIA_BASE_URL_TEST', 'https://api-demo.facturotopia.com/api')), '/'),
            'token' => (string) env('FT_TOKEN', env('FACTUROTOPIA_API_KEY_TEST', '')),
        ],

        // Entorno producción
        'production' => [
            'base'  => rtrim((string) env('FACTUROTOPIA_BASE_URL', 'https://api.facturotopia.com'), '/'),
            'token' => (string) env('FACTUROTOPIA_API_KEY_LIVE', ''),
        ],

        // Compatibilidad hacia atrás
        'base_url' => rtrim((string) (
            env('FACTUROTOPIA_MODE', 'sandbox') === 'production'
                ? env('FACTUROTOPIA_BASE_URL', 'https://api.facturotopia.com')
                : env('FT_BASE', env('FACTUROTOPIA_BASE_URL_TEST', 'https://api-demo.facturotopia.com/api'))
        ), '/'),

        'api_key' => (string) (
            env('FACTUROTOPIA_MODE', 'sandbox') === 'production'
                ? env('FACTUROTOPIA_API_KEY_LIVE', '')
                : env('FT_TOKEN', env('FACTUROTOPIA_API_KEY_TEST', ''))
        ),

        'api_key_test' => (string) env('FACTUROTOPIA_API_KEY_TEST', env('FT_TOKEN', '')),
        'api_key_live' => (string) env('FACTUROTOPIA_API_KEY_LIVE', ''),
    ],

];