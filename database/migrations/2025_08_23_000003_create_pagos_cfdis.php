<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->nullable();
                $t->decimal('monto',12,2)->default(0);
                $t->dateTime('fecha')->nullable();
                $t->string('estado',20)->nullable(); // pagado|pendiente
                $t->string('metodo_pago',50)->nullable();
                $t->string('referencia',64)->nullable();
                $t->timestamps();
                $t->index('cliente_id');
                $t->index('fecha');
            });
        }

        if (!Schema::hasTable('cfdis')) {
            Schema::create('cfdis', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->nullable();
                $t->string('serie',10)->nullable();
                $t->string('folio',20)->nullable();
                $t->decimal('total',12,2)->default(0);
                $t->dateTime('fecha')->nullable();
                $t->string('estatus',20)->nullable(); // emitido|cancelado
                $t->uuid('uuid')->unique();
                $t->timestamps();
                $t->index('cliente_id');
                $t->index('fecha');
            });
        }
    }
    public function down(): void {}
};
