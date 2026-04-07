<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SatOpsPaymentsController extends Controller
{
    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $status = strtolower(trim((string) $request->query('status', '')));
        $type   = strtolower(trim((string) $request->query('type', '')));
        $range  = strtolower(trim((string) $request->query('range', '')));
        $per    = (int) $request->query('per', 25);

        if ($per < 10) {
            $per = 10;
        }
        if ($per > 100) {
            $per = 100;
        }

        $page = max(1, (int) $request->query('page', 1));

        $connName = 'mysql_clientes';
        $conn     = DB::connection($connName);
        $schema   = Schema::connection($connName);

        if (!$schema->hasTable('sat_downloads')) {
            return view('admin.sat.ops.payments.index', [
                'title'        => 'SAT · Operación · Pagos',
                'rows'         => new LengthAwarePaginator([], 0, $per, $page, [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]),
                'q'            => $q,
                'status'       => $status,
                'type'         => $type,
                'range'        => $range,
                'per'          => $per,
                'kpiTotal'     => 0,
                'kpiPagados'   => 0,
                'kpiPendientes'=> 0,
                'kpiFallidos'  => 0,
                'totalMonto'   => 0,
                'typeOptions'  => collect(),
            ]);
        }

        $hasSatDownloads = function (string $column) use ($schema): bool {
            try {
                return $schema->hasColumn('sat_downloads', $column);
            } catch (\Throwable) {
                return false;
            }
        };

        $base = $conn->table('sat_downloads as sd');

        $select = [
            'sd.id',
            $hasSatDownloads('cuenta_id')        ? 'sd.cuenta_id'        : DB::raw('NULL as cuenta_id'),
            $hasSatDownloads('rfc')              ? 'sd.rfc'              : DB::raw('NULL as rfc'),
            $hasSatDownloads('tipo')             ? 'sd.tipo'             : DB::raw('NULL as tipo'),
            $hasSatDownloads('status')           ? 'sd.status'           : DB::raw('NULL as status'),
            $hasSatDownloads('stripe_session_id')? 'sd.stripe_session_id': DB::raw('NULL as stripe_session_id'),
            $hasSatDownloads('paid_at')          ? 'sd.paid_at'          : DB::raw('NULL as paid_at'),
            $hasSatDownloads('created_at')       ? 'sd.created_at'       : DB::raw('NULL as created_at'),
            $hasSatDownloads('updated_at')       ? 'sd.updated_at'       : DB::raw('NULL as updated_at'),
            $hasSatDownloads('request_id')       ? 'sd.request_id'       : DB::raw('NULL as request_id'),
            $hasSatDownloads('package_id')       ? 'sd.package_id'       : DB::raw('NULL as package_id'),
            $hasSatDownloads('alias')            ? 'sd.alias'            : DB::raw('NULL as alias'),
            $hasSatDownloads('nombre')           ? 'sd.nombre'           : DB::raw('NULL as nombre'),
            $hasSatDownloads('xml_count')        ? 'sd.xml_count'        : DB::raw('NULL as xml_count'),
            $hasSatDownloads('cfdi_count')       ? 'sd.cfdi_count'       : DB::raw('NULL as cfdi_count'),
            $hasSatDownloads('peso_mb')          ? 'sd.peso_mb'          : DB::raw('NULL as peso_mb'),
            $hasSatDownloads('size_mb')          ? 'sd.size_mb'          : DB::raw('NULL as size_mb'),
            $hasSatDownloads('costo')            ? 'sd.costo'            : DB::raw('NULL as costo'),
            $hasSatDownloads('subtotal')         ? 'sd.subtotal'         : DB::raw('NULL as subtotal'),
            $hasSatDownloads('iva')              ? 'sd.iva'              : DB::raw('NULL as iva'),
            $hasSatDownloads('total')            ? 'sd.total'            : DB::raw('NULL as total'),
            $hasSatDownloads('meta')             ? 'sd.meta'             : DB::raw('NULL as meta'),
            $hasSatDownloads('origen')           ? 'sd.origen'           : DB::raw('NULL as origen'),
        ];

        $base->select($select);

        // Solo ventas / pagos relacionados al SAT real
        $base->where(function ($w) {
            $w->whereNotNull('sd.paid_at')
              ->orWhere(function ($w2) {
                  $w2->whereNotNull('sd.stripe_session_id')
                     ->where('sd.stripe_session_id', '<>', '');
              });
        });

        // Excluir bóveda / vault del módulo de descargas SAT
        if ($hasSatDownloads('tipo')) {
            $base->whereRaw("LOWER(COALESCE(sd.tipo,'')) NOT LIKE '%vault%'")
                 ->whereRaw("LOWER(COALESCE(sd.tipo,'')) NOT LIKE '%boveda%'")
                 ->whereRaw("LOWER(COALESCE(sd.tipo,'')) NOT LIKE '%bóveda%'");
        }

        if ($hasSatDownloads('origen')) {
            $base->whereRaw("LOWER(COALESCE(sd.origen,'')) NOT LIKE '%vault%'")
                 ->whereRaw("LOWER(COALESCE(sd.origen,'')) NOT LIKE '%boveda%'")
                 ->whereRaw("LOWER(COALESCE(sd.origen,'')) NOT LIKE '%bóveda%'");
        }

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('sd.id', 'like', '%' . $q . '%')
                  ->orWhere('sd.rfc', 'like', '%' . $q . '%')
                  ->orWhere('sd.cuenta_id', 'like', '%' . $q . '%')
                  ->orWhere('sd.stripe_session_id', 'like', '%' . $q . '%')
                  ->orWhere('sd.request_id', 'like', '%' . $q . '%')
                  ->orWhere('sd.package_id', 'like', '%' . $q . '%')
                  ->orWhere('sd.alias', 'like', '%' . $q . '%')
                  ->orWhere('sd.nombre', 'like', '%' . $q . '%');
            });
        }

        if (in_array($range, ['today', '7d', '30d'], true)) {
            $from = match ($range) {
                'today' => now()->startOfDay(),
                '7d'    => now()->subDays(7)->startOfDay(),
                '30d'   => now()->subDays(30)->startOfDay(),
                default => null,
            };

            if ($from) {
                $base->where(function ($w) use ($from) {
                    $w->where('sd.paid_at', '>=', $from)
                      ->orWhere('sd.updated_at', '>=', $from)
                      ->orWhere('sd.created_at', '>=', $from);
                });
            }
        }

        $base->orderByDesc('sd.paid_at')
             ->orderByDesc('sd.updated_at')
             ->orderByDesc('sd.created_at');

        $rawRows = $base->get();

        $cuentaIds = $rawRows
            ->pluck('cuenta_id')
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->unique()
            ->values();

        $clienteMap = $this->loadClienteMap($cuentaIds);

        $grouped = $rawRows
            ->groupBy(function ($row) {
                $sessionId = trim((string) ($row->stripe_session_id ?? ''));
                return $sessionId !== '' ? 'stripe:' . $sessionId : 'download:' . (string) $row->id;
            })
            ->map(function (Collection $group) use ($clienteMap) {
                $first = $group->first();

                $sessionId = trim((string) ($first->stripe_session_id ?? ''));
                $cuentaId  = trim((string) ($first->cuenta_id ?? ''));
                $cliente   = $clienteMap[$cuentaId]['cliente'] ?? ($cuentaId !== '' ? 'Cuenta ' . $cuentaId : 'Sin cuenta');
                $rfc       = $this->firstNonEmpty($group->pluck('rfc')->all());
                $tipos     = $group->pluck('tipo')->filter(fn ($v) => trim((string) $v) !== '')->unique()->values();
                $tipoTxt   = $tipos->isNotEmpty() ? $tipos->implode(' + ') : 'SAT';

                $fecha = $group
                    ->map(function ($row) {
                        return $this->parseDate(
                            $row->paid_at
                            ?? $row->updated_at
                            ?? $row->created_at
                            ?? null
                        );
                    })
                    ->filter()
                    ->sortDesc()
                    ->first();

                $monto = $group->sum(function ($row) {
                    return $this->resolveRowAmount($row);
                });

                $statuses = $group->map(fn ($row) => $this->normalizePaymentStatus(
                    (string) ($row->status ?? ''),
                    $row->paid_at ?? null,
                    (string) ($row->stripe_session_id ?? '')
                ));

                $estatus = $this->resolveGroupStatus($statuses->all(), $sessionId !== '');

                $referencia = $sessionId !== ''
                    ? $sessionId
                    : ($this->firstNonEmpty([
                        (string) ($first->package_id ?? ''),
                        (string) ($first->request_id ?? ''),
                        (string) ($first->id ?? ''),
                    ]) ?: '—');

                $metodo = $sessionId !== '' ? 'Stripe' : 'Aplicado SAT';
                $descargas = $group->count();
                $xmlTotal  = (int) $group->sum(fn ($row) => (int) ($row->xml_count ?? 0));
                $cfdiTotal = (int) $group->sum(fn ($row) => (int) ($row->cfdi_count ?? 0));

                return [
                    'group_key'      => $sessionId !== '' ? $sessionId : ('download:' . (string) $first->id),
                    'payment_id'      => $sessionId !== '' ? $sessionId : (string) $first->id,
                    'cliente'         => $cliente,
                    'cuenta_id'       => $cuentaId,
                    'rfc'             => strtoupper(trim((string) $rfc)),
                    'tipo'            => $tipoTxt,
                    'monto'           => round((float) $monto, 2),
                    'moneda'          => 'MXN',
                    'metodo'          => $metodo,
                    'estatus'         => $estatus,
                    'referencia'      => $referencia,
                    'fecha'           => $fecha?->format('Y-m-d H:i') ?? '—',
                    'fecha_sort'      => $fecha?->timestamp ?? 0,
                    'descargas'       => $descargas,
                    'xml_total'       => $xmlTotal,
                    'cfdi_total'      => $cfdiTotal,
                    'download_ids'    => $group->pluck('id')->map(fn ($v) => (string) $v)->values()->all(),
                    'stripe_session_id' => $sessionId,
                    'rows_count'      => $group->count(),
                ];
            })
            ->values();

        $allTypeOptions = $grouped
            ->pluck('tipo')
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->unique()
            ->sort()
            ->values();

        if ($type !== '') {
            $grouped = $grouped->filter(function (array $row) use ($type) {
                return str_contains(mb_strtolower((string) $row['tipo']), $type);
            })->values();
        }

        if (in_array($status, ['pagado', 'aplicado', 'pendiente', 'fallido'], true)) {
            $grouped = $grouped->filter(function (array $row) use ($status) {
                return mb_strtolower((string) $row['estatus']) === $status;
            })->values();
        }

        $grouped = $grouped->sortByDesc('fecha_sort')->values();

        $kpiTotal      = $grouped->count();
        $kpiPagados    = $grouped->where('estatus', 'pagado')->count();
        $kpiPendientes = $grouped->where('estatus', 'pendiente')->count();
        $kpiFallidos   = $grouped->where('estatus', 'fallido')->count();
        $totalMonto    = round((float) $grouped->sum('monto'), 2);

        $total = $grouped->count();
        $items = $grouped->slice(($page - 1) * $per, $per)->values();

        $rows = new LengthAwarePaginator(
            $items,
            $total,
            $per,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.sat.ops.payments.index', [
            'title'          => 'SAT · Operación · Pagos',
            'rows'           => $rows,
            'q'              => $q,
            'status'         => $status,
            'type'           => $type,
            'range'          => $range,
            'per'            => $per,
            'kpiTotal'       => $kpiTotal,
            'kpiPagados'     => $kpiPagados,
            'kpiPendientes'  => $kpiPendientes,
            'kpiFallidos'    => $kpiFallidos,
            'totalMonto'     => $totalMonto,
            'typeOptions'    => $allTypeOptions,
        ]);
    }

    /**
     * @param Collection<int, mixed> $cuentaIds
     * @return array<string, array{cliente:string}>
     */
    private function loadClienteMap(Collection $cuentaIds): array
    {
        $map = [];

        if ($cuentaIds->isEmpty()) {
            return $map;
        }

        $schema = Schema::connection('mysql_clientes');

        if (!$schema->hasTable('cuentas_cliente')) {
            return $map;
        }

        $has = function (string $column) use ($schema): bool {
            try {
                return $schema->hasColumn('cuentas_cliente', $column);
            } catch (\Throwable) {
                return false;
            }
        };

        $select = [
            'id',
            $has('razon_social')     ? 'razon_social'     : DB::raw('NULL as razon_social'),
            $has('nombre_comercial') ? 'nombre_comercial' : DB::raw('NULL as nombre_comercial'),
        ];

        $rows = DB::connection('mysql_clientes')
            ->table('cuentas_cliente')
            ->select($select)
            ->whereIn('id', $cuentaIds->all())
            ->get();

        foreach ($rows as $row) {
            $id = trim((string) ($row->id ?? ''));
            if ($id === '') {
                continue;
            }

            $cliente = trim((string) ($row->razon_social ?? ''));
            if ($cliente === '') {
                $cliente = trim((string) ($row->nombre_comercial ?? ''));
            }
            if ($cliente === '') {
                $cliente = 'Cuenta ' . $id;
            }

            $map[$id] = [
                'cliente' => $cliente,
            ];
        }

        return $map;
    }

    private function resolveRowAmount(object $row): float
    {
        $total    = (float) ($row->total ?? 0);
        $subtotal = (float) ($row->subtotal ?? 0);
        $iva      = (float) ($row->iva ?? 0);
        $costo    = (float) ($row->costo ?? 0);

        if ($total > 0) {
            return $total;
        }

        if (($subtotal + $iva) > 0) {
            return $subtotal + $iva;
        }

        if ($subtotal > 0) {
            return $subtotal;
        }

        if ($costo > 0) {
            return $costo;
        }

        return 0.0;
    }

    private function normalizePaymentStatus(string $status, mixed $paidAt, string $stripeSessionId): string
    {
        $status = strtolower(trim($status));

        if ($paidAt !== null && trim((string) $paidAt) !== '') {
            return trim($stripeSessionId) !== '' ? 'pagado' : 'aplicado';
        }

        if (in_array($status, ['error', 'failed', 'fallido', 'canceled', 'cancelado'], true)) {
            return 'fallido';
        }

        if (in_array($status, ['pending', 'pendiente', 'processing', 'requested', 'created'], true)) {
            return 'pendiente';
        }

        if (in_array($status, ['paid', 'pagado'], true)) {
            return 'pagado';
        }

        return 'pendiente';
    }

    /**
     * @param array<int, string> $statuses
     */
    private function resolveGroupStatus(array $statuses, bool $hasStripeSession): string
    {
        $statuses = array_values(array_unique(array_filter($statuses)));

        if (in_array('fallido', $statuses, true)) {
            return 'fallido';
        }

        if (in_array('pendiente', $statuses, true) && !in_array('pagado', $statuses, true) && !in_array('aplicado', $statuses, true)) {
            return 'pendiente';
        }

        if (in_array('pagado', $statuses, true)) {
            return 'pagado';
        }

        if (in_array('aplicado', $statuses, true)) {
            return $hasStripeSession ? 'pagado' : 'aplicado';
        }

        return 'pendiente';
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        try {
            if ($value instanceof Carbon) {
                return $value;
            }

            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                return null;
            }

            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}