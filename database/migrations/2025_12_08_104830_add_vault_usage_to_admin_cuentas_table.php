<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Esta migración es para el esquema ADMIN
    protected string $connectionName = 'mysql_admin';

    public function up(): void
    {
        $conn      = $this->connectionName;
        $tableName = 'cuentas';

        if (! Schema::connection($conn)->hasTable($tableName)) {
            return;
        }

        Schema::connection($conn)->table($tableName, function (Blueprint $table) use ($conn, $tableName) {
            $schema = Schema::connection($conn);

            // Bytes usados de bóveda (total acumulado)
            if (! $schema->hasColumn($tableName, 'vault_used_bytes')) {
                $table->unsignedBigInteger('vault_used_bytes')
                    ->default(0);
            }

            // GB usados (derivado, para dashboards / display)
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
        });
    }
};
