<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $connection, string $table, string $indexName): bool
    {
        $dbName = (string) DB::connection($connection)->getDatabaseName();

        $row = DB::connection($connection)->selectOne(
            "SELECT COUNT(1) AS c
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?",
            [$dbName, $table, $indexName]
        );

        return (int)($row->c ?? 0) > 0;
    }

    public function up(): void
    {
        /**
         * ✅ Guard: esta migración ES SOLO para mysql_admin.
         * Si corres migrate con --database=mysql_clientes, NO hacemos nada y evitamos que truene.
         */
        $runnerConn = Schema::getConnection()->getName(); // conexión del migrator en esta corrida
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        // Operamos explícitamente sobre mysql_admin
        $conn  = 'mysql_admin';
        $table = 'payments';

        if (!Schema::connection($conn)->hasTable($table)) {
            return;
        }

        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            // Si tu tabla ya tiene stripe_session_id, sólo cuidamos índices.
            // (Si no existe, no tocamos nada.)
            if (!Schema::connection($conn)->hasColumn($table, 'stripe_session_id')) {
                return;
            }

            // El índice problemático que te está tronando:
            // payments_stripe_sess_idx2(stripe_session_id)
            // Lo agregamos SOLO si no existe.
        });

        // Agregar índice de manera segura (fuera del closure para poder consultar information_schema)
        if (Schema::connection($conn)->hasColumn($table, 'stripe_session_id')) {
            $idx = 'payments_stripe_sess_idx2';

            if (!$this->indexExists($conn, $table, $idx)) {
                Schema::connection($conn)->table($table, function (Blueprint $t) use ($idx) {
                    $t->index(['stripe_session_id'], $idx);
                });
            }
        }
    }

    public function down(): void
    {
        // ✅ Mismo guard
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        $conn  = 'mysql_admin';
        $table = 'payments';

        if (!Schema::connection($conn)->hasTable($table)) {
            return;
        }

        $idx = 'payments_stripe_sess_idx2';

        // Drop seguro si existe
        $dbName = (string) DB::connection($conn)->getDatabaseName();
        $row = DB::connection($conn)->selectOne(
            "SELECT COUNT(1) AS c
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?",
            [$dbName, $table, $idx]
        );

        if ((int)($row->c ?? 0) > 0) {
            Schema::connection($conn)->table($table, function (Blueprint $t) use ($idx) {
                $t->dropIndex($idx);
            });
        }
    }
};
