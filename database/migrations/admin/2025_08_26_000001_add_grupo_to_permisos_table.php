<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('permisos', function (Blueprint $table) {
            if (!Schema::hasColumn('permisos', 'grupo')) {
                $table->string('grupo', 64)->default('')->index()->after('clave');
            }
        });
    }

    public function down(): void {
        Schema::table('permisos', function (Blueprint $table) {
            if (Schema::hasColumn('permisos', 'grupo')) {
                $table->dropColumn('grupo');
            }
        });
    }
};
