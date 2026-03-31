<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $sc = Schema::connection($this->conn);

        if (!$sc->hasTable('billing_statements')) {
            return;
        }

        DB::connection($this->conn)->beginTransaction();

        try {

            // 1. Agregar columna temporal BIGINT
            if (!$sc->hasColumn('billing_statements', 'account_id_tmp')) {
                $sc->table('billing_statements', function (Blueprint $t) {
                    $t->unsignedBigInteger('account_id_tmp')->nullable()->after('account_id');
                });
            }

            // 2. Migrar datos (string → int)
            DB::connection($this->conn)->statement("
                UPDATE billing_statements
                SET account_id_tmp = CAST(account_id AS UNSIGNED)
                WHERE account_id IS NOT NULL
            ");

            // 3. Validación: detectar registros inválidos
            $invalid = DB::connection($this->conn)->table('billing_statements')
                ->whereNull('account_id_tmp')
                ->count();

            if ($invalid > 0) {
                throw new \Exception("Hay account_id no convertibles a bigint en billing_statements");
            }

            // 4. Eliminar índice UNIQUE viejo
            DB::connection($this->conn)->statement("
                ALTER TABLE billing_statements
                DROP INDEX uq_statement_account_period
            ");

            // 5. Eliminar columna vieja
            $sc->table('billing_statements', function (Blueprint $t) {
                $t->dropColumn('account_id');
            });

            // 6. Renombrar columna nueva
            $sc->table('billing_statements', function (Blueprint $t) {
                $t->renameColumn('account_id_tmp', 'account_id');
            });

            // 7. Re-crear índice
            $sc->table('billing_statements', function (Blueprint $t) {
                $t->index('account_id');
                $t->unique(['account_id', 'period'], 'uq_statement_account_period');
            });

            DB::connection($this->conn)->commit();

        } catch (\Throwable $e) {
            DB::connection($this->conn)->rollBack();
            throw $e;
        }
    }

    public function down(): void
    {
        // No reversible de forma segura
    }
};