<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Pactopia360'),

    /*
    |--------------------------------------------------------------------------
    | Application Version (opcional)
    |--------------------------------------------------------------------------
    | Puedes definir APP_VERSION en .env o crear un archivo VERSION en la raíz.
    */
    'version' => env('APP_VERSION', (function () {
        $path = base_path('VERSION');
        return is_file($path) ? trim((string) @file_get_contents($path)) : null;
    })()),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Asset URL (opcional, útil detrás de CDN)
    |--------------------------------------------------------------------------
    */
    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    | Alineado a tu huso horario en México.
    */
    'timezone' => env('APP_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key & Cipher
    |--------------------------------------------------------------------------
    */
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    | driver: file | cache
    | store: usa 'database' si config/cache apunta a database/redis.
    */
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store'  => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deploy Secret (hooks/healthchecks)
    |--------------------------------------------------------------------------
    */
    'deploy_secret' => env('APP_DEPLOY_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | Superadmins (lista separada por comas en .env)
    |--------------------------------------------------------------------------
    */
    'superadmins' => array_filter(
        array_map('trim', explode(',', (string) env('APP_SUPERADMINS', '')))
    ),

    'providers' => Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        // ---- Tus providers de aplicación ----
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class, // opcional
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

        // ---- Tu provider de vistas personalizado ----
        App\Providers\ViewServiceProvider::class,
    ])->toArray(),


    'aliases' => Illuminate\Support\Facades\Facade::defaultAliases()->merge([
        // Alias personalizados opcionales...
    ])->toArray(),


];
