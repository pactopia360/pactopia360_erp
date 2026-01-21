<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class SatDiscountCode extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'sat_discount_codes';

    protected $fillable = [
        'code','label','type','pct','amount_mxn','scope','account_id','partner_type','partner_id',
        'active','starts_at','ends_at','max_uses','uses_count','meta',
    ];

    protected $casts = [
        'active' => 'boolean',
        'pct' => 'integer',
        'amount_mxn' => 'decimal:2',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function isValidNow(): bool
    {
        if (!$this->active) return false;

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;

        if ($this->max_uses !== null && $this->max_uses > 0) {
            if ((int)$this->uses_count >= (int)$this->max_uses) return false;
        }

        return true;
    }

    /**
     * Devuelve el descuento como:
     * - pct (0-90) si type=percent
     * - amount_mxn si type=fixed
     */
    public function normalized(): array
    {
        $type = strtolower((string)($this->type ?? 'percent'));

        if ($type === 'fixed') {
            return ['type' => 'fixed', 'amount_mxn' => (float)$this->amount_mxn];
        }

        $pct = (int)($this->pct ?? 0);
        if ($pct < 0) $pct = 0;
        if ($pct > 90) $pct = 90;

        return ['type' => 'percent', 'pct' => $pct];
    }
}
