<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn  = 'mysql_clientes';
        $table = 'cfdis';

        // Idempotente: si ya existe, no hace nada
        if (Schema::connection($conn)->hasTable($table)) {
            return;
        }

        Schema::connection($conn)->create($table, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('cuenta_id')->nullable()->index();
            $table->string('uuid', 40)->unique();

            $table->date('fecha')->index();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('iva', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->string('tipo', 20)->nullable()->index();
            $table->string('rfc_emisor', 20)->nullable()->index();
            $table->string('rfc_receptor', 20)->nullable()->index();

            $table->string('status', 40)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('cfdis');
    }
};
