<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sat_user_metadata_items')) {
            return;
        }

        Schema::connection($this->connection)->create('sat_user_metadata_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('metadata_upload_id')->index();

            $table->uuid('cuenta_id')->index();
            $table->uuid('usuario_id')->index();
            $table->string('rfc_owner', 13)->index();

            $table->string('uuid', 64)->index();

            $table->string('rfc_emisor', 13)->nullable()->index();
            $table->string('nombre_emisor', 255)->nullable();
            $table->string('rfc_receptor', 13)->nullable()->index();
            $table->string('nombre_receptor', 255)->nullable();

            $table->dateTime('fecha_emision')->nullable()->index();
            $table->dateTime('fecha_certificacion_sat')->nullable();

            $table->decimal('monto', 16, 2)->default(0);
            $table->string('efecto_comprobante', 20)->nullable();
            $table->string('estatus', 50)->nullable();
            $table->dateTime('fecha_cancelacion')->nullable();

            $table->string('direction', 20)->default('mixto')->index(); // emitidos|recibidos|mixto|no_relacionado
            $table->longText('raw_line')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['metadata_upload_id', 'uuid'], 'uq_sat_user_metadata_items_upload_uuid');
            $table->index(['cuenta_id', 'usuario_id', 'rfc_owner', 'uuid'], 'idx_sat_user_metadata_scope_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_user_metadata_items');
    }
};