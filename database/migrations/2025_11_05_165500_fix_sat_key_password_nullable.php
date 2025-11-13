<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        $c = $this->conn;

        if (Schema::connection($c)->hasTable('sat_credentials')) {
            // Asegurar columna key_password_enc (NULL)
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'key_password_enc')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN key_password_enc TEXT NULL"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY key_password_enc TEXT NULL"); } catch (\Throwable $e) {}
            }

            // Hacer key_password NULL si existe y está NOT NULL
            if (Schema::connection($c)->hasColumn('sat_credentials', 'key_password')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY key_password TEXT NULL"); } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        // No forzamos volver a NOT NULL para no romper inserciones futuras.
    }
};
