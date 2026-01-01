<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OJO: esta tabla vive en mysql_clientes (p360v1_clientes)
     */
    private string $conn = 'mysql_clientes';
    private string $table = 'cuentas_cliente';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            // Si tu app ya tiene un campo equivalente, NO lo duplicamos.
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'vault_active')) {
                $table->boolean('vault_active')->default(false)->after('espacio_asignado_mb')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'vault_quota_bytes')) {
                $table->unsignedBigInteger('vault_quota_bytes')->default(0)->after('vault_active')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'vault_used_bytes')) {
                $table->unsignedBigInteger('vault_used_bytes')->default(0)->after('vault_quota_bytes')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            if (Schema::connection($this->conn)->hasColumn($this->table, 'vault_used_bytes')) {
                $table->dropColumn('vault_used_bytes');
            }
            if (Schema::connection($this->conn)->hasColumn($this->table, 'vault_quota_bytes')) {
                $table->dropColumn('vault_quota_bytes');
            }
            if (Schema::connection($this->conn)->hasColumn($this->table, 'vault_active')) {
                $table->dropColumn('vault_active');
            }
        });
    }
};
