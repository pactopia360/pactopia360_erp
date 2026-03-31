<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $sc = Schema::connection($this->conn);

        if (! $sc->hasTable('billing_statements')) {
            return;
        }

        // 1) Crear columna temporal si no existe
        if (! $sc->hasColumn('billing_statements', 'account_id_tmp')) {
            $sc->table('billing_statements', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id_tmp')->nullable()->after('account_id');
            });
        }

        // 2) Migrar datos string -> bigint
        DB::connection($this->conn)->statement("
            UPDATE billing_statements
            SET account_id_tmp = CAST(account_id AS UNSIGNED)
            WHERE account_id IS NOT NULL
        ");

        // 3) Validar que todo convirtió bien
        $invalid = DB::connection($this->conn)
            ->table('billing_statements')
            ->whereNull('account_id_tmp')
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException('Hay account_id no convertibles a bigint en billing_statements.');
        }

        // 4) Tirar índices viejos si existen
        try {
            DB::connection($this->conn)->statement("
                ALTER TABLE billing_statements
                DROP INDEX uq_statement_account_period
            ");
        } catch (\Throwable $e) {
            // ignorar si no existe
        }

        try {
            DB::connection($this->conn)->statement("
                ALTER TABLE billing_statements
                DROP INDEX billing_statements_account_id_index
            ");
        } catch (\Throwable $e) {
            // ignorar si no existe
        }

        // 5) Eliminar columna vieja
        if ($sc->hasColumn('billing_statements', 'account_id')) {
            $sc->table('billing_statements', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }

        // 6) Renombrar temporal a definitiva
        if ($sc->hasColumn('billing_statements', 'account_id_tmp')) {
            $sc->table('billing_statements', function (Blueprint $table) {
                $table->renameColumn('account_id_tmp', 'account_id');
            });
        }

        // 7) Recrear índices
        $sc->table('billing_statements', function (Blueprint $table) {
            $table->index('account_id', 'billing_statements_account_id_index');
            $table->unique(['account_id', 'period'], 'uq_statement_account_period');
        });
    }

    public function down(): void
    {
        // No reversible de forma segura.
    }
};