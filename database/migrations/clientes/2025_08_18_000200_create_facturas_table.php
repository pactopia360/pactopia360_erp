<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('facturas')) {
            Schema::connection('mysql_clientes')->create('facturas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cliente_id')->nullable()->index();

                // Campos mínimos que esperan StatsService / IncomeService
                $table->dateTime('fecha')->nullable()->index();           // fecha de emisión
                $table->decimal('total', 12, 2)->default(0);              // importe total

                // Metadatos
                $table->timestamps();

                // FK opcional hacia clientes
                $table->foreign('cliente_id')
                      ->references('id')->on('clientes')
                      ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('facturas');
    }
};
