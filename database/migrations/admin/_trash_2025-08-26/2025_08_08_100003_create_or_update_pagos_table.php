<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
    $conn = 'mysql_admin';
    if (!Schema::connection($conn)->hasTable('pagos')) {
        Schema::connection($conn)->create('pagos', function (Blueprint $table) {
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
        Schema::connection($conn)->table('pagos', function (Blueprint $table) use ($conn) {
            $cols = Schema::connection($conn)->getColumnListing('pagos');
            if (!in_array('cliente_id', $cols))   $table->foreignId('cliente_id')->nullable()->index();
            if (!in_array('monto', $cols))        $table->decimal('monto',14,2)->default(0);
            if (!in_array('fecha', $cols))        $table->dateTime('fecha')->nullable()->index();
            if (!in_array('estado', $cols))       $table->string('estado',20)->nullable()->index();
            if (!in_array('metodo_pago', $cols))  $table->string('metodo_pago',30)->nullable();
            if (!in_array('referencia', $cols))   $table->string('referencia',80)->nullable();
            if (!in_array('created_at', $cols))   $table->timestamps();
        });
    }
}


    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('pagos');
    }
};
