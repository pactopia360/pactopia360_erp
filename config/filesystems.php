<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'sat_downloads_disk' => 'sat_zip',

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
            'report' => false,
        ],

        'private' => [
            'driver'     => 'local',
            'root'       => storage_path('app/private'),
            'visibility' => 'private',
            'throw'      => false,
            'report'     => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
            'report'     => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'report'                  => false,
        ],

        'sat_downloads' => [
            'driver'     => 'local',
            'root'       => storage_path('app/sat_downloads'),
            'visibility' => 'private',
            'throw'      => false,
            'report'     => false,
        ],

        // ZIPs de descargas SAT
        'sat_zip' => [
            'driver'     => 'local',
            'root'       => storage_path('app/sat'),
            'visibility' => 'private',
            'throw'      => false,
            'report'     => false,
        ],

        // Legacy disk
        'sat' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'throw'  => false,
            'report' => false,
        ],

        /*
        |----------------------------------------------------------
        | SAT VAULT – Bóveda Fiscal (ZIP/XML/PDF ya ingestados)
        |----------------------------------------------------------
        | Este disco es para almacenamiento privado de bóveda.
        | Root sugerido:
        |   storage/app/sat_vault
        */
        'sat_vault' => [
            'driver'     => 'local',
            'root'       => storage_path('app/sat_vault'),
            'visibility' => 'private',
            'throw'      => false,
            'report'     => false,
        ],

        /*
        |----------------------------------------------------------
        | Alias "vault" (por si algunos módulos usan Storage::disk('vault'))
        |----------------------------------------------------------
        */
        'vault' => [
            'driver'     => 'local',
            'root'       => storage_path('app/sat_vault'),
            'visibility' => 'private',
            'throw'      => false,
            'report'     => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
