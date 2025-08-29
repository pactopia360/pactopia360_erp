<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('subscriptions')) return;

        Schema::connection($this->conn)->table('subscriptions', function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','plan_id')) {
                $t->unsignedBigInteger('plan_id')->nullable()->after('account_id')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','status')) {
                $t->enum('status', ['trial','active','past_due','canceled','paused','blocked'])->default('active')->after('plan_id')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','billing_cycle')) {
                $t->enum('billing_cycle',['monthly','annual'])->nullable()->after('status')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','current_period_start')) {
                $t->timestamp('current_period_start')->nullable()->after('billing_cycle');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','current_period_end')) {
                $t->timestamp('current_period_end')->nullable()->after('current_period_start');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','next_invoice_date')) {
                $t->date('next_invoice_date')->nullable()->after('current_period_end')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','grace_days')) {
                $t->unsignedSmallInteger('grace_days')->default(5)->after('next_invoice_date');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions','auto_renew')) {
                $t->boolean('auto_renew')->default(true)->after('grace_days');
            }
        });
    }

    public function down(): void
    {
        // Idempotente: no borramos columnas para no romper datos en entornos compartidos
    }
};
