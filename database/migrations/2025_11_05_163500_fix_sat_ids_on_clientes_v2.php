<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->conn;

        // ==========================================================
        // sat_credentials
        // ==========================================================
        if (Schema::connection($conn)->hasTable('sat_credentials')) {

            // 1) Asegurar columnas (si no existen, se crean)
            Schema::connection($conn)->table('sat_credentials', function (Blueprint $table) use ($conn) {

                if (!Schema::connection($conn)->hasColumn('sat_credentials', 'razon_social')) {
                    // lo pedías AFTER rfc, pero Schema no garantiza "after" en todos los drivers;
                    // en MySQL sí, usamos after() si existe.
                    $col = $table->string('razon_social', 190)->nullable();
                    try { $col->after('rfc'); } catch (\Throwable $e) {}
                }

                if (!Schema::connection($conn)->hasColumn('sat_credentials', 'meta')) {
                    // JSON (MySQL) / TEXT fallback si tu MariaDB no soportara JSON (pero normalmente sí)
                    try {
                        $table->json('meta')->nullable();
                    } catch (\Throwable $e) {
                        $table->text('meta')->nullable();
                    }
                }

                if (!Schema::connection($conn)->hasColumn('sat_credentials', 'validated_at')) {
                    $table->dateTime('validated_at')->nullable();
                }

                if (!Schema::connection($conn)->hasColumn('sat_credentials', 'key_password_enc')) {
                    $table->text('key_password_enc')->nullable();
                }
            });

            // 2) Normalizar tipos SOLO si existe la columna y es razonable
            //    - IMPORTANTE: NO tocamos id si es AUTO_INCREMENT.
            $this->safeModify($conn, 'sat_credentials', 'cuenta_id', "CHAR(36) NOT NULL");
            $this->safeModify($conn, 'sat_credentials', 'rfc', "VARCHAR(13) NOT NULL");
            $this->safeModify($conn, 'sat_credentials', 'cer_path', "VARCHAR(255) NULL");
            $this->safeModify($conn, 'sat_credentials', 'key_path', "VARCHAR(255) NULL");
            $this->safeModify($conn, 'sat_credentials', 'key_password_enc', "TEXT NULL");
            $this->safeModify($conn, 'sat_credentials', 'validated_at', "DATETIME NULL");

            // meta: forzamos JSON NULL si el motor lo soporta; si truena, lo dejamos.
            if ($this->columnExists($conn, 'sat_credentials', 'meta')) {
                try {
                    DB::connection($conn)->statement("ALTER TABLE `sat_credentials` MODIFY `meta` JSON NULL");
                } catch (\Throwable $e) {
                    // si no soporta JSON, no hacemos nada
                }
            }

            // id: solo si NO es auto_increment y existe
            if ($this->columnExists($conn, 'sat_credentials', 'id') && !$this->isAutoIncrement($conn, 'sat_credentials', 'id')) {
                $this->safeModify($conn, 'sat_credentials', 'id', "CHAR(36) NOT NULL");
            }

            // Índice (idempotente con try/catch)
            try {
                DB::connection($conn)->statement("CREATE INDEX idx_sat_credentials_cuenta_rfc ON sat_credentials (cuenta_id, rfc)");
            } catch (\Throwable $e) {}
        }

        // ==========================================================
        // sat_downloads
        // ==========================================================
        if (Schema::connection($conn)->hasTable('sat_downloads')) {

            // Asegurar algunas columnas que a veces faltan (según tu historial)
            Schema::connection($conn)->table('sat_downloads', function (Blueprint $table) use ($conn) {

                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'zip_path')) {
                    $table->string('zip_path', 255)->nullable();
                }
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'expires_at')) {
                    $table->dateTime('expires_at')->nullable();
                }
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'request_id')) {
                    $table->string('request_id', 64)->nullable();
                }
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'package_id')) {
                    $table->string('package_id', 64)->nullable();
                }
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'date_from')) {
                    $table->dateTime('date_from')->nullable();
                }
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'date_to')) {
                    $table->dateTime('date_to')->nullable();
                }

                // opcional auto
                if (!Schema::connection($conn)->hasColumn('sat_downloads', 'auto')) {
                    $table->boolean('auto')->default(false);
                }
            });

            // Normalizaciones seguras
            $this->safeModify($conn, 'sat_downloads', 'cuenta_id', "CHAR(36) NOT NULL");
            $this->safeModify($conn, 'sat_downloads', 'rfc', "VARCHAR(13) NOT NULL");
            $this->safeModify($conn, 'sat_downloads', 'tipo', "VARCHAR(10) NOT NULL");
            $this->safeModify($conn, 'sat_downloads', 'status', "VARCHAR(20) NOT NULL");
            $this->safeModify($conn, 'sat_downloads', 'date_from', "DATETIME NULL");
            $this->safeModify($conn, 'sat_downloads', 'date_to', "DATETIME NULL");
            $this->safeModify($conn, 'sat_downloads', 'request_id', "VARCHAR(64) NULL");
            $this->safeModify($conn, 'sat_downloads', 'package_id', "VARCHAR(64) NULL");
            $this->safeModify($conn, 'sat_downloads', 'zip_path', "VARCHAR(255) NULL");
            $this->safeModify($conn, 'sat_downloads', 'error_message', "TEXT NULL");
            $this->safeModify($conn, 'sat_downloads', 'expires_at', "DATETIME NULL");

            // auto (si existe)
            if ($this->columnExists($conn, 'sat_downloads', 'auto')) {
                try {
                    DB::connection($conn)->statement("ALTER TABLE `sat_downloads` MODIFY `auto` TINYINT(1) NOT NULL DEFAULT 0");
                } catch (\Throwable $e) {}
            }

            // id: solo si NO es auto_increment
            if ($this->columnExists($conn, 'sat_downloads', 'id') && !$this->isAutoIncrement($conn, 'sat_downloads', 'id')) {
                $this->safeModify($conn, 'sat_downloads', 'id', "CHAR(36) NOT NULL");
            }

            // Índices
            try {
                DB::connection($conn)->statement("CREATE INDEX idx_sat_downloads_cuenta_rfc ON sat_downloads (cuenta_id, rfc)");
            } catch (\Throwable $e) {}
            try {
                DB::connection($conn)->statement("CREATE INDEX idx_sat_downloads_status_created ON sat_downloads (status, created_at)");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        $conn = $this->conn;
        try { DB::connection($conn)->statement("DROP INDEX idx_sat_credentials_cuenta_rfc ON sat_credentials"); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement("DROP INDEX idx_sat_downloads_cuenta_rfc ON sat_downloads"); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement("DROP INDEX idx_sat_downloads_status_created ON sat_downloads"); } catch (\Throwable $e) {}
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private function columnExists(string $conn, string $table, string $col): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isAutoIncrement(string $conn, string $table, string $col): bool
    {
        try {
            $db = DB::connection($conn)->getDatabaseName();
            $row = DB::connection($conn)->selectOne(
                "SELECT EXTRA FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1",
                [$db, $table, $col]
            );

            $extra = strtolower((string)($row->EXTRA ?? ''));
            return str_contains($extra, 'auto_increment');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeModify(string $conn, string $table, string $col, string $definition): void
    {
        if (!$this->columnExists($conn, $table, $col)) {
            return;
        }

        // Si intentan modificar un id autoincrement con raw, se rompe. Lo evitamos.
        if ($col === 'id' && $this->isAutoIncrement($conn, $table, $col)) {
            return;
        }

        try {
            DB::connection($conn)->statement("ALTER TABLE `$table` MODIFY `$col` $definition");
        } catch (\Throwable $e) {
            // idempotente: si no se puede por compatibilidad de motor/tipo, no reventamos instalación
        }
    }
};
