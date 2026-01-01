<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migración es ADMIN. Por diseño crea la tabla en mysql_admin.
     * IMPORTANTE: debe ser idempotente (si ya existe, no falla).
     */
    public function up(): void
    {
        $conn = 'mysql_admin';
        $table = 'stripe_price_list';

        // Si ya existe, NO volver a crear (evita SQLSTATE[42S01])
        if (Schema::connection($conn)->hasTable($table)) {
            return;
        }

        Schema::connection($conn)->create($table, function (Blueprint $table) {
            $table->id();
            $table->string('price_key', 60);
            $table->string('name', 120)->nullable();
            $table->string('plan', 30)->default('PRO');
            $table->enum('billing_cycle', ['mensual', 'anual']);
            $table->string('stripe_price_id', 120);
            $table->string('currency', 10)->default('MXN');
            $table->decimal('display_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('price_key', 'uq_stripe_price_list_price_key');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('stripe_price_list');
    }
};
