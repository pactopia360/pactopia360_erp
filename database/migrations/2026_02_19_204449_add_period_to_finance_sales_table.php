<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        // ✅ Checa fuera del closure (evita el problema del scope)
        if (!Schema::connection($conn)->hasTable('finance_sales')) {
            return;
        }

        if (Schema::connection($conn)->hasColumn('finance_sales', 'period')) {
            return;
        }

        Schema::connection($conn)->table('finance_sales', function (Blueprint $table) {
            // YYYY-MM (2026-02)
            $table->string('period', 7)->nullable()->after('account_id');
            $table->index('period', 'finance_sales_period_idx');
        });
    }

    public function down(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($conn)->hasTable('finance_sales')) {
            return;
        }

        if (!Schema::connection($conn)->hasColumn('finance_sales', 'period')) {
            return;
        }

        Schema::connection($conn)->table('finance_sales', function (Blueprint $table) {
            // Drop index si existe (en MySQL suele ser necesario antes del drop column)
            try { $table->dropIndex('finance_sales_period_idx'); } catch (\Throwable $e) {}
            $table->dropColumn('period');
        });
    }
};
