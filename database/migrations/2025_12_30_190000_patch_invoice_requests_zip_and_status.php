<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $this->patchTable('invoice_requests');          // legacy (tu cliente)
        $this->patchTable('billing_invoice_requests');  // hub (si existe)
    }

    public function down(): void
    {
        // no-op (patch irreversible intencionalmente)
    }

    private function patchTable(string $table): void
    {
        if (!Schema::connection($this->conn)->hasTable($table)) return;

        $cols = Schema::connection($this->conn)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        // 1) Normaliza status: evitar ENUM / truncamientos
        if ($has('status')) {
            $type = $this->columnType($table, 'status'); // enum|varchar|...
            // si es enum o varchar muy corto, lo pasamos a VARCHAR(40)
            if ($type === 'enum' || $this->varcharLen($table, 'status') < 40) {
                DB::connection($this->conn)->statement("ALTER TABLE {$table} MODIFY status VARCHAR(40) NOT NULL DEFAULT 'requested'");
            }
        }

        // 2) Campos ZIP / CFDI
        $add = function(string $sql) { DB::connection($this->conn)->statement($sql); };

        if (!$has('zip_disk'))   $add("ALTER TABLE {$table} ADD COLUMN zip_disk VARCHAR(40) NULL AFTER status");
        if (!$has('zip_path'))   $add("ALTER TABLE {$table} ADD COLUMN zip_path VARCHAR(1024) NULL AFTER zip_disk");
        if (!$has('zip_name'))   $add("ALTER TABLE {$table} ADD COLUMN zip_name VARCHAR(255) NULL AFTER zip_path");
        if (!$has('zip_size'))   $add("ALTER TABLE {$table} ADD COLUMN zip_size BIGINT NULL AFTER zip_name");
        if (!$has('zip_sha1'))   $add("ALTER TABLE {$table} ADD COLUMN zip_sha1 VARCHAR(40) NULL AFTER zip_size");

        if (!$has('cfdi_uuid'))  $add("ALTER TABLE {$table} ADD COLUMN cfdi_uuid VARCHAR(80) NULL AFTER zip_sha1");

        if (!$has('zip_ready_at')) $add("ALTER TABLE {$table} ADD COLUMN zip_ready_at DATETIME NULL AFTER cfdi_uuid");
        if (!$has('zip_sent_at'))  $add("ALTER TABLE {$table} ADD COLUMN zip_sent_at DATETIME NULL AFTER zip_ready_at");

        // timestamps si no existen
        if (!$has('created_at')) $add("ALTER TABLE {$table} ADD COLUMN created_at DATETIME NULL");
        if (!$has('updated_at')) $add("ALTER TABLE {$table} ADD COLUMN updated_at DATETIME NULL");
    }

    private function columnType(string $table, string $column): string
    {
        $db = DB::connection($this->conn)->getDatabaseName();

        $row = DB::connection($this->conn)->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first(['DATA_TYPE']);

        return strtolower((string)($row->DATA_TYPE ?? ''));
    }

    private function varcharLen(string $table, string $column): int
    {
        $db = DB::connection($this->conn)->getDatabaseName();

        $row = DB::connection($this->conn)->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first(['CHARACTER_MAXIMUM_LENGTH']);

        return (int)($row->CHARACTER_MAXIMUM_LENGTH ?? 0);
    }
};
