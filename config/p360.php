<?php

return [
    'conn' => [
        'admin'   => env('P360_ADMIN_CONN', 'mysql_admin'),
        'clients' => env('P360_CLIENTS_CONN', 'mysql_clientes'),
    ],

    'features' => [
        'stats_logs' => env('P360_FEATURE_STATS_LOGS', true),
    ],

    /**
     * Datos de soporte / contacto (centraliza para evitar hardcode en vistas/controladores).
     * Recomendación: usa soporte@pactopia360.com cuando lo tengas listo para recibir.
     */
    'support' => [
        'email' => env('P360_SUPPORT_EMAIL', env('MAIL_REPLY_TO_ADDRESS', 'soporte@pactopia.com')),
        'name'  => env('P360_SUPPORT_NAME', env('MAIL_REPLY_TO_NAME', 'Soporte Pactopia360')),
    ],

    /**
     * Datos públicos / URLs (para evitar links hardcodeados).
     */
    'public' => [
        // URL pública principal (por defecto usa APP_URL)
        'site_url' => env('P360_SITE_URL', env('APP_URL', 'https://pactopia360.com')),
    ],

    /**
     * Datos para pago por transferencia (mostrar en modal de pago).
     * Nota: Idealmente esto lo administre el portal Admin (settings),
     * pero mientras tanto va por .env.
     */
    'transfer' => [
        'name'  => env('P360_TRANSFER_NAME', ''),
        'clabe' => env('P360_TRANSFER_CLABE', ''),
        'bank'  => env('P360_TRANSFER_BANK', ''),
        'rfc'   => env('P360_TRANSFER_RFC', ''),
    ],

    /**
     * ✅ Pricing canónico por plan.
     * - Valores en MXN (pesos).
     * - Si modo_cobro = anual, se puede dividir a mensual para UI (o cobrar anual si así lo decides).
     *
     * Puedes sobreescribir por .env si quieres:
     * P360_PRICE_PRO_M=999
     * P360_PRICE_PRO_A=9990
     */
    'pricing' => [
        'currency' => env('P360_BILLING_CURRENCY', 'mxn'),

        'plans' => [
            'FREE' => [
                'mensual' => (float) env('P360_PRICE_FREE_M', 0),
                'anual'   => (float) env('P360_PRICE_FREE_A', 0),
            ],
            'PRO' => [
                'mensual' => (float) env('P360_PRICE_PRO_M', 999),
                'anual'   => (float) env('P360_PRICE_PRO_A', 9990),
            ],
            'PREMIUM' => [
                'mensual' => (float) env('P360_PRICE_PREMIUM_M', 1499),
                'anual'   => (float) env('P360_PRICE_PREMIUM_A', 14990),
            ],
        ],
    ],
];
