<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sat_user_xml_uploads')) {
            return;
        }

        Schema::connection($this->connection)->create('sat_user_xml_uploads', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('cuenta_id')->index();
            $table->uuid('usuario_id')->index();
            $table->string('rfc_owner', 13)->index();

            $table->string('source_type', 20)->default('manual')->index(); // manual|zip|xml
            $table->string('original_name', 255)->nullable();
            $table->string('stored_name', 255)->nullable();
            $table->string('disk', 50)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('mime', 120)->nullable();

            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('files_count')->default(0);

            $table->string('direction_detected', 20)->default('mixto')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['cuenta_id', 'usuario_id', 'rfc_owner'], 'idx_sat_xml_uploads_scope');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_user_xml_uploads');
    }
};