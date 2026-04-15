<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conexión donde vive la tabla espejo de clientes.
     */
    protected $connection = 'mysql_clientes';

    /**
     * Nombre de tabla.
     */
    protected string $tableName = 'sat_user_metadata_uploads';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        Schema::connection($this->connection)->create($this->tableName, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('cuenta_id', 50)->nullable()->index();
            $table->string('usuario_id', 50)->nullable()->index();

            $table->string('rfc_owner', 20)->nullable()->index();
            $table->string('source_type', 60)->nullable()->index();

            $table->string('original_name', 255)->nullable();
            $table->string('stored_name', 255)->nullable();

            $table->string('disk', 60)->nullable();
            $table->text('path')->nullable();
            $table->string('mime', 150)->nullable();

            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('rows_count')->default(0);

            $table->string('direction_detected', 20)->nullable()->index();
            $table->string('status', 40)->default('uploaded')->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['cuenta_id', 'rfc_owner'], 'sumu_cuenta_rfc_idx');
            $table->index(['rfc_owner', 'status'], 'sumu_rfc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->tableName);
    }
};