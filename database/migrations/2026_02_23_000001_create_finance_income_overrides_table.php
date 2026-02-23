<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        Schema::connection($conn)->create('finance_income_overrides', function (Blueprint $t) {
            $t->bigIncrements('id');

            // projection|statement
            $t->string('row_type', 20)->default('projection');

            // Account puede ser UUID (clientes) o admin_account_id numérico en string
            $t->string('account_id', 64);
            $t->string('period', 7); // YYYY-MM

            // Para ventas únicas (si se quiere usar override; normalmente se actualiza finance_sales directo)
            $t->unsignedBigInteger('sale_id')->nullable();

            // Overrides operativos
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('ec_status', 20)->nullable();       // pending|emitido|pagado|vencido
            $t->string('invoice_status', 30)->nullable();  // pending|requested|ready|issued|cancelled
            $t->string('cfdi_uuid', 60)->nullable();
            $t->string('rfc_receptor', 20)->nullable();
            $t->string('forma_pago', 40)->nullable();

            // Montos override (opcional, útil para proyecciones)
            $t->decimal('subtotal', 14, 2)->nullable();
            $t->decimal('iva', 14, 2)->nullable();
            $t->decimal('total', 14, 2)->nullable();

            $t->text('notes')->nullable();

            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();

            $t->unique(['row_type', 'account_id', 'period'], 'ux_income_overrides_row_acc_per');
            $t->index(['account_id', 'period']);
            $t->index(['sale_id']);
        });
    }

    public function down(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        Schema::connection($conn)->dropIfExists('finance_income_overrides');
    }
};