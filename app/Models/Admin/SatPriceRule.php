<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

final class SatPriceRule extends Model
{
    protected $connection = 'mysql_admin';
    protected $table = 'sat_price_rules';

    protected $fillable = [
        'name','active','unit','min_xml','max_xml','price_per_xml','flat_price','currency','sort','meta',
    ];

    protected $casts = [
    'active' => 'boolean',
    'min_xml' => 'integer',
    'max_xml' => 'integer', // ✅ si te funciona, déjalo. Si ves problemas, cámbialo a:
    // 'max_xml' => 'integer', // (ok)
    'price_per_xml' => 'decimal:6',
    'flat_price' => 'decimal:2',
    'sort' => 'integer',
    'meta' => 'array',
    ];

    public static function evaluateCost(int $xmlCount): float
    {
        $n = max(0, $xmlCount);

        /** @var \Illuminate\Support\Collection<int, self> $rules */
        $rules = self::query()
            ->where('active', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('min_xml', 'asc')
            ->get();

        foreach ($rules as $r) {
            $min = (int)($r->min_xml ?? 0);
            $max = $r->max_xml !== null ? (int)$r->max_xml : null;

            if ($n < $min) continue;
            if ($max !== null && $n > $max) continue;

            $unit = (string)($r->unit ?? 'range_per_xml');

            if ($unit === 'flat') {
                return (float)($r->flat_price ?? 0);
            }

            // default: range_per_xml
            $pp = (float)($r->price_per_xml ?? 0);
            return round($n * $pp, 2);
        }

        return 0.0;
    }
}
