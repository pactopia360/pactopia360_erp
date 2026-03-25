<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->create('sat_user_report_uploads', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('cuenta_id', 50)->index();
            $table->string('usuario_id', 50)->index();
            $table->string('rfc_owner', 20)->index();

            $table->string('report_type', 50)->default('csv_report')->index();

            $table->unsignedBigInteger('linked_metadata_upload_id')->nullable()->index();
            $table->unsignedBigInteger('linked_xml_upload_id')->nullable()->index();

            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('disk', 80)->default('sat_vault');
            $table->string('path', 500);
            $table->string('mime', 120)->nullable();

            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('rows_count')->default(0);

            $table->string('status', 40)->default('uploaded')->index();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['cuenta_id', 'usuario_id', 'rfc_owner'], 'sat_report_uploads_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('sat_user_report_uploads');
    }
};