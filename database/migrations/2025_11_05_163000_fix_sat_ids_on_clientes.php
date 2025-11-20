<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Conexión: clientes
        $schema = Schema::connection('mysql_clientes');

        // ========== sat_credentials ==========
        if ($schema->hasTable('sat_credentials')) {
            $schema->table('sat_credentials', function (Blueprint $table) {
                // id -> CHAR(36) PK
                try {
                    $table->char('id', 36)->change();
                } catch (\Throwable $e) {
                    // Si no se puede change(), lo ignoramos
                }

                // cuenta_id -> CHAR(36)
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'cuenta_id')) {
                    try {
                        $table->char('cuenta_id', 36)->nullable(false)->change();
                    } catch (\Throwable $e) {}
                } else {
                    $table->char('cuenta_id', 36)->nullable(false);
                }

                // rfc -> 13 chars típicamente
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'rfc')) {
                    try {
                        $table->string('rfc', 13)->nullable(false)->change();
                    } catch (\Throwable $e) {}
                } else {
                    $table->string('rfc', 13)->nullable(false);
                }

                // alias / razón social (si faltaba)
                if (!Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'razon_social')) {
                    $table->string('razon_social', 190)->nullable()->after('rfc');
                }

                // paths
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'cer_path')) {
                    try {
                        $table->string('cer_path', 255)->nullable()->change();
                    } catch (\Throwable $e) {}
                }
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'key_path')) {
                    try {
                        $table->string('key_path', 255)->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // password cifrada
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'key_password_enc')) {
                    try {
                        $table->text('key_password_enc')->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // meta
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'meta')) {
                    try {
                        $table->json('meta')->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // validated_at
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'validated_at')) {
                    try {
                        $table->timestamp('validated_at')->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // timestamps si faltan
                if (!Schema::connection('mysql_clientes')->hasColumn('sat_credentials','created_at')) {
                    $table->timestamps();
                }

                // ❌ OJO: sin índices aquí para evitar errores reentrantes
            });
        }

        // ========== sat_downloads ==========
        if ($schema->hasTable('sat_downloads')) {
            $schema->table('sat_downloads', function (Blueprint $table) {
                // id -> CHAR(36)
                try {
                    $table->char('id', 36)->change();
                } catch (\Throwable $e) {}

                // cuenta_id -> CHAR(36)
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'cuenta_id')) {
                    try {
                        $table->char('cuenta_id', 36)->nullable(false)->change();
                    } catch (\Throwable $e) {}
                } else {
                    $table->char('cuenta_id', 36)->nullable(false);
                }

                // rfc / tipo / status
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'rfc')) {
                    try {
                        $table->string('rfc', 13)->nullable(false)->change();
                    } catch (\Throwable $e) {}
                }
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'tipo')) {
                    try {
                        $table->string('tipo', 10)->nullable(false)->change();
                    } catch (\Throwable $e) {}
                }
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'status')) {
                    try {
                        $table->string('status', 20)->nullable(false)->default('pending')->change();
                    } catch (\Throwable $e) {}
                }

                // fechas (DATE o DATETIME según tengas; aquí las dejamos datetime)
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'date_from')) {
                    try {
                        $table->dateTime('date_from')->nullable()->change();
                    } catch (\Throwable $e) {}
                }
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'date_to')) {
                    try {
                        $table->dateTime('date_to')->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // request/package
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'request_id')) {
                    try {
                        $table->string('request_id', 64)->nullable()->change();
                    } catch (\Throwable $e) {}
                }
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'package_id')) {
                    try {
                        $table->string('package_id', 64)->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // zip / error
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_path')) {
                    try {
                        $table->string('zip_path', 255)->nullable()->change();
                    } catch (\Throwable $e) {}
                }
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'error_message')) {
                    try {
                        $table->text('error_message')->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // expires_at
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'expires_at')) {
                    try {
                        $table->timestamp('expires_at')->nullable()->change();
                    } catch (\Throwable $e) {}
                }

                // flag auto (si existe)
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads','auto')) {
                    try {
                        $table->boolean('auto')->default(false)->change();
                    } catch (\Throwable $e) {}
                }

                // ❌ Sin índices aquí tampoco
            });
        }
    }

    public function down(): void
    {
        // No revertimos nada (safe). Esta migración solo normaliza tipos.
    }
};
