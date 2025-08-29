<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Conexión explícita a la BD de clientes
        $conn = DB::connection('mysql_clientes');

        // Verificaciones mínimas
        if (!Schema::connection('mysql_clientes')->hasTable('clientes')) {
            $this->command?->warn("[DashboardDemoSeeder] No existe la tabla 'clientes' en mysql_clientes. Nada que hacer.");
            return;
        }

        // Columnas disponibles (modo compatible)
        $hasRazonSocial     = Schema::connection('mysql_clientes')->hasColumn('clientes', 'razon_social');
        $hasNombreComercial = Schema::connection('mysql_clientes')->hasColumn('clientes', 'nombre_comercial');
        $hasEmpresa         = Schema::connection('mysql_clientes')->hasColumn('clientes', 'empresa');   // por compat
        $hasPlanId          = Schema::connection('mysql_clientes')->hasColumn('clientes', 'plan_id');
        $hasPlan            = Schema::connection('mysql_clientes')->hasColumn('clientes', 'plan');
        $hasActivo          = Schema::connection('mysql_clientes')->hasColumn('clientes', 'activo');
        $hasEstado          = Schema::connection('mysql_clientes')->hasColumn('clientes', 'estado');
        $hasBajaAt          = Schema::connection('mysql_clientes')->hasColumn('clientes', 'baja_at');
        $hasTimbres         = Schema::connection('mysql_clientes')->hasColumn('clientes', 'timbres');
        $hasRfc             = Schema::connection('mysql_clientes')->hasColumn('clientes', 'rfc');
        $hasDeletedAt       = Schema::connection('mysql_clientes')->hasColumn('clientes', 'deleted_at'); // por si existe

        // Generamos 30 clientes de demo (sin tocar 'id')
        $now = Carbon::now();
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            // Nombre base
            $nombre = "Demo Co " . Str::upper(Str::random(5));

            // Armado compatible por columnas
            $row = [
                // timestamps compatibles
                'created_at' => $now->copy()->subMonths(rand(0, 18))->subDays(rand(0, 28)),
                'updated_at' => $now->copy()->subDays(rand(0, 120)),
            ];

            // Razon social (OBLIGATORIO si la columna existe)
            if ($hasRazonSocial) {
                $row['razon_social'] = $nombre;
            }

            // nombre_comercial (si existe)
            if ($hasNombreComercial) {
                $row['nombre_comercial'] = $nombre;
            }

            // Columna legacy 'empresa' (si existe, la llenamos también)
            if ($hasEmpresa) {
                $row['empresa'] = $nombre;
            }

            // RFC (si existe)
            if ($hasRfc) {
                // RFC demo de 13 caracteres alfanum
                $row['rfc'] = Str::upper(Str::random(13));
            }

            // Plan / Plan_id
            if ($hasPlanId) {
                // Déjalo null (o mapea si quieres a tus planes reales)
                $row['plan_id'] = null;
            } elseif ($hasPlan) {
                // Texto simple si no hay FKs
                $row['plan'] = (rand(0, 1) ? 'free' : 'premium');
            }

            // Activo/Estado
            if ($hasActivo) {
                $row['activo'] = rand(0, 1);
            } elseif ($hasEstado) {
                $row['estado'] = (rand(0, 1) ? 'activo' : 'inactivo');
            }

            // Timbres (si existe)
            if ($hasTimbres) {
                $row['timbres'] = rand(50, 2000);
            }

            // Baja_at (si existe) — algunos con fecha futura/pasada o null
            if ($hasBajaAt) {
                $row['baja_at'] = (rand(0, 4) === 0)
                    ? $now->copy()->addMonths(rand(1, 6))
                    : null;
            }

            // deleted_at (si existe, dejamos null)
            if ($hasDeletedAt) {
                $row['deleted_at'] = null;
            }

            // ¡IMPORTANTE!: NO seteamos 'id' (evitamos truncation en INT AI)
            $rows[] = $row;
        }

        // Inserta en bloques para no exceder packet size
        foreach (array_chunk($rows, 500) as $chunk) {
            $conn->table('clientes')->insert($chunk);
        }

        $this->command?->info("[DashboardDemoSeeder] Clientes demo insertados en mysql_clientes.clientes (modo compatible).");
    }
}
