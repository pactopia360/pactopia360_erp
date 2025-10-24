<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        Schema::connection($this->conn)->create('productos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cuenta_id')->index()->nullable(); // multi-tenant
            $t->string('sku', 80)->nullable();
            $t->string('descripcion', 500);
            $t->string('clave_prodserv', 20)->nullable();
            $t->string('clave_unidad', 20)->nullable();
            $t->decimal('precio_unitario', 14, 4)->default(0);
            $t->decimal('iva_tasa', 6, 4)->default(0.1600); // 16% default
            $t->boolean('activo')->default(true);
            $t->json('extras')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('productos');
    }
};
