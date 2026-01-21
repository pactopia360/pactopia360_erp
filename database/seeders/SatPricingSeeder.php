<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SatPricingSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection('mysql_admin');

        // ===== sat_price_rules =====
        if ((int)$db->table('sat_price_rules')->count() === 0) {
            $now = now();

            $db->table('sat_price_rules')->insert([
                [
                    'name' => 'Básico · 0–500 XML',
                    'active' => 1,
                    'unit' => 'range_per_xml',
                    'min_xml' => 0,
                    'max_xml' => 500,
                    'price_per_xml' => 0.50,
                    'flat_price' => null,
                    'currency' => 'MXN',
                    'sort' => 10,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Plus · 501–2,000 XML',
                    'active' => 1,
                    'unit' => 'range_per_xml',
                    'min_xml' => 501,
                    'max_xml' => 2000,
                    'price_per_xml' => 0.35,
                    'flat_price' => null,
                    'currency' => 'MXN',
                    'sort' => 20,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Pro · 2,001–10,000 XML',
                    'active' => 1,
                    'unit' => 'range_per_xml',
                    'min_xml' => 2001,
                    'max_xml' => 10000,
                    'price_per_xml' => 0.20,
                    'flat_price' => null,
                    'currency' => 'MXN',
                    'sort' => 30,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Enterprise · 10,001–50,000 XML',
                    'active' => 1,
                    'unit' => 'range_per_xml',
                    'min_xml' => 10001,
                    'max_xml' => 50000,
                    'price_per_xml' => 0.12,
                    'flat_price' => null,
                    'currency' => 'MXN',
                    'sort' => 40,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Masivo · 50,001+ XML',
                    'active' => 1,
                    'unit' => 'range_per_xml',
                    'min_xml' => 50001,
                    'max_xml' => null,
                    'price_per_xml' => 0.08,
                    'flat_price' => null,
                    'currency' => 'MXN',
                    'sort' => 50,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Flat mensual (demo)',
                    'active' => 0,
                    'unit' => 'flat',
                    'min_xml' => 0,
                    'max_xml' => null,
                    'price_per_xml' => null,
                    'flat_price' => 1999.00,
                    'currency' => 'MXN',
                    'sort' => 90,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ===== sat_discount_codes =====
        if ((int)$db->table('sat_discount_codes')->count() === 0) {
            $now = now();

            $db->table('sat_discount_codes')->insert([
                [
                    'code' => 'PROMO10',
                    'label' => 'Promo 10% (global)',
                    'type' => 'percent',
                    'pct' => 10,
                    'amount_mxn' => 0,
                    'scope' => 'global',
                    'account_id' => null,
                    'partner_type' => null,
                    'partner_id' => null,
                    'active' => 1,
                    'starts_at' => null,
                    'ends_at' => null,
                    'max_uses' => null,
                    'uses_count' => 0,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'SOCIO15',
                    'label' => 'Socio 15% (demo)',
                    'type' => 'percent',
                    'pct' => 15,
                    'amount_mxn' => 0,
                    'scope' => 'partner',
                    'account_id' => null,
                    'partner_type' => 'socio',
                    'partner_id' => 'DEMO',
                    'active' => 1,
                    'starts_at' => null,
                    'ends_at' => null,
                    'max_uses' => null,
                    'uses_count' => 0,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'FIJO200',
                    'label' => 'Descuento fijo 200 MXN (demo)',
                    'type' => 'fixed',
                    'pct' => 0,
                    'amount_mxn' => 200,
                    'scope' => 'global',
                    'account_id' => null,
                    'partner_type' => null,
                    'partner_id' => null,
                    'active' => 0,
                    'starts_at' => null,
                    'ends_at' => null,
                    'max_uses' => 100,
                    'uses_count' => 0,
                    'meta' => json_encode(['note' => 'seed'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }
}
