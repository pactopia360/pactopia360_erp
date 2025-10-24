<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

return new class extends Migration {
    /** Conexión primaria tentativa para datos de clientes */
    private string $primaryConn = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->resolveConnection();

        // 0) Validar existencia de tabla
        if (!$conn || !Schema::connection($conn)->hasTable('clientes')) {
            return; // no hay tabla clientes en esta conexión; salimos limpio
        }

        // 1) Garantizar columna `codigo` si falta
        if (!Schema::connection($conn)->hasColumn('clientes', 'codigo')) {
            Schema::connection($conn)->table('clientes', function (Blueprint $t) {
                $t->string('codigo', 40)->nullable()->after('id');
                $t->index('codigo', 'clientes_codigo_idx');
            });
        }

        // 2) Backfill para NULL/'' en chunks
        DB::connection($conn)->table('clientes')
            ->where(function ($q) {
                $q->whereNull('codigo')->orWhere('codigo', '');
            })
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($conn) {
                foreach ($rows as $r) {
                    DB::connection($conn)->table('clientes')
                        ->where('id', $r->id)
                        ->update([
                            'codigo'     => $this->genCodigoUnico($conn),
                            'updated_at' => now(),
                        ]);
                }
            });

        // 3) Desduplicar (conserva el primero) en chunks
        $dups = DB::connection($conn)->table('clientes')
            ->select('codigo', DB::raw('COUNT(*) as c'))
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->groupBy('codigo')
            ->having('c', '>', 1)
            ->pluck('codigo');

        foreach ($dups as $codigo) {
            $ids = DB::connection($conn)->table('clientes')
                ->where('codigo', $codigo)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_shift($ids); // deja el primero con el código original

            foreach ($ids as $id) {
                DB::connection($conn)->table('clientes')
                    ->where('id', $id)
                    ->update([
                        'codigo'     => $this->genCodigoUnico($conn),
                        'updated_at' => now(),
                    ]);
            }
        }

        // 4) Intentar índice único (best-effort)
        try {
            Schema::connection($conn)->table('clientes', function (Blueprint $t) {
                $t->unique('codigo', 'clientes_codigo_unique');
            });
        } catch (\Throwable $e) {
            // ya existe o no se pudo — ignoramos para no romper deploy
        }
    }

    public function down(): void
    {
        // Safe-only: no eliminamos índice ni columna para no perder datos.
        // Si deseas revertir, haz otra migración explícita que lo quite.
    }

    /** Determina la conexión correcta para trabajar con `clientes`. */
    private function resolveConnection(): ?string
    {
        // Si existe la tabla en mysql_clientes, usamos esa conexión.
        if (Schema::connection($this->primaryConn)->hasTable('clientes')) {
            return $this->primaryConn;
        }

        // Fallback: conexión por defecto (por si el proyecto no separa BD)
        try {
            if (Schema::hasTable('clientes')) {
                return config('database.default');
            }
        } catch (\Throwable $e) {
            // no hay default usable
        }

        return null;
    }

    /** Genera un código único no repetido en `clientes.codigo` */
    private function genCodigoUnico(string $conn): string
    {
        do {
            // Prefijo C + timestamp base36 + random 6
            $cand = 'C' . base_convert((string) time(), 10, 36) . strtoupper(Str::random(6));
        } while (
            DB::connection($conn)->table('clientes')
                ->where('codigo', $cand)
                ->exists()
        );

        return $cand;
    }
};
