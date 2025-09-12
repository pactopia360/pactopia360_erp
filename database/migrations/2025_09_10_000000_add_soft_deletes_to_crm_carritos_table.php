<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_carritos')) {
            Schema::table('crm_carritos', function (Blueprint $table) {
                if (!Schema::hasColumn('crm_carritos', 'deleted_at')) {
                    // agrega columna para borrado lógico
                    $table->softDeletes()->after('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_carritos')) {
            Schema::table('crm_carritos', function (Blueprint $table) {
                if (Schema::hasColumn('crm_carritos', 'deleted_at')) {
                    // elimina la columna de borrado lógico
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
