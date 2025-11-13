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
                // Algunas DBs no permiten change() directo si era int; hacemos safe-guard.
                try {
                    $table->char('id', 36)->change();
                } catch (\Throwable $e) {
                    // Si no se puede change(), lo recrea si es necesario
                }

                // cuenta_id -> CHAR(36) + índice compuesto útil
                if ($table->hasColumn('cuenta_id')) {
                    try { $table->char('cuenta_id', 36)->nullable(false)->change(); } catch (\Throwable $e) {}
                } else {
                    $table->char('cuenta_id', 36)->nullable(false)->index();
                }

                // rfc -> 13 chars típicamente
                if ($table->hasColumn('rfc')) {
                    try { $table->string('rfc', 13)->nullable(false)->change(); } catch (\Throwable $e) {}
                } else {
                    $table->string('rfc', 13)->nullable(false);
                }

                // alias / razón social (si faltaba)
                if (!Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'razon_social')) {
                    $table->string('razon_social', 190)->nullable()->after('rfc');
                }

                // paths
                if ($table->hasColumn('cer_path')) { try { $table->string('cer_path', 255)->nullable()->change(); } catch (\Throwable $e) {} }
                if ($table->hasColumn('key_path')) { try { $table->string('key_path', 255)->nullable()->change(); } catch (\Throwable $e) {} }

                // password cifrada
                if ($table->hasColumn('key_password_enc')) { try { $table->text('key_password_enc')->nullable()->change(); } catch (\Throwable $e) {} }

                // meta
                if ($table->hasColumn('meta')) { try { $table->json('meta')->nullable()->change(); } catch (\Throwable $e) {} }

                // validated_at
                if ($table->hasColumn('validated_at')) { try { $table->timestamp('validated_at')->nullable()->change(); } catch (\Throwable $e) {} }
                // timestamps si faltan
                if (!Schema::connection('mysql_clientes')->hasColumn('sat_credentials','created_at')) {
                    $table->timestamps();
                }

                // índices útiles
                $table->index(['cuenta_id','rfc']);
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
                if ($table->hasColumn('cuenta_id')) {
                    try { $table->char('cuenta_id', 36)->nullable(false)->change(); } catch (\Throwable $e) {}
                } else {
                    $table->char('cuenta_id', 36)->nullable(false)->index();
                }

                // rfc / tipo / status
                if ($table->hasColumn('rfc'))   { try { $table->string('rfc', 13)->nullable(false)->change(); } catch (\Throwable $e) {} }
                if ($table->hasColumn('tipo'))  { try { $table->string('tipo', 10)->nullable(false)->change(); } catch (\Throwable $e) {} }
                if ($table->hasColumn('status')){ try { $table->string('status', 20)->nullable(false)->default('pending')->change(); } catch (\Throwable $e) {} }

                // fechas (DATE o DATETIME según tengas; aquí las dejamos datetime)
                if ($table->hasColumn('date_from')) { try { $table->dateTime('date_from')->nullable()->change(); } catch (\Throwable $e) {} }
                if ($table->hasColumn('date_to'))   { try { $table->dateTime('date_to')->nullable()->change(); } catch (\Throwable $e) {} }

                // request/package
                if ($table->hasColumn('request_id')) { try { $table->string('request_id', 64)->nullable()->change(); } catch (\Throwable $e) {} }
                if ($table->hasColumn('package_id')) { try { $table->string('package_id', 64)->nullable()->change(); } catch (\Throwable $e) {} }

                // zip / error
                if ($table->hasColumn('zip_path'))      { try { $table->string('zip_path', 255)->nullable()->change(); } catch (\Throwable $e) {} }
                if ($table->hasColumn('error_message')) { try { $table->text('error_message')->nullable()->change(); } catch (\Throwable $e) {} }

                // expires_at
                if ($table->hasColumn('expires_at')) { try { $table->timestamp('expires_at')->nullable()->change(); } catch (\Throwable $e) {} }

                // flag auto (si existe)
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads','auto')) {
                    try { $table->boolean('auto')->default(false)->change(); } catch (\Throwable $e) {}
                }

                // índices
                $table->index(['cuenta_id','rfc']);
                $table->index(['status','created_at']);
            });
        }
    }

    public function down(): void
    {
        // No revertimos tipos (safe), solo quitamos índices agregados.
        $schema = Schema::connection('mysql_clientes');

        if ($schema->hasTable('sat_credentials')) {
            $schema->table('sat_credentials', function (Blueprint $table) {
                try { $table->dropIndex(['sat_credentials_cuenta_id_rfc_index']); } catch (\Throwable $e) {}
            });
        }
        if ($schema->hasTable('sat_downloads')) {
            $schema->table('sat_downloads', function (Blueprint $table) {
                try { $table->dropIndex(['sat_downloads_cuenta_id_rfc_index']); } catch (\Throwable $e) {}
                try { $table->dropIndex(['sat_downloads_status_created_at_index']); } catch (\Throwable $e) {}
            });
        }
    }
};
