<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentas_cliente')) {
            return;
        }

        Schema::create('cuentas_cliente', function (Blueprint $t) {
            $t->uuid('id')->primary();

            // Identidad / enlace con ADMIN
            $t->unsignedBigInteger('admin_account_id')->nullable()->index();
            $t->string('rfc_padre', 13)->index();              // RFC principal (UPPER)
            $t->string('razon_social', 190)->nullable();

            // Códigos internos
            $t->string('codigo_cliente', 50)->nullable()->index();
            $t->unsignedInteger('customer_no')->nullable()->index();

            // Plan / estado
            $t->string('plan_actual', 20)->nullable()->index();    // FREE|PRO
            $t->string('modo_cobro', 20)->nullable()->index();     // free|mensual|anual
            $t->string('estado_cuenta', 30)->nullable()->index();  // pendiente|bloqueada_pago|activa|etc
            $t->boolean('activo')->default(false)->index();
            $t->boolean('is_blocked')->default(false)->index();

            // Contacto espejo (opcional, por si lo quieres en clientes)
            $t->string('email', 150)->nullable()->index();
            $t->string('telefono', 25)->nullable();

            // Límites / cuotas
            $t->unsignedInteger('espacio_asignado_mb')->nullable(); // FREE 512, PRO 15360, etc
            $t->unsignedInteger('hits_asignados')->nullable();      // si lo sigues usando
            $t->unsignedInteger('max_usuarios')->nullable();
            $t->unsignedInteger('max_empresas')->nullable();

            // Billing espejo (opcional)
            $t->string('billing_cycle', 20)->nullable(); // mensual|anual
            $t->date('next_invoice_date')->nullable();

            $t->timestamps();

            // Únicos (los puedes relajar si aún estás migrando legacy)
            $t->unique(['rfc_padre'], 'uq_cuentas_cliente_rfc_padre');
            $t->unique(['customer_no'], 'uq_cuentas_cliente_customer_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_cliente');
    }
};
