<?php
// database/migrations/admin/2025_08_13_000010_alter_subscriptions_add_billing_cycle_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_admin';
    private string $table = 'subscriptions';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            // Si no existe, créala mínima para que el seeder funcione.
            Schema::connection($this->conn)->create($this->table, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('account_id')->index();
                $t->unsignedBigInteger('plan_id')->nullable()->index();
                $t->enum('status', ['trial','active','past_due','canceled','paused'])->default('active')->index();
                $t->enum('billing_cycle', ['monthly','annual'])->default('monthly')->index();
                $t->timestamp('current_period_start')->nullable();
                $t->timestamp('current_period_end')->nullable();
                $t->date('next_invoice_date')->nullable();
                $t->unsignedInteger('grace_days')->default(0);
                $t->boolean('auto_renew')->default(true);
                $t->timestamps();
                $t->softDeletes();
            });
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'plan_id')) {
                $t->unsignedBigInteger('plan_id')->nullable()->index()->after('account_id');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'status')) {
                $t->enum('status', ['trial','active','past_due','canceled','paused'])->default('active')->index()->after('plan_id');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'billing_cycle')) {
                $t->enum('billing_cycle', ['monthly','annual'])->default('monthly')->index()->after('status');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'current_period_start')) {
                $t->timestamp('current_period_start')->nullable()->after('billing_cycle');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'current_period_end')) {
                $t->timestamp('current_period_end')->nullable()->after('current_period_start');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'next_invoice_date')) {
                $t->date('next_invoice_date')->nullable()->after('current_period_end');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'grace_days')) {
                $t->unsignedInteger('grace_days')->default(0)->after('next_invoice_date');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'auto_renew')) {
                $t->boolean('auto_renew')->default(true)->after('grace_days');
            }
            if (!Schema::connection($this->conn)->hasColumns($this->table, ['created_at','updated_at'])) {
                $t->timestamps();
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // No tiramos la tabla; sólo quitamos las columnas añadidas.
        if (!Schema::connection($this->conn)->hasTable($this->table)) return;

        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            foreach (['billing_cycle','current_period_start','current_period_end','next_invoice_date','grace_days','auto_renew'] as $col) {
                if (Schema::connection($this->conn)->hasColumn($this->table, $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
