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
        'display_price_monthly'  => (float) env('STRIPE_DISPLAY_PRICE_MONTHLY', 990.00),
        'display_price_annual'   => (float) env('STRIPE_DISPLAY_PRICE_ANNUAL', 9990.00),
    ],

    // ===== reCAPTCHA (registro/login si se desea) =====
    'recaptcha' => [
        'enabled'    => (bool) env('RECAPTCHA_ENABLED', false),
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    // OTP (WhatsApp / SMS)
    'otp' => [
        // 'whatsapp' -> usa WhatsApp (Meta o Twilio), 'twilio' -> SMS por Twilio
        'driver'   => env('OTP_DRIVER', 'whatsapp'),

        // Cuando driver = 'whatsapp'
        'whatsapp' => [
            // 'meta' para WhatsApp Cloud API, 'twilio' para WhatsApp via Twilio
            'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        ],
    ],

    // Twilio (SMS y/o WhatsApp vía Twilio)
    'twilio' => [
        'sid'            => env('TWILIO_ACCOUNT_SID'),
        'token'          => env('TWILIO_AUTH_TOKEN'),
        'from'           => env('TWILIO_SMS_FROM'),        // ej: +12025550123
        'whatsapp_from'  => env('TWILIO_WHATSAPP_FROM'),   // ej: whatsapp:+14155238886
    ],

    // WhatsApp Cloud (Meta)
    'whatsapp_cloud' => [
        'token'           => env('WHATSAPP_CLOUD_TOKEN'),        // Bearer
        'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_ID'),     // ID del nº remitente
    ],

    'facturotopia' => [
        'base'  => env('FT_BASE', 'https://api-demo.facturotopia.com/api'),
        'token' => env('FT_TOKEN'),
    ],

    // SAT / servicios relacionados
    'sat' => [
        // driver legacy general (si tienes algo que use esto)
        'driver' => env('SAT_DRIVER', 'fake'),

        // si usas algún proxy tipo Facturotopia
        'base'   => env('FT_BASE', ''),
        'token'  => env('FT_TOKEN', ''),

        // Config específico para descarga masiva vía SATWS (phpcfdi/sat-ws-descarga-masiva)
        'download' => [
            // 'satws' = directo al SAT, 'fake' o 'database' para otros drivers
            'driver'       => env('SAT_DOWNLOAD_DRIVER', 'satws'),
            // timeout HTTP en segundos
            'http_timeout' => (int) env('SAT_DOWNLOAD_HTTP_TIMEOUT', 60),
        ],
    ],

];
