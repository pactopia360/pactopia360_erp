<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->create('sat_regimenes', function (Blueprint $table) {
            $table->string('clave', 5)->primary();
            $table->string('descripcion');
            $table->boolean('fisica')->default(false);
            $table->boolean('moral')->default(false);
            $table->timestamps();
        });

        Schema::connection('mysql_clientes')->create('sat_usos_cfdi', function (Blueprint $table) {
            $table->string('clave', 5)->primary();
            $table->string('descripcion');
            $table->boolean('fisica')->default(false);
            $table->boolean('moral')->default(false);
            $table->json('regimenes')->nullable(); // relación dinámica
            $table->timestamps();
        });

        Schema::connection('mysql_clientes')->create('sat_formas_pago', function (Blueprint $table) {
            $table->string('clave', 5)->primary();
            $table->string('descripcion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('sat_formas_pago');
        Schema::connection('mysql_clientes')->dropIfExists('sat_usos_cfdi');
        Schema::connection('mysql_clientes')->dropIfExists('sat_regimenes');
    }
};