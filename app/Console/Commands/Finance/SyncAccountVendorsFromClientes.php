<?php

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SyncAccountVendorsFromClientes extends Command
{
    protected $signature = 'finance:sync-account-vendors
        {--dry-run : No inserta, solo muestra conteos}
        {--vendor-id= : Vendor por defecto si no se puede inferir}
        {--only-active=1 : Solo cuentas activas (1|0)}
        {--limit=0 : Limitar número de cuentas (0 = sin límite)}
    ';

    protected $description = 'Sincroniza finance_account_vendor desde mysql_clientes.cuentas_cliente (UUID) mapeando a admin_account_id.';

    public function handle(): int
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        if (!Schema::connection($cli)->hasTable('cuentas_cliente')) {
            $this->error("No existe cuentas_cliente en [$cli].");
            return self::FAILURE;
        }
        if (!Schema::connection($adm)->hasTable('finance_account_vendor')) {
            $this->error("No existe finance_account_vendor en [$adm].");
            return self::FAILURE;
        }
        if (!Schema::connection($adm)->hasTable('finance_vendors')) {
            $this->error("No existe finance_vendors en [$adm].");
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $onlyActive = (int) ($this->option('only-active') ?? 1);
        $limit = (int) ($this->option('limit') ?? 0);

        $defaultVendorId = $this->option('vendor-id');
        $defaultVendorId = is_null($defaultVendorId) || $defaultVendorId === '' ? null : (int) $defaultVendorId;

        if ($defaultVendorId !== null) {
            $exists = DB::connection($adm)->table('finance_vendors')->where('id', $defaultVendorId)->exists();
            if (!$exists) {
                $this->error("vendor-id={$defaultVendorId} no existe en finance_vendors.");
                return self::FAILURE;
            }
        }

        // 1) Traemos cuentas cliente (UUID + admin_account_id)
        $q = DB::connection($cli)->table('cuentas_cliente')
            ->select(['id', 'admin_account_id', 'activo', 'nombre_comercial', 'razon_social', 'rfc_padre', 'rfc', 'meta'])
            ->whereNotNull('admin_account_id');

        if ($onlyActive === 1) {
            $q->where('activo', 1);
        }

        if ($limit > 0) $q->limit($limit);

        $cuentas = collect($q->get());

        if ($cuentas->isEmpty()) {
            $this->info("No hay cuentas para procesar.");
            return self::SUCCESS;
        }

        // 2) Catálogo vendors (para resolver por nombre si lo necesitas)
        $vendors = collect(DB::connection($adm)->table('finance_vendors')->get(['id', 'name']))->keyBy('id');

        // Resolver vendor por heurística:
        // - meta.vendor_id (si existe)
        // - meta.vendor (nombre) (si existe, intenta match parcial)
        // - default vendor (si lo pasas)
        $resolveVendorId = function ($cc) use ($vendors, $defaultVendorId): ?int {
            $meta = [];
            try {
                $meta = is_array($cc->meta) ? $cc->meta : (json_decode((string)$cc->meta, true) ?: []);
            } catch (\Throwable $e) {
                $meta = [];
            }

            $vid = data_get($meta, 'vendor_id');
            if (!empty($vid) && is_numeric($vid)) return (int) $vid;

            $vname = (string) (data_get($meta, 'vendor') ?? data_get($meta, 'vendor_name') ?? '');
            $vname = trim($vname);
            if ($vname !== '') {
                $needle = mb_strtolower($vname);
                foreach ($vendors as $v) {
                    $hay = mb_strtolower((string)($v->name ?? ''));
                    if ($hay !== '' && str_contains($hay, $needle)) {
                        return (int) $v->id;
                    }
                }
            }

            return $defaultVendorId;
        };

        $toUpsert = [];
        $skipNoVendor = 0;

        foreach ($cuentas as $cc) {
            $adminAccId = (int) ($cc->admin_account_id ?? 0);
            $uuid = (string) ($cc->id ?? '');

            if ($adminAccId <= 0 || $uuid === '') continue;

            $vendorId = $resolveVendorId($cc);

            if (!$vendorId) {
                $skipNoVendor++;
                continue;
            }

            // upsert identity: (account_id, vendor_id, is_primary=1, client_uuid)
            $toUpsert[] = [
                'account_id'  => $adminAccId,
                'client_uuid' => $uuid,
                'vendor_id'   => $vendorId,
                'starts_on'   => null,
                'ends_on'     => null,
                'is_primary'  => 1,
                'updated_at'  => now(),
                'created_at'  => now(),
            ];
        }

        $this->info("Cuentas leídas: ".$cuentas->count());
        $this->info("Registros candidatos: ".count($toUpsert));
        $this->info("Sin vendor (saltadas): ".$skipNoVendor);

        if ($dry) {
            $this->warn("DRY RUN: no se insertó nada.");
            return self::SUCCESS;
        }

        if (empty($toUpsert)) {
            $this->warn("No hay registros para insertar.");
            return self::SUCCESS;
        }

        // 3) Upsert: clave natural (account_id, client_uuid, is_primary)
        // Nota: si tu tabla no tiene UNIQUE, esto hará inserts duplicados.
        // Recomendación: crear unique index (account_id, client_uuid, is_primary).
        // Aquí hacemos estrategia segura: delete+insert por account_id si ya existe primary.
        DB::connection($adm)->transaction(function () use ($adm, $toUpsert) {

            $adminIds = collect($toUpsert)->pluck('account_id')->unique()->values()->all();

            // borra solo primary actuales de esas cuentas (evita duplicados)
            DB::connection($adm)->table('finance_account_vendor')
                ->whereIn('account_id', $adminIds)
                ->where('is_primary', 1)
                ->delete();

            DB::connection($adm)->table('finance_account_vendor')->insert($toUpsert);
        });

        $this->info("OK: sincronización aplicada.");
        return self::SUCCESS;
    }
}