<?php
// database/migrations/admin/2025_08_13_000110_fix_subscriptions_billing_cycle.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('subscriptions')) return;

        // 1) Si no existe la columna, la creamos con default 'monthly'
        if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'billing_cycle')) {
            Schema::connection($this->conn)->table('subscriptions', function (Blueprint $t) {
                // usamos ENUM para seguir la convención del proyecto
                $t->enum('billing_cycle', ['monthly', 'annual'])->default('monthly')->after('status');
            });
        } else {
            // 2) Normalizamos nulos → 'monthly' para evitar el error "cannot be null"
            DB::connection($this->conn)->table('subscriptions')
                ->whereNull('billing_cycle')->update(['billing_cycle' => 'monthly']);

            // 3) Nos aseguramos de que sea NOT NULL y con DEFAULT 'monthly'
            // Evitamos DBAL: usamos SQL directo (MySQL/MariaDB)
            try {
                DB::connection($this->conn)->statement(
                    "ALTER TABLE subscriptions 
                     MODIFY billing_cycle ENUM('monthly','annual') NOT NULL DEFAULT 'monthly'"
                );
            } catch (\Throwable $e) {
                // Si tu tipo es VARCHAR (no ENUM), cae aquí: lo dejamos con VARCHAR(10)
                DB::connection($this->conn)->statement(
                    "ALTER TABLE subscriptions 
                     MODIFY billing_cycle VARCHAR(10) NOT NULL DEFAULT 'monthly'"
                );
            }
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable('subscriptions')) return;
        // No eliminamos la columna para no romper ambientes previos
        // (si necesitas revertir: comenta lo de arriba y ajusta según tu caso)
    }
};
