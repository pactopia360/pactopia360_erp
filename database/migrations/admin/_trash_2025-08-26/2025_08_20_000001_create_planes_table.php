<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $conn = 'mysql_admin';

        // Si ya existe la tabla en p360v1_admin, solo aseguramos columnas clave.
        if (Schema::connection($conn)->hasTable('planes')) {
            // Ajustes “por si faltan” columnas usadas por seeders/código
            Schema::connection($conn)->table('planes', function (Blueprint $table) {
                if (!Schema::connection('mysql_admin')->hasColumn('planes','nombre')) {
                    $table->string('nombre')->unique()->after('id');
                }
                if (!Schema::connection('mysql_admin')->hasColumn('planes','costo_mensual')) {
                    $table->decimal('costo_mensual',10,2)->default(0)->after('nombre');
                }
                if (!Schema::connection('mysql_admin')->hasColumn('planes','costo_anual')) {
                    $table->decimal('costo_anual',10,2)->default(0)->after('costo_mensual');
                }
                if (!Schema::connection('mysql_admin')->hasColumn('planes','limite_timbres')) {
                    $table->integer('limite_timbres')->nullable()->after('costo_anual');
                }
                if (!Schema::connection('mysql_admin')->hasColumn('planes','limite_espacio_mb')) {
                    $table->integer('limite_espacio_mb')->nullable()->after('limite_timbres');
                }
                if (!Schema::connection('mysql_admin')->hasColumn('planes','activo')) {
                    $table->boolean('activo')->default(true)->after('limite_espacio_mb');
                }
                // timestamps si faltan
                if (!Schema::connection('mysql_admin')->hasColumn('planes','created_at')) {
                    $table->timestamps();
                }
            });
            return;
        }

        // Crear tabla desde cero en la conexión admin
        Schema::connection($conn)->create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();              // free, premium
            $table->decimal('costo_mensual', 10, 2)->default(0);
            $table->decimal('costo_anual', 10, 2)->default(0);
            $table->integer('limite_timbres')->nullable();
            $table->integer('limite_espacio_mb')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('planes');
    }
};
