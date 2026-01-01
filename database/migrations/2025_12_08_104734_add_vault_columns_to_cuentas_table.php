<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Esta migración es para el esquema de clientes
    protected string $connectionName = 'mysql_clientes';

    public function up(): void
    {
        $conn      = $this->connectionName;
        $tableName = 'cuentas';

        if (! Schema::connection($conn)->hasTable($tableName)) {
            return;
        }

        Schema::connection($conn)->table($tableName, function (Blueprint $table) use ($conn, $tableName) {
            $schema = Schema::connection($conn);

            // Cuota total de bóveda en bytes (por plan)
            if (! $schema->hasColumn($tableName, 'vault_quota_bytes')) {
                $table->unsignedBigInteger('vault_quota_bytes')
                    ->default(0);
            }

            // Cuota en GB (derivada, para dashboards / display)
            if (! $schema->hasColumn($tableName, 'vault_quota_gb')) {
                $table->decimal('vault_quota_gb', 10, 4)
                    ->default(0);
            }

            // Bytes usados reales
            if (! $schema->hasColumn($tableName, 'vault_used_bytes')) {
                $table->unsignedBigInteger('vault_used_bytes')
                    ->default(0);
            }

            // GB usados (derivado de bytes, para pantallas y cálculo rápido)
            if (! $schema->hasColumn($tableName, 'vault_used_gb')) {
                $table->decimal('vault_used_gb', 10, 4)
                    ->default(0);
            }
        });
    }

    public function down(): void
    {
        $conn      = $this->connectionName;
        $tableName = 'cuentas';

        if (! Schema::connection($conn)->hasTable($tableName)) {
            return;
        }

        Schema::connection($conn)->table($tableName, function (Blueprint $table) use ($conn, $tableName) {
            $schema = Schema::connection($conn);

            if ($schema->hasColumn($tableName, 'vault_used_gb')) {
                $table->dropColumn('vault_used_gb');
            }
            if ($schema->hasColumn($tableName, 'vault_used_bytes')) {
                $table->dropColumn('vault_used_bytes');
            }
            if ($schema->hasColumn($tableName, 'vault_quota_gb')) {
                $table->dropColumn('vault_quota_gb');
            }
            if ($schema->hasColumn($tableName, 'vault_quota_bytes')) {
                $table->dropColumn('vault_quota_bytes');
            }
        });
    }
};
