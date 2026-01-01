<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * ✅ SOLO mysql_admin.
         * Si la ejecutas con --database=mysql_clientes, no debe tocar nada.
         */
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        $conn = 'mysql_admin';

        // -------------------------
        // modules
        // -------------------------
        if (!Schema::connection($conn)->hasTable('modules')) {
            Schema::connection($conn)->create('modules', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('key', 80)->unique();
                $t->string('name', 191);
                $t->text('description')->nullable();
                $t->boolean('active')->default(true)->index();
                $t->timestamps();
            });
        } else {
            // ✅ Si ya existe, solo aseguramos índices/columnas mínimas (sin romper)
            // (Opcional) Aquí podrías agregar hasColumn(...) si luego requieres nuevas columnas.
        }

        // -------------------------
        // account_modules
        // -------------------------
        if (!Schema::connection($conn)->hasTable('account_modules')) {
            Schema::connection($conn)->create('account_modules', function (Blueprint $t) {
                $t->bigIncrements('id');

                $t->unsignedBigInteger('account_id')->index();
                $t->unsignedBigInteger('module_id')->index();

                $t->boolean('enabled')->default(true)->index();
                $t->json('meta')->nullable();

                $t->timestamps();

                $t->unique(['account_id', 'module_id'], 'uq_account_module');
            });
        }
    }

    public function down(): void
    {
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        $conn = 'mysql_admin';

        Schema::connection($conn)->dropIfExists('account_modules');
        Schema::connection($conn)->dropIfExists('modules');
    }
};
