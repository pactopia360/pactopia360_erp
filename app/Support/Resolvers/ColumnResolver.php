<?php
declare(strict_types=1);

namespace App\Support\Resolvers;

use App\Support\Concerns\ResolvesColumns;
use Carbon\Carbon;

/**
 * Implementación concreta que usa el trait ResolvesColumns.
 * La exponemos al contenedor para resolver peticiones a
 * app(\App\Support\Concerns\ResolvesColumns::class).
 */
class ColumnResolver
{
    use ResolvesColumns;

    /**
     * Utilidad común para el dashboard: genera etiquetas de meses.
     * Formato: 'YYYY-MM', del más antiguo al más reciente.
     */
    public function monthLabels(int $months): array
    {
        $months = max(1, min(24, $months));
        $labels = [];

        $cursor = Carbon::now()->startOfMonth()->subMonths($months - 1);
        for ($i = 0; $i < $months; $i++) {
            $labels[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $labels;
    }
}
