<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection;

        if (!Schema::connection($conn)->hasTable('cfdis')) {
            return;
        }

        Schema::connection($conn)->table('cfdis', function (Blueprint $table) use ($conn) {

            // Nota: NO usamos ->after(...) porque en algunos MariaDB/Win da lata según orden real.
            if (!Schema::connection($conn)->hasColumn('cfdis', 'tipo')) {
                $table->string('tipo', 20)->nullable()->index();
            }

            if (!Schema::connection($conn)->hasColumn('cfdis', 'rfc_emisor')) {
                $table->string('rfc_emisor', 13)->nullable()->index();
            }
            if (!Schema::connection($conn)->hasColumn('cfdis', 'rfc_receptor')) {
                $table->string('rfc_receptor', 13)->nullable()->index();
            }

            if (!Schema::connection($conn)->hasColumn('cfdis', 'razon_emisor')) {
                $table->string('razon_emisor', 255)->nullable();
            }
            if (!Schema::connection($conn)->hasColumn('cfdis', 'razon_receptor')) {
                $table->string('razon_receptor', 255)->nullable();
            }

            if (!Schema::connection($conn)->hasColumn('cfdis', 'subtotal')) {
                $table->decimal('subtotal', 14, 2)->default(0);
            }
            if (!Schema::connection($conn)->hasColumn('cfdis', 'iva')) {
                $table->decimal('iva', 14, 2)->default(0);
            }

            if (!Schema::connection($conn)->hasColumn('cfdis', 'fecha_cfdi')) {
                $table->dateTime('fecha_cfdi')->nullable()->index();
            }
        });
    }

    public function down(): void {}
};
