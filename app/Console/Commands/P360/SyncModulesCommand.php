<?php

declare(strict_types=1);

namespace App\Console\Commands\P360;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SyncModulesCommand extends Command
{
    protected $signature = 'p360:sync-modules
        {--only= : Sincroniza solo un admin_account_id (ej: 14)}
        {--from= : Rango inicio admin_account_id}
        {--to= : Rango fin admin_account_id}
        {--dry-run : No escribe, solo simula}
        {--force-default= : Si falta SOT modules, fuerza default: all_on|all_off}
        {--conn-admin= : Connection admin (default config(p360.conn.admin) o mysql_admin)}
        {--conn-clients= : Connection clients (default config(p360.conn.clients) o mysql_clientes)}
        {--table-clients=cuentas_cliente : Tabla en DB clientes}
        {--meta-col=meta : Columna meta en tabla clientes}';

    protected $description = 'Sincroniza meta.modules desde admin.accounts hacia clientes.cuentas_cliente por admin_account_id';

    /** @var array<string,bool> */
    private array $catalogOrder = [
        'sat_descargas' => true,
        'boveda_fiscal' => true,
        'facturacion'   => true,
        'nomina'        => true,
        'crm'           => true,
        'pos'           => true,
    ];

    public function handle(): int
    {
        $adminConn   = (string)($this->option('conn-admin') ?: (config('p360.conn.admin') ?: 'mysql_admin'));
        $clientsConn = (string)($this->option('conn-clients') ?: (config('p360.conn.clients') ?: 'mysql_clientes'));
        $table       = (string)($this->option('table-clients') ?: 'cuentas_cliente');
        $metaCol     = (string)($this->option('meta-col') ?: 'meta');

        $dryRun = (bool)$this->option('dry-run');

        $only = $this->option('only');
        $from = $this->option('from');
        $to   = $this->option('to');

        $forceDefault = (string)($this->option('force-default') ?: '');
        $forceDefault = trim(strtolower($forceDefault));

        if (!Schema::connection($adminConn)->hasTable('accounts')) {
            $this->error("No existe tabla {$adminConn}.accounts");
            return self::FAILURE;
        }
        if (!Schema::connection($clientsConn)->hasTable($table)) {
            $this->error("No existe tabla {$clientsConn}.{$table}");
            return self::FAILURE;
        }
        if (!Schema::connection($clientsConn)->hasColumn($table, $metaCol)) {
            $this->error("No existe columna {$clientsConn}.{$table}.{$metaCol}");
            return self::FAILURE;
        }

        $this->info("AdminConn={$adminConn} | ClientsConn={$clientsConn} | Table={$table} | MetaCol={$metaCol}");
        $this->info($dryRun ? "Modo: DRY-RUN (no escribe)" : "Modo: WRITE");

        $defaultAllOn = [
            'sat_descargas' => true,
            'boveda_fiscal' => true,
            'facturacion'   => true,
            'nomina'        => true,
            'crm'           => true,
            'pos'           => true,
        ];
        $defaultAllOff = [
            'sat_descargas' => false,
            'boveda_fiscal' => false,
            'facturacion'   => false,
            'nomina'        => false,
            'crm'           => false,
            'pos'           => false,
        ];

        $q = DB::connection($clientsConn)->table($table)
            ->select('id', 'admin_account_id', $metaCol)
            ->whereNotNull('admin_account_id')
            ->orderBy('admin_account_id');

        if ($only !== null && $only !== '') {
            $q->where('admin_account_id', (int)$only);
        } else {
            if ($from !== null && $from !== '') $q->where('admin_account_id', '>=', (int)$from);
            if ($to !== null && $to !== '')     $q->where('admin_account_id', '<=', (int)$to);
        }

        $rows = $q->get();
        $total = $rows->count();
        $this->line("Filas clientes a procesar: {$total}");

        $updated = 0;
        $skipped = 0;

        $unchanged = 0;
        $failed = 0;

        $missingAccount = 0;
        $invalidAdminId = 0;
        $defaulted = 0;

        foreach ($rows as $cr) {
            $aid = (int)($cr->admin_account_id ?? 0);
            if ($aid <= 0) {
                $invalidAdminId++;
                $skipped++;
                continue;
            }

            $acc = DB::connection($adminConn)->table('accounts')->select('id', 'meta')->where('id', $aid)->first();
            if (!$acc) {
                $missingAccount++;
                $skipped++;
                continue;
            }

            $metaAdmin = $this->decodeMeta($acc->meta ?? null);
            $mods = $metaAdmin['modules'] ?? null;

            if (!is_array($mods)) {
                if ($forceDefault === 'all_on') {
                    $mods = $defaultAllOn;
                    $defaulted++;
                } elseif ($forceDefault === 'all_off') {
                    $mods = $defaultAllOff;
                    $defaulted++;
                } else {
                    // Sin force-default: no inventar módulos
                    $skipped++;
                    continue;
                }
            }

            // Normaliza módulos SOT (orden/llaves/bool)
            $modsNorm = $this->normalizeModules($mods);

            // Meta cliente
            $metaClient = $this->decodeMeta($cr->{$metaCol} ?? null);
            $currentClientMods = $this->normalizeModules(
                is_array(($metaClient['modules'] ?? null)) ? (array)$metaClient['modules'] : []
            );

            // Si ya es idéntico => NO escribas (evita pisar bywho/at)
            if ($this->modulesEqual($currentClientMods, $modsNorm)) {
                $unchanged++;
                continue;
            }

            // Aquí sí hay cambio real
            $metaClient['modules'] = $modsNorm;
            $metaClient['modules_updated_at'] = now()->toISOString();
            $metaClient['modules_updated_by'] = 'cmd.p360:sync-modules';

            if ($dryRun) {
                $updated++;
                continue;
            }

            try {
                $payload = [
                    $metaCol => json_encode($metaClient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
                if (Schema::connection($clientsConn)->hasColumn($table, 'updated_at')) {
                    $payload['updated_at'] = now();
                }

                DB::connection($clientsConn)->table($table)->where('id', (string)$cr->id)->update($payload);
                $updated++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("Fallo update id={$cr->id} admin_account_id={$aid}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("DONE");
        $this->line("UPDATED={$updated}");
        $this->line("UNCHANGED={$unchanged}");
        $this->line("SKIPPED={$skipped}");
        $this->line("FAILED={$failed}");
        $this->line("MISSING_SOT_ACCOUNT={$missingAccount}");
        $this->line("INVALID_ADMIN_ID={$invalidAdminId}");
        $this->line("DEFAULTED={$defaulted}");
        $this->newLine();

        $this->line("SQL verificación (en p360v1_clientes):");
        $this->line("SELECT admin_account_id, JSON_EXTRACT({$metaCol},'$.modules') modules, JSON_EXTRACT({$metaCol},'$.modules_updated_by') bywho FROM {$table} ORDER BY admin_account_id;");

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function decodeMeta(mixed $raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $j = json_decode($raw, true);
            return is_array($j) ? $j : [];
        }
        if (is_array($raw)) return $raw;
        if (is_object($raw)) return (array)$raw;
        return [];
    }

    /**
     * Normaliza:
     * - Fuerza llaves del catálogo (si faltan => false por defecto)
     * - Orden estable por catálogo
     * - Cast a bool
     *
     * @param array<mixed> $mods
     * @return array<string,bool>
     */
    private function normalizeModules(array $mods): array
    {
        $out = [];
        foreach ($this->catalogOrder as $k => $_) {
            $out[$k] = (bool)($mods[$k] ?? false);
        }
        return $out;
    }

    /**
     * @param array<string,bool> $a
     * @param array<string,bool> $b
     */
    private function modulesEqual(array $a, array $b): bool
    {
        // Ya vienen normalizados por catálogo/orden, así que comparación directa es segura
        return $a === $b;
    }
}
