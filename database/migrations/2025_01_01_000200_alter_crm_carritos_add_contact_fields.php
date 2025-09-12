<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('crm_carritos')) return;

        Schema::table('crm_carritos', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_carritos', 'cliente')) {
                $table->string('cliente', 160)->nullable()->after('contacto_id');
            }
            if (!Schema::hasColumn('crm_carritos', 'email')) {
                $table->string('email', 160)->nullable()->after('cliente');
            }
            if (!Schema::hasColumn('crm_carritos', 'telefono')) {
                $table->string('telefono', 60)->nullable()->after('email');
            }
            if (!Schema::hasColumn('crm_carritos', 'origen')) {
                $table->string('origen', 60)->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('crm_carritos', 'etiquetas')) {
                $table->json('etiquetas')->nullable()->after('origen');
            }
            if (!Schema::hasColumn('crm_carritos', 'meta')) {
                $table->json('meta')->nullable()->after('etiquetas');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_carritos')) return;

        Schema::table('crm_carritos', function (Blueprint $table) {
            foreach (['cliente','email','telefono','origen','etiquetas','meta'] as $col) {
                if (Schema::hasColumn('crm_carritos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
