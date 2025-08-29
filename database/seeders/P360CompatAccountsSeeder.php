<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class P360CompatAccountsSeeder extends Seeder
{
    protected string $adminConn = 'mysql_admin';

    public function run(): void
    {
        // Detectar tabla de cuentas
        $table = $this->detectTable(['cuentas', 'accounts']);
        if (!$table) {
            $this->command->warn('[P360] No encontré tabla de cuentas (ni "cuentas" ni "accounts"). Nada que hacer.');
            return;
        }

        $cols = collect(Schema::connection($this->adminConn)->getColumnListing($table))
            ->mapWithKeys(fn($c) => [strtolower($c) => $c]); // normaliza a lower para comparar

        $now = now();
        $demo = [
            [
                'rfc'   => 'XAXX010101000',
                'name'  => 'Pactopia Demo 1',
                'email' => 'owner1@demo.com',
                'plan'  => 'FREE',
                'periodicidad' => null,
                'hits'  => 20,
                'gb'    => 1,
            ],
            [
                'rfc'   => 'GLO020202XYZ',
                'name'  => 'Globex',
                'email' => 'owner2@demo.com',
                'plan'  => 'PREMIUM',
                'periodicidad' => 'MENSUAL',
                'hits'  => 500,
                'gb'    => 50,
            ],
        ];

        foreach ($demo as $d) {
            $id = (string) Str::ulid();
            $codigo = strtoupper(substr($d['rfc'], 0, 3)).'-'.substr($id, -6);

            // Construir payload SOLO con columnas que existan
            $payload = [];

            // Razón social / nombre
            $this->put($payload, $cols, ['razon_social','nombre_cuenta','name','account_name'], $d['name']);

            // RFC padre
            $this->put($payload, $cols, ['rfc_padre','rfc'], $d['rfc']);

            // Código cliente / code
            $this->put($payload, $cols, ['codigo_cliente','code','account_code'], $codigo);

            // Plan
            if (!$this->hasAny($cols, ['plan_id'])) {
                $this->put($payload, $cols, ['plan','plan_slug','plan_code'], $d['plan']);
            }

            // Periodicidad
            $this->put($payload, $cols, ['periodicidad','billing_cycle'], $d['periodicidad']);

            // Email owner si existiera columna
            $this->put($payload, $cols, ['email_owner','owner_email','email','email_principal'], $d['email']);

            // Estado/estatus
            $this->put($payload, $cols, ['estatus','status'], 'activa');

            // Métricas
            $this->put($payload, $cols, ['hits_incluidos','hits_included'], $d['hits']);
            $this->put($payload, $cols, ['hits_consumidos','hits_used'], 0);
            $this->put($payload, $cols, ['espacio_gb','storage_gb'], $d['gb']);

            // Fechas y flags comunes si existen
            $this->put($payload, $cols, ['email_verified_at'], $now);
            if (isset($cols['proximo_corte'])) {
                $payload[$cols['proximo_corte']] = $d['periodicidad'] === 'MENSUAL'
                    ? Carbon::now()->startOfMonth()->addMonth()->toDateString()
                    : null;
            }
            if (isset($cols['created_at'])) $payload[$cols['created_at']] = $now;
            if (isset($cols['updated_at'])) $payload[$cols['updated_at']] = $now;

            // Si la tabla tiene PK 'id', la seteamos (ULID)
            if (isset($cols['id'])) $payload[$cols['id']] = $id;

            // Customer_no → generar único si existe la columna
            if (isset($cols['customer_no'])) {
                $payload[$cols['customer_no']] = $this->nextCustomerNo($table, $cols['customer_no']);
            }

            // Clave única para upsert: RFC o código
            $unique = null;
            if ($this->hasAny($cols, ['rfc_padre','rfc'])) {
                $colRfc = $cols[$this->firstHit($cols, ['rfc_padre','rfc'])];
                $unique = [$colRfc => $d['rfc']];
            } elseif ($this->hasAny($cols, ['codigo_cliente','code','account_code'])) {
                $colCode = $cols[$this->firstHit($cols, ['codigo_cliente','code','account_code'])];
                $unique = [$colCode => $codigo];
            } else {
                $this->command->warn("[P360] Sin columna RFC o CODE para upsert en tabla {$table}. Me salto esta cuenta ({$d['name']}).");
                continue;
            }

            DB::connection($this->adminConn)->table($table)->updateOrInsert($unique, $payload);
        }

        $this->command->info("[P360] Cuentas demo sembradas en tabla '{$table}' de forma compatible.");
    }

    private function put(array &$payload, \Illuminate\Support\Collection $cols, array $candidates, $value): void
    {
        foreach ($candidates as $c) {
            $key = strtolower($c);
            if (isset($cols[$key])) {
                $payload[$cols[$key]] = $value;
                return;
            }
        }
    }

    private function hasAny(\Illuminate\Support\Collection $cols, array $candidates): bool
    {
        foreach ($candidates as $c) {
            if (isset($cols[strtolower($c)])) return true;
        }
        return false;
    }

    private function firstHit(\Illuminate\Support\Collection $cols, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            $lc = strtolower($c);
            if (isset($cols[$lc])) return $lc;
        }
        return null;
    }

    private function detectTable(array $candidates): ?string
    {
        foreach ($candidates as $t) {
            if (Schema::connection($this->adminConn)->hasTable($t)) return $t;
        }
        return null;
    }

    /** Genera un número de cliente único incremental */
    private function nextCustomerNo(string $table, string $col): int
    {
        $max = DB::connection($this->adminConn)->table($table)->max($col);
        return $max ? $max + 1 : 1;
    }
}
