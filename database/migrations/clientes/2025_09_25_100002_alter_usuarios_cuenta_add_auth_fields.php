<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    // Usar propiedad pública SIN tipo
    public $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn  = $this->connection;
        $table = 'usuarios_cuenta';

        if (!Schema::connection($conn)->hasTable($table)) {
            return; // nada que alterar
        }

        // ===== Cols (solo si faltan)
        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            if (!Schema::connection($conn)->hasColumn($table, 'remember_token')) {
                $t->rememberToken()->nullable();
            }
            if (!Schema::connection($conn)->hasColumn($table, 'must_change_password')) {
                $t->boolean('must_change_password')->default(true)->after('password');
            }
            if (!Schema::connection($conn)->hasColumn($table, 'ultimo_login_at')) {
                $t->timestamp('ultimo_login_at')->nullable()->after('updated_at');
            }
            if (!Schema::connection($conn)->hasColumn($table, 'ip_ultimo_login')) {
                $t->string('ip_ultimo_login', 45)->nullable()->after('ultimo_login_at');
            }
            if (!Schema::connection($conn)->hasColumn($table, 'sync_version')) {
                $t->unsignedInteger('sync_version')->default(1)->after('ip_ultimo_login');
            }
        });

        // ===== Índices (solo si no existen)
        $addIndex = function (string $indexName, \Closure $callback) use ($conn, $table) {
            if (!$this->indexExists($conn, $table, $indexName)) {
                Schema::connection($conn)->table($table, function (Blueprint $t) use ($callback) {
                    $callback($t);
                });
            }
        };

        // activo
        $addIndex('usuarios_cuenta_activo_index', function (Blueprint $t) {
            $t->index('activo', 'usuarios_cuenta_activo_index');
        });

        // cuenta_id
        $addIndex('usuarios_cuenta_cuenta_id_index', function (Blueprint $t) {
            $t->index('cuenta_id', 'usuarios_cuenta_cuenta_id_index');
        });

        // email (index simple; si ya tienes UNIQUE, este bloque se saltará por nombre distinto)
        $addIndex('usuarios_cuenta_email_index', function (Blueprint $t) {
            $t->index('email', 'usuarios_cuenta_email_index');
        });
    }

    public function down(): void
    {
        $conn  = $this->connection;
        $table = 'usuarios_cuenta';
        if (!Schema::connection($conn)->hasTable($table)) return;

        // Quitar índices si existen (no falla si no están)
        foreach ([
            'usuarios_cuenta_activo_index',
            'usuarios_cuenta_cuenta_id_index',
            'usuarios_cuenta_email_index',
        ] as $idx) {
            if ($this->indexExists($conn, $table, $idx)) {
                Schema::connection($conn)->table($table, function (Blueprint $t) use ($idx) {
                    $t->dropIndex($idx);
                });
            }
        }

        // Quitar columnas si existen
        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            foreach (['sync_version','ip_ultimo_login','ultimo_login_at','must_change_password','remember_token'] as $col) {
                if (Schema::connection($conn)->hasColumn($table, $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }

    /** Checa si un índice existe consultando information_schema */
    private function indexExists(string $connection, string $table, string $indexName): bool
    {
        $db = DB::connection($connection)->getDatabaseName();
        return DB::connection($connection)->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
