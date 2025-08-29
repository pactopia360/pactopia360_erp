<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cfdis')) {
            Schema::create('cfdis', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete()->index();
                $table->string('serie', 10)->nullable();
                $table->string('folio', 20)->nullable();
                $table->decimal('total', 14, 2)->default(0);
                $table->dateTime('fecha')->index(); // fecha de timbrado/emisión
                $table->string('estatus', 20)->default('emitido')->index(); // emitido|cancelado...
                $table->uuid('uuid')->nullable()->unique();
                $table->timestamps();
            });
        } else {
            Schema::table('cfdis', function (Blueprint $table) {
                if (!Schema::hasColumn('cfdis','cliente_id')) $table->foreignId('cliente_id')->nullable()->index();
                if (!Schema::hasColumn('cfdis','serie')) $table->string('serie',10)->nullable();
                if (!Schema::hasColumn('cfdis','folio')) $table->string('folio',20)->nullable();
                if (!Schema::hasColumn('cfdis','total')) $table->decimal('total',14,2)->default(0);
                if (!Schema::hasColumn('cfdis','fecha')) $table->dateTime('fecha')->nullable()->index();
                if (!Schema::hasColumn('cfdis','estatus')) $table->string('estatus',20)->nullable()->index();
                if (!Schema::hasColumn('cfdis','uuid')) $table->uuid('uuid')->nullable()->unique();
                if (!Schema::hasColumn('cfdis','created_at')) $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('cfdis');
    }
};
