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

        if (!Schema::connection($conn)->hasTable('billing_statements')) {
            Schema::connection($conn)->create('billing_statements', function (Blueprint $t) {
                $t->bigIncrements('id');

                // Cuenta (UUID ULID/UUID de mysql_clientes.cuentas_cliente.id)
                $t->string('account_id', 36)->index();

                // Periodo canónico: YYYY-MM (ej. 2025-12)
                $t->string('period', 7)->index();

                // Totales
                $t->decimal('total_cargo', 14, 2)->default(0);
                $t->decimal('total_abono', 14, 2)->default(0);
                $t->decimal('saldo', 14, 2)->default(0);

                // Estatus del estado de cuenta
                // pending: saldo>0, paid: saldo=0, credit: saldo<0, void: anulado
                $t->string('status', 20)->default('pending')->index();

                // Vencimiento / fechas relevantes
                $t->date('due_date')->nullable()->index();
                $t->timestamp('sent_at')->nullable()->index();
                $t->timestamp('paid_at')->nullable()->index();

                // Snapshot de datos (razón social, RFC, correos, plan/licencia) para que el PDF del mes no cambie retroactivamente
                $t->json('snapshot')->nullable();

                // Meta para integraciones / notas / tags
                $t->json('meta')->nullable();

                // Lock para evitar modificaciones accidentales (si está pagado)
                $t->boolean('is_locked')->default(false)->index();

                $t->timestamps();

                $t->unique(['account_id', 'period'], 'uq_statement_account_period');
            });
        }
    }

    public function down(): void
    {
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        Schema::connection('mysql_admin')->dropIfExists('billing_statements');
    }
};
