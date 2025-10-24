<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        Schema::connection($this->conn)->create('cfdi_conceptos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cfdi_id')->index();
            $t->unsignedBigInteger('producto_id')->nullable();
            $t->string('descripcion', 500);
            $t->decimal('cantidad', 14, 4)->default(1);
            $t->decimal('precio_unitario', 14, 4)->default(0);
            $t->decimal('subtotal', 14, 4)->default(0);
            $t->decimal('iva', 14, 4)->default(0);
            $t->decimal('total', 14, 4)->default(0);
            $t->json('impuestos')->nullable(); // para retenciones/IEPS futuros
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('cfdi_conceptos');
    }
};
