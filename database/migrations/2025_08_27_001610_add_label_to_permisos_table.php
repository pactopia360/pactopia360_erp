<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permisos')) return;

        // 1) Añadir 'label' solo si falta
        Schema::table('permisos', function (Blueprint $table) {
            if (!Schema::hasColumn('permisos', 'label')) {
                // Si existe 'grupo', lo colocamos después; si no, al final
                if (Schema::hasColumn('permisos', 'grupo')) {
                    $table->string('label', 191)->nullable()->after('grupo');
                } else {
                    $table->string('label', 191)->nullable();
                }
            }
        });

        // 2) Crear UNIQUE(clave) solo si no existe
        $uniqExists = collect(DB::select(
            "SHOW INDEX FROM permisos WHERE Key_name = 'permisos_clave_unique'"
        ))->isNotEmpty();

        if (!$uniqExists) {
            Schema::table('permisos', function (Blueprint $table) {
                $table->unique('clave', 'permisos_clave_unique');
            });
        }

        // 3) Backfill opcional de 'label' si quedó NULL/vacío
        try {
            DB::statement("
                UPDATE permisos
                SET label = REPLACE(REPLACE(clave,'_',' '),'.',' · ')
                WHERE (label IS NULL OR label = '')
            ");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('permisos')) return;

        // Elimina 'label' si existe
        Schema::table('permisos', function (Blueprint $table) {
            if (Schema::hasColumn('permisos', 'label')) {
                $table->dropColumn('label');
            }
            // Quita el índice si existe (tolerante a errores)
            try { $table->dropUnique('permisos_clave_unique'); } catch (\Throwable $e) {}
        });
    }
};
