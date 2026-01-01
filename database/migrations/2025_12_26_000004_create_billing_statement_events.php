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
        if (Schema::connection($conn)->hasTable('billing_statement_events')) {
            return;
        }

        Schema::connection($conn)->create('billing_statement_events', function (Blueprint $t) use ($conn) {
            $t->bigIncrements('id');

            $t->unsignedBigInteger('statement_id')->index();
            $t->string('event', 60)->index(); // created|synced|sent|updated|locked|unlocked|paid|void
            $t->string('actor', 60)->nullable()->index(); // admin_user_id, system, webhook, etc.
            $t->text('notes')->nullable();
            $t->json('meta')->nullable();

            $t->timestamp('created_at')->useCurrent();

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

        Schema::connection('mysql_admin')->dropIfExists('billing_statement_events');
    }
};
