<?php

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'cliente'),   // <— cliente por defecto
        'passwords' => env('AUTH_PASSWORD_BROKER', 'clientes'),
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'clientes',
        ],

        // Alias equivalente a 'web' para compatibilidad
        'cliente' => [
            'driver'   => 'session',
            'provider' => 'clientes',
        ],

        'admin' => [
            'driver'   => 'session',
            'provider' => 'usuario_administrativos',
        ],

        'api' => [
            'driver'   => 'token',
            'provider' => 'clientes',
            'hash'     => false,
        ],
    ],

    'providers' => [
        'clientes' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Cliente\UsuarioCuenta::class,
        ],

        'usuario_administrativos' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Admin\Auth\UsuarioAdministrativo::class,
        ],
    ],

    'passwords' => [
        'clientes' => [
            'provider'   => 'clientes',
            'table'      => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'     => 60,
            'throttle'   => 60,
            'connection' => env('DB_CLIENT_PASSWORDS_CONNECTION', 'mysql_clientes'),
        ],
        'cliente' => [
            'provider'   => 'clientes',
            'table'      => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'     => 60,
            'throttle'   => 60,
            'connection' => env('DB_CLIENT_PASSWORDS_CONNECTION', 'mysql_clientes'),
        ],
        'admins' => [
            'provider'   => 'usuario_administrativos',
            'table'      => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'     => 60,
            'throttle'   => 60,
            'connection' => env('DB_ADMIN_PASSWORDS_CONNECTION', 'mysql_admin'),
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
