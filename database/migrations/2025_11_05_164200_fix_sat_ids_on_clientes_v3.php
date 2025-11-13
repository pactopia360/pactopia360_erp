<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        $c = $this->conn;

        // ==========================
        // sat_credentials
        // ==========================
        if (Schema::connection($c)->hasTable('sat_credentials')) {
            // id, cuenta_id, rfc -> a tipos correctos (CHAR(36) / VARCHAR)
            // Ojo: MODIFY falla si no existe la columna, por eso asumimos que sí existen estas 3 base.
            try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY id CHAR(36) NOT NULL"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY cuenta_id CHAR(36) NOT NULL"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY rfc VARCHAR(13) NOT NULL"); } catch (\Throwable $e) {}

            // razon_social
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'razon_social')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN razon_social VARCHAR(190) NULL AFTER rfc"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY razon_social VARCHAR(190) NULL"); } catch (\Throwable $e) {}
            }

            // cer_path
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'cer_path')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN cer_path VARCHAR(255) NULL"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY cer_path VARCHAR(255) NULL"); } catch (\Throwable $e) {}
            }

            // key_path
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'key_path')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN key_path VARCHAR(255) NULL"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY key_path VARCHAR(255) NULL"); } catch (\Throwable $e) {}
            }

            // key_password_enc
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'key_password_enc')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN key_password_enc TEXT NULL"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY key_password_enc TEXT NULL"); } catch (\Throwable $e) {}
            }

            // meta (JSON)
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'meta')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN meta JSON NULL"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY meta JSON NULL"); } catch (\Throwable $e) {}
            }

            // validated_at
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'validated_at')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN validated_at DATETIME NULL"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_credentials MODIFY validated_at DATETIME NULL"); } catch (\Throwable $e) {}
            }

            // created_at/updated_at (si no existen, agrégalos rápido)
            if (!Schema::connection($c)->hasColumn('sat_credentials', 'created_at')) {
                try {
                    DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN created_at DATETIME NULL");
                    DB::connection($c)->statement("ALTER TABLE sat_credentials ADD COLUMN updated_at DATETIME NULL");
                } catch (\Throwable $e) {}
            }

            // índices
            try { DB::connection($c)->statement("CREATE INDEX idx_sat_credentials_cuenta_rfc ON sat_credentials (cuenta_id, rfc)"); } catch (\Throwable $e) {}
        }

        // ==========================
        // sat_downloads
        // ==========================
        if (Schema::connection($c)->hasTable('sat_downloads')) {
            // columnas base que ya deben existir
            try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY id CHAR(36) NOT NULL"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY cuenta_id CHAR(36) NOT NULL"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY rfc VARCHAR(13) NOT NULL"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY tipo VARCHAR(10) NOT NULL"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY status VARCHAR(20) NOT NULL"); } catch (\Throwable $e) {}

            // fechas y otros
            if (Schema::connection($c)->hasColumn('sat_downloads','date_from')) { try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY date_from DATETIME NULL"); } catch (\Throwable $e) {} }
            if (Schema::connection($c)->hasColumn('sat_downloads','date_to'))   { try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY date_to DATETIME NULL"); } catch (\Throwable $e) {} }
            if (Schema::connection($c)->hasColumn('sat_downloads','request_id')){ try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY request_id VARCHAR(64) NULL"); } catch (\Throwable $e) {} }
            if (Schema::connection($c)->hasColumn('sat_downloads','package_id')){ try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY package_id VARCHAR(64) NULL"); } catch (\Throwable $e) {} }
            if (Schema::connection($c)->hasColumn('sat_downloads','zip_path'))  { try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY zip_path VARCHAR(255) NULL"); } catch (\Throwable $e) {} }
            if (Schema::connection($c)->hasColumn('sat_downloads','error_message')) { try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY error_message TEXT NULL"); } catch (\Throwable $e) {} }
            if (Schema::connection($c)->hasColumn('sat_downloads','expires_at')){ try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY expires_at DATETIME NULL"); } catch (\Throwable $e) {} }

            // auto (si no existe, agrégalo)
            if (!Schema::connection($c)->hasColumn('sat_downloads','auto')) {
                try { DB::connection($c)->statement("ALTER TABLE sat_downloads ADD COLUMN auto TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
            } else {
                try { DB::connection($c)->statement("ALTER TABLE sat_downloads MODIFY auto TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
            }

            // timestamps si faltan
            if (!Schema::connection($c)->hasColumn('sat_downloads', 'created_at')) {
                try {
                    DB::connection($c)->statement("ALTER TABLE sat_downloads ADD COLUMN created_at DATETIME NULL");
                    DB::connection($c)->statement("ALTER TABLE sat_downloads ADD COLUMN updated_at DATETIME NULL");
                } catch (\Throwable $e) {}
            }

            // índices
            try { DB::connection($c)->statement("CREATE INDEX idx_sat_downloads_cuenta_rfc ON sat_downloads (cuenta_id, rfc)"); } catch (\Throwable $e) {}
            try { DB::connection($c)->statement("CREATE INDEX idx_sat_downloads_status_created ON sat_downloads (status, created_at)"); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        $c = $this->conn;
        try { DB::connection($c)->statement("DROP INDEX idx_sat_credentials_cuenta_rfc ON sat_credentials"); } catch (\Throwable $e) {}
        try { DB::connection($c)->statement("DROP INDEX idx_sat_downloads_cuenta_rfc ON sat_downloads"); } catch (\Throwable $e) {}
        try { DB::connection($c)->statement("DROP INDEX idx_sat_downloads_status_created ON sat_downloads"); } catch (\Throwable $e) {}
    }
};
