<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatQuoteService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final class SatQuoteService
{
    public function __construct(
        private readonly SatDownloadMetrics $metrics,
    ) {}

    public function normalizeIvaRate($ivaRaw): int
    {
        $ivaRate = 16;
        if (is_numeric($ivaRaw)) {
            $v = (float) $ivaRaw;
            $ivaRate = ($v > 1) ? (int) round($v) : (int) round($v * 100);
        }
        return max(0, min(16, $ivaRate));
    }

    /**
     * @return array{plan:string,empresa:string}
     */
    public function resolvePlanAndEmpresa(object $user, string $cuentaId, string $conn = 'mysql_clientes'): array
    {
        static $cache = [];
        $key = trim((string) $cuentaId);
        if ($key !== '' && isset($cache[$key])) return $cache[$key];

        $pick = static function ($obj, array $cols): ?string {
            if (!$obj) return null;
            foreach ($cols as $c) {
                try {
                    if (isset($obj->{$c}) && trim((string) $obj->{$c}) !== '') return trim((string) $obj->{$c});
                } catch (\Throwable) {}
            }
            return null;
        };

        $cuentaRow = null;
        try {
            if ($key !== '' && Schema::connection($conn)->hasTable('cuentas_cliente')) {
                $cuentaRow = DB::connection($conn)->table('cuentas_cliente')->where('id', $key)->first();
            }
        } catch (\Throwable) { $cuentaRow = null; }

        $planRaw = $pick($cuentaRow, ['plan_actual','plan','plan_name']);
        if (!$planRaw) {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                $planRaw = $pick($c, ['plan_actual','plan','plan_name']);
            } catch (\Throwable) {}
        }
        $plan = strtoupper(trim((string) $planRaw)) ?: 'FREE';

        $empresaRaw = $pick($cuentaRow, ['razon_social','razon','empresa','nombre','name']);
        if (!$empresaRaw) {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                $empresaRaw = $pick($c, ['razon_social','razon','empresa','nombre','name']);
            } catch (\Throwable) {}
        }
        $empresa = ($empresaRaw && $empresaRaw !== '') ? $empresaRaw : '—';

        $cache[$key] = ['plan' => $plan, 'empresa' => $empresa];
        return $cache[$key];
    }

    public function computeDownloadCostPhp(int $n): float
    {
        return (float) $this->metrics->computeDownloadCost($n);
    }

    /**
     * @return array{0:float,1:string,2:string} [base_mxn, note, source]
     */
    public function resolveAdminPriceForXml(int $xmlCount): array
    {
        $n = max(0, (int) $xmlCount);
        if ($n <= 0) return [0.0, 'Sin documentos.', 'fallback'];

        $conn = 'mysql_admin';
        $tables = [
            'sat_download_price_ranges',
            'sat_download_prices',
            'sat_price_ranges',
            'sat_prices',
            'sat_price_catalog',
            'sat_cfdi_price_ranges',
        ];

        $toNum = static function ($v): float {
            if (is_numeric($v)) return (float) $v;
            $s = trim((string) $v);
            if ($s === '') return 0.0;
            $s = str_replace(['$', ',', ' '], ['', '', ''], $s);
            return is_numeric($s) ? (float) $s : 0.0;
        };

        try {
            $schema = Schema::connection($conn);

            $tableFound = null;
            foreach ($tables as $t) {
                if ($schema->hasTable($t)) { $tableFound = $t; break; }
            }
            if (!$tableFound) return [$this->computeDownloadCostPhp($n), '', 'fallback'];

            $cols = $schema->getColumnListing($tableFound);

            $pick = static function (array $candidates, array $cols): ?string {
                foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
                return null;
            };

            $colMin = $pick(['min_xml','min','min_docs','min_count','desde','from'], $cols);
            $colMax = $pick(['max_xml','max','max_docs','max_count','hasta','to'], $cols);

            $colFlat = $pick(['price','price_mxn','base_mxn','amount','amount_mxn','flat_price','flat_mxn','total','total_mxn'], $cols);
            $colUnit = $pick(['unit_price','unit_price_mxn','unit_mxn','per_xml','per_doc','rate','rate_mxn'], $cols);

            $colNote   = $pick(['note','label','descripcion','description','name'], $cols);
            $colActive = $pick(['is_active','active','enabled','estatus','status'], $cols);
            $colSort   = $pick(['sort','priority','orden','order'], $cols);

            $q = DB::connection($conn)->table($tableFound);

            if ($colActive) {
                $q->where(function ($w) use ($colActive) {
                    $w->where($colActive, 1)
                      ->orWhere($colActive, true)
                      ->orWhereRaw('LOWER(COALESCE(' . $colActive . ', "")) IN ("1","true","active","activo","enabled","on")');
                });
            }

            if ($colSort) $q->orderBy($colSort, 'asc');
            if ($colMin)  $q->orderBy($colMin, 'asc');

            $rows = $q->get();

            $best = null;
            foreach ($rows as $row) {
                $minOk = true;
                $maxOk = true;

                if ($colMin && isset($row->{$colMin}) && is_numeric($row->{$colMin})) $minOk = $n >= (int) $row->{$colMin};
                if ($colMax && isset($row->{$colMax}) && is_numeric($row->{$colMax})) $maxOk = $n <= (int) $row->{$colMax};

                if ($minOk && $maxOk) { $best = $row; break; }
            }

            if (!$best) return [$this->computeDownloadCostPhp($n), '', 'fallback'];

            $flat = ($colFlat && isset($best->{$colFlat})) ? $toNum($best->{$colFlat}) : 0.0;
            $unit = ($colUnit && isset($best->{$colUnit})) ? $toNum($best->{$colUnit}) : 0.0;

            if ($flat > 0) $base = $flat;
            elseif ($unit > 0) $base = $unit * $n;
            else $base = $this->computeDownloadCostPhp($n);

            $note = ($colNote && isset($best->{$colNote}) && trim((string) $best->{$colNote}) !== '')
                ? trim((string) $best->{$colNote})
                : '';

            if ($note === '') {
                $minTxt = ($colMin && isset($best->{$colMin}) && is_numeric($best->{$colMin})) ? (int) $best->{$colMin} : null;
                $maxTxt = ($colMax && isset($best->{$colMax}) && is_numeric($best->{$colMax})) ? (int) $best->{$colMax} : null;

                if ($minTxt !== null && $maxTxt !== null) $note = "Precio Admin ({$minTxt}-{$maxTxt} XML)";
                elseif ($minTxt !== null) $note = "Precio Admin (>= {$minTxt} XML)";
                elseif ($maxTxt !== null) $note = "Precio Admin (<= {$maxTxt} XML)";
                else $note = 'Precio Admin';
            }

            return [(float) $base, $note, $tableFound];
        } catch (\Throwable $e) {
            Log::warning('[SatQuoteService:resolveAdminPriceForXml] fallback', ['xml_count' => $n, 'err' => $e->getMessage()]);
            return [$this->computeDownloadCostPhp($n), '', 'fallback'];
        }
    }

    /**
     * @return array{
     *   pct:float,amount:float,applied_code:?string,label:?string,reason:?string,type:string,value:float|null
     * }
     */
    public function resolveSatDiscountSmart(string $cuentaId, ?string $discountCode, float $base): array
    {
        $code = strtoupper(trim((string) ($discountCode ?? '')));
        if ($code === '') return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>null,'reason'=>null,'type'=>'none','value'=>null];

        $base = max(0.0, (float) $base);
        $cuentaIdTrim = trim((string) $cuentaId);

        $adminAccountId = null;
        $meta = [];

        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $row = DB::connection('mysql_clientes')
                    ->table('cuentas_cliente')
                    ->select(['admin_account_id','meta'])
                    ->where('id', $cuentaIdTrim)
                    ->first();

                if ($row) {
                    $adminAccountId = isset($row->admin_account_id) ? (int) $row->admin_account_id : null;

                    $m = $row->meta ?? null;
                    if (is_string($m) && $m !== '') {
                        $tmp = json_decode($m, true);
                        if (is_array($tmp)) $meta = $tmp;
                    } elseif (is_array($m)) $meta = $m;
                    elseif (is_object($m)) $meta = (array) $m;
                }
            }
        } catch (\Throwable) {}

        $partnerType = $this->metaPickString($meta, ['partner_type','partnerType','tipo_partner','tipoPartner','socio_type','socioType']);
        $partnerId   = $this->metaPickString($meta, ['partner_id','partnerId','partner','partner_code','partnerCode','socio_partner','socioPartner']);

        $partnerType = strtolower(trim((string) $partnerType));
        $partnerId   = strtoupper(trim((string) $partnerId));

        try {
            if (!Schema::connection('mysql_admin')->hasTable('sat_discount_codes')) {
                return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>null,'reason'=>'Código no encontrado.','type'=>'none','value'=>null];
            }

            $r = DB::connection('mysql_admin')->selectOne("
                SELECT id, code, label, type, pct, amount_mxn, scope, account_id, partner_type, partner_id,
                       active, starts_at, ends_at, max_uses, uses_count
                FROM sat_discount_codes
                WHERE UPPER(code)=UPPER(?)
                LIMIT 1
            ", [$code]);

            if (!$r) return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>null,'reason'=>'Código no encontrado.','type'=>'none','value'=>null];

            $active = (int) ($r->active ?? 0) === 1;
            if (!$active) return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código inactivo.','type'=>'none','value'=>null];

            $now = now();
            try {
                if (!empty($r->starts_at)) {
                    $st = Carbon::parse((string)$r->starts_at);
                    if ($st->isFuture()) return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código aún no inicia.','type'=>'none','value'=>null];
                }
                if (!empty($r->ends_at)) {
                    $en = Carbon::parse((string)$r->ends_at);
                    if ($en->isPast()) return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código expirado.','type'=>'none','value'=>null];
                }
            } catch (\Throwable) {}

            try {
                $max = $r->max_uses ?? null;
                $use = $r->uses_count ?? 0;
                if ($max !== null && is_numeric($max) && (int)$max > 0 && (int)$use >= (int)$max) {
                    return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código sin usos disponibles.','type'=>'none','value'=>null];
                }
            } catch (\Throwable) {}

            $scope = strtolower(trim((string) ($r->scope ?? 'global')));
            $rPartnerType = strtolower(trim((string) ($r->partner_type ?? '')));
            $rPartnerId   = strtoupper(trim((string) ($r->partner_id ?? '')));

            if ($scope === 'account') {
                $need = $r->account_id ?? null;
                if ($need !== null && is_numeric($need)) {
                    $need = (int) $need;
                    if (!$adminAccountId || (int)$adminAccountId !== $need) {
                        return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código no válido para esta cuenta.','type'=>'none','value'=>null];
                    }
                }
            } elseif ($scope === 'partner') {
                if ($partnerType === '' || $partnerId === '') {
                    return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Tu cuenta no tiene partner asociado para aplicar este código.','type'=>'none','value'=>null];
                }
                if ($rPartnerType !== '' && $partnerType !== $rPartnerType) {
                    return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código no corresponde a tu partner.','type'=>'none','value'=>null];
                }
                if ($rPartnerId !== '' && $partnerId !== $rPartnerId) {
                    return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>(string)($r->label ?? null),'reason'=>'Código no corresponde a tu partner.','type'=>'none','value'=>null];
                }
            }

            $type  = strtolower(trim((string) ($r->type ?? 'percent')));
            $label = trim((string) ($r->label ?? ''));
            if ($label === '') $label = null;

            if ($base <= 0) return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>$code,'label'=>$label,'reason'=>'Base inválida.','type'=>'none','value'=>null];

            if (in_array($type, ['percent','percentage','pct'], true)) {
                $pct = (float) ($r->pct ?? 0);
                $pct = max(0.0, min(100.0, $pct));

                $amt = round($base * ($pct / 100), 2);

                return [
                    'pct' => $pct,
                    'amount' => min($amt, $base),
                    'applied_code' => (string) ($r->code ?? $code),
                    'label' => $label,
                    'reason' => 'Código aplicado.',
                    'type' => 'percent',
                    'value' => $pct,
                ];
            }

            if (in_array($type, ['amount','fixed','mxn'], true)) {
                $mxn = (float) ($r->amount_mxn ?? 0);
                $mxn = max(0.0, $mxn);

                $amt = min(round($mxn, 2), $base);
                $pct = ($base > 0) ? round(($amt / $base) * 100, 4) : 0.0;

                return [
                    'pct' => $pct,
                    'amount' => $amt,
                    'applied_code' => (string) ($r->code ?? $code),
                    'label' => $label,
                    'reason' => 'Código aplicado.',
                    'type' => 'amount',
                    'value' => $mxn,
                ];
            }

            return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>$label,'reason'=>'Tipo de descuento no soportado.','type'=>'none','value'=>null];
        } catch (\Throwable $e) {
            Log::warning('[SatQuoteService:resolveSatDiscountSmart] error', [
                'cuenta_id' => $cuentaIdTrim,
                'code' => $code,
                'err' => $e->getMessage(),
            ]);
            return ['pct'=>0.0,'amount'=>0.0,'applied_code'=>null,'label'=>null,'reason'=>'Error validando código.','type'=>'none','value'=>null];
        }
    }

    public function pricingNoteForXml(int $xmlCount): string
    {
        $n = max(0, $xmlCount);

        if ($n <= 0) return 'Sin documentos.';
        if ($n <= 5000) return 'Tarifa: $1 MXN por documento (1–5,000).';
        if ($n <= 25000) return 'Tarifa: $0.08 MXN por documento (5,001–25,000).';
        if ($n <= 40000) return 'Tarifa: $0.05 MXN por documento (25,001–40,000).';
        if ($n <= 100000) return 'Tarifa: $0.03 MXN por documento (40,001–100,000).';
        if ($n <= 500000) return 'Tarifa plana: $12,500 MXN (100,001–500,000).';
        if ($n <= 1000000) return 'Tarifa plana: $18,500 MXN (500,001–1,000,000).';
        if ($n <= 2000000) return 'Tarifa plana: $25,000 MXN (1,000,001–2,000,000).';
        if ($n <= 3000000) return 'Tarifa plana: $31,000 MXN (2,000,001–3,000,000).';

        return 'Tarifa: $0.01 MXN por documento (> 3,000,000).';
    }

    /**
     * @return array{
     *   folio:string, generated:Carbon, validUntil:Carbon, plan:string, empresa:string, xmlCount:int,
     *   base:float, discountCode:?string, discountCodeApplied:?string, discountLabel:?string, discountReason:?string,
     *   discountType:?string, discountValue:float|null, discountPct:float, discountAmount:float, subtotal:float,
     *   ivaRate:int, ivaAmount:float, total:float, note:string, priceSource:string
     * }
     */
    public function buildSatQuotePayload(
        object $user,
        string $cuentaId,
        int $xmlCount,
        ?string $discountCode,
        int $ivaRate,
        bool $useAdminPrice = true
    ): array {
        $cuentaId = trim((string) $cuentaId);

        $xmlCount = (int) $xmlCount;
        if ($xmlCount < 1) $xmlCount = 1;

        $ivaRate = $this->normalizeIvaRate($ivaRate);

        $discountCode = trim((string) ($discountCode ?? ''));
        $discountCode = ($discountCode !== '') ? $discountCode : null;

        $ctx = $this->resolvePlanAndEmpresa($user, $cuentaId);
        $plan    = strtoupper(trim((string) ($ctx['plan'] ?? 'FREE'))) ?: 'FREE';
        $empresa = trim((string) ($ctx['empresa'] ?? '')) !== '' ? trim((string) ($ctx['empresa'] ?? '')) : '—';

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        $priceSource = $useAdminPrice ? 'admin' : 'metrics';
        $priceNote   = '';

        if ($useAdminPrice) {
            [$base, $priceNote, $src] = $this->resolveAdminPriceForXml($xmlCount);
            $base = (float) $base;
            $priceSource = (string) ($src ?: 'admin');
        } else {
            $base = (float) $this->computeDownloadCostPhp($xmlCount);
        }

        $base = round(max(0.0, $base), 2);

        $disc = $this->resolveSatDiscountSmart($cuentaId, $discountCode, $base);

        $discountPct    = (float) ($disc['pct'] ?? 0.0);
        $discountAmount = (float) ($disc['amount'] ?? 0.0);

        $discountPct = max(0.0, min(100.0, $discountPct));
        $discountAmount = max(0.0, min($base, $discountAmount));
        $discountAmount = round($discountAmount, 2);

        $subtotal  = round(max(0.0, $base - $discountAmount), 2);
        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $priceNote !== '' ? $priceNote : $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . (int) round($discountPct) . '%.';
        if ($useAdminPrice && $priceSource !== '') $note .= ' Fuente: ' . $priceSource . '.';

        return [
            'folio'          => $folio,
            'generated'      => $generated,
            'validUntil'     => $validUntil,
            'plan'           => $plan,
            'empresa'        => $empresa,
            'xmlCount'       => $xmlCount,
            'base'           => $base,
            'discountCode'   => $discountCode,
            'discountCodeApplied' => $disc['applied_code'] ?? null,
            'discountLabel'        => $disc['label'] ?? null,
            'discountReason'       => $disc['reason'] ?? null,
            'discountType'         => $disc['type'] ?? null,
            'discountValue'        => $disc['value'] ?? null,
            'discountPct'    => $discountPct,
            'discountAmount' => $discountAmount,
            'subtotal'       => $subtotal,
            'ivaRate'        => $ivaRate,
            'ivaAmount'      => $ivaAmount,
            'total'          => $total,
            'note'           => $note,
            'priceSource'    => $priceSource,
        ];
    }

    public function quotePdfHash(array $payload): string
    {
        try {
            $fmt2 = static fn($v) => number_format((float) $v, 2, '.', '');

            $safe = [
                'mode'         => (string) ($payload['mode'] ?? ''),
                'folio'        => (string) ($payload['folio'] ?? ''),
                'cuenta_id'    => (string) ($payload['cuenta_id'] ?? ''),
                'empresa'      => (string) ($payload['empresa'] ?? ''),
                'xml_count'    => (int)    ($payload['xml_count'] ?? 0),

                'base'         => $fmt2($payload['base'] ?? 0),
                'discount_pct' => $fmt2($payload['discount_pct'] ?? 0),
                'subtotal'     => $fmt2($payload['subtotal'] ?? 0),
                'iva_rate'     => (int)    ($payload['iva_rate'] ?? 0),
                'iva_amount'   => $fmt2($payload['iva_amount'] ?? 0),
                'total'        => $fmt2($payload['total'] ?? 0),

                'valid_until'  => is_object($payload['valid_until'] ?? null)
                    ? (string) ($payload['valid_until']->toDateString() ?? '')
                    : (string) ($payload['valid_until'] ?? ''),
            ];

            $key = (string) config('app.key', '');
            $raw = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (str_starts_with($key, 'base64:')) $key = base64_decode(substr($key, 7)) ?: $key;
            if (trim($key) === '') $key = (string) (config('app.url') . '|' . config('app.name'));

            return strtoupper(substr(hash_hmac('sha256', (string) $raw, (string) $key), 0, 16));
        } catch (\Throwable) {
            return strtoupper(substr(sha1((string) microtime(true)), 0, 16));
        }
    }

    private function metaPickString(array $meta, array $keys): string
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $meta)) continue;
            $v = $meta[$k];
            if (is_string($v) && trim($v) !== '') return trim($v);
            if (is_numeric($v)) return trim((string)$v);
        }
        return '';
    }
}
