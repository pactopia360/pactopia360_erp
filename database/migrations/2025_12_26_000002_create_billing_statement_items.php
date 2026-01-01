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
        if (Schema::connection($conn)->hasTable('billing_statement_items')) {
            return;
        }

        Schema::connection($conn)->create('billing_statement_items', function (Blueprint $t) use ($conn) {
            $t->bigIncrements('id');

            $t->unsignedBigInteger('statement_id')->index();
            $t->string('type', 30)->index(); // license|purchase|adjustment|payment|credit
            $t->string('code', 60)->nullable()->index(); // opcional: SKU/clave interna
            $t->string('description', 240);
            $t->decimal('qty', 14, 4)->default(1);
            $t->decimal('unit_price', 14, 2)->default(0);
            $t->decimal('amount', 14, 2)->default(0); // qty*unit_price (positivo=cargo, negativo=abono/credito)

            // Referencias externas: stripe invoice/payment_intent, folio interno, etc.
            $t->string('ref', 191)->nullable()->index();
            $t->json('meta')->nullable();

            $t->timestamps();

            /**
             * FK solo si existe billing_statements (por seguridad en ambientes mixtos).
             */
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

        Schema::connection('mysql_admin')->dropIfExists('billing_statement_items');
    }
};
