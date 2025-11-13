<?php

return [
    'storage' => [
        'base' => storage_path('app/sat'),
        'packages' => storage_path('app/sat/packages'),
        'temp' => storage_path('app/sat/tmp'),
    ],

    // Tamaño máximo de ventana de fechas por request (SAT recomienda <= 40 días)
    'max_days' => 40,

    // Timeout HTTP (seg) para el cliente SAT
    'http_timeout' => 60,
];
