<?php
// database/migrations/admin/2025_08_12_000004_create_or_fix_subscriptions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_admin';
    private string $table = 'subscriptions';

    public function up(): void
    {
        // 1) Crear la tabla si NO existe
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            Schema::connection($this->conn)->create($this->table, function (Blueprint $t) {
                $t->bigIncrements('id');

                // Relaciones
                $t->unsignedBigInteger('account_id')->index();
                $t->unsignedBigInteger('plan_id')->nullable()->index();

                // Estado y ciclo
                $t->enum('status', ['trial','active','past_due','canceled','paused'])->default('active');
                $t->enum('billing_cycle', ['monthly','annual'])->default('monthly');

                // Ventanas de periodo
                $t->timestamp('current_period_start')->nullable();
                $t->timestamp('current_period_end')->nullable();
                $t->date('next_invoice_date')->nullable();

                // Reglas
                $t->unsignedInteger('grace_days')->default(0);
                $t->boolean('auto_renew')->default(true);

                // Metadatos de cálculo / sync opcionales
                $t->json('meta')->nullable();

                $t->timestamps();
                $t->softDeletes();

                // FKs (suaves: si las tablas aún no existen, puedes comentar y luego re-habilitar)
                // $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
                // $t->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            });
            return;
        }

        // 2) Si ya existe, asegurar columnas faltantes (idempotente)
        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            $has = fn(string $col) => Schema::connection($this->conn)->hasColumn($this->table, $col);

            if (!$has('account_id'))        $t->unsignedBigInteger('account_id')->index()->after('id');
            if (!$has('plan_id'))           $t->unsignedBigInteger('plan_id')->nullable()->index()->after('account_id');

            if (!$has('status'))            $t->enum('status', ['trial','active','past_due','canceled','paused'])->default('active')->after('plan_id');
            if (!$has('billing_cycle'))     $t->enum('billing_cycle', ['monthly','annual'])->default('monthly')->after('status');

            if (!$has('current_period_start')) $t->timestamp('current_period_start')->nullable()->after('billing_cycle');
            if (!$has('current_period_end'))   $t->timestamp('current_period_end')->nullable()->after('current_period_start');
            if (!$has('next_invoice_date'))    $t->date('next_invoice_date')->nullable()->after('current_period_end');

            if (!$has('grace_days'))        $t->unsignedInteger('grace_days')->default(0)->after('next_invoice_date');
            if (!$has('auto_renew'))        $t->boolean('auto_renew')->default(true)->after('grace_days');

            if (!$has('meta'))              $t->json('meta')->nullable()->after('auto_renew');

            if (!$has('created_at'))        $t->timestamps();
            if (!$has('deleted_at'))        $t->softDeletes();
        });
    }

    public function down(): void
    {
        // No la borramos por seguridad. Si necesitas revertir, descomenta:
        // Schema::connection($this->conn)->dropIfExists($this->table);
    }
};
