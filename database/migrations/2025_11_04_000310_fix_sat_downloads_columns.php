<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Hacemos las columnas flexibles y con defaults sensatos.
        Schema::table('sat_downloads', function (Blueprint $table) {
            // status: permitir nuestros estados y evitar ENUMs rígidos
            $table->string('status', 24)->default('pending')->change();

            // tipo: 'emitidos' | 'recibidos'
            if (Schema::hasColumn('sat_downloads', 'tipo')) {
                $table->string('tipo', 16)->nullable(false)->change();
            }

            // request_id / package_id: por si eran cortos
            if (Schema::hasColumn('sat_downloads', 'request_id')) {
                $table->string('request_id', 64)->nullable()->change();
            }
            if (Schema::hasColumn('sat_downloads', 'package_id')) {
                $table->string('package_id', 64)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // No regresamos a ENUM; mantenemos string por compatibilidad.
        Schema::table('sat_downloads', function (Blueprint $table) {
            $table->string('status', 24)->default('pending')->change();
            $table->string('tipo', 16)->nullable(false)->change();
            $table->string('request_id', 64)->nullable()->change();
            $table->string('package_id', 64)->nullable()->change();
        });
    }
};
