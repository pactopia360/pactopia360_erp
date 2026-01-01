<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta tabla vive en la BD de clientes.
     */
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection ?? config('database.default');

        // Crear tabla solo si NO existe
        if (!Schema::connection($conn)->hasTable('sat_cart_items')) {
            Schema::connection($conn)->create('sat_cart_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cuenta_id');       // vínculo lógico a cuentas.id (en otro esquema/admin)
                $table->uuid('sat_download_id'); // FK real a sat_downloads en mysql_clientes

                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('MXN');
                $table->string('status', 20)->default('pending'); // pending, paid, canceled
                $table->timestamps();

                $table->unique(['cuenta_id', 'sat_download_id']);

                // Índices
                $table->index('cuenta_id');
                $table->index('sat_download_id');

                // FK SOLO a sat_downloads (misma BD mysql_clientes)
                $table->foreign('sat_download_id')
                    ->references('id')
                    ->on('sat_downloads')
                    ->onDelete('cascade');
            });
        }

        // IMPORTANTE:
        // Ya NO intentamos agregar FK a `cuentas` aquí, porque vive en otra BD
        // o no existe en mysql_clientes. El vínculo con cuenta se hace por UUID
        // a nivel de aplicación.
    }

    public function down(): void
    {
        $conn = $this->connection ?? config('database.default');

        Schema::connection($conn)->dropIfExists('sat_cart_items');
    }
};
