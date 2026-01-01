<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        // ✅ Si ya existe, no intentar crear (evita 1050 Table already exists)
        if (Schema::connection($this->conn)->hasTable('receptores')) {
            // Asegura el índice compuesto si por alguna razón no existe.
            // Nota: sin Doctrine DBAL no podemos inspeccionar índices de forma portable,
            // así que evitamos errores solo intentando agregarlo dentro de try/catch.
            try {
                Schema::connection($this->conn)->table('receptores', function (Blueprint $t) {
                    $t->index(['cuenta_id', 'rfc'], 'receptores_cuenta_id_rfc_index');
                });
            } catch (\Throwable $e) {
                // Si el índice ya existe (o el motor no permite duplicados), ignoramos.
            }
            return;
        }

        Schema::connection($this->conn)->create('receptores', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cuenta_id')->nullable()->index(); // multi-tenant
            $t->string('rfc', 13)->index();
            $t->string('razon_social', 255);
            $t->string('uso_cfdi', 10)->nullable();
            $t->string('regimen_fiscal', 10)->nullable();
            $t->string('email', 180)->nullable();
            $t->string('telefono', 40)->nullable();
            $t->json('extras')->nullable();
            $t->timestamps();

            // ✅ Índice compuesto (nombre explícito para control)
            $t->index(['cuenta_id', 'rfc'], 'receptores_cuenta_id_rfc_index');
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('receptores');
    }
};
