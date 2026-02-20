<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_sales', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Identidad / referencia
            $t->string('sale_code', 64)->nullable(); // folio interno opcional
            $t->unsignedBigInteger('account_id')->nullable(); // cuenta cliente si aplica (mysql_admin.accounts id o similar)
            $t->unsignedBigInteger('vendor_id')->nullable();

            // Origen / periodicidad
            // origen = recurrente / no_recurrente
            $t->enum('origin', ['recurrente', 'no_recurrente'])->default('no_recurrente');
            // periodicidad: mensual/anual/unico (si es unico => no_recurrente)
            $t->enum('periodicity', ['mensual', 'anual', 'unico'])->default('unico');

            // Fechas (las que pediste)
            $t->date('f_cta')->nullable();   // Fecha Emisión Estado de cuenta
            $t->date('f_mov')->nullable();   // Fecha Movimiento (compra dentro del mes)
            $t->date('invoice_date')->nullable(); // Fecha Factura
            $t->date('paid_date')->nullable();    // Fecha Pago (venta)
            $t->date('sale_date')->nullable();    // Fecha venta (si no hay otra)

            // Datos de facturación
            $t->string('receiver_rfc', 20)->nullable();
            $t->string('pay_method', 40)->nullable(); // forma de pago (PUE/PPD o tarjeta/transferencia etc)
            $t->string('cfdi_use', 40)->nullable();   // opcional

            // Importes
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('iva', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);

            // Estatus Estado de cuenta (para grid): pending/emitido/pagado
            $t->enum('statement_status', ['pending', 'emitido', 'pagado'])->default('pending');
            $t->date('statement_sent_at')->nullable();
            $t->date('statement_paid_at')->nullable();

            // Estatus factura (de solicitud de facturas)
            $t->enum('invoice_status', ['sin_solicitud', 'solicitada', 'en_proceso', 'facturada', 'rechazada'])->default('sin_solicitud');
            $t->string('invoice_uuid', 64)->nullable();

            // Integraciones
            $t->unsignedBigInteger('payment_id')->nullable();        // liga a payments si existe
            $t->unsignedBigInteger('invoice_request_id')->nullable(); // liga a billing_invoice_requests si existe

            // Reglas de “incluir en estado de cuenta”
            $t->boolean('include_in_statement')->default(false);
            $t->string('target_period', 7)->nullable(); // 'YYYY-MM' (siguiente mes normalmente)

            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['sale_date', 'origin', 'periodicity']);
            $t->index(['statement_status', 'invoice_status']);
            $t->index(['vendor_id']);
            $t->index(['receiver_rfc']);
            $t->index(['target_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_sales');
    }
};
