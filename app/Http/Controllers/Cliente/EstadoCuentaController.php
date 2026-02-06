<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Throwable;

class EstadoCuentaController extends Controller
{
    public function index()
    {
        $user = Auth::guard('web')->user();
        abort_if(!$user, 403);

        $cliConn = 'mysql_clientes';
        $admConn = 'mysql_admin';

        // ===== Cuenta espejo (mysql_clientes.cuentas_cliente)
        $cuentaId       = (string) ($user->cuenta_id ?? '');
        $cuenta         = null;
        $rfc            = null;
        $adminAccountId = null;

        if ($cuentaId !== '' && Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
            $select = ['id', 'rfc_padre'];

            foreach (['estado_cuenta', 'is_blocked', 'admin_account_id', 'plan_actual', 'modo_cobro', 'razon_social'] as $c) {
                if (Schema::connection($cliConn)->hasColumn('cuentas_cliente', $c)) {
                    $select[] = $c;
                }
            }

            $cuenta = DB::connection($cliConn)
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->select($select)
                ->first();

            if ($cuenta) {
                $rfc = $cuenta->rfc_padre ?? null;

                if (isset($cuenta->admin_account_id) && is_numeric($cuenta->admin_account_id)) {
                    $adminAccountId = (int) $cuenta->admin_account_id;
                }
            }
        }

        // ===== Cuenta admin (mysql_admin.accounts)
        $account = null;

        if (Schema::connection($admConn)->hasTable('accounts')) {
            $emailCol   = $this->colAdminEmail();
            $accSelect  = ['id', DB::raw("{$emailCol} as email")];

            foreach ([
                'plan',
                'billing_cycle',
                'next_invoice_date',
                'email_verified_at',
                'phone_verified_at',
                'is_blocked',
                'estado_cuenta',
                'razon_social',
            ] as $c) {
                if (Schema::connection($admConn)->hasColumn('accounts', $c)) {
                    $accSelect[] = $c;
                }
            }

            if ($adminAccountId) {
                $account = DB::connection($admConn)
                    ->table('accounts')
                    ->where('id', $adminAccountId)
                    ->select($accSelect)
                    ->first();
            }

            if (!$account && $rfc) {
                // Buscar por RFC en distintas columnas
                foreach (['rfc', 'rfc_padre', 'tax_id'] as $rc) {
                    if (Schema::connection($admConn)->hasColumn('accounts', $rc)) {
                        $acc = DB::connection($admConn)
                            ->table('accounts')
                            ->select($accSelect)
                            ->whereRaw('UPPER(' . $rc . ')=?', [strtoupper((string) $rfc)])
                            ->first();

                        if ($acc) {
                            $account        = $acc;
                            $adminAccountId = (int) $acc->id;
                            break;
                        }
                    }
                }

                // Fallback: algunos esquemas usan el RFC como id
                if (!$account) {
                    $acc = DB::connection($admConn)
                        ->table('accounts')
                        ->select($accSelect)
                        ->where('id', $rfc)
                        ->first();

                    if ($acc) {
                        $account        = $acc;
                        $adminAccountId = (int) $acc->id;
                    }
                }
            }
        }

        // ===== Movimientos (mysql_admin.estados_cuenta) -> para balance global
        $movs = collect();

        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $cols = $this->existingColumns($admConn, 'estados_cuenta', [
                'id',
                'periodo',
                'concepto',
                'detalle',
                'cargo',
                'abono',
                'saldo',
                'moneda',
                'created_at',
                'updated_at',
                'account_id',
                'cuenta_id',
                'rfc',
            ]);

            $orderCol = $this->firstExisting($admConn, 'estados_cuenta', ['periodo', 'created_at', 'id']);

            $q = DB::connection($admConn)
                ->table('estados_cuenta')
                ->select($cols)
                ->orderByDesc($orderCol)
                ->limit(120);

            // Vinculación flexible
            if ($adminAccountId && Schema::connection($admConn)->hasColumn('estados_cuenta', 'account_id')) {
                $q->where('account_id', $adminAccountId);
            } elseif ($adminAccountId && Schema::connection($admConn)->hasColumn('estados_cuenta', 'cuenta_id')) {
                $q->where('cuenta_id', $adminAccountId);
            } elseif ($rfc && Schema::connection($admConn)->hasColumn('estados_cuenta', 'rfc')) {
                $q->whereRaw('UPPER(rfc)=?', [strtoupper((string) $rfc)]);
            } else {
                // Sin forma clara de enlazar: evita traer datos ajenos
                $q->whereRaw('1=0');
            }

            $movs = $q->get();
        }

        // ===== Balance calculado
        $balance = null;

        if ($movs->count()) {
            if (isset($movs[0]->saldo) && $movs[0]->saldo !== null) {
                $balance = (float) $movs[0]->saldo;
            } else {
                $cargo   = (float) $movs->sum(fn ($r) => (float) ($r->cargo ?? 0));
                $abono   = (float) $movs->sum(fn ($r) => (float) ($r->abono ?? 0));
                $balance = $cargo - $abono;
            }
        }

        // ===== Estado (bloqueo)
        $estadoBloqueado = false;
        $estadoTexto     = null;

        if ($cuenta && isset($cuenta->estado_cuenta)) {
            $estadoTexto     = (string) $cuenta->estado_cuenta;
            $estadoBloqueado = in_array(
                Str::lower($estadoTexto),
                ['bloqueada', 'bloqueada_pago', 'suspendida', 'inactiva', 'pendiente_pago'],
                true
            );
        } elseif ($account && isset($account->estado_cuenta)) {
            $estadoTexto = (string) $account->estado_cuenta;
        }

        if (!$estadoBloqueado && $cuenta && isset($cuenta->is_blocked)) {
            $estadoBloqueado = ((int) $cuenta->is_blocked) === 1;
        }
        if (!$estadoBloqueado && $account && isset($account->is_blocked)) {
            $estadoBloqueado = ((int) $account->is_blocked) === 1;
        }

        // ===== Resumen tipo Home/Perfil para que el layout pinte PRO correctamente
        $summary = $this->buildAccountSummary();

        /**
         * ============================================================
         * ✅ SOT BILLING: construir "statements" como Admin
         * ============================================================
         * Objetivo:
         * - Si Enero está pagado en Admin, aquí debe verse PAGADO.
         * - El siguiente mes a cobrar debe ser FEBRERO (no Enero).
         */
        [$statements, $billingMeta] = $this->buildStatementsSot(
            $admConn,
            $cliConn,
            $adminAccountId,
            $rfc
        );

        // Enriquecer summary con periodos canónicos para que la vista no invente desde "now()"
        if (!empty($billingMeta['current_period_ym'])) {
            $summary['current_period_ym'] = $billingMeta['current_period_ym'];
        }
        if (!empty($billingMeta['last_paid_ym'])) {
            $summary['last_paid_ym'] = $billingMeta['last_paid_ym'];
        }
        if (!empty($billingMeta['next_due_ym'])) {
            $summary['next_due_ym'] = $billingMeta['next_due_ym'];
        }

        // accountInfo: agrega account_id para que la vista muestre ID Cuenta correctamente
        $accountInfo = [
            'email'            => $account->email ?? null,
            'email_verified'   => isset($account->email_verified_at) && !empty($account->email_verified_at),
            'phone_verified'   => isset($account->phone_verified_at) && !empty($account->phone_verified_at),
            'plan'             => $account->plan ?? ($cuenta->plan_actual ?? null),
            'billing_cycle'    => $account->billing_cycle ?? ($cuenta->modo_cobro ?? null),
            'next_invoice_at'  => $account->next_invoice_date ?? null,
            'estado_cuenta'    => $estadoTexto,
            'is_blocked'       => $estadoBloqueado,
            'admin_account_id' => $adminAccountId,
            'account_id'       => $adminAccountId, // 👈 clave que tu blade sí usa
            'rfc'              => $rfc,
            'razon_social'     => $account->razon_social ?? ($cuenta->razon_social ?? null),
        ];

        return view('cliente.estado_cuenta', [
            'movs'       => $movs,
            'balance'    => $balance,
            'account'    => $accountInfo,
            'cuenta'     => $cuenta,
            'summary'    => $summary,
            'statements' => $statements, // 👈 tu blade espera esto
        ]);
    }

    /**
     * Construye estados de cuenta (billing statements) usando SOT:
     * - billing_statements si existe (admin o clientes)
     * - crea "virtual" del siguiente mes si no existe aún
     */
    private function buildStatementsSot(
        string $admConn,
        string $cliConn,
        ?int $adminAccountId,
        ?string $rfc
    ): array {
        $rows = collect();
        $meta = [
            'last_paid_ym'    => null,
            'next_due_ym'     => null,
            'current_period_ym' => null,
        ];

        // 1) Detectar tabla billing_statements en admin o clientes
        $srcConn  = null;
        $srcTable = null;

        foreach ([$admConn, $cliConn] as $conn) {
            if (Schema::connection($conn)->hasTable('billing_statements')) {
                $srcConn  = $conn;
                $srcTable = 'billing_statements';
                break;
            }
        }

        // 2) Si NO hay billing_statements, no podemos alinear status; regresamos vacío (la UI mostrará "Sin estados")
        if (!$srcConn || !$srcTable) {
            return [collect(), $meta];
        }

        // 3) Columnas flexibles
        $colPeriod = $this->firstExisting($srcConn, $srcTable, ['period_ym', 'periodo', 'period', 'period_key']);
        $colStatus = $this->firstExisting($srcConn, $srcTable, ['status', 'estado', 'payment_status']);
        $colTotal  = $this->firstExisting($srcConn, $srcTable, ['total', 'amount_total', 'monto_total', 'amount']);
        $colStart  = $this->firstExisting($srcConn, $srcTable, ['period_start', 'starts_at', 'start_date', 'from_date']);
        $colEnd    = $this->firstExisting($srcConn, $srcTable, ['period_end', 'ends_at', 'end_date', 'to_date']);
        $colRfc    = Schema::connection($srcConn)->hasColumn($srcTable, 'rfc') ? 'rfc' : null;

        // pdf url/path columnas (si existen)
        $colPdfUrl = $this->firstExisting($srcConn, $srcTable, ['pdf_url', 'pdf_public_url']);
        $colPdf    = $this->firstExisting($srcConn, $srcTable, ['pdf_path', 'pdf_file', 'pdf']);

        // account_id/cuenta_id
        $linkCol = null;
        if (Schema::connection($srcConn)->hasColumn($srcTable, 'account_id') && $adminAccountId) {
            $linkCol = 'account_id';
        } elseif (Schema::connection($srcConn)->hasColumn($srcTable, 'cuenta_id') && $adminAccountId) {
            $linkCol = 'cuenta_id';
        }

        // 4) Traer últimos statements (sin traer data ajena)
        $q = DB::connection($srcConn)->table($srcTable)->limit(24);

        if ($linkCol && $adminAccountId) {
            $q->where($linkCol, $adminAccountId);
        } elseif ($colRfc && $rfc) {
            $q->whereRaw('UPPER(' . $colRfc . ')=?', [strtoupper((string) $rfc)]);
        } else {
            $q->whereRaw('1=0');
        }

        // ordenar por periodo si existe, si no por id
        if ($colPeriod && Schema::connection($srcConn)->hasColumn($srcTable, $colPeriod)) {
            $q->orderByDesc($colPeriod);
        } else {
            $q->orderByDesc('id');
        }

        $dbRows = collect($q->get());

        // 5) Normalizar a estructura que tu blade ya entiende
        $normalized = $dbRows->map(function ($r) use ($colPeriod, $colStatus, $colTotal, $colStart, $colEnd, $colPdfUrl, $colPdf) {
            $ym = $this->normalizeYm((string) data_get($r, $colPeriod, ''));
            $st = $this->normalizeStatus((string) data_get($r, $colStatus, 'pending'));
            $tt = (float) data_get($r, $colTotal, 0);

            $ps = data_get($r, $colStart);
            $pe = data_get($r, $colEnd);

            // Si no vienen fechas, derivarlas del ym
            if (!$ps || !$pe) {
                try {
                    if ($ym) {
                        $c = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
                        $ps = $ps ?: $c->copy()->startOfMonth()->toDateString();
                        $pe = $pe ?: $c->copy()->endOfMonth()->toDateString();
                    }
                } catch (\Throwable $e) {
                    // noop
                }
            }

            $pdfUrl = '';
            $u = (string) data_get($r, $colPdfUrl, '');
            if ($u) $pdfUrl = $u;

            // si solo hay path y no url, lo dejamos vacío (tu UI ya maneja fallback)
            $path = (string) data_get($r, $colPdf, '');
            if (!$pdfUrl && $path && Str::startsWith($path, ['http://', 'https://'])) {
                $pdfUrl = $path;
            }

            $label = $this->monthLabelFromYm($ym);

            return [
                'period_ym'    => $ym,
                'month_label'  => $label ?: ($ym ?: '—'),
                'period_start' => $ps,
                'period_end'   => $pe,
                'status'       => $st,
                'total'        => $tt,
                'currency'     => 'MXN',
                'pdf_url'      => $pdfUrl,
                'pay_url'      => '', // tu blade prefiere route('cliente.billing.pay', period)
                'invoice'      => [
                    'pdf_url' => '',
                    'xml_url' => '',
                    'zip_url' => '',
                ],
            ];
        })->filter(fn($r) => !empty($r['period_ym']))->values();

        // 6) Determinar último pagado real
        $lastPaid = $normalized->first(function ($r) {
            $s = strtolower((string) ($r['status'] ?? ''));
            return Str::contains($s, ['paid', 'pagado']);
        });

        $lastPaidYm = $lastPaid['period_ym'] ?? null;
        $meta['last_paid_ym'] = $lastPaidYm;

        // 7) Calcular siguiente por pagar (next_due_ym)
        $nextDueYm = null;
        if ($lastPaidYm && preg_match('/^\d{4}\-\d{2}$/', $lastPaidYm)) {
            try {
                $nextDueYm = Carbon::createFromFormat('Y-m', $lastPaidYm)->addMonth()->format('Y-m');
            } catch (\Throwable $e) {
                $nextDueYm = null;
            }
        }

        // Si no hay lastPaid, usa el primer pending como "current"
        if (!$nextDueYm) {
            $firstPending = $normalized->first(function ($r) {
                $s = strtolower((string) ($r['status'] ?? ''));
                return Str::contains($s, ['pending', 'pendiente', 'unpaid', 'por pagar']);
            });
            $nextDueYm = $firstPending['period_ym'] ?? null;
        }

        $meta['next_due_ym'] = $nextDueYm;
        $meta['current_period_ym'] = $nextDueYm ?: ($lastPaidYm ?: null);

        // 8) Construir lista final SIN DUPLICADOS:
        // - incluye lastPaid (si existe)
        // - incluye nextDue (si existe; si no existe en DB, crea virtual pending)
        $final = collect();

        $pushUnique = function(array $row) use (&$final) {
            $ym = (string)($row['period_ym'] ?? '');
            if (!$ym) return;
            if ($final->firstWhere('period_ym', $ym)) return;
            $final->push($row);
        };

        if ($lastPaidYm) {
            $pushUnique($normalized->firstWhere('period_ym', $lastPaidYm));
        }

        if ($nextDueYm) {
            $exists = $normalized->firstWhere('period_ym', $nextDueYm);
            if ($exists) {
                $pushUnique($exists);
            } else {
                // Virtual: siguiente mes pendiente (total=0, tu UI puede aplicar mensualidad fija si la tienes en summary)
                $pushUnique($this->makeVirtualPending($nextDueYm));
            }
        }

        // Además: agrega otros cercanos (por ejemplo 2 hacia atrás y 2 hacia adelante) para UX
        foreach ($normalized as $r) {
            $pushUnique($r);
        }

        // Ordenar DESC por period_ym
        $final = $final->sortByDesc('period_ym')->values();

        return [$final, $meta];
    }

    private function makeVirtualPending(string $ym): array
    {
        $ps = null; $pe = null;
        try {
            $c  = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
            $ps = $c->copy()->startOfMonth()->toDateString();
            $pe = $c->copy()->endOfMonth()->toDateString();
        } catch (\Throwable $e) {
            // noop
        }

        return [
            'period_ym'    => $ym,
            'month_label'  => $this->monthLabelFromYm($ym) ?: $ym,
            'period_start' => $ps,
            'period_end'   => $pe,
            'status'       => 'pending',
            'total'        => 0.0,
            'currency'     => 'MXN',
            'pdf_url'      => '',
            'pay_url'      => '',
            'invoice'      => [
                'pdf_url' => '',
                'xml_url' => '',
                'zip_url' => '',
            ],
        ];
    }

    private function normalizeYm(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') return null;

        // ya viene Y-m
        if (preg_match('/^\d{4}\-\d{2}$/', $v)) return $v;

        // viene como YYYYMM o YYYY/MM
        if (preg_match('/^(\d{4})[\/\-]?(\d{2})$/', $v, $m)) {
            return $m[1] . '-' . $m[2];
        }

        // viene como fecha completa
        try {
            $c = Carbon::parse($v);
            return $c->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeStatus(string $s): string
    {
        $x = Str::lower(trim($s));

        if ($x === '') return 'pending';

        if (Str::contains($x, ['paid', 'pagado', 'payment_succeeded', 'succeeded', 'ok'])) return 'paid';
        if (Str::contains($x, ['overdue', 'vencido', 'expired'])) return 'overdue';
        if (Str::contains($x, ['empty', 'sin_generar', 'no_generado', 'sin generar'])) return 'empty';
        if (Str::contains($x, ['pending', 'pendiente', 'unpaid', 'por pagar'])) return 'pending';

        // default conservador
        return $x;
    }

    private function monthLabelFromYm(?string $ym): ?string
    {
        if (!$ym || !preg_match('/^\d{4}\-\d{2}$/', $ym)) return null;
        try {
            $m = Carbon::createFromFormat('Y-m', $ym)->locale('es')->translatedFormat('F Y');
            return Str::title($m);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ===== Helpers existentes =====

    private function colAdminEmail(): string
    {
        try {
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'correo_contacto')) {
                return 'correo_contacto';
            }
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'email')) {
                return 'email';
            }
        } catch (Throwable $e) {
            // noop
        }

        return 'email';
    }

    private function existingColumns(string $conn, string $table, array $wanted): array
    {
        $out = [];

        foreach ($wanted as $c) {
            try {
                if (Schema::connection($conn)->hasColumn($table, $c)) {
                    $out[] = $c;
                }
            } catch (Throwable $e) {
                // noop
            }
        }

        return $out ?: ['id'];
    }

    private function firstExisting(string $conn, string $table, array $cands): string
    {
        foreach ($cands as $c) {
            try {
                if (Schema::connection($conn)->hasColumn($table, $c)) {
                    return $c;
                }
            } catch (Throwable $e) {
                // noop
            }
        }

        return 'id';
    }

    /**
     * Resumen de cuenta igualado a la lógica de Home/Perfil
     * para que el header sepa si la cuenta es PRO.
     */
    private function buildAccountSummary(): array
    {
        $u      = Auth::guard('web')->user();
        $cuenta = $u?->cuenta;

        if (!$cuenta) {
            return [
                'razon'        => (string) ($u->nombre ?? $u->email ?? '—'),
                'plan'         => 'free',
                'is_pro'       => false,
                'cycle'        => 'mensual',
                'next_invoice' => null,
                'estado'       => null,
                'blocked'      => false,
                'balance'      => 0.0,
                'space_total'  => 512.0,
                'space_used'   => 0.0,
                'space_pct'    => 0.0,
                'timbres'      => 0,
                'admin_id'     => null,
            ];
        }

        $admConn = 'mysql_admin';

        $planKey = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($planKey === 'FREE' ? 10 : 0));
        $saldoMx = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ($u->nombre ?? $u->email ?? '—');

        $adminId = $cuenta->admin_account_id ?? null;
        $rfc     = $cuenta->rfc_padre ?? null;

        if (!$adminId && $rfc && Schema::connection($admConn)->hasTable('accounts') && Schema::connection($admConn)->hasColumn('accounts', 'rfc')) {
            $acc = DB::connection($admConn)
                ->table('accounts')
                ->select('id')
                ->whereRaw('UPPER(rfc)=?', [strtoupper((string) $rfc)])
                ->first();

            if ($acc) {
                $adminId = (int) $acc->id;
            }
        }

        $acc = null;

        if ($adminId && Schema::connection($admConn)->hasTable('accounts')) {
            $cols = ['id'];

            foreach ([
                'plan',
                'billing_cycle',
                'next_invoice_date',
                'estado_cuenta',
                'is_blocked',
                'razon_social',
                'email',
                'email_verified_at',
                'phone_verified_at',
            ] as $c) {
                if (Schema::connection($admConn)->hasColumn('accounts', $c)) {
                    $cols[] = $c;
                }
            }

            $acc = DB::connection($admConn)
                ->table('accounts')
                ->select($cols)
                ->where('id', $adminId)
                ->first();
        }

        $balance = $saldoMx;

        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $linkCol = null;
            $linkVal = null;

            if (Schema::connection($admConn)->hasColumn('estados_cuenta', 'account_id') && $adminId) {
                $linkCol = 'account_id';
                $linkVal = $adminId;
            } elseif (Schema::connection($admConn)->hasColumn('estados_cuenta', 'cuenta_id') && $adminId) {
                $linkCol = 'cuenta_id';
                $linkVal = $adminId;
            } elseif (Schema::connection($admConn)->hasColumn('estados_cuenta', 'rfc') && $rfc) {
                $linkCol = 'rfc';
                $linkVal = strtoupper((string) $rfc);
            }

            if ($linkCol !== null) {
                $orderCol = Schema::connection($admConn)->hasColumn('estados_cuenta', 'periodo')
                    ? 'periodo'
                    : (Schema::connection($admConn)->hasColumn('estados_cuenta', 'created_at') ? 'created_at' : 'id');

                $last = DB::connection($admConn)
                    ->table('estados_cuenta')
                    ->where($linkCol, $linkVal)
                    ->orderByDesc($orderCol)
                    ->first();

                if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                    $balance = (float) $last->saldo;
                } else {
                    $hasCargo = Schema::connection($admConn)->hasColumn('estados_cuenta', 'cargo');
                    $hasAbono = Schema::connection($admConn)->hasColumn('estados_cuenta', 'abono');

                    if ($hasCargo || $hasAbono) {
                        $cargo = $hasCargo
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol, $linkVal)->sum('cargo')
                            : 0.0;

                        $abono = $hasAbono
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol, $linkVal)->sum('abono')
                            : 0.0;

                        $balance = $cargo - $abono;
                    }
                }
            }
        }

        $spaceTotal = (float) ($cuenta->espacio_total_mb ?? 512);
        $spaceUsed  = (float) ($cuenta->espacio_usado_mb ?? 0);
        $spacePct   = $spaceTotal > 0 ? min(100, round(($spaceUsed / $spaceTotal) * 100, 1)) : 0;

        $plan    = strtolower((string) ($acc->plan ?? $planKey));
        $cycle   = $acc->billing_cycle ?? ($cuenta->modo_cobro ?? 'mensual');
        $estado  = $acc->estado_cuenta ?? ($cuenta->estado_cuenta ?? null);
        $blocked = (bool) (($acc->is_blocked ?? 0) || ($cuenta->is_blocked ?? 0));

        return [
            'razon'        => (string) ($acc->razon_social ?? $razon),
            'plan'         => $plan,
            'is_pro'       => in_array($plan, ['pro', 'premium', 'empresa', 'business'], true) || Str::startsWith($plan, 'pro'),
            'cycle'        => $cycle,
            'next_invoice' => $acc->next_invoice_date ?? null,
            'estado'       => $estado,
            'blocked'      => $blocked,
            'balance'      => $balance,
            'space_total'  => $spaceTotal,
            'space_used'   => $spaceUsed,
            'space_pct'    => $spacePct,
            'timbres'      => $timbres,
            'admin_id'     => $adminId,
        ];
    }

        /**
     * Normaliza periodo a Y-m desde valores tipo:
     * - 2026-01
     * - 202601
     * - 2026/01
     * - 2026-01-31
     */
    private function normalizeYmLoose(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;

        if (preg_match('/^\d{4}\-\d{2}$/', $v)) return $v;

        if (preg_match('/^(\d{4})[\/\-]?(\d{2})$/', $v, $m)) {
            return $m[1] . '-' . $m[2];
        }

        try {
            return Carbon::parse($v)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A partir de movimientos (estados_cuenta) arma un mapa por periodo:
     * ['2026-01' => ['cargo'=>..., 'abono'=>..., 'saldo'=>...]]
     */
    private function buildMovPeriodMap($movs): array
    {
        $map = [];

        foreach ($movs ?? [] as $r) {
            $ym = $this->normalizeYmLoose((string)($r->periodo ?? ''));
            if (!$ym) continue;

            $cargo = (float)($r->cargo ?? 0);
            $abono = (float)($r->abono ?? 0);

            if (!isset($map[$ym])) {
                $map[$ym] = ['cargo' => 0.0, 'abono' => 0.0];
            }

            $map[$ym]['cargo'] += $cargo;
            $map[$ym]['abono'] += $abono;
        }

        // saldo por periodo = cargo - abono (lo pendiente de ese mes)
        foreach ($map as $ym => $x) {
            $map[$ym]['saldo'] = (float)$x['cargo'] - (float)$x['abono'];
        }

        return $map;
    }

    /**
     * Determina si un periodo está "pagado" usando movimientos:
     * - si cargo>0 y saldo<=0 => pagado
     */
    private function periodIsPaidFromMov(array $movPeriodMap, string $ym): bool
    {
        if (!isset($movPeriodMap[$ym])) return false;
        $cargo = (float)($movPeriodMap[$ym]['cargo'] ?? 0);
        $saldo = (float)($movPeriodMap[$ym]['saldo'] ?? 0);

        if ($cargo <= 0.00001) return false;
        return $saldo <= 0.00001;
    }

}
