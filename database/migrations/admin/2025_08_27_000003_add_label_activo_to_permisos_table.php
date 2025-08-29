<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('permisos')) return;

        Schema::table('permisos', function (Blueprint $table) {
            // Añade columnas si faltan (idempotente)
            if (!Schema::hasColumn('permisos', 'label')) {
                $table->string('label', 191)->nullable()->after('grupo');
            }
            if (!Schema::hasColumn('permisos', 'activo')) {
                $table->boolean('activo')->default(true)->after('label');
            }

            // Asegura UNIQUE en 'clave' (puede existir ya)
            try { $table->unique('clave'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void {
        if (!Schema::hasTable('permisos')) return;

        Schema::table('permisos', function (Blueprint $table) {
            if (Schema::hasColumn('permisos', 'activo')) {
                $table->dropColumn('activo');
            }
            if (Schema::hasColumn('permisos', 'label')) {
                $table->dropColumn('label');
            }
            // No quitamos el índice único para no romper referencias
        });
    }
};
