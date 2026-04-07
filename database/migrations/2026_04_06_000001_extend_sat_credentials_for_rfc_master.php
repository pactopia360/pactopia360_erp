<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $conn = 'mysql_clientes';
    protected string $table = 'sat_credentials';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            // =========================================================
            // Identidad / clasificación del RFC
            // =========================================================
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'tipo_origen')) {
                $table->string('tipo_origen', 20)
                    ->nullable()
                    ->after('razon_social');
                // valores esperados: interno | externo | admin
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'origen_detalle')) {
                $table->string('origen_detalle', 40)
                    ->nullable()
                    ->after('tipo_origen');
                // valores esperados: cliente | invitacion | importado | admin_manual
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'source_label')) {
                $table->string('source_label', 120)
                    ->nullable()
                    ->after('origen_detalle');
            }

            // =========================================================
            // FIEL obligatoria
            // =========================================================
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'fiel_cer_path')) {
                $table->string('fiel_cer_path', 255)
                    ->nullable()
                    ->after('source_label');
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'fiel_key_path')) {
                $table->string('fiel_key_path', 255)
                    ->nullable()
                    ->after('fiel_cer_path');
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'fiel_password_enc')) {
                $table->text('fiel_password_enc')
                    ->nullable()
                    ->after('fiel_key_path');
            }

            // =========================================================
            // CSD opcional
            // =========================================================
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'csd_cer_path')) {
                $table->string('csd_cer_path', 255)
                    ->nullable()
                    ->after('fiel_password_enc');
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'csd_key_path')) {
                $table->string('csd_key_path', 255)
                    ->nullable()
                    ->after('csd_cer_path');
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'csd_password_enc')) {
                $table->text('csd_password_enc')
                    ->nullable()
                    ->after('csd_key_path');
            }

            // =========================================================
            // Flujo externo / estado operativo
            // =========================================================
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'external_upload_id')) {
                $table->unsignedBigInteger('external_upload_id')
                    ->nullable()
                    ->after('csd_password_enc');
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'estatus_operativo')) {
                $table->string('estatus_operativo', 30)
                    ->nullable()
                    ->after('external_upload_id');
                // valores esperados: draft | pending | validated | rejected | inactive
            }

            if (!Schema::connection($this->conn)->hasColumn($this->table, 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // =========================================================
        // Índices
        // =========================================================
        $this->createIndexIfNotExists(
            ['cuenta_id', 'tipo_origen'],
            'sat_credentials_cuenta_tipo_origen_idx'
        );

        $this->createIndexIfNotExists(
            ['cuenta_id', 'estatus_operativo'],
            'sat_credentials_cuenta_estatus_operativo_idx'
        );

        $this->createIndexIfNotExists(
            ['external_upload_id'],
            'sat_credentials_external_upload_id_idx'
        );
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        $this->dropIndexIfExists('sat_credentials_cuenta_tipo_origen_idx');
        $this->dropIndexIfExists('sat_credentials_cuenta_estatus_operativo_idx');
        $this->dropIndexIfExists('sat_credentials_external_upload_id_idx');

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            $dropIfExists = function (string $column) use ($table) {
                try {
                    if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', $column)) {
                        $table->dropColumn($column);
                    }
                } catch (\Throwable) {
                    // no-op
                }
            };

            $dropIfExists('tipo_origen');
            $dropIfExists('origen_detalle');
            $dropIfExists('source_label');

            $dropIfExists('fiel_cer_path');
            $dropIfExists('fiel_key_path');
            $dropIfExists('fiel_password_enc');

            $dropIfExists('csd_cer_path');
            $dropIfExists('csd_key_path');
            $dropIfExists('csd_password_enc');

            $dropIfExists('external_upload_id');
            $dropIfExists('estatus_operativo');

            try {
                if (Schema::connection('mysql_clientes')->hasColumn('sat_credentials', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            } catch (\Throwable) {
                // no-op
            }
        });
    }

    protected function createIndexIfNotExists(array $columns, string $indexName): void
    {
        if (!$this->allColumnsExist($columns)) {
            return;
        }

        if ($this->indexExists($indexName)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }

    protected function dropIndexIfExists(string $indexName): void
    {
        if (!$this->indexExists($indexName)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    protected function allColumnsExist(array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::connection($this->conn)->hasColumn($this->table, $column)) {
                return false;
            }
        }

        return true;
    }

    protected function indexExists(string $indexName): bool
    {
        $database = DB::connection($this->conn)->getDatabaseName();

        $result = DB::connection($this->conn)->select(
            '
                SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = ?
                  AND table_name = ?
                  AND index_name = ?
                LIMIT 1
            ',
            [$database, $this->table, $indexName]
        );

        return !empty($result);
    }
};