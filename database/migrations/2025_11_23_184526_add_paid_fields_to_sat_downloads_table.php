<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IMPORTANTE: usar la misma conexión que utilizas para SAT
        $conn = 'mysql_clientes';

        // Si ya existe, no hacemos nada (evitamos el error 1050)
        if (Schema::connection($conn)->hasTable('sat_credentials')) {
            return;
        }

        Schema::connection($conn)->create('sat_credentials', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('cuenta_id', 36);
            $table->string('rfc', 13);
            $table->string('cer_path', 191)->nullable();
            $table->string('key_path', 191)->nullable();
            $table->text('key_password')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $conn = 'mysql_clientes';

        if (Schema::connection($conn)->hasTable('sat_credentials')) {
            Schema::connection($conn)->drop('sat_credentials');
        }
    }
};
