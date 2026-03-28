<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('sat_user_metadata_items')) {
            $schema->table('sat_user_metadata_items', function (Blueprint $table) {
                if (!$this->hasIndex('sat_user_metadata_items', 'idx_sat_meta_scope_dir_fecha')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'direction', 'fecha_emision'],
                        'idx_sat_meta_scope_dir_fecha'
                    );
                }

                if (!$this->hasIndex('sat_user_metadata_items', 'idx_sat_meta_scope_dir_estatus')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'direction', 'estatus'],
                        'idx_sat_meta_scope_dir_estatus'
                    );
                }

                if (!$this->hasIndex('sat_user_metadata_items', 'idx_sat_meta_scope_fecha')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'fecha_emision'],
                        'idx_sat_meta_scope_fecha'
                    );
                }

                if (!$this->hasIndex('sat_user_metadata_items', 'idx_sat_meta_scope_emisor')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'rfc_emisor'],
                        'idx_sat_meta_scope_emisor'
                    );
                }

                if (!$this->hasIndex('sat_user_metadata_items', 'idx_sat_meta_scope_receptor')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'rfc_receptor'],
                        'idx_sat_meta_scope_receptor'
                    );
                }
            });
        }

        if ($schema->hasTable('sat_user_cfdis')) {
            $schema->table('sat_user_cfdis', function (Blueprint $table) {
                if (!$this->hasIndex('sat_user_cfdis', 'idx_sat_cfdi_scope_hash')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'xml_hash'],
                        'idx_sat_cfdi_scope_hash'
                    );
                }

                if (!$this->hasIndex('sat_user_cfdis', 'idx_sat_cfdi_scope_dir_fecha')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'direction', 'fecha_emision'],
                        'idx_sat_cfdi_scope_dir_fecha'
                    );
                }

                if (!$this->hasIndex('sat_user_cfdis', 'idx_sat_cfdi_scope_fecha')) {
                    $table->index(
                        ['cuenta_id', 'usuario_id', 'rfc_owner', 'fecha_emision'],
                        'idx_sat_cfdi_scope_fecha'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('sat_user_metadata_items')) {
            $schema->table('sat_user_metadata_items', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'sat_user_metadata_items', 'idx_sat_meta_scope_dir_fecha');
                $this->dropIndexIfExists($table, 'sat_user_metadata_items', 'idx_sat_meta_scope_dir_estatus');
                $this->dropIndexIfExists($table, 'sat_user_metadata_items', 'idx_sat_meta_scope_fecha');
                $this->dropIndexIfExists($table, 'sat_user_metadata_items', 'idx_sat_meta_scope_emisor');
                $this->dropIndexIfExists($table, 'sat_user_metadata_items', 'idx_sat_meta_scope_receptor');
            });
        }

        if ($schema->hasTable('sat_user_cfdis')) {
            $schema->table('sat_user_cfdis', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'sat_user_cfdis', 'idx_sat_cfdi_scope_hash');
                $this->dropIndexIfExists($table, 'sat_user_cfdis', 'idx_sat_cfdi_scope_dir_fecha');
                $this->dropIndexIfExists($table, 'sat_user_cfdis', 'idx_sat_cfdi_scope_fecha');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = DB::connection($this->connection)->getDatabaseName();

        $row = DB::connection($this->connection)->selectOne(
            'SELECT COUNT(*) AS total
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ((int) ($row->total ?? 0)) > 0;
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if ($this->hasIndex($tableName, $indexName)) {
            $table->dropIndex($indexName);
        }
    }
};