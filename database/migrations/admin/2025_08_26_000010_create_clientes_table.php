<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * No tipar: la clase padre ya define el tipo.
     */
    protected $connection = 'mysql_admin';

    private string $table = 'clientes';
    private string $uniqueIndex = 'clientes_codigo_unique';

    public function up(): void
    {
        // 1) Crear tabla si no existe
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
                $table->bigIncrements('id');

                // Identidad del cliente
                $table->string('codigo', 64)->nullable(false); // único, non-null
                $table->string('rfc', 20)->nullable()->index();
                $table->string('razon_social', 255)->nullable();

                // Contacto
                $table->string('email', 191)->nullable()->index();
                $table->string('telefono', 50)->nullable();

                // Estado / plan
                $table->string('plan', 50)->default('free'); // free|premium
                $table->boolean('activo')->default(true);

                $table->timestamps();
            });

            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('codigo', $this->uniqueIndex);
            });

            return;
        }

        // 2) Si la tabla ya existe, asegurar columnas mínimas (con guardas)
        if (!Schema::connection($this->connection)->hasColumn($this->table, 'codigo')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->string('codigo', 64)->nullable(false)->after('id');
            });
        }
        if (!Schema::connection($this->connection)->hasColumn($this->table, 'plan')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->string('plan', 50)->default('free')->after('telefono');
            });
        }
        if (!Schema::connection($this->connection)->hasColumn($this->table, 'activo')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('plan');
            });
        }

        // 3) Normalizar datos existentes SIN usar NULL
        $db = DB::connection($this->connection);

        // 3.1 Reemplazar cadenas vacías ('') por códigos únicos
        $vacios = $db->table($this->table)->select('id')->where('codigo', '')->get();
        foreach ($vacios as $row) {
            $db->table($this->table)->where('id', $row->id)->update([
                'codigo' => $this->genCodigoUnico($db),
            ]);
        }

        // 3.2 Desduplicar 'codigo' repetidos (mantén el primero; a los demás asígnales uno nuevo)
        $dups = $db->table($this->table)
            ->select('codigo', DB::raw('COUNT(*) as c'))
            ->groupBy('codigo')
            ->having('c', '>', 1)
            ->pluck('codigo')
            ->toArray();

        if (!empty($dups)) {
            foreach ($dups as $codigo) {
                // Obtener todos con ese codigo, ordenados; saltar el primero
                $rows = $db->table($this->table)
                    ->select('id')
                    ->where('codigo', $codigo)
                    ->orderBy('id', 'asc')
                    ->get();

                $skipFirst = true;
                foreach ($rows as $row) {
                    if ($skipFirst) { $skipFirst = false; continue; }
                    $db->table($this->table)->where('id', $row->id)->update([
                        'codigo' => $this->genCodigoUnico($db),
                    ]);
                }
            }
        }

        // 4) Crear índice único sólo si no existe
        if (!$this->indexExists($db, $this->table, $this->uniqueIndex)) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('codigo', $this->uniqueIndex);
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->table);
    }

    /**
     * Genera un 'codigo' único (compacto) no existente en la tabla.
     */
    private function genCodigoUnico(\Illuminate\Database\Connection $db): string
    {
        // Prefijo C + base36 de timestamp + 6 chars aleatorios → corto y único
        do {
            $cand = 'C' . base_convert((string) time(), 10, 36) . strtoupper(Str::random(6));
        } while ($db->table($this->table)->where('codigo', $cand)->exists());

        return $cand;
    }

    /**
     * Verifica si un índice existe en MySQL sin doctrine/dbal.
     */
    private function indexExists(\Illuminate\Database\Connection $db, string $table, string $indexName): bool
    {
        if ($db->getDriverName() !== 'mysql') return false;
        $res = $db->select("SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?", [$indexName]);
        return !empty($res);
    }
};
