<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatDownloadsRequestService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatDownloadsRequestService
{
    public function __construct(
        private readonly SatClientContext $ctx,
        private readonly SatDownloadService $service,
    ) {}

    public function monthsSpanInclusive(Carbon $from, Carbon $to): int
    {
        if ($from->gt($to)) [$from, $to] = [$to, $from];

        $a = $from->copy()->startOfMonth();
        $b = $to->copy()->startOfMonth();

        $months = (($b->year - $a->year) * 12) + ($b->month - $a->month) + 1;
        return max(1, (int) $months);
    }

    public function isProPlanForCuenta($cuenta): bool
    {
        if (!$cuenta) return false;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $raw = (string) ($cuenta->plan_actual ?? $cuenta->plan ?? $cuenta->plan_name ?? 'FREE');
        $raw = trim($raw);
        if ($raw === '') return false;

        $plan = strtoupper($raw);
        $plan = str_replace(['_', '-'], ' ', $plan);
        $plan = preg_replace('/\s+/', ' ', $plan) ?: $plan;

        if (in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true)) return true;

        foreach (['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'] as $needle) {
            if (str_starts_with($plan, $needle)) return true;
        }

        return false;
    }

    public function parseManualFlag(mixed $v): bool
    {
        try {
            if (is_bool($v)) return $v;
            if (is_numeric($v)) return ((int) $v) === 1;
            if (is_string($v)) {
                $vv = strtolower(trim($v));
                return in_array($vv, ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
            }
        } catch (\Throwable) {}
        return false;
    }

    /**
     * Ejecuta la creación de solicitudes sat_downloads (emitidos/recibidos) y retorna payload listo para JSON.
     * Lanza excepciones sólo para errores “fatales” (p.ej. falta tabla), no por errores por RFC individual.
     */
    public function createRequests(
        Request $request,
        string $trace,
        object $user,
        string $cuentaId,
        ?object $cuentaObj
    ): array {
        $data = $request->validate([
            'tipo'   => 'required|string|in:emitidos,recibidos,ambos',
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'rfcs'   => 'required|array|min:1',
            'rfcs.*' => 'required|string|min:12|max:13',
            'manual' => 'nullable',
        ]);

        $isManual = $this->parseManualFlag($data['manual'] ?? null);

        try {
            $from = Carbon::parse((string) $data['from'])->startOfDay();
            $to   = Carbon::parse((string) $data['to'])->endOfDay();
        } catch (\Throwable) {
            return [
                'ok' => false,
                'status' => 422,
                'msg' => 'Rango de fechas inválido.',
                'trace_id' => $trace,
            ];
        }

        $isProPlan = $this->isProPlanForCuenta($cuentaObj);

        if (!$isProPlan && !$isManual) {
            $months = $this->monthsSpanInclusive($from, $to);
            if ($months > 1) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'msg' => 'En FREE sólo puedes solicitar hasta 1 mes por ejecución.',
                    'code' => 'FREE_MONTH_LIMIT',
                    'trace_id' => $trace,
                    'meta' => [
                        'months' => $months,
                        'from' => $from->toDateString(),
                        'to' => $to->toDateString(),
                    ],
                ];
            }
        }

        $rfcs = array_values(array_unique(array_filter(array_map(
            static fn($r) => strtoupper(trim((string) $r)),
            (array) ($data['rfcs'] ?? [])
        ), static fn($r) => $r !== '')));

        if (!count($rfcs)) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Debes seleccionar al menos un RFC.', 'trace_id' => $trace];
        }

        $credList = SatCredential::on('mysql_clientes')
            ->where('cuenta_id', $cuentaId)
            ->whereIn(DB::raw('UPPER(rfc)'), $rfcs)
            ->get();

        $credByRfc = $credList->keyBy(fn($c) => strtoupper(trim((string) ($c->rfc ?? ''))));

        $validRfcs = $credList->filter(function ($c) {
            $estatusRaw = strtolower((string) ($c->estatus ?? ''));

            return
                !empty($c->validado ?? null)
                || !empty($c->validated_at ?? null)
                || !empty($c->has_files ?? null)
                || !empty($c->has_csd ?? null)
                || !empty($c->cer_path ?? null)
                || !empty($c->key_path ?? null)
                || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);
        })->pluck('rfc')->map(fn($r) => strtoupper((string) $r))->unique()->values()->all();

        if (!count($validRfcs)) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Debes seleccionar al menos un RFC validado (con CSD cargado).', 'trace_id' => $trace];
        }

        $tipo  = (string) $data['tipo'];
        $tipos = $tipo === 'ambos' ? ['emitidos', 'recibidos'] : [$tipo];

        $dlModel = new SatDownload();
        $dlModel->setConnection('mysql_clientes');

        $conn   = $dlModel->getConnectionName() ?? 'mysql_clientes';
        $table  = $dlModel->getTable();
        $schema = Schema::connection($conn);

        if (!$schema->hasTable($table)) {
            return ['ok' => false, 'status' => 422, 'msg' => 'No existe la tabla sat_downloads.', 'trace_id' => $trace];
        }

        $has = static function (string $col) use ($schema, $table): bool {
            try { return $schema->hasColumn($table, $col); } catch (\Throwable) { return false; }
        };

        $hasIsManual = $has('is_manual') || $has('manual');
        $hasMeta     = $has('meta');

        $colCuenta = $has('cuenta_id') ? 'cuenta_id' : ($has('account_id') ? 'account_id' : null);
        if (!$colCuenta) {
            return ['ok' => false, 'status' => 422, 'msg' => 'La tabla sat_downloads no tiene cuenta_id ni account_id.', 'trace_id' => $trace];
        }

        $created = [];

        foreach ($validRfcs as $rfc) {
            foreach ($tipos as $tipoSat) {
                try {
                    $dl = new SatDownload();
                    $dl->setConnection('mysql_clientes');

                    $dl->{$colCuenta} = $cuentaId;

                    if ($has('rfc'))  $dl->rfc  = $rfc;
                    if ($has('tipo')) $dl->tipo = $tipoSat;

                    if ($has('status'))     $dl->status     = 'pending';
                    if ($has('estado'))     $dl->estado     = 'REQUESTED';
                    if ($has('sat_status')) $dl->sat_status = 'REQUESTED';

                    if ($has('desde'))     $dl->desde     = $from->toDateString();
                    if ($has('hasta'))     $dl->hasta     = $to->toDateString();
                    if ($has('date_from')) $dl->date_from = $from->toDateString();
                    if ($has('date_to'))   $dl->date_to   = $to->toDateString();

                    if ($has('user_id') && isset($user->id)) $dl->user_id = $user->id;

                    if ($isManual) {
                        if ($hasIsManual) {
                            if ($has('is_manual')) $dl->is_manual = 1;
                            if ($has('manual'))    $dl->manual    = 1;
                        }

                        if ($hasMeta) {
                            $meta = [];

                            try {
                                $credForMeta = $credByRfc->get(strtoupper($rfc));
                                $raw = $credForMeta?->meta ?? null;

                                $tmp = [];
                                if (is_array($raw)) $tmp = $raw;
                                elseif (is_string($raw) && $raw !== '') {
                                    $j = json_decode($raw, true);
                                    if (is_array($j)) $tmp = $j;
                                }

                                foreach (['source','external_email','invite_id','note'] as $k) {
                                    if (array_key_exists($k, $tmp)) $meta[$k] = $tmp[$k];
                                }
                            } catch (\Throwable) {}

                            $meta['is_manual']     = true;
                            $meta['manual']        = true;
                            $meta['source']        = 'manual_ui';
                            $meta['requested_at']  = now()->toDateTimeString();
                            $meta['trace_id']      = $trace;

                            $dl->meta = $meta;
                        }
                    }

                    $dl->save();
                    $created[] = (string) $dl->id;

                    try {
                        if (method_exists($this->service, 'createRequest')) {
                            $satRef = $this->service->createRequest($dl);
                            if ($satRef && $has('sat_request_id')) {
                                $dl->sat_request_id = $satRef;
                                $dl->save();
                            }
                        }
                    } catch (\Throwable $e) {
                        if ($has('estado'))     $dl->estado     = 'ERROR';
                        if ($has('sat_status')) $dl->sat_status = 'ERROR';
                        if ($has('status'))     $dl->status     = 'ERROR';
                        if ($has('error_msg'))  $dl->error_msg  = mb_substr((string) $e->getMessage(), 0, 900);
                        $dl->save();
                    }
                } catch (\Throwable $e) {
                    Log::error('[SAT:request] Error creando registro de descarga', [
                        'trace_id'  => $trace,
                        'cuenta_id' => $cuentaId,
                        'user_id'   => $user->id ?? null,
                        'rfc'       => $rfc,
                        'tipo'      => $tipoSat,
                        'manual'    => $isManual ? 1 : 0,
                        'msg'       => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!count($created)) {
            return [
                'ok' => false,
                'status' => 500,
                'msg' => 'No se pudieron crear las solicitudes SAT. Revisa el log.',
                'trace_id' => $trace,
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'trace_id' => $trace,
            'count' => count($created),
            'manual' => $isManual ? 1 : 0,
            'ids' => $created,
        ];
    }
}
