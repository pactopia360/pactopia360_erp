<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ¡OJO! No tipar la propiedad. Dejarla sin tipo.
     * Siempre usar Schema::connection($this->connection)
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // Si la tabla ya existe, no vuelvas a crearla
        if (Schema::connection($this->connection)->hasTable('perfiles')) {
            // Opcional: aquí podrías agregar columnas faltantes si las hubiera
            return;
        }

        Schema::connection($this->connection)->create('perfiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('clave', 191)->nullable(false);
            $table->string('nombre', 191)->nullable(false);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['clave', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('perfiles');
    }
};
