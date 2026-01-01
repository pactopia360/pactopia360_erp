<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class P360SyncAdminAccountIds extends Command
{
    protected $signature = 'p360:sync-admin-account-ids
        {--only-missing : Solo actualiza cuentas_cliente donde admin_account_id es NULL}
        {--dry-run : No escribe cambios, solo muestra qué haría}
        {--limit=0 : Limita el número de registros a procesar (0 = sin límite)}
        {--id=0 : Procesa solo cuentas_cliente.id específico}
        {--user_id= : Procesa solo por user_id (si existe la columna)}
        {--verbose-log : Log detallado por cada registro}';

    protected $description = 'Sincroniza cuentas_cliente.admin_account_id con accounts.id (DB admin), usando admin_account_id/meta/account_id o match por RFC/email.';

    public function handle(): int
    {
        $cliConn = config('p360.conn.clients', 'mysql_clientes');
        $admConn = config('p360.conn.admin', 'mysql_admin');

        if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
            $this->error("No existe tabla cuentas_cliente en conexión [$cliConn].");
            return self::FAILURE;
        }
        if (!Schema::connection($admConn)->hasTable('accounts')) {
            $this->error("No existe tabla accounts en conexión [$admConn].");
            return self::FAILURE;
        }
        if (!Schema::connection($cliConn)->hasColumn('cuentas_cliente', 'admin_account_id')) {
            $this->error("No existe columna cuentas_cliente.admin_account_id en [$cliConn].");
            return self::FAILURE;
        }

        $onlyMissing = (bool) $this->option('only-missing');
        $dryRun      = (bool) $this->option('dry-run');
        $limit       = (int)  $this->option('limit');
        $idFilter    = (int)  $this->option('id');
        $userIdOpt   = trim((string) ($this->option('user_id') ?? ''));
        $verboseLog  = (bool) $this->option('verbose-log');

        // ===== Detecta columnas reales en cuentas_cliente =====
        $cols = Schema::connection($cliConn)->getColumnListing('cuentas_cliente');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $colId       = 'id';
        $colAdminId  = 'admin_account_id';

        $colUserId   = $has('user_id') ? 'user_id' : null;

        $colRfc = $has('rfc') ? 'rfc'
            : ($has('rfc_fiscal') ? 'rfc_fiscal'
            : ($has('tax_id') ? 'tax_id'
            : null));

        $colEmail = $has('email') ? 'email'
            : ($has('correo') ? 'correo'
            : ($has('mail') ? 'mail'
            : null));

        $colMeta  = $has('meta') ? 'meta' : null;

        // Algunas instalaciones usaron account_id como ID del admin
        $colAccId = $has('account_id') ? 'account_id' : null;

        // ===== Query base =====
        $q = DB::connection($cliConn)->table('cuentas_cliente');

        if ($idFilter > 0) {
            $q->where($colId, $idFilter);
        }

        // --user_id solo si existe la columna
        if ($userIdOpt !== '') {
            if (!$colUserId) {
                $this->warn("Opción --user_id ignorada: la columna user_id no existe en cuentas_cliente.");
            } else {
                $q->where($colUserId, $userIdOpt);
            }
        }

        if ($onlyMissing) {
            $q->whereNull($colAdminId);
        }

        if ($limit > 0) {
            $q->limit($limit);
        }

        // ===== Select dinámico =====
        $select = [$colId, $colAdminId];
        if ($colUserId) $select[] = $colUserId;
        if ($colRfc)    $select[] = $colRfc;
        if ($colEmail)  $select[] = $colEmail;
        if ($colMeta)   $select[] = $colMeta;
        if ($colAccId)  $select[] = $colAccId;

        $rows = $q->orderBy($colId)->get($select);

        $this->info("Procesando {$rows->count()} registros en [$cliConn]. dry-run=" . ($dryRun ? 'yes' : 'no'));

        $updated  = 0;
        $skipped  = 0;
        $notFound = 0;

        foreach ($rows as $cc) {
            $ccId = is_numeric($cc->{$colId} ?? null) ? (int) $cc->{$colId} : 0;
            if ($ccId <= 0) { $skipped++; continue; }

            $current = is_numeric($cc->{$colAdminId} ?? null) ? (int) $cc->{$colAdminId} : 0;
            if ($current > 0 && $onlyMissing) { $skipped++; continue; }

            // 1) Si ya hay admin_account_id y existe en admin, no tocar
            if ($current > 0 && $this->adminAccountExists($admConn, $current)) {
                $skipped++;
                continue;
            }

            $candidate = 0;
            $source = 'unresolved';

            // 2) meta.admin_account_id
            if ($candidate <= 0 && $colMeta && isset($cc->{$colMeta})) {
                $meta = $this->decodeMeta($cc->{$colMeta});
                $mId  = (int) (data_get($meta, 'admin_account_id') ?? 0);
                if ($mId > 0 && $this->adminAccountExists($admConn, $mId)) {
                    $candidate = $mId;
                    $source = 'clientes.cuentas_cliente.meta.admin_account_id';
                }
            }

            // 3) account_id (si lo usaron como admin id)
            if ($candidate <= 0 && $colAccId && is_numeric($cc->{$colAccId} ?? null)) {
                $aid = (int) $cc->{$colAccId};
                if ($aid > 0 && $this->adminAccountExists($admConn, $aid)) {
                    $candidate = $aid;
                    $source = 'clientes.cuentas_cliente.account_id';
                }
            }

            // 4) match por RFC
            if ($candidate <= 0 && $colRfc) {
                $rfc = strtoupper(trim((string) ($cc->{$colRfc} ?? '')));
                if ($rfc !== '') {
                    $aid = $this->findAdminByRfc($admConn, $rfc);
                    if ($aid > 0) {
                        $candidate = $aid;
                        $source = 'admin.accounts.rfc|meta match';
                    }
                }
            }

            // 5) match por email
            if ($candidate <= 0 && $colEmail) {
                $email = strtolower(trim((string) ($cc->{$colEmail} ?? '')));
                if ($email !== '') {
                    $aid = $this->findAdminByEmail($admConn, $email);
                    if ($aid > 0) {
                        $candidate = $aid;
                        $source = 'admin.accounts.email|meta match';
                    }
                }
            }

            if ($candidate <= 0) {
                $notFound++;
                if ($verboseLog) {
                    Log::warning('[P360] sync-admin-account-ids: unresolved', [
                        'cc_id'  => $ccId,
                        'rfc'    => $colRfc ? (string) ($cc->{$colRfc} ?? '') : null,
                        'email'  => $colEmail ? (string) ($cc->{$colEmail} ?? '') : null,
                        'has_user_id_col' => (bool) $colUserId,
                        'user_id' => $colUserId ? (string) ($cc->{$colUserId} ?? '') : null,
                    ]);
                }
                continue;
            }

            if ($dryRun) {
                $this->line("DRY cc.id={$ccId} => admin_account_id={$candidate} ({$source})");
                if ($verboseLog) {
                    Log::info('[P360] sync-admin-account-ids dry-run', [
                        'cc_id' => $ccId,
                        'admin_account_id' => $candidate,
                        'source' => $source,
                    ]);
                }
                $updated++;
                continue;
            }

            DB::connection($cliConn)->table('cuentas_cliente')
                ->where($colId, $ccId)
                ->update([$colAdminId => $candidate]);

            $updated++;

            if ($verboseLog) {
                Log::info('[P360] sync-admin-account-ids updated', [
                    'cc_id' => $ccId,
                    'admin_account_id' => $candidate,
                    'source' => $source,
                ]);
            }
        }

        $this->info("Listo. updated={$updated}, skipped={$skipped}, notFound={$notFound}");
        return self::SUCCESS;
    }

    private function decodeMeta(mixed $raw): array
    {
        try {
            if (is_array($raw)) return $raw;
            if (is_object($raw)) return (array) $raw;
            if (is_string($raw)) return json_decode($raw, true) ?: [];
        } catch (\Throwable $e) {}
        return [];
    }

    private function adminAccountExists(string $admConn, int $id): bool
    {
        try {
            return DB::connection($admConn)->table('accounts')->where('id', $id)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function findAdminByRfc(string $admConn, string $rfc): int
    {
        try {
            $cols = Schema::connection($admConn)->getColumnListing('accounts');

            if (in_array('rfc', $cols, true)) {
                $row = DB::connection($admConn)->table('accounts')
                    ->select(['id'])
                    ->whereRaw('UPPER(rfc) = ?', [$rfc])
                    ->orderByDesc('id')
                    ->first();

                if ($row && is_numeric($row->id ?? null)) return (int) $row->id;
            }

            // meta scan (limit razonable)
            $rows = DB::connection($admConn)->table('accounts')
                ->select(['id', 'meta'])
                ->orderByDesc('id')
                ->limit(6000)
                ->get();

            foreach ($rows as $acc) {
                $meta = $this->decodeMeta($acc->meta ?? null);
                foreach ([
                    data_get($meta, 'billing.rfc'),
                    data_get($meta, 'billing.rfc_fiscal'),
                    data_get($meta, 'company.rfc'),
                    data_get($meta, 'company.rfc_fiscal'),
                ] as $v) {
                    $v = strtoupper(trim((string) ($v ?? '')));
                    if ($v !== '' && $v === $rfc) return (int) $acc->id;
                }
            }
        } catch (\Throwable $e) {}

        return 0;
    }

    private function findAdminByEmail(string $admConn, string $email): int
    {
        try {
            $cols = Schema::connection($admConn)->getColumnListing('accounts');

            if (in_array('email', $cols, true)) {
                $row = DB::connection($admConn)->table('accounts')
                    ->select(['id'])
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->orderByDesc('id')
                    ->first();

                if ($row && is_numeric($row->id ?? null)) return (int) $row->id;
            }

            $rows = DB::connection($admConn)->table('accounts')
                ->select(['id', 'meta'])
                ->orderByDesc('id')
                ->limit(6000)
                ->get();

            foreach ($rows as $acc) {
                $meta = $this->decodeMeta($acc->meta ?? null);
                foreach ([
                    data_get($meta, 'billing.email'),
                    data_get($meta, 'company.email'),
                    data_get($meta, 'email'),
                ] as $v) {
                    $v = strtolower(trim((string) ($v ?? '')));
                    if ($v !== '' && $v === $email) return (int) $acc->id;
                }
            }
        } catch (\Throwable $e) {}

        return 0;
    }
}
