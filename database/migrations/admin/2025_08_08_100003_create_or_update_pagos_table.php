<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete()->index();
                $table->decimal('monto', 14, 2)->default(0);
                $table->dateTime('fecha')->index();
                $table->string('estado', 20)->default('pendiente')->index(); // pendiente|pagado|cancelado
                $table->string('metodo_pago', 30)->nullable();
                $table->string('referencia', 80)->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('pagos', function (Blueprint $table) {
                if (!Schema::hasColumn('pagos','cliente_id')) $table->foreignId('cliente_id')->nullable()->index();
                if (!Schema::hasColumn('pagos','monto')) $table->decimal('monto',14,2)->default(0);
                if (!Schema::hasColumn('pagos','fecha')) $table->dateTime('fecha')->nullable()->index();
                if (!Schema::hasColumn('pagos','estado')) $table->string('estado',20)->nullable()->index();
                if (!Schema::hasColumn('pagos','metodo_pago')) $table->string('metodo_pago',30)->nullable();
                if (!Schema::hasColumn('pagos','referencia')) $table->string('referencia',80)->nullable();
                if (!Schema::hasColumn('pagos','created_at')) $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('pagos');
    }
};
