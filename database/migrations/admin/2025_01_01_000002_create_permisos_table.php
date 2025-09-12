<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NO tipar la propiedad $connection (Laravel 12 ya la tipa en la clase padre).
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // Si la tabla ya existe, no la recrees
        if (Schema::connection($this->connection)->hasTable('permisos')) {
            // Si necesitas agregar columnas nuevas en el futuro, hazlo aquí con hasColumn(...)
            return;
        }

        Schema::connection($this->connection)->create('permisos', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Claves/atributos base del permiso
            $table->string('clave', 191);          // ej. admin.usuarios.ver
            $table->string('grupo', 191);          // ej. usuarios, clientes, planes
            $table->string('label', 191);          // ej. "Ver usuarios"
            $table->text('descripcion')->nullable();

            $table->boolean('activo')->default(true);

            $table->timestamps();

            // Índices útiles
            $table->index(['grupo', 'activo']);
            $table->unique('clave'); // clave de permiso única
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('permisos');
    }
};
