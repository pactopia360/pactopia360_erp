<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Solo conexión clientes
        $conn = 'mysql_clientes';

        // ===== sat_credentials =====
        if (Schema::connection($conn)->hasTable('sat_credentials')) {
            // Asegura columnas base
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'razon_social')) {
                DB::connection($conn)->statement("ALTER TABLE sat_credentials ADD COLUMN razon_social VARCHAR(190) NULL AFTER rfc");
            }

            // Normaliza tipos a UUID/strings
            // NOTA: CHANGE/MODIFY quita AUTO_INCREMENT si existía.
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY id CHAR(36) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY cuenta_id CHAR(36) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY rfc VARCHAR(13) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY cer_path VARCHAR(255) NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY key_path VARCHAR(255) NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY key_password_enc TEXT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY meta JSON NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_credentials MODIFY validated_at DATETIME NULL");

            // Índices útiles
            try { DB::connection($conn)->statement("CREATE INDEX idx_sat_credentials_cuenta_rfc ON sat_credentials (cuenta_id, rfc)"); } catch (\Throwable $e) {}
        }

        // ===== sat_downloads =====
        if (Schema::connection($conn)->hasTable('sat_downloads')) {
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY id CHAR(36) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY cuenta_id CHAR(36) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY rfc VARCHAR(13) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY tipo VARCHAR(10) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY status VARCHAR(20) NOT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY date_from DATETIME NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY date_to DATETIME NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY request_id VARCHAR(64) NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY package_id VARCHAR(64) NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY zip_path VARCHAR(255) NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY error_message TEXT NULL");
            DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY expires_at DATETIME NULL");

            // Campos opcionales que agregaste después (si existen, normalízalos)
            try { DB::connection($conn)->statement("ALTER TABLE sat_downloads MODIFY auto TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}

            try { DB::connection($conn)->statement("CREATE INDEX idx_sat_downloads_cuenta_rfc ON sat_downloads (cuenta_id, rfc)"); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement("CREATE INDEX idx_sat_downloads_status_created ON sat_downloads (status, created_at)"); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // No revertimos tipos (seguro).
        // Si quieres limpiar índices:
        $conn = 'mysql_clientes';
        try { DB::connection($conn)->statement("DROP INDEX idx_sat_credentials_cuenta_rfc ON sat_credentials"); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement("DROP INDEX idx_sat_downloads_cuenta_rfc ON sat_downloads"); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement("DROP INDEX idx_sat_downloads_status_created ON sat_downloads"); } catch (\Throwable $e) {}
    }
};
