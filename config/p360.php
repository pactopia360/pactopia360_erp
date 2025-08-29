<?php

return [
    'conn' => [
        'admin'   => env('P360_ADMIN_CONN', 'mysql_admin'),
        'clients' => env('P360_CLIENTS_CONN', 'mysql_clientes'),
    ],
    'features' => [
        'stats_logs' => env('P360_FEATURE_STATS_LOGS', true),
    ],
];
