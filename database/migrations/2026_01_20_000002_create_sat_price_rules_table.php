<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // NOOP: sat_price_rules ya existe (creada por otra migración).
        // Dejamos esto para no romper el historial de migraciones.
    }

    public function down(): void
    {
        // NOOP
    }
};
