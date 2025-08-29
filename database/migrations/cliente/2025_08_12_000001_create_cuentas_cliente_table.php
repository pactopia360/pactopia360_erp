<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cuentas_cliente')) {
            Schema::create('cuentas_cliente', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('codigo_cliente', 50)->unique();
                $table->unsignedBigInteger('customer_no')->unique();
                $table->string('rfc_padre', 13)->nullable()->index();
                $table->string('razon_social', 255);
                $table->string('plan_actual', 50)->default('BASIC');     // BASIC | PRO | PREMIUM
                $table->string('modo_cobro', 20)->default('mensual');    // mensual | anual
                $table->string('estado_cuenta', 20)->default('activa');  // activa | suspendida | cancelada
                $table->unsignedInteger('espacio_asignado_mb')->default(1024);
                $table->unsignedInteger('hits_asignados')->default(100);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_cliente');
    }
};
