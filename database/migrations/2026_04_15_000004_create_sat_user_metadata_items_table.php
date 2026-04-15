<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';
    protected string $tableName = 'sat_user_metadata_items';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        Schema::connection($this->connection)->create($this->tableName, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('metadata_upload_id')->nullable()->index();

            $table->string('cuenta_id', 50)->nullable()->index();
            $table->string('usuario_id', 50)->nullable()->index();

            $table->string('rfc_owner', 20)->nullable()->index();

            $table->string('uuid', 50)->nullable()->index();

            $table->string('rfc_emisor', 20)->nullable()->index();
            $table->string('nombre_emisor', 255)->nullable();

            $table->string('rfc_receptor', 20)->nullable()->index();
            $table->string('nombre_receptor', 255)->nullable();

            $table->dateTime('fecha_emision')->nullable()->index();
            $table->dateTime('fecha_certificacion_sat')->nullable();

            $table->decimal('monto', 18, 2)->default(0);

            $table->string('efecto_comprobante', 20)->nullable();
            $table->string('estatus', 50)->nullable()->index();

            $table->dateTime('fecha_cancelacion')->nullable();

            $table->string('direction', 10)->nullable()->index();

            $table->longText('raw_line')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['rfc_owner', 'fecha_emision'], 'sumi_rfc_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->tableName);
    }
};