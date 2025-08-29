<?php
declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Facades\Schema;

/**
 * Métodos helper para resolver columnas existentes en tablas.
 */
trait ResolvesColumns
{
    protected function pickColumn(string $conn, string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (Schema::connection($conn)->hasColumn($table, $c)) {
                return $c;
            }
        }
        return null;
    }

    protected function firstExisting(string $conn, string $table, array $columns): ?string
    {
        foreach ($columns as $col) {
            if (Schema::connection($conn)->hasColumn($table, $col)) {
                return $col;
            }
        }
        return null;
    }
}
