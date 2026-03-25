<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sat_user_cfdis')) {
            return;
        }

        Schema::connection($this->connection)->create('sat_user_cfdis', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('xml_upload_id')->nullable()->index();

            $table->uuid('cuenta_id')->index();
            $table->uuid('usuario_id')->index();
            $table->string('rfc_owner', 13)->index();

            $table->string('uuid', 64)->index();
            $table->string('version_cfdi', 10)->nullable();

            $table->string('rfc_emisor', 13)->nullable()->index();
            $table->string('nombre_emisor', 255)->nullable();
            $table->string('rfc_receptor', 13)->nullable()->index();
            $table->string('nombre_receptor', 255)->nullable();

            $table->dateTime('fecha_emision')->nullable()->index();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('descuento', 16, 2)->default(0);
            $table->decimal('iva', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);

            $table->string('tipo_comprobante', 10)->nullable();
            $table->string('moneda', 10)->nullable();
            $table->string('metodo_pago', 10)->nullable();
            $table->string('forma_pago', 10)->nullable();

            $table->string('direction', 20)->default('mixto')->index(); // emitidos|recibidos|mixto|no_relacionado

            $table->string('xml_path', 500)->nullable();
            $table->string('xml_hash', 64)->nullable()->index();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['cuenta_id', 'usuario_id', 'rfc_owner', 'uuid'], 'uq_sat_user_cfdis_scope_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_user_cfdis');
    }
};