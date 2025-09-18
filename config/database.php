<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Default "mysql" apuntando a la BD de admin.
    | Tu .env debe tener DB_DATABASE=p360v1_admin para local.
    |
    */
    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Conexiones pensadas para LOCAL (MySQL) y PRODUCCIÓN (MariaDB) usando
    | el mismo driver PDO MySQL. Charset/collation en utf8mb4 para ambos.
    |
    | - mysql           → admin por variables DB_* (default)
    | - mysql_admin     → admin explícito (DB_ADMIN_*)
    | - mysql_clientes  → clientes (DB_CLIENT_*)
    |
    */
    'connections' => [

        // --- SQLite opcional para pruebas/herramientas ---
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        // ============= DEFAULT → Admin (usa DB_*) =============
        'mysql' => [
            'driver' => 'mysql', // MariaDB en prod también usa este driver
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'p360v1_admin'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter(array_merge(
                [PDO::ATTR_EMULATE_PREPARES => false],
                (env('MYSQL_ATTR_SSL_CA') && defined('PDO::MYSQL_ATTR_SSL_CA')) ? [PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA')] : [],
                (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) ? [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false] : []
            )) : [],
        ],

        // ============= ADMIN explícita (p360v1_admin) =============
        'mysql_admin' => [
            'driver' => env('DB_ADMIN_CONNECTION', 'mysql'),
            'url' => env('DB_ADMIN_URL'),
            'host' => env('DB_ADMIN_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_ADMIN_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_ADMIN_DATABASE', env('DB_DATABASE', 'p360v1_admin')),
            'username' => env('DB_ADMIN_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_ADMIN_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_ADMIN_SOCKET', ''),
            'charset' => env('DB_ADMIN_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('DB_ADMIN_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter(array_merge(
                [PDO::ATTR_EMULATE_PREPARES => false],
                (env('DB_ADMIN_MYSQL_ATTR_SSL_CA') && defined('PDO::MYSQL_ATTR_SSL_CA')) ? [PDO::MYSQL_ATTR_SSL_CA => env('DB_ADMIN_MYSQL_ATTR_SSL_CA')] : [],
                (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) ? [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false] : []
            )) : [],
        ],

        // ============= CLIENTES (p360v1_clientes) =============
        'mysql_clientes' => [
            'driver' => env('DB_CLIENT_CONNECTION', 'mysql'),
            'url' => env('DB_CLIENT_URL'),
            'host' => env('DB_CLIENT_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_CLIENT_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_CLIENT_DATABASE', 'p360v1_clientes'),
            'username' => env('DB_CLIENT_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_CLIENT_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_CLIENT_SOCKET', ''),
            'charset' => env('DB_CLIENT_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('DB_CLIENT_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter(array_merge(
                [PDO::ATTR_EMULATE_PREPARES => false],
                (env('DB_CLIENT_MYSQL_ATTR_SSL_CA') && defined('PDO::MYSQL_ATTR_SSL_CA')) ? [PDO::MYSQL_ATTR_SSL_CA => env('DB_CLIENT_MYSQL_ATTR_SSL_CA')] : [],
                (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) ? [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false] : []
            )) : [],
        ],

        // ---- Otros drivers por compatibilidad (no usados ahora) ----
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | Conservamos tu estructura ampliada. Si prefieres el valor clásico
    | 'migrations' => 'migrations', dímelo y lo cambio.
    |
    */
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
