<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMPORTANTE:
     * Esto va en la BD de CLIENTES, NO en admin.
     * Ajusta el nombre de conexión si tu proyecto usa otro alias.
     */
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        Schema::connection($this->conn)->create('sat_fiel_external', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con cuenta cliente (ajusta si tu campo real se llama distinto)
            $table->unsignedBigInteger('account_id')->index();

            // Datos visibles en tabla
            $table->string('rfc', 13)->nullable()->index();
            $table->string('razon_social', 255)->nullable();
            $table->string('reference', 120)->nullable();

            // Archivo ZIP
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            // Estado de procesamiento
            $table->string('status', 40)->default('PENDING')->index();

            // Metadata extra (por si guardas notas, ip, user_agent, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Opcional: si quieres evitar duplicados por cuenta + nombre
            $table->index(['account_id', 'file_name']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('sat_fiel_external');
    }
};
