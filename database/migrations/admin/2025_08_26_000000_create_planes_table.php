<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * NO tipar esta propiedad (Laravel 12 ya la define tipada en la clase padre).
     */
    protected $connection = 'mysql_admin';

    private string $table = 'planes';
    private string $uniqueIndex = 'planes_clave_unique';

    public function up(): void
    {
        // 1) Si NO existe la tabla, créala con el índice único
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->string('clave', 100);            // ej: free, premium
                $table->string('nombre', 150);           // “Free”, “Premium”
                $table->text('descripcion')->nullable();

                $table->decimal('precio_mensual', 10, 2)->default(0);
                $table->decimal('precio_anual', 10, 2)->nullable();

                $table->boolean('activo')->default(true);

                $table->timestamps();
            });

            // índice único solo si no existe (en tablas recién creadas no existirá)
            if (!$this->indexExists($this->table, $this->uniqueIndex)) {
                Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                    $table->unique('clave', $this->uniqueIndex);
                });
            }

            return;
        }

        // 2) Si la tabla YA existe, solo asegúrate de que el índice único exista (sin duplicarlo)
        if (!$this->indexExists($this->table, $this->uniqueIndex)) {
            // Antes de crear el índice, valida que exista la columna 'clave'
            if (!Schema::connection($this->connection)->hasColumn($this->table, 'clave')) {
                Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                    $table->string('clave', 100)->after('id');
                });
            }

            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('clave', $this->uniqueIndex);
            });
        }

        // 3) (Opcional) Asegura columnas mínimas si faltaran — todas con guardas
        $this->ensureColumn('nombre', fn (Blueprint $t) => $t->string('nombre', 150)->nullable()->after('clave'));
        $this->ensureColumn('descripcion', fn (Blueprint $t) => $t->text('descripcion')->nullable()->after('nombre'));
        $this->ensureColumn('precio_mensual', fn (Blueprint $t) => $t->decimal('precio_mensual', 10, 2)->default(0)->after('descripcion'));
        $this->ensureColumn('precio_anual', fn (Blueprint $t) => $t->decimal('precio_anual', 10, 2)->nullable()->after('precio_mensual'));
        $this->ensureColumn('activo', fn (Blueprint $t) => $t->boolean('activo')->default(true)->after('precio_anual'));
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->table);
    }

    /**
     * Verifica si un índice existe (MySQL) sin requerir doctrine/dbal.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::connection($this->connection);
        if ($db->getDriverName() !== 'mysql') {
            // Si en algún momento cambias de motor, ajusta esta verificación
            return false;
        }
        $res = $db->select("SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?", [$indexName]);
        return !empty($res);
    }

    /**
     * Asegura columna si no existe, usando un callback para definirla.
     */
    private function ensureColumn(string $column, callable $definition): void
    {
        if (!Schema::connection($this->connection)->hasColumn($this->table, $column)) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) use ($definition) {
                $definition($table);
            });
        }
    }
};
