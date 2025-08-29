<?php
// database/migrations/admin/2025_08_12_000004_create_subscriptions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        // Si no existe la tabla, créala completa con las columnas necesarias
        if (!Schema::connection($this->conn)->hasTable('subscriptions')) {
            Schema::connection($this->conn)->create('subscriptions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('account_id')->index(); // relación lógica con accounts (sin FK cross-DB)
                $t->unsignedBigInteger('plan_id')->nullable()->index(); // relations con plans/plans/planes
                $t->enum('status', ['trial', 'active', 'past_due', 'canceled', 'paused'])->default('active');
                $t->enum('billing_cycle', ['monthly', 'annual'])->default('monthly'); // <- NOT NULL
                $t->timestamp('current_period_start')->nullable();
                $t->timestamp('current_period_end')->nullable();
                $t->date('next_invoice_date')->nullable();
                $t->unsignedSmallInteger('grace_days')->default(0);
                $t->boolean('auto_renew')->default(true);
                $t->json('meta')->nullable(); // tracking de cupones, origen, etc.
                $t->timestamps();

                // en caso de querer único por account
                $t->unique(['account_id'], 'uniq_sub_by_account');
            });
            return;
        }

        // Si ya existe, garantizar columnas críticas (idempotente)
        Schema::connection($this->conn)->table('subscriptions', function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'account_id')) {
                $t->unsignedBigInteger('account_id')->index()->after('id');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'plan_id')) {
                $t->unsignedBigInteger('plan_id')->nullable()->index()->after('account_id');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'status')) {
                $t->enum('status', ['trial','active','past_due','canceled','paused'])->default('active')->after('plan_id');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'billing_cycle')) {
                $t->enum('billing_cycle', ['monthly','annual'])->default('monthly')->after('status');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'current_period_start')) {
                $t->timestamp('current_period_start')->nullable()->after('billing_cycle');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'current_period_end')) {
                $t->timestamp('current_period_end')->nullable()->after('current_period_start');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'next_invoice_date')) {
                $t->date('next_invoice_date')->nullable()->after('current_period_end');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'grace_days')) {
                $t->unsignedSmallInteger('grace_days')->default(0)->after('next_invoice_date');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'auto_renew')) {
                $t->boolean('auto_renew')->default(true)->after('grace_days');
            }
            if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'meta')) {
                $t->json('meta')->nullable()->after('auto_renew');
            }
        });

        // Asegurar unique lógico por cuenta
        if (!Schema::connection($this->conn)->hasColumn('subscriptions', 'account_id')) return;
        // El helper para unique puede fallar si ya existe; lo intentamos suavemente
        try {
            Schema::connection($this->conn)->table('subscriptions', function (Blueprint $t) {
                $t->unique(['account_id'], 'uniq_sub_by_account');
            });
        } catch (\Throwable $e) {
            // noop
        }
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('subscriptions');
    }
};
