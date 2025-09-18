<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        // Clientes (frontend)
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Administradores (ERP maestro)
        'admin' => [
            'driver'   => 'session',
            'provider' => 'usuario_administrativos',
        ],
    ],

    'providers' => [
        // Usuarios cliente
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', App\Models\User::class),
        ],

        // Usuarios administrativos
        'usuario_administrativos' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Admin\Auth\UsuarioAdministrativo::class,
        ],
    ],

    'passwords' => [
        // Broker para usuarios cliente
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],

        // Broker opcional para admins (usa misma tabla de tokens)
        'admins' => [
            'provider' => 'usuario_administrativos',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
