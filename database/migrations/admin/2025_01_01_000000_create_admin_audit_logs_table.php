<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NO tipar esta propiedad. Dejarla sin tipo.
     * En Laravel 12 la clase padre ya define el tipo (?string).
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        Schema::connection($this->connection)->create('admin_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Quién hizo la acción (usuario admin)
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();

            // Contexto de la acción
            $table->string('action', 100)->index();       // ej. login, create, update, delete
            $table->string('entity_type', 150)->nullable()->index(); // Modelo afectado (App\Models\X)
            $table->string('entity_id', 100)->nullable()->index();   // PK del registro

            // Datos de cambio (opcional)
            $table->json('meta')->nullable(); // diffs, payload, headers, etc.

            // Trazabilidad
            $table->string('ip', 64)->nullable()->index();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            // índices útiles
            $table->index(['action', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('admin_audit_logs');
    }
};
