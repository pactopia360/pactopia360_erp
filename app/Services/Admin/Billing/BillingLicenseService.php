<?php declare(strict_types=1);

namespace App\Services\Admin\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class BillingLicenseService
{
    private string $conn;
    private string $table;
    private string $metaCol;

    public function __construct()
    {
        $this->conn    = (string) (env('P360_BILLING_SOT_CONN') ?: 'mysql_admin');
        $this->table   = (string) (env('P360_BILLING_SOT_TABLE') ?: 'accounts');
        $this->metaCol = (string) (env('P360_BILLING_META_COL') ?: 'meta');
    }

    public function assignDefaultProIfMissing(string $accountId, string $source = 'system.register'): bool
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) return false;
        if (!Schema::connection($this->conn)->hasColumn($this->table, $this->metaCol)) return false;

        $acc = DB::connection($this->conn)->table($this->table)->where('id', $accountId)->first();
        if (!$acc) return false;

        $meta = $this->decodeMeta($acc->{$this->metaCol} ?? null);

        // Si ya tiene billing.amount_mxn > 0 y price_key, no tocamos nada.
        $existingPk  = (string) data_get($meta, 'billing.price_key', '');
        $existingAmt = (int) data_get($meta, 'billing.amount_mxn', 0);

        if ($existingPk !== '' && $existingAmt > 0) {
            return false;
        }

        $catalog = $this->priceCatalog();
        $defaultKey = (string) (config('p360.billing.default_price_key') ?: env('P360_BILLING_DEFAULT_PRICE_KEY') ?: 'pro_mensual');
        if (!isset($catalog[$defaultKey])) {
            $defaultKey = isset($catalog['pro_mensual']) ? 'pro_mensual' : (string) array_key_first($catalog);
        }

        $p = $catalog[$defaultKey] ?? null;
        if (!$p) return false;

        $cycle = (string) ($p['billing_cycle'] ?? 'monthly');
        $amt   = (int) ($p['amount_mxn'] ?? 0);
        $spid  = (string) ($p['stripe_price_id'] ?? '');

        data_set($meta, 'billing.price_key', $defaultKey);
        data_set($meta, 'billing.billing_cycle', $cycle);
        data_set($meta, 'billing.amount_mxn', $amt);
        data_set($meta, 'billing.stripe_price_id', $spid !== '' ? $spid : null);
        data_set($meta, 'billing.assigned_at', now()->toISOString());
        data_set($meta, 'billing.assigned_by', $source);

        DB::connection($this->conn)->table($this->table)->where('id', $accountId)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at'   => now(),
        ]);

        // Mantener columnas visibles si existen
        $upd = [];
        if (Schema::connection($this->conn)->hasColumn($this->table, 'plan')) $upd['plan'] = $defaultKey;
        if (Schema::connection($this->conn)->hasColumn($this->table, 'billing_cycle')) $upd['billing_cycle'] = $cycle;

        if (!empty($upd)) {
            $upd['updated_at'] = now();
            DB::connection($this->conn)->table($this->table)->where('id', $accountId)->update($upd);
        }

        Log::info('BillingLicenseService.assignDefaultProIfMissing.applied', [
            'account_id' => $accountId,
            'price_key'  => $defaultKey,
            'cycle'      => $cycle,
            'amount_mxn' => $amt,
            'source'     => $source,
        ]);

        return true;
    }

    private function priceCatalog(): array
    {
        $cfg = config('p360.billing.prices');
        if (is_array($cfg) && !empty($cfg)) return $cfg;

        return [
            'pro_mensual' => [
                'label' => 'PRO mensual',
                'billing_cycle' => 'monthly',
                'amount_mxn' => 899,
                'stripe_price_id' => null,
            ],
            'pro_anual' => [
                'label' => 'PRO anual',
                'billing_cycle' => 'yearly',
                'amount_mxn' => 8990,
                'stripe_price_id' => null,
            ],
            'free' => [
                'label' => 'FREE',
                'billing_cycle' => 'none',
                'amount_mxn' => 0,
                'stripe_price_id' => null,
            ],
        ];
    }

    private function decodeMeta($raw): array
    {
        if (is_array($raw)) return $raw;
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            return is_array($d) ? $d : [];
        }
        return [];
    }

    private function encodeMeta(array $meta): string
    {
        return json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
