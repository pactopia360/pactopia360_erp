<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NO tipar la propiedad. Laravel 12 ya la define tipada en la clase padre.
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // Si la tabla ya existe, no la recrees
        if (Schema::connection($this->connection)->hasTable('perfil_permiso')) {
            return;
        }

        Schema::connection($this->connection)->create('perfil_permiso', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('perfil_id');
            $table->unsignedBigInteger('permiso_id');

            $table->timestamps();

            // Índices / unicidad para evitar duplicados en el pivot
            $table->unique(['perfil_id', 'permiso_id'], 'ux_perfil_permiso');

            // FK opcionales (descomenta si ya existen tablas y columnas referenciadas)
            // $table->foreign('perfil_id')->references('id')->on('perfiles')->onDelete('cascade');
            // $table->foreign('permiso_id')->references('id')->on('permisos')->onDelete('cascade');

            $table->index('perfil_id');
            $table->index('permiso_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('perfil_permiso');
    }
};
