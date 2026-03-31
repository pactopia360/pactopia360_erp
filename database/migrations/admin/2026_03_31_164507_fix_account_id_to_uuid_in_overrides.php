<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $sc = Schema::connection($this->conn);

        if (! $sc->hasTable('billing_statement_status_overrides')) {
            return;
        }

        // 1) Agregar columna temporal UUID
        if (! $sc->hasColumn('billing_statement_status_overrides', 'account_id_uuid')) {
            $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->string('account_id_uuid', 36)->nullable()->after('account_id');
            });
        }

        // 2) Mapear BIGINT → UUID usando accounts
        DB::connection($this->conn)->statement("
            UPDATE billing_statement_status_overrides o
            JOIN accounts a ON a.id = o.account_id
            SET o.account_id_uuid = a.uuid
        ");

        // 3) Validar
        $invalid = DB::connection($this->conn)
            ->table('billing_statement_status_overrides')
            ->whereNull('account_id_uuid')
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException('No se pudieron mapear algunos account_id a UUID');
        }

        // 4) Drop índices
        DB::connection($this->conn)->statement("
            ALTER TABLE billing_statement_status_overrides
            DROP INDEX bsso_account_period_unique
        ");

        // 5) Drop columna vieja
        $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
            $table->dropColumn('account_id');
        });

        // 6) Renombrar
        $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
            $table->renameColumn('account_id_uuid', 'account_id');
        });

        // 7) Re-crear índice
        $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
            $table->index('account_id');
            $table->unique(['account_id', 'period'], 'bsso_account_period_unique');
        });
    }

    public function down(): void
    {
        // no reversible
    }
};