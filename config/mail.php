<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',

            // NO uses scheme/url para evitar DSN vacío
            'host' => env('MAIL_HOST', 'mail.pactopia.com'),
            'port' => (int) env('MAIL_PORT', 465),
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'),

            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),

            'timeout' => null,
            'auth_mode' => null,

            'local_domain' => env(
                'MAIL_EHLO_DOMAIN',
                parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)
            ),
        ],

        'ses' => ['transport' => 'ses'],
        'postmark' => ['transport' => 'postmark'],
        'resend' => ['transport' => 'resend'],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => ['transport' => 'array'],

        'failover' => [
            'transport' => 'failover',
            'mailers' => ['smtp', 'log'],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'notificaciones@pactopia.com'),
        'name' => env('MAIL_FROM_NAME', 'PACTOPIA360 Notificaciones'),
    ],

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS', null),
        'name' => env('MAIL_REPLY_TO_NAME', null),
    ],

];
