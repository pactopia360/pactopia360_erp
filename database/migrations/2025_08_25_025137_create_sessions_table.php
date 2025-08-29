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
        // Si NO existe, la creamos con el esquema estándar de Laravel
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
            return;
        }

        // Si YA existe, solo aseguramos columnas faltantes (sin borrar datos)
        Schema::table('sessions', function (Blueprint $table) {
            // NOTA: usamos Schema::hasColumn para no intentar agregar algo que ya esté

            if (!Schema::hasColumn('sessions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->index()->after('id');
            }

            if (!Schema::hasColumn('sessions', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('sessions', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }

            if (!Schema::hasColumn('sessions', 'payload')) {
                $table->longText('payload')->after('user_agent');
            }

            if (!Schema::hasColumn('sessions', 'last_activity')) {
                $table->integer('last_activity')->index()->after('payload');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En rollback sí se permite eliminar la tabla (comportamiento estándar)
        Schema::dropIfExists('sessions');
    }
};
