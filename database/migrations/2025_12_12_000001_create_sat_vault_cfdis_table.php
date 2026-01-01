<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = 'mysql_clientes';

        if (Schema::connection($conn)->hasTable('sat_vault_cfdis')) {
            // ya existe: idempotente
            return;
        }

        Schema::connection($conn)->create('sat_vault_cfdis', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('cuenta_id', 64)->index();
            $table->string('uuid', 64)->index();

            $table->date('fecha_emision')->nullable();

            $table->enum('tipo', ['emitidos', 'recibidos'])->default('emitidos')->index();

            $table->string('rfc_emisor', 20)->nullable()->index();
            $table->string('rfc_receptor', 20)->nullable()->index();
            $table->string('razon_emisor', 255)->nullable();
            $table->string('razon_receptor', 255)->nullable();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('iva', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);

            $table->unsignedBigInteger('vault_file_id')->nullable()->index();
            $table->string('xml_path', 500)->nullable();
            $table->string('pdf_path', 500)->nullable();

            $table->timestamps();

            $table->unique(['cuenta_id', 'uuid'], 'uq_sat_vault_cfdis_cuenta_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('sat_vault_cfdis');
    }
};
