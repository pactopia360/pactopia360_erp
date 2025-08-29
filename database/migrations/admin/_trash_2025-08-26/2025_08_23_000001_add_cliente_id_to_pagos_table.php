<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $t = 'pagos';
    private string $clientes = 'clientes';

    public function up(): void
    {
        if (!Schema::hasTable($this->t)) return;

        Schema::table($this->t, function (Blueprint $table) {
            if (!Schema::hasColumn($this->t, 'cliente_id')) {
                // unsignedBigInteger para empatar con clientes.id autoincrement
                $table->unsignedBigInteger('cliente_id')->nullable()->index()->after('id');
            }
        });

        // Agrega FK solo si existen ambas tablas y la columna id en clientes
        if (Schema::hasTable($this->clientes) && Schema::hasColumn($this->clientes, 'id')) {
            Schema::table($this->t, function (Blueprint $table) {
                // evita error si ya existe la FK
                $fks = $this->listTableForeignKeys($this->t);
                if (!in_array('pagos_cliente_id_foreign', $fks, true)) {
                    $table->foreign('cliente_id')->references('id')->on($this->clientes)->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->t)) return;

        // drop FK si existe
        $fks = $this->listTableForeignKeys($this->t);
        Schema::table($this->t, function (Blueprint $table) use ($fks) {
            if (in_array('pagos_cliente_id_foreign', $fks, true)) {
                $table->dropForeign('pagos_cliente_id_foreign');
            }
            if (Schema::hasColumn($this->t, 'cliente_id')) {
                $table->dropColumn('cliente_id');
            }
        });
    }

    private function listTableForeignKeys(string $table): array
    {
        try {
            $cn = config('database.default');
            $conn = Schema::getConnection()->getDoctrineSchemaManager();
            $doctrineTable = $conn->listTableDetails(Schema::getConnection()->getTablePrefix().$table);
            return array_map(fn($fk) => $fk->getName(), $doctrineTable->getForeignKeys());
        } catch (\Throwable $e) {
            return [];
        }
    }
};
