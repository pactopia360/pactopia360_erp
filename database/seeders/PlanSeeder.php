<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlanSeeder extends Seeder
{
    /** Conexión admin fija */
    private string $conn  = 'mysql_admin';
    /** Tabla planes */
    private string $table = 'planes';

    public function run(): void
    {
        // Idempotente: si no existe, salir sin romper
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            $this->command?->warn("[PlanSeeder] No existe tabla '{$this->table}' en conexión {$this->conn}. Nada que hacer.");
            return;
        }

        // Columnas presentes (normaliza a lower → nombre real)
        $cols = collect(Schema::connection($this->conn)->getColumnListing($this->table))
            ->mapWithKeys(fn($c) => [strtolower($c) => $c]);

        // Helpers
        $has = fn(string $c) => isset($cols[strtolower($c)]);
        $col = fn(array $cands) => $this->firstHit($cols, $cands); // nombre real o null

        // Clave de upsert (preferimos 'clave'/'slug', evitamos 'id' si es autoincremental)
        $keyCol = $col(['clave', 'slug', 'codigo', 'code', 'nombre', 'name']) ?? 'id';
        $now    = now();

        // Definición (mantengo tus valores originales y añado flags/opciones)
        $planes = [
            [
                'clave'           => 'free',
                'nombre'          => 'Free',
                'precio_mensual'  => 0,
                'precio_anual'    => 0,
                'limite_espacio'  => 1024,     // 1 GB en MB (si hay columna *_gb lo convierto)
                'limite_timbres'  => 50,
                'es_premium'      => false,
                'activo'          => 1,
                'opciones'        => ['soporte' => 'básico', 'usuarios' => 1],
            ],
            [
                'clave'           => 'premium',
                'nombre'          => 'Premium',
                'precio_mensual'  => 999,      // MXN
                'precio_anual'    => 999 * 12, // sin descuento por ahora
                'limite_espacio'  => 50 * 1024, // 50 GB en MB
                'limite_timbres'  => 1000,
                'es_premium'      => true,
                'activo'          => 1,
                'opciones'        => ['soporte' => 'prioritario', 'usuarios' => 5],
            ],
        ];

        foreach ($planes as $p) {
            // Normaliza clave (minúsculas)
            $p['clave'] = strtolower($p['clave']);

            $payload = [];

            // Clave / nombre
            $this->put($payload, $cols, ['clave','slug','codigo','code'], $p['clave']);
            $this->put($payload, $cols, ['nombre','name','titulo'], $p['nombre']);

            // Precios
            $this->put($payload, $cols, ['precio_mensual','costo_mensual','price_month','mensual'], $p['precio_mensual']);
            $this->put($payload, $cols, ['precio_anual','costo_anual','price_year','anual'],     $p['precio_anual']);

            // Límite espacio: en MB o GB según columna disponible
            if ($c = $col(['limite_espacio_mb','espacio_mb','espacio_limite_mb','espacio'])) {
                $payload[$c] = $p['limite_espacio']; // MB
            } elseif ($c = $col(['limite_espacio_gb','espacio_gb'])) {
                $payload[$c] = (int) round($p['limite_espacio'] / 1024); // a GB
            }

            // Timbres incluidos / hits
            if ($c = $col(['timbres_incluidos','limite_timbres','hits_incluidos'])) {
                $payload[$c] = $p['limite_timbres'];
            }

            // Flag premium / activo
            $this->put($payload, $cols, ['es_premium','premium','is_premium'], (int) $p['es_premium']);
            $this->put($payload, $cols, ['activo','status','estatus'],        (int) $p['activo']);

            // Opciones (json) si existe alguna columna equivalente
            if ($c = $col(['opciones','config','features','settings'])) {
                $payload[$c] = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);
            }

            // Timestamps si existen
            if (isset($cols['created_at'])) $payload[$cols['created_at']] = $now;
            if (isset($cols['updated_at'])) $payload[$cols['updated_at']] = $now;

            // Valor para upsert (clave preferente, o el nombre)
            $keyValue = $payload[$cols[strtolower($keyCol)]] ?? ($p['clave'] ?? $p['nombre']);

            DB::connection($this->conn)->table($this->table)
                ->updateOrInsert([$keyCol => $keyValue], $payload);
        }

        $this->command?->info("[PlanSeeder] Planes Free y Premium actualizados (conexión {$this->conn}).");
    }

    /** Inserta $value en el primer nombre de columna que exista (de $candidates). */
    private function put(array &$payload, \Illuminate\Support\Collection $cols, array $candidates, $value): void
    {
        foreach ($candidates as $c) {
            $lc = strtolower($c);
            if (isset($cols[$lc])) { $payload[$cols[$lc]] = $value; return; }
        }
    }

    /** Devuelve el primer nombre real de columna que exista o null. */
    private function firstHit(\Illuminate\Support\Collection $cols, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            $lc = strtolower($c);
            if (isset($cols[$lc])) return $cols[$lc];
        }
        return null;
    }
}
