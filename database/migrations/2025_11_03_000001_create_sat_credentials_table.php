<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Importante: esta migración corre sobre mysql_clientes
        // porque llamas: php artisan migrate --database=mysql_clientes

        if (Schema::hasTable('sat_credentials')) {
            // Ya existe en mysql_clientes, no hacemos nada
            return;
        }

        Schema::create('sat_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cuenta_id')->index();
            $table->string('rfc', 13)->index();
            $table->string('cer_path')->nullable();
            $table->string('key_path')->nullable();
            $table->text('key_password')->nullable(); // (opcional cifrar luego)
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['cuenta_id','rfc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_credentials');
    }
};
