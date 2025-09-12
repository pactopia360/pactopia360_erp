<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('crm_carritos')) return;

        // 1) Forzar tipo de 'estado' a VARCHAR(20) con default 'nuevo'
        //    (usamos SQL directo para no requerir doctrine/dbal)
        try {
            DB::statement("ALTER TABLE `crm_carritos` MODIFY `estado` VARCHAR(20) NOT NULL DEFAULT 'nuevo'");
        } catch (\Throwable $e) {
            // Si ya es VARCHAR o el motor no permite 'MODIFY' aquí, ignora silenciosamente
        }

        // 2) Asegurar columnas que usa el controlador/vistas
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

        // 3) Normalizar valores existentes en 'estado' a algo válido
        //    (si había números o valores raros, los pasamos a 'nuevo')
        try {
            DB::table('crm_carritos')
              ->whereNotIn('estado', ['nuevo','abierto','convertido','cancelado'])
              ->update(['estado' => 'nuevo']);
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_carritos')) return;

        // No revertimos el tipo de 'estado' (evitar pérdida de datos).
        // Eliminamos sólo las columnas opcionales si existen.
        Schema::table('crm_carritos', function (Blueprint $table) {
            foreach (['cliente','email','telefono','origen','etiquetas','meta'] as $col) {
                if (Schema::hasColumn('crm_carritos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
