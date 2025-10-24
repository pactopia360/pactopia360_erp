<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        Schema::connection($this->conn)->create('receptores', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cuenta_id')->index()->nullable(); // multi-tenant
            $t->string('rfc', 13)->index();
            $t->string('razon_social', 255);
            $t->string('uso_cfdi', 10)->nullable();
            $t->string('regimen_fiscal', 10)->nullable();
            $t->string('email', 180)->nullable();
            $t->string('telefono', 40)->nullable();
            $t->json('extras')->nullable();
            $t->timestamps();
        });
        // Índice compuesto útil
        Schema::connection($this->conn)->table('receptores', function (Blueprint $t) {
            $t->index(['cuenta_id','rfc']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('receptores');
    }
};
