<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sat_vault_cfdis')) {
            return;
        }

        Schema::create('sat_vault_cfdis', function (Blueprint $table) {
            $table->bigIncrements('id');

            // multi-tenant
            $table->uuid('cuenta_id')->index();

            // principales
            $table->string('uuid', 48)->index();
            $table->dateTime('fecha')->nullable()->index();

            $table->string('tipo', 20)->nullable()->index(); // I/E/T/N/P etc (si lo detectas)
            $table->string('rfc_emisor', 20)->nullable()->index();
            $table->string('rfc_receptor', 20)->nullable()->index();
            $table->string('razon_social_emisor', 255)->nullable();
            $table->string('razon_social_receptor', 255)->nullable();

            // importes
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('iva', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('tasa_iva', 8, 6)->nullable(); // 0.160000 etc

            // para trazabilidad
            $table->unsignedBigInteger('vault_file_id')->nullable()->index(); // sat_vault_files.id
            $table->string('source', 30)->default('zip'); // zip / import / etc
            $table->json('meta')->nullable();

            $table->timestamps();

            // No duplicates por cuenta
            $table->unique(['cuenta_id', 'uuid'], 'uq_vault_cfdis_cuenta_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_vault_cfdis');
    }
};
