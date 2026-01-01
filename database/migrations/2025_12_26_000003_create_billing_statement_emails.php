<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * ✅ SOLO mysql_admin.
         * Si ejecutas: php artisan migrate --database=mysql_clientes
         * esta migración NO debe tocar nada.
         */
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        $conn = 'mysql_admin';

        // Si ya existe, no hagas nada.
        if (Schema::connection($conn)->hasTable('billing_statement_emails')) {
            return;
        }

        Schema::connection($conn)->create('billing_statement_emails', function (Blueprint $t) use ($conn) {
            $t->bigIncrements('id');

            $t->unsignedBigInteger('statement_id')->index();
            $t->string('email', 191)->index();
            $t->boolean('is_primary')->default(false)->index();

            $t->timestamps();

            $t->unique(['statement_id', 'email'], 'uq_statement_email');

            // FK solo si existe la tabla padre
            if (Schema::connection($conn)->hasTable('billing_statements')) {
                $t->foreign('statement_id')
                    ->references('id')->on('billing_statements')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        Schema::connection('mysql_admin')->dropIfExists('billing_statement_emails');
    }
};
