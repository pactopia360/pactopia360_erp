<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración obsoleta: la tabla sat_cart_items
     * ya se define en 2025_11_24_194114_create_sat_cart_items_table.
     *
     * La dejamos como NO-OP para evitar errores de
     * "Base table or view already exists" en producción.
     */
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection ?? config('database.default');

        // Si la tabla ya existe (caso normal), no hacemos nada.
        if (Schema::connection($conn)->hasTable('sat_cart_items')) {
            return;
        }

        // Si NO existiera (caso muy raro porque falta la migración del 24),
        // podrías crearla aquí copiando el esquema correcto, pero por ahora
        // lo dejamos vacío para no duplicar definiciones.
    }

    public function down(): void
    {
        // No borramos nada aquí porque esta migración no crea la tabla.
        // El drop real ya lo maneja la migración 2025_11_24_194114_...
    }
};
