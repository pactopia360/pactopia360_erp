<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->conn;

        // Si no existe la tabla, no hacemos nada
        if (! Schema::connection($conn)->hasTable('sat_downloads')) {
            return;
        }

        Schema::connection($conn)->table('sat_downloads', function (Blueprint $table) use ($conn) {
            // Solo agregar si NO existe todavía
            if (! Schema::connection($conn)->hasColumn('sat_downloads', 'costo')) {
                // 👈 OJO: SIN "after('xml_count')" para no depender de esa columna
                $table->decimal('costo', 12, 2)
                      ->nullable()
                      ->default(null);
            }
        });
    }

    public function down(): void
    {
        $conn = $this->conn;

        if (! Schema::connection($conn)->hasTable('sat_downloads')) {
            return;
        }

        Schema::connection($conn)->table('sat_downloads', function (Blueprint $table) use ($conn) {
            if (Schema::connection($conn)->hasColumn('sat_downloads', 'costo')) {
                $table->dropColumn('costo');
            }
        });
    }
};
