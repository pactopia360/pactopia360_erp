<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Si no existe la tabla, no tocamos nada aquí
        if (!Schema::hasTable('sat_downloads')) {
            return;
        }

        // Detecta si la PK actual es auto-increment y la cambia a CHAR(36)
        // Además, normaliza columnas de estado/fechas/paths.
        Schema::table('sat_downloads', function (Blueprint $table) {
            // Quitar PK previa si es necesario (MySQL requiere pasos separados)
        });

        // 1) Quitar PK previa si la hubiera (solo si la PK es 'id' autoincrement BIGINT)
        try {
            DB::statement('ALTER TABLE `sat_downloads` DROP PRIMARY KEY');
        } catch (\Throwable $e) {
            // Puede ya no tener PK o ser diferente; ignoramos
        }

        // 2) Cambiar 'id' a CHAR(36) NOT NULL
        try {
            DB::statement('ALTER TABLE `sat_downloads` MODIFY `id` CHAR(36) NOT NULL');
        } catch (\Throwable $e) {
            // Si no existe 'id', la creamos
            if (str_contains($e->getMessage(), 'Unknown column')) {
                DB::statement('ALTER TABLE `sat_downloads` ADD `id` CHAR(36) NOT NULL');
            } else {
                throw $e;
            }
        }

        // 3) Volver a poner la PK en 'id'
        try {
            DB::statement('ALTER TABLE `sat_downloads` ADD PRIMARY KEY (`id`)');
        } catch (\Throwable $e) {
            // Si ya existe, ignorar
        }

        // 4) Ajustar el resto de columnas a tipos compatibles
        Schema::table('sat_downloads', function (Blueprint $table) {
            if (Schema::hasColumn('sat_downloads', 'status')) {
                $table->string('status', 20)->nullable(false)->change(); // pending|ready|done|error
            } else {
                $table->string('status', 20)->default('pending');
            }

            if (Schema::hasColumn('sat_downloads', 'tipo')) {
                $table->string('tipo', 12)->nullable(false)->change(); // emitidos|recibidos
            } else {
                $table->string('tipo', 12)->default('emitidos');
            }

            if (Schema::hasColumn('sat_downloads', 'request_id')) {
                $table->string('request_id', 50)->nullable()->change();
            } else {
                $table->string('request_id', 50)->nullable();
            }

            if (Schema::hasColumn('sat_downloads', 'package_id')) {
                $table->string('package_id', 50)->nullable()->change();
            } else {
                $table->string('package_id', 50)->nullable();
            }

            if (Schema::hasColumn('sat_downloads', 'zip_path')) {
                $table->string('zip_path', 191)->nullable()->change();
            } else {
                $table->string('zip_path', 191)->nullable();
            }

            if (Schema::hasColumn('sat_downloads', 'date_from')) {
                $table->date('date_from')->nullable(false)->change();
            } else {
                $table->date('date_from');
            }

            if (Schema::hasColumn('sat_downloads', 'date_to')) {
                $table->date('date_to')->nullable(false)->change();
            } else {
                $table->date('date_to');
            }
        });
    }

    public function down(): void
    {
        // No revertimos a BIGINT para no romper datos.
        // Solo dejamos los tipos actuales.
    }
};
