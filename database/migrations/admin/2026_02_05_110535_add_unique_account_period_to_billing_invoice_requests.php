<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        // Defensivo: si no existe tabla, no romper deploy
        if (!Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            return;
        }

        // Evita duplicar índice si ya existe (MySQL)
        $idx = 'bir_account_period_unique';

        // MySQL: revisar índices existentes
        try {
            $indexes = Schema::connection($adm)
                ->getConnection()
                ->select("SHOW INDEX FROM billing_invoice_requests WHERE Key_name = ?", [$idx]);

            if (!empty($indexes)) return;
        } catch (\Throwable $e) {
            // si falla el check, intentamos crear (y si ya existe, MySQL lanzará error)
        }

        Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) use ($idx) {
            $table->unique(['account_id', 'period'], $idx);
        });
    }

    public function down(): void
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            return;
        }

        Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) {
            $table->dropUnique('bir_account_period_unique');
        });
    }
};
