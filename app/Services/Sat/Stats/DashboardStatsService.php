<?php

declare(strict_types=1);

namespace App\Services\Sat\Stats;

use App\Models\SatDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DashboardStatsService
{
    /**
     * Construye stats del dashboard SAT para una cuenta.
     * - NO resuelve sesión (eso queda en controller)
     * - NO usa helpers del controller
     */
    public function build(Request $request, string $cuentaId): array
    {
        $cuentaId = trim($cuentaId);
        if ($cuentaId === '') {
            return [
                'ok'  => false,
                'msg' => 'Cuenta inválida.',
            ];
        }

        // Rango defensivo
        [$from, $to, $rangeDays] = $this->resolveDateRange($request);

        $conn  = 'mysql_clientes';
        $dl    = new SatDownload();
        $table = $dl->getTable();

        $schema = Schema::connection($conn);

        if (!$schema->hasTable($table)) {
            return [
                'ok'  => false,
                'msg' => 'No existe la tabla sat_downloads.',
            ];
        }

        $cols = [];
        try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }

        $has = static function (string $c) use ($cols): bool {
            return in_array($c, $cols, true);
        };

        $colCreated = $has('created_at') ? 'created_at' : null;

        $colTipo    = $has('tipo') ? 'tipo' : null;
        $colRfc     = $has('rfc') ? 'rfc' : null;

        $colStatus  = $has('status') ? 'status' : null;
        $colEstado  = $has('estado') ? 'estado' : null;
        $colSatStat = $has('sat_status') ? 'sat_status' : null;

        $colPaidAt  = $has('paid_at') ? 'paid_at' : null;

        $colXmlA = $has('xml_count') ? 'xml_count' : null;
        $colXmlB = $has('total_xml') ? 'total_xml' : null;
        $colXmlC = $has('num_xml') ? 'num_xml' : null;

        $colCostoA = $has('costo') ? 'costo' : null;
        $colCostoB = $has('price_mxn') ? 'price_mxn' : null;
        $colCostoC = $has('precio') ? 'precio' : null;

        $colIsManual = $has('is_manual') ? 'is_manual' : ($has('manual') ? 'manual' : null);

        $colCuenta = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
        if (!$colCuenta) {
            return [
                'ok'  => false,
                'msg' => 'La tabla sat_downloads no tiene cuenta_id ni account_id.',
            ];
        }

        $q = DB::connection($conn)->table($table)->where($colCuenta, $cuentaId);

        // Excluir vault/bóveda (si hay tipo)
        if ($colTipo) {
            $q->whereRaw('LOWER(COALESCE(' . $colTipo . ', "")) NOT IN ("vault","boveda","bóveda")');
        }

        // ✅ EXCLUIR REGISTROS "EXTERNAL ZIP REGISTER"
        try {
            if ($has('zip_path')) {
                $q->where(function ($w) {
                    $w->whereNull('zip_path')
                        ->orWhere('zip_path', '=', '')
                        ->orWhere('zip_path', 'not like', 'sat/external_zip/%');
                });
            }

            if ($has('meta')) {
                $q->where(function ($w) {
                    $w->whereNull('meta')
                        ->orWhere('meta', '=', '')
                        ->orWhere('meta', 'not like', '%"source":"external_zip_register"%')
                        ->orWhere('meta', 'not like', '%external_zip_register%');
                });
            }
        } catch (\Throwable) {
            // defensivo
        }

        // Fecha (si existe created_at)
        if ($colCreated) {
            $q->whereBetween($colCreated, [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ]);
        }

        // Select mínimo
        $select = ['id', $colCuenta];
        if ($colCreated) $select[] = $colCreated;
        if ($colTipo)    $select[] = $colTipo;
        if ($colRfc)     $select[] = $colRfc;

        if ($colStatus)  $select[] = $colStatus;
        if ($colEstado)  $select[] = $colEstado;
        if ($colSatStat) $select[] = $colSatStat;

        if ($colPaidAt)  $select[] = $colPaidAt;

        if ($colXmlA)    $select[] = $colXmlA;
        if ($colXmlB)    $select[] = $colXmlB;
        if ($colXmlC)    $select[] = $colXmlC;

        if ($colCostoA)  $select[] = $colCostoA;
        if ($colCostoB)  $select[] = $colCostoB;
        if ($colCostoC)  $select[] = $colCostoC;

        if ($colIsManual) $select[] = $colIsManual;

        $hasMeta = $has('meta');
        if ($hasMeta) $select[] = 'meta';

        $rows = $q->select($select)->get();

        // Totales
        $totCount = 0;
        $totXml   = 0;
        $totCost  = 0.0;

        $byStatus = [
            'pending'     => 0,
            'processing'  => 0,
            'ready'       => 0,
            'paid'        => 0,
            'downloaded'  => 0,
            'expired'     => 0,
            'error'       => 0,
            'other'       => 0,
        ];

        $byTipo = [
            'emitidos'  => 0,
            'recibidos' => 0,
            'ambos'     => 0,
            'other'     => 0,
        ];

        $byManual = [
            'manual'  => 0,
            'regular' => 0,
        ];

        $uniqueRfcs = [];

        // Serie diaria
        $series = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $k = $cursor->format('Y-m-d');
            $series[$k] = [
                'date'       => $k,
                'count'      => 0,
                'ready'      => 0,
                'paid'       => 0,
                'pending'    => 0,
                'processing' => 0,
                'error'      => 0,
                'downloaded' => 0,
                'other'      => 0,
            ];
            $cursor->addDay();
        }

        foreach ($rows as $r) {
            $totCount++;

            // tipo
            $tipoLow = '';
            if ($colTipo && isset($r->{$colTipo})) {
                $tipoLow = strtolower(trim((string) $r->{$colTipo}));
            }

            $tipoBucket = 'other';
            if ($tipoLow !== '') {
                if (in_array($tipoLow, ['emitidos','emitido','out','salida'], true) || str_contains($tipoLow, 'emit')) {
                    $tipoBucket = 'emitidos';
                } elseif (in_array($tipoLow, ['recibidos','recibido','in','entrada'], true) || str_contains($tipoLow, 'recib')) {
                    $tipoBucket = 'recibidos';
                } elseif (in_array($tipoLow, ['ambos','both','mix','mixto'], true)) {
                    $tipoBucket = 'ambos';
                }
            }
            $byTipo[$tipoBucket]++;

            // RFCs únicos
            if ($colRfc && isset($r->{$colRfc})) {
                $rr = strtoupper(trim((string) $r->{$colRfc}));
                if ($rr !== '') $uniqueRfcs[$rr] = true;
            }

            // xml_count
            $xml = 0;
            foreach ([$colXmlA, $colXmlB, $colXmlC] as $c) {
                if ($c && isset($r->{$c}) && is_numeric($r->{$c}) && (int)$r->{$c} > 0) {
                    $xml = (int) $r->{$c};
                    break;
                }
            }
            if ($xml <= 0 && $hasMeta && !empty($r->meta)) {
                $m = $this->decodeMeta($r->meta);
                foreach (['xml_count','total_xml','num_xml'] as $k) {
                    if (isset($m[$k]) && (int)$m[$k] > 0) { $xml = (int)$m[$k]; break; }
                }
            }
            $totXml += max(0, $xml);

            // costo
            $cost = 0.0;
            foreach ([$colCostoA, $colCostoB, $colCostoC] as $c) {
                if ($c && isset($r->{$c}) && is_numeric($r->{$c}) && (float)$r->{$c} > 0) {
                    $cost = (float) $r->{$c};
                    break;
                }
            }
            if ($cost <= 0 && $hasMeta && !empty($r->meta)) {
                $m = $this->decodeMeta($r->meta);
                foreach (['price_mxn','costo','cost_mxn','precio'] as $k) {
                    if (isset($m[$k]) && (float)$m[$k] > 0) { $cost = (float)$m[$k]; break; }
                }
            }
            $totCost += max(0.0, $cost);

            // manual?
            $isManual = false;
            if ($colIsManual && isset($r->{$colIsManual})) {
                $v = $r->{$colIsManual};
                if (is_bool($v)) $isManual = $v;
                elseif (is_numeric($v)) $isManual = ((int)$v) === 1;
                elseif (is_string($v)) $isManual = in_array(strtolower(trim($v)), ['1','true','yes','si','sí','on'], true);
            } elseif ($hasMeta && !empty($r->meta)) {
                $m = $this->decodeMeta($r->meta);
                $isManual = !empty($m['is_manual'] ?? null) || !empty($m['manual'] ?? null);
            }
            $byManual[$isManual ? 'manual' : 'regular']++;

            // status normalizado
            $paid = false;
            if ($colPaidAt && !empty($r->{$colPaidAt})) $paid = true;

            $rawStatus = '';
            foreach ([$colStatus, $colEstado, $colSatStat] as $c) {
                if ($c && isset($r->{$c}) && trim((string)$r->{$c}) !== '') {
                    $rawStatus = (string) $r->{$c};
                    break;
                }
            }

            $st = $this->normalizeStatus($rawStatus, $paid);
            if (!isset($byStatus[$st])) $st = 'other';
            $byStatus[$st]++;

            // serie por día
            if ($colCreated && !empty($r->{$colCreated})) {
                try {
                    $dt  = Carbon::parse((string) $r->{$colCreated});
                    $key = $dt->format('Y-m-d');

                    if (isset($series[$key])) {
                        $series[$key]['count']++;
                        $bucket = isset($series[$key][$st]) ? $st : 'other';
                        if (!isset($series[$key][$bucket])) $series[$key][$bucket] = 0;
                        $series[$key][$bucket]++;
                    }
                } catch (\Throwable) {}
            }
        }

        return [
            'ok'    => true,
            'range' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'days' => $rangeDays,
            ],
            'totals' => [
                'count'        => $totCount,
                'rfcs'         => count($uniqueRfcs),
                'xml_count'    => $totXml,
                'cost_mxn'     => round($totCost, 2),
                'avg_cost_mxn' => $totCount > 0 ? round($totCost / $totCount, 2) : 0.0,
            ],
            'breakdown' => [
                'status' => $byStatus,
                'tipo'   => $byTipo,
                'manual' => $byManual,
            ],
            'series' => array_values($series),
        ];
    }

    /**
     * Rango por defecto: últimos 30 días. Cap: 180.
     */
    private function resolveDateRange(Request $request): array
    {
        $maxDays = 180;

        $fromQ = trim((string) $request->query('from', ''));
        $toQ   = trim((string) $request->query('to', ''));

        $tz   = (string) (config('app.timezone') ?: 'UTC');
        $now  = Carbon::now($tz);
        $to   = $now->copy()->endOfDay();
        $from = $now->copy()->subDays(29)->startOfDay();

        try {
            if ($toQ !== '') {
                $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toQ)
                    ? Carbon::createFromFormat('Y-m-d', $toQ, $tz)->endOfDay()
                    : Carbon::parse($toQ, $tz)->endOfDay();
            }
        } catch (\Throwable) {}
        try {
            if ($fromQ !== '') {
                $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromQ)
                    ? Carbon::createFromFormat('Y-m-d', $fromQ, $tz)->startOfDay()
                    : Carbon::parse($fromQ, $tz)->startOfDay();
            }
        } catch (\Throwable) {}

        if ($to->gt($now->copy()->endOfDay())) {
            $to = $now->copy()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $days = (int) max(1, $from->diffInDays($to) + 1);

        if ($days > $maxDays) {
            $from = $to->copy()->subDays($maxDays - 1)->startOfDay();
            $days = $maxDays;
        }

        return [$from, $to, $days];
    }

    private function decodeMeta(mixed $meta): array
    {
        try {
            if (is_array($meta)) return $meta;
            if (is_object($meta)) return (array) $meta;
            if (is_string($meta) && $meta !== '') {
                $tmp = json_decode($meta, true);
                return is_array($tmp) ? $tmp : [];
            }
        } catch (\Throwable) {}
        return [];
    }

    /**
     * Normaliza status para dashboard.
     */
    private function normalizeStatus(string $raw, bool $paid): string
    {
        if ($paid) return 'paid';

        $s = strtolower(trim($raw));
        if ($s === '') return 'pending';

        $s = str_replace(['-', ' '], ['_', '_'], $s);

        if (in_array($s, ['downloaded','descargado','descargada','download','descarga'], true)) return 'downloaded';
        if (in_array($s, ['ready','done','listo','completed','finalizado','finished','terminado','success','ok','ready_to_download'], true)) return 'ready';
        if (in_array($s, ['pending','pendiente','requested','request','solicitud','created','creada','queued','en_cola','queue'], true)) return 'pending';
        if (in_array($s, ['processing','en_proceso','procesando','working','running','in_progress','progress'], true)) return 'processing';
        if (in_array($s, ['expired','expirada','expirado','timeout','timed_out'], true)) return 'expired';
        if (in_array($s, ['error','failed','fail','cancelled','canceled','cancelado','cancelada'], true)) return 'error';
        if (in_array($s, ['paid','pagado','pagada','payment_ok','paid_ok'], true)) return 'paid';

        return 'other';
    }
}
