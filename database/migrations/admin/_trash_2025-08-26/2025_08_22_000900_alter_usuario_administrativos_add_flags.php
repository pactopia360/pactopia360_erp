<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // usa la conexión por defecto (ya apunta a p360v1_admin en tu .env)
    public function up(): void
    {
        if (!Schema::hasTable('usuario_administrativos')) return;

        Schema::table('usuario_administrativos', function (Blueprint $table) {
            if (!Schema::hasColumn('usuario_administrativos', 'es_superadmin')) {
                $table->boolean('es_superadmin')->default(false)->after('password');
            }
            if (!Schema::hasColumn('usuario_administrativos', 'activo')) {
                $table->boolean('activo')->default(true)->after('es_superadmin');
            }
            if (!Schema::hasColumn('usuario_administrativos', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('activo');
            }
            if (!Schema::hasColumn('usuario_administrativos', 'last_login_ip')) {
                $table->string('last_login_ip',45)->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        // No bajamos columnas para no romper data en entornos existentes
    }
};
