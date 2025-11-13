<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        // sat_credentials
        Schema::connection($this->conn)->table('sat_credentials', function (Blueprint $t) {
            // id CHAR(36)
            if (Schema::connection($this->conn)->hasColumn('sat_credentials', 'id')) {
                $t->char('id', 36)->change();
            }
            // cuenta_id CHAR(36)
            if (Schema::connection($this->conn)->hasColumn('sat_credentials', 'cuenta_id')) {
                $t->char('cuenta_id', 36)->change();
            }
            // alias/razon_social opcional
            if (!Schema::connection($this->conn)->hasColumn('sat_credentials', 'razon_social')) {
                $t->string('razon_social', 190)->nullable()->after('rfc');
            }
            // paths opcionales
            if (Schema::connection($this->conn)->hasColumn('sat_credentials','cer_path')) {
                $t->string('cer_path', 255)->nullable()->change();
            }
            if (Schema::connection($this->conn)->hasColumn('sat_credentials','key_path')) {
                $t->string('key_path', 255)->nullable()->change();
            }
            // contraseña cifrada (texto) — si no existe, la creamos
            if (!Schema::connection($this->conn)->hasColumn('sat_credentials','key_password_enc')) {
                $t->text('key_password_enc')->nullable()->after('key_path');
            }
            // meta json nullable
            if (Schema::connection($this->conn)->hasColumn('sat_credentials','meta')) {
                $t->json('meta')->nullable()->change();
            }
            // validated_at datetime nullable
            if (Schema::connection($this->conn)->hasColumn('sat_credentials','validated_at')) {
                $t->dateTime('validated_at')->nullable()->change();
            }
        });

        // sat_downloads
        Schema::connection($this->conn)->table('sat_downloads', function (Blueprint $t) {
            if (Schema::connection($this->conn)->hasColumn('sat_downloads','id')) {
                $t->char('id',36)->change();
            }
            if (Schema::connection($this->conn)->hasColumn('sat_downloads','cuenta_id')) {
                $t->char('cuenta_id',36)->change();
            }
            // flags y tipos
            if (!Schema::connection($this->conn)->hasColumn('sat_downloads','auto')) {
                $t->boolean('auto')->default(false)->after('tipo');
            }
            if (Schema::connection($this->conn)->hasColumn('sat_downloads','status')) {
                $t->string('status', 32)->default('pending')->change();
            }
            if (!Schema::connection($this->conn)->hasColumn('sat_downloads','request_id')) {
                $t->string('request_id', 64)->nullable()->after('status');
            }
            if (!Schema::connection($this->conn)->hasColumn('sat_downloads','package_id')) {
                $t->string('package_id', 64)->nullable()->after('request_id');
            }
            if (Schema::connection($this->conn)->hasColumn('sat_downloads','zip_path')) {
                $t->string('zip_path', 255)->nullable()->change();
            }
            if (!Schema::connection($this->conn)->hasColumn('sat_downloads','error_message')) {
                $t->text('error_message')->nullable()->after('zip_path');
            }
            if (!Schema::connection($this->conn)->hasColumn('sat_downloads','expires_at')) {
                $t->dateTime('expires_at')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        // No revertimos tipos para evitar perder datos.
    }
};
