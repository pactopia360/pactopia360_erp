<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ===== Tabla cache =====
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        } else {
            Schema::table('cache', function (Blueprint $table) {
                // Agregar columnas faltantes sin tocar las existentes
                if (!Schema::hasColumn('cache', 'key')) {
                    $table->string('key')->primary();
                }
                if (!Schema::hasColumn('cache', 'value')) {
                    $table->mediumText('value')->after('key');
                }
                if (!Schema::hasColumn('cache', 'expiration')) {
                    $table->integer('expiration')->after('value');
                }
                // Nota: no intentamos cambiar tipos ni llaves primarias existentes
                // para evitar requerir doctrine/dbal y no arriesgar datos.
            });
        }

        // ===== Tabla cache_locks =====
        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        } else {
            Schema::table('cache_locks', function (Blueprint $table) {
                if (!Schema::hasColumn('cache_locks', 'key')) {
                    $table->string('key')->primary();
                }
                if (!Schema::hasColumn('cache_locks', 'owner')) {
                    $table->string('owner')->after('key');
                }
                if (!Schema::hasColumn('cache_locks', 'expiration')) {
                    $table->integer('expiration')->after('owner');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
