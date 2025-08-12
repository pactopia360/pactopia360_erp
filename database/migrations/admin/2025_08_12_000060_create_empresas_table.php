<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('empresas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cuenta_id');
            $table->string('rfc_empresa', 13);
            $table->string('razon_social');
            $table->string('regimen_fiscal', 100)->nullable();
            $table->json('direccion_json')->nullable();
            $table->boolean('activa')->default(true);
            $table->bigInteger('sync_version')->default(1);
            $table->timestamps();

            $table->unique(['cuenta_id', 'rfc_empresa']);
            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('empresas');
    }
};
