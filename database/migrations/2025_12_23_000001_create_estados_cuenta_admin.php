<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            return;
        }

        Schema::connection($this->conn)->create('estados_cuenta', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Relación con accounts.id (en mysql_admin)
            $t->unsignedBigInteger('account_id')->index();

            /**
             * Periodo "Y-m" (ej: 2025-12) para que tu query:
             * where('periodo', 'like', $period.'%')
             * funcione estable.
             */
            $t->string('periodo', 7)->index();

            $t->string('concepto', 255);
            $t->text('detalle')->nullable();

            $t->decimal('cargo', 12, 2)->default(0);
            $t->decimal('abono', 12, 2)->default(0);

            // opcional: saldo calculado al insertar/actualizar el último movimiento
            $t->decimal('saldo', 12, 2)->nullable();

            // trazabilidad (stripe|manual|system)
            $t->string('source', 30)->nullable()->index()->comment('system|manual|stripe');

            // referencia (session_id, invoice_id, folio, etc.)
            $t->string('ref', 191)->nullable()->index()->comment('stripe session/invoice/payment_intent or folio');

            // metadata libre
            $t->json('meta')->nullable();

            $t->timestamps();

            // Si quieres FK (opcional), descomenta:
            // $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('estados_cuenta');
    }
};
