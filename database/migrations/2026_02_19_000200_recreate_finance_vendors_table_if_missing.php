<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        // ✅ Si ya existe, no hacemos nada (idempotente)
        if (Schema::connection($conn)->hasTable('finance_vendors')) {
            return;
        }

        Schema::connection($conn)->create('finance_vendors', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name', 140);
            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 40)->nullable()->index();

            $table->boolean('is_active')->default(true)->index();

            // % comisión (ej: 0.050 = 5.0%)
            $table->decimal('commission_rate', 6, 3)->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        Schema::connection($conn)->dropIfExists('finance_vendors');
    }
};
