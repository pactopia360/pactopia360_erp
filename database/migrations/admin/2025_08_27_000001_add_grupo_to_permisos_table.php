<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('permisos')) {
            Schema::table('permisos', function (Blueprint $table) {
                if (!Schema::hasColumn('permisos', 'grupo')) {
                    $table->string('grupo', 64)->default('')->index()->after('clave');
                }
                // Asegura índice único sobre clave para que el upsert deduplique
                try { $table->unique('clave'); } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void {
        if (Schema::hasTable('permisos') && Schema::hasColumn('permisos','grupo')) {
            Schema::table('permisos', function (Blueprint $table) {
                $table->dropColumn('grupo');
            });
        }
    }
};
