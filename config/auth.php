<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // Controlado por .env (AUTH_GUARD). Para admin usa "admin"; para front clientes usa "web".
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'clientes'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    | web   -> sesión para clientes (frontend)
    | admin -> sesión para administradores/backoffice
    */
    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'clientes',
        ],

        'admin' => [
            'driver'   => 'session',
            'provider' => 'usuario_administrativos',
        ],

        // Opcional: token simple para APIs internas de clientes
        'api' => [
            'driver'   => 'token',
            'provider' => 'clientes',
            'hash'     => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        // Clientes (frontend)
        'clientes' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Cliente\UsuarioCuenta::class,
        ],

        // Admins (backoffice)
        'usuario_administrativos' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Admin\Auth\UsuarioAdministrativo::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Settings
    |--------------------------------------------------------------------------
    | Nota: Los brokers apuntan a la tabla unificada "password_reset_tokens".
    | Si manejas BD separadas, puedes crear la tabla en ambas y (opcionalmente)
    | indicar una conexión preferida con las variables de entorno.
    */
    'passwords' => [
        'clientes' => [
            'provider'   => 'clientes',
            'table'      => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'     => 60,
            'throttle'   => 60,
            // Algunas instalaciones personalizan el repositorio con conexión:
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

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
