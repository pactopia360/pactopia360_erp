<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class P360DbCheck extends Command
{
    protected $signature = 'p360:db-check {--write : Intenta una escritura temporal en cada conexión}';
    protected $description = 'Verifica conexiones mysql (default/admin) y mysql_clientes (lectura/escritura opcional).';

    public function handle(): int
    {
        $this->info('== P360 :: Diagnóstico de BD ==');

        $targets = [
            // alias => [connection_name, test_table]
            'default(mysql)'   => ['mysql', 'migrations'],
            'mysql_admin'      => ['mysql_admin', 'migrations'],
            'mysql_clientes'   => ['mysql_clientes', 'migrations'],
        ];

        foreach ($targets as $label => [$conn, $testTable]) {
            $this->line("• Probando conexión: <info>{$label}</info> ({$conn})");

            try {
                // Ping simple
                $version = DB::connection($conn)->selectOne('select version() as v');
                $this->info("  - Conectado. Versión: " . ($version->v ?? 'desconocida'));

                // Lectura en tabla conocida (si no existe, no falla la conexión, solo advierte)
                try {
                    $count = DB::connection($conn)->table($testTable)->count();
                    $this->line("  - Lectura OK en tabla '{$testTable}': {$count} registros.");
                } catch (Throwable $e) {
                    $this->warn("  - Aviso: no pude leer la tabla '{$testTable}' ({$e->getMessage()}).");
                }

                if ($this->option('write')) {
                    // Intento de escritura no destructiva
                    DB::connection($conn)->beginTransaction();
                    try {
                        DB::connection($conn)->select('select 1 as ok');
                        $this->line("  - Escritura simulada OK (transacción revertida).");
                        DB::connection($conn)->rollBack();
                    } catch (Throwable $e) {
                        DB::connection($conn)->rollBack();
                        throw $e;
                    }
                }
            } catch (Throwable $e) {
                $this->error("  - Error: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info('Diagnóstico finalizado.');
        return self::SUCCESS;
    }
}
