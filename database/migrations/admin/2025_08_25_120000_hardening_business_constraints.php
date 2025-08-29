C:\wamp64\www\pactopia360_erp\database\migrations\admin\2025_08_25_120000_hardening_business_constraints.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $index): bool
    {
        $conn = Schema::getConnection()->getName();
        $db   = DB::connection($conn)->getDatabaseName();
        $sql  = "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1";
        return (bool) DB::selectOne($sql, [$db, $table, $index]);
    }

    public function up(): void
    {
        // clientes: RFC único si existe la col.
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes','rfc')) {
            DB::table('clientes')->whereNotNull('rfc')->update([
                'rfc' => DB::raw('UPPER(TRIM(rfc))'),
            ]);
            if (!$this->hasIndex('clientes','clientes_rfc_unique')) {
                DB::statement('ALTER TABLE clientes ADD UNIQUE INDEX clientes_rfc_unique (rfc)');
            }
        }

        // planes: clave única
        if (Schema::hasTable('planes') && Schema::hasColumn('planes','clave')) {
            if (!$this->hasIndex('planes','planes_clave_unique')) {
                DB::statement('ALTER TABLE planes ADD UNIQUE INDEX planes_clave_unique (clave)');
            }
        }

        // cfdis/facturas: uuid único
        foreach (['cfdis','facturas','comprobantes','facturacion'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t,'uuid')) {
                if (!$this->hasIndex($t, "{$t}_uuid_unique")) {
                    DB::statement("ALTER TABLE {$t} ADD UNIQUE INDEX {$t}_uuid_unique (uuid)");
                }
                break;
            }
        }

        // pagos: defaults/índices suaves
        if (Schema::hasTable('pagos')) {
            if (Schema::hasColumn('pagos','estado')) {
                DB::table('pagos')->whereNull('estado')->update(['estado'=>'pendiente']);
            }
            if (Schema::hasColumn('pagos','fecha') && !$this->hasIndex('pagos','pagos_fecha_index')) {
                DB::statement('ALTER TABLE pagos ADD INDEX pagos_fecha_index (fecha)');
            }
            if (Schema::hasColumn('pagos','cliente_id') && !$this->hasIndex('pagos','pagos_cliente_id_index')) {
                DB::statement('ALTER TABLE pagos ADD INDEX pagos_cliente_id_index (cliente_id)');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && $this->hasIndex('clientes','clientes_rfc_unique')) {
            DB::statement('ALTER TABLE clientes DROP INDEX clientes_rfc_unique');
        }
        if (Schema::hasTable('planes') && $this->hasIndex('planes','planes_clave_unique')) {
            DB::statement('ALTER TABLE planes DROP INDEX planes_clave_unique');
        }
        foreach (['cfdis','facturas','comprobantes','facturacion'] as $t) {
            if (Schema::hasTable($t) && $this->hasIndex($t, "{$t}_uuid_unique")) {
                DB::statement("ALTER TABLE {$t} DROP INDEX {$t}_uuid_unique");
                break;
            }
        }
        if (Schema::hasTable('pagos')) {
            if ($this->hasIndex('pagos','pagos_fecha_index')) {
                DB::statement('ALTER TABLE pagos DROP INDEX pagos_fecha_index');
            }
            if ($this->hasIndex('pagos','pagos_cliente_id_index')) {
                DB::statement('ALTER TABLE pagos DROP INDEX pagos_cliente_id_index');
            }
        }
    }
};
