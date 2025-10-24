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
    // - secret/key: credenciales API
    // - webhook_secret: firma para validar webhooks
    // - price_monthly/annual: IDs de precios (price_XXX en Stripe)
    // - display_price_*: precios de vitrina para mostrar en UI (MXN)
    'stripe' => [
        'secret'                 => env('STRIPE_SECRET'),
        'key'                    => env('STRIPE_KEY'),
        'webhook_secret'         => env('STRIPE_WEBHOOK_SECRET'),

        // IDs de precio en Stripe (price_xxx) para Checkout
        'price_monthly'          => env('STRIPE_PRICE_MONTHLY'),
        'price_annual'           => env('STRIPE_PRICE_ANNUAL'),

        // Precios visibles en UI (solo informativos)
        'display_price_monthly'  => (float) env('STRIPE_DISPLAY_PRICE_MONTHLY', 249.99),
        'display_price_annual'   => (float) env('STRIPE_DISPLAY_PRICE_ANNUAL', 1999.99),
    ],

    // ===== reCAPTCHA (registro/login si se desea) =====
    'recaptcha' => [
        'enabled' => (bool) env('RECAPTCHA_ENABLED', false),
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    // ===== OTP (opcional) =====
    // Activa uno de los drivers cuando vayas a enviar SMS/WhatsApp.
    // Implementación en un futuro OtpService:
    //   - driver: 'twilio' | 'whatsapp' | null
    //   - Los tokens/ids se leerán desde aquí
    'otp' => [
        'driver' => env('OTP_DRIVER', null), // 'twilio' | 'whatsapp'
        'twilio' => [
            'sid'   => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from'  => env('TWILIO_FROM'), // E.164
        ],
        'whatsapp' => [
            'token'         => env('WA_CLOUD_API_TOKEN'),
            'phone_number'  => env('WA_CLOUD_API_PHONE'),   //  WhatsApp Business number ID
            'app_id'        => env('WA_CLOUD_APP_ID'),
        ],
    ],


    'facturotopia' => [
        'base'  => env('FT_BASE', 'https://api-demo.facturotopia.com/api'),
        'token' => env('FT_TOKEN'), 
    ],

];
