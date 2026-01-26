<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;



final class SatCartController extends Controller
{
    public function __construct(
        private readonly SatDownloadService $service
    ) {}

    // =============================
    // Helpers internos (SOT Cliente)
    // =============================

    private function clientGuard(): string
    {
        try {
            auth()->guard('cliente');
            return 'cliente';
        } catch (\Throwable) {
            return 'web';
        }
    }

    protected function cu(): ?object
    {
        try {
            $u = auth('cliente')->user();
            if ($u) return $u;
        } catch (\Throwable) {}

        try {
            return auth('web')->user();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cuentaId(): ?string
    {
        $u = $this->cu();
        if (!$u) return null;

        // directo
        $cid = (string) ($u->cuenta_id ?? $u->account_id ?? '');
        if ($cid !== '') return $cid;

        // relación/prop cuenta
        try {
            if (isset($u->cuenta)) {
                $c = $u->cuenta;
                if (is_array($c))  $c = (object) $c;
                if (is_object($c)) {
                    $cid = (string) ($c->id ?? $c->cuenta_id ?? '');
                    if ($cid !== '') return $cid;
                }
            }
        } catch (\Throwable) {}

        // fallbacks por sesión (si existen)
        foreach ([
            'cliente.cuenta_id',
            'cliente.account_id',
            'client.cuenta_id',
            'client.account_id',
            'cuenta_id',
            'account_id',
            'client_cuenta_id',
            'client_account_id',
        ] as $k) {
            $v = (string) session($k, '');
            if ($v !== '') return $v;
        }

        return null;
    }


    protected function cartKey(): string
    {
        $cid = $this->cuentaId() ?: 'guest';
        return 'sat_cart_ids_' . $cid;
    }

    /**
     * Obtiene IDs del carrito desde la sesión.
     * Siempre regresa una colección de strings.
     */
    protected function getCartIds(): Collection
    {
        $raw = Session::get($this->cartKey(), []);
        return collect($raw)
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values();
    }

    protected function saveCartIds(Collection $ids): void
    {
        Session::put($this->cartKey(), $ids->values()->all());
    }

    /**
     * Precios de bóveda fiscal en MXN / mes, por capacidad.
     */
    protected function vaultPricing(): array
    {
        return [
            5    => 249.0,
            10   => 449.0,
            20   => 799.0,
            50   => 1499.0,
            100  => 2499.0,
            500  => 7999.0,
            1024 => 12999.0, // 1 TB
        ];
    }

    /**
     * Normaliza la capacidad solicitada al siguiente escalón permitido.
     */
    protected function normalizeVaultGb(int $gb): int
    {
        $allowed = [5, 10, 20, 50, 100, 500, 1024];
        sort($allowed);

        foreach ($allowed as $val) {
            if ($gb <= $val) return $val;
        }

        return 1024;
    }

    /**
     * RFC que usaremos para el item de bóveda.
     * Intenta usar el primer RFC SAT de la cuenta, si no, XAXX010101000.
     */
    protected function resolveVaultRfc(): string
    {
        $cuentaId = $this->cuentaId();
        if ($cuentaId) {
            $rfc = SatCredential::query()
                ->where('cuenta_id', $cuentaId)
                ->value('rfc');

            if ($rfc) return strtoupper(trim((string) $rfc));
        }

        return 'XAXX010101000';
    }

    /**
     * Crea un registro SatDownload tipo VAULT con el precio según los GB.
     * Estos registros se usan SOLO para el carrito y para sumar cuota,
     * luego los ocultamos en el listado de descargas.
     */
    protected function createVaultDownload(int $gb, string $mode = 'activate'): SatDownload
    {
        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $pricing = $this->vaultPricing();
        $gbNorm  = $this->normalizeVaultGb($gb);
        $price   = $pricing[$gbNorm] ?? $pricing[10];

        $model  = new SatDownload();
        $conn   = $model->getConnectionName();
        $table  = $model->getTable();
        $schema = Schema::connection($conn);

        $dl = new SatDownload();
        $dl->setConnection($conn);

        // ID string seguro
        $dl->id = (string) Str::uuid();

        if ($schema->hasColumn($table, 'cuenta_id')) $dl->cuenta_id = $cuentaId;
        if ($schema->hasColumn($table, 'tipo'))      $dl->tipo      = 'VAULT';
        if ($schema->hasColumn($table, 'status'))    $dl->status    = 'PENDING';
        if ($schema->hasColumn($table, 'rfc'))       $dl->rfc       = $this->resolveVaultRfc();
        if ($schema->hasColumn($table, 'origen'))    $dl->origen    = 'VAULT';

        if ($schema->hasColumn($table, 'vault_gb')) $dl->vault_gb = $gbNorm;

        // Costo (VAULT sí es por tarifa fija)
        if ($schema->hasColumn($table, 'costo'))     $dl->costo     = $price;
        if ($schema->hasColumn($table, 'costo_mxn')) $dl->costo_mxn = $price;

        // Label
        $label = "Bóveda fiscal {$gbNorm} GB (nube)";
        if ($schema->hasColumn($table, 'alias'))  $dl->alias  = $label;
        if (!$schema->hasColumn($table, 'alias') && $schema->hasColumn($table, 'nombre')) $dl->nombre = $label;

        // Peso estimado 0 MB (no aplica)
        if ($schema->hasColumn($table, 'peso_mb')) $dl->peso_mb = 0;

        $dl->save();

        Log::info('[SAT:CART] vault item created', [
            'cuenta_id'   => $cuentaId,
            'user_id'     => $userId,
            'download_id' => $dl->id,
            'gb'          => $gbNorm,
            'price_mxn'   => $price,
            'mode'        => $mode,
        ]);

        return $dl;
    }

    /**
     * Crea el registro VAULT y lo agrega al carrito (sesión) a partir de los GB.
     */
    protected function addVaultItemToCartFromGb(int $gb, string $mode = 'activate'): ?SatDownload
    {
        if ($gb <= 0) return null;

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        try {
            $dl = $this->createVaultDownload($gb, $mode);

            $ids = $this->getCartIds();
            if (!$ids->contains((string) $dl->id)) $ids->push((string) $dl->id);
            $this->saveCartIds($ids);

            Log::info('[SAT:CART] vault item added to cart', [
                'cuenta_id'   => $cuentaId,
                'user_id'     => $userId,
                'download_id' => $dl->id,
                'gb'          => $dl->vault_gb ?? null,
                'mode'        => $mode,
            ]);

            return $dl;
        } catch (\Throwable $e) {
            Log::error('[SAT:CART] error adding vault item to cart', [
                'cuenta_id' => $cuentaId,
                'user_id'   => $userId,
                'gb'        => $gb,
                'mode'      => $mode,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Flujo especial para agregar la bóveda al carrito (type = vault).
     */
    protected function addVaultToCart(Request $request): JsonResponse
    {
        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        Log::info('[SAT:CART] add vault request raw', [
            'payload'   => $request->all(),
            'cuenta_id' => $cuentaId,
            'user_id'   => $userId,
        ]);

        $requestedGb = (int) ($request->input('gb', $request->input('vault_gb', 10)));
        $mode        = (string) ($request->input('action', 'activate') ?: 'activate');

        if ($requestedGb <= 0) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Debes seleccionar una cantidad de Gb válida.',
            ], 422);
        }

        try {
            $dl = $this->addVaultItemToCartFromGb($requestedGb, $mode);
            if (!$dl) throw new \RuntimeException('No se pudo crear el item de bóveda.');

            $cart = $this->buildCartData();

            return response()->json([
                'ok'   => true,
                'msg'  => 'La ampliación de bóveda se agregó a tu carrito SAT.',
                'cart' => [
                    'ids'       => $cart['ids'],
                    'rows'      => $cart['rows'],
                    'count'     => $cart['count'],
                    'subtotal'  => $cart['subtotal'],
                    'weight_mb' => $cart['weight_mb'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:CART] add vault error', [
                'cuenta_id' => $cuentaId,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo agregar la bóveda al carrito. Intenta más tarde o contacta soporte.',
            ], 500);
        }
    }

    /**
     * Marca un conjunto de SatDownload como pagados y registra:
     * - uso de bóveda por ZIP (descargas reales)
     * - cuota de bóveda por items tipo VAULT
     */
    protected function markDownloadsAsPaid(array $downloadIds, ?string $stripeSessionId = null): void
    {
        $downloadIds = array_values(array_unique(array_filter($downloadIds)));
        if (empty($downloadIds)) return;

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $model   = new SatDownload();
        $conn    = $model->getConnectionName();
        $table   = $model->getTable();
        $schema  = Schema::connection($conn);

        $hasIsPaid        = $schema->hasColumn($table, 'is_paid'); // ojo: en tu schema NO existe, queda false
        $hasPaidAt        = $schema->hasColumn($table, 'paid_at');
        $hasStatus        = $schema->hasColumn($table, 'status');
        $hasCuentaId      = $schema->hasColumn($table, 'cuenta_id');
        $hasExpiresAt     = $schema->hasColumn($table, 'expires_at');
        $hasIsExpired     = $schema->hasColumn($table, 'is_expired');
        $hasStripeSessId  = $schema->hasColumn($table, 'stripe_session_id'); // ✅ en tu schema SÍ existe

        $now  = now();
        $rows = collect();

        DB::connection($conn)->transaction(function () use (
            $model,
            $downloadIds,
            $cuentaId,
            $hasIsPaid,
            $hasPaidAt,
            $hasStatus,
            $hasCuentaId,
            $hasExpiresAt,
            $hasIsExpired,
            $now,
            $stripeSessionId,
            $userId,
            &$rows
        ) {
            $query = $model->newQuery()->whereIn('id', $downloadIds);

            if ($hasCuentaId && $cuentaId) {
                $query->where('cuenta_id', $cuentaId);
            }

            /**
             * Idempotencia:
             * - Si hay stripe_session_id en tabla y tenemos $stripeSessionId,
             *   SOLO procesamos filas que NO tengan esa sesión ya aplicada.
             * - Si NO tenemos sessionId, seguimos el flujo clásico.
             */
            if ($hasStripeSessId && $stripeSessionId) {
                $query->where(function ($qq) use ($stripeSessionId) {
                    $qq->whereNull('stripe_session_id')
                    ->orWhere('stripe_session_id', '!=', $stripeSessionId);
                });
            }

            $rowsToUpdate = $query->get();

            // Si no hay nada por actualizar, dejamos $rows vacío para que NO vuelva a sumar bóveda/uso.
            if ($rowsToUpdate->isEmpty()) {
                $rows = collect();
                Log::info('[SAT:CART] marked paid (idempotent no-op)', [
                    'cuenta_id'    => $cuentaId,
                    'user_id'      => $userId,
                    'download_ids' => $downloadIds,
                    'session_id'   => $stripeSessionId,
                    'rows_updated' => [],
                ]);
                return;
            }

            foreach ($rowsToUpdate as $row) {
                if ($hasIsPaid) {
                    $row->is_paid = 1;
                }

                if ($hasPaidAt && empty($row->paid_at)) {
                    $row->paid_at = $now;
                }

                if ($hasExpiresAt) {
                    $row->expires_at = $now->copy()->addDays(15);
                }
                if ($hasIsExpired) {
                    $row->is_expired = 0;
                }

                // Candado por sesión
                if ($hasStripeSessId && $stripeSessionId) {
                    $row->stripe_session_id = $stripeSessionId;
                }

                if ($hasStatus) {
                    $current = strtolower((string) ($row->status ?? ''));
                    if (!in_array($current, ['paid', 'pagado'], true)) {
                        $row->status = 'PAID';
                    }
                }

                $row->save();
            }

            // IMPORTANTE: aquí $rows solo contiene las filas recién actualizadas.
            $rows = $rowsToUpdate;

            Log::info('[SAT:CART] marked paid', [
                'cuenta_id'    => $cuentaId,
                'user_id'      => $userId,
                'download_ids' => $downloadIds,
                'session_id'   => $stripeSessionId,
                'rows_updated' => $rowsToUpdate->pluck('id')->all(),
            ]);


            Log::info('[SAT:CART] marked paid', [
                'cuenta_id'    => $cuentaId,
                'user_id'      => $userId,
                'download_ids' => $downloadIds,
                'session_id'   => $stripeSessionId,
                'rows_updated' => $rowsFound->pluck('id')->all(),
            ]);
        });

        // 1) Uso bóveda por descargas con ZIP
        if ($rows->isNotEmpty()) {
            try {
                $zipRows = $rows->filter(fn ($r) => !empty($r->zip_path));
                if ($zipRows->isNotEmpty()) $this->service->registerVaultUsageForDownloads($zipRows);
            } catch (\Throwable $e) {
                Log::warning('[SAT:CART] Error registrando uso de bóveda tras pago', [
                    'download_ids' => $downloadIds,
                    'ex'           => $e->getMessage(),
                ]);
            }
        }

        // 2) Sumar cuota por items tipo VAULT
        if ($rows->isNotEmpty()) {
            $totalVaultGb = 0;

            foreach ($rows as $row) {
                $tipoLower = strtolower((string) ($row->tipo ?? $row->origen ?? ''));
                if (!str_contains($tipoLower, 'vault') && !str_contains($tipoLower, 'boveda') && !str_contains($tipoLower, 'bóveda')) {
                    continue;
                }

                $gb = 0;

                try {
                    $rowConn  = $row->getConnectionName();
                    $rowTable = $row->getTable();
                    if (Schema::connection($rowConn)->hasColumn($rowTable, 'vault_gb')) {
                        $gb = (int) ($row->vault_gb ?? 0);
                    }
                } catch (\Throwable) {
                    // ignore
                }

                if ($gb <= 0) {
                    $source = (string) ($row->alias ?? $row->nombre ?? '');
                    if (preg_match('/(\d+)\s*gb/i', $source, $m)) $gb = (int) $m[1];
                }

                if ($gb > 0) $totalVaultGb += $gb;
            }

            if ($totalVaultGb > 0) {
                $user   = $this->cu();
                $cuenta = null;

                if ($user && method_exists($user, 'cuenta')) $cuenta = $user->cuenta;

                if ($cuenta) {
                    try {
                        $connCuenta  = $cuenta->getConnectionName();
                        $tableCuenta = $cuenta->getTable();

                        if (Schema::connection($connCuenta)->hasColumn($tableCuenta, 'vault_quota_gb')) {
                            $current = (float) ($cuenta->vault_quota_gb ?? 0);
                            $cuenta->vault_quota_gb = $current + $totalVaultGb;

                            if (
                                Schema::connection($connCuenta)->hasColumn($tableCuenta, 'vault_activated_at') &&
                                empty($cuenta->vault_activated_at)
                            ) {
                                $cuenta->vault_activated_at = now();
                            }

                            $cuenta->save();

                            Log::info('[SAT:CART] vault quota updated', [
                                'cuenta_id'    => $cuenta->id ?? null,
                                'user_id'      => $user ?->id ?? null,
                                'added_gb'     => $totalVaultGb,
                                'new_quota_gb' => $cuenta->vault_quota_gb,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('[SAT:CART] error updating vault quota', [
                            'cuenta_id' => $cuenta->id ?? null,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Construye datos del carrito:
     * - Seguridad: filtra por cuenta si existe columna cuenta_id
     * - Costo: usa costo/costo_mxn existente; si falta, intenta cotizar con SatDownloadService (si hay método)
     */
    protected function buildCartData(): array
    {
        $ids = $this->getCartIds();

        if ($ids->isEmpty()) {
            return [
                'ids'       => [],
                'rows'      => [],
                'count'     => 0,
                'subtotal'  => 0.0,
                'weight_mb' => 0.0,
            ];
        }

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $q = SatDownload::query()->whereIn('id', $ids->all());

        // Filtra por cuenta si es posible
        try {
            $model  = new SatDownload();
            $conn   = $model->getConnectionName();
            $table  = $model->getTable();
            $schema = Schema::connection($conn);
            if ($cuentaId && $schema->hasColumn($table, 'cuenta_id')) {
                $q->where('cuenta_id', $cuentaId);
            }
        } catch (\Throwable) {
            // no-op
        }

        $rows = $q->get();

        // Mantener el orden del carrito (sesión)
        $order = $ids->all();
        $rows  = $rows->sortBy(function ($row) use ($order) {
            $pos = array_search((string) $row->id, $order, true);
            return $pos === false ? 999999 : $pos;
        })->values();

        $subtotal = 0.0;
        $weightMb = 0.0;

        $normalized = [];

        foreach ($rows as $r) {
            // ===== Peso MB =====
            $mb = (float) (
                $r->peso_mb
                ?? $r->peso
                ?? $r->size_mb
                ?? 0
            );

            if ($mb <= 0) {
                $bytes = (float) (
                    $r->size_bytes
                    ?? $r->peso_bytes
                    ?? $r->zip_bytes
                    ?? $r->bytes
                    ?? 0
                );
                if ($bytes > 0) $mb = $bytes / 1024 / 1024;
            }

            if ($mb < 0) $mb = 0.0;

            // ===== Costo (SOT: DB / servicio de cotización) =====
            $cost = (float) (
                $r->costo_mxn
                ?? $r->costo
                ?? $r->costo_total_mxn
                ?? $r->subtotal_mxn
                ?? 0
            );

            // Si falta costo, intentamos cotizar vía servicio SI EXISTE (sin romper deploy)
            if ($cost <= 0) {
                try {
                    if (method_exists($this->service, 'quoteDownloadCostMxn')) {
                        $quoted = $this->service->quoteDownloadCostMxn($r);
                        $cost = (float) ($quoted ?? 0);
                    } elseif (method_exists($this->service, 'calculateDownloadCostMxn')) {
                        $quoted = $this->service->calculateDownloadCostMxn($r);
                        $cost = (float) ($quoted ?? 0);
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }

            $weightMb += $mb;
            $subtotal += $cost;

            $normalized[] = [
                'id'     => (string) $r->id,
                'costo'  => round($cost, 2),
                'peso'   => round($mb, 4),
                'tipo'   => $r->tipo ?? null,
                'rfc'    => $r->rfc ?? null,
                'alias'  => $r->alias ?? ($r->nombre ?? null),
                'status' => $r->status ?? null,
            ];
        }

        $count = count($normalized);

        Log::info('[SAT:CART] summary', [
            'cuenta_id' => $cuentaId,
            'user_id'   => $userId,
            'ids'       => $ids->all(),
            'count'     => $count,
            'total'     => round($subtotal, 2),
        ]);

        return [
            'ids'       => $ids->all(),
            'rows'      => $normalized,
            'count'     => $count,
            'subtotal'  => round($subtotal, 2),
            'weight_mb' => round($weightMb, 4),
        ];
    }

    // =============================
    // 1) Vista /cliente/sat/cart
    // =============================

    public function index(Request $request): View
    {
        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $addedVault = null;

        if ($request->filled('vault_gb')) {
            $rawGb = (int) $request->input('vault_gb', 0);

            Log::info('[SAT:CART] index with vault_gb param', [
                'cuenta_id' => $cuentaId,
                'user_id'   => $userId,
                'vault_gb'  => $rawGb,
            ]);

            if ($rawGb > 0) {
                $addedVault = $this->addVaultItemToCartFromGb($rawGb, 'upgrade');

                if ($addedVault) {
                    Session::flash('success', 'Se agregó la ampliación de bóveda (+' . ($addedVault->vault_gb ?? $rawGb) . ' Gb) a tu carrito SAT.');
                } else {
                    Session::flash('error', 'No se pudo agregar la ampliación de bóveda al carrito. Intenta de nuevo.');
                }
            }
        }

        $cart  = $this->buildCartData();
        $items = $cart['rows'];

        Log::info('[SAT:CART] index', [
            'cuenta_id'      => $cuentaId,
            'user_id'        => $userId,
            'count'          => $cart['count'],
            'total'          => $cart['subtotal'],
            'added_vault_id' => $addedVault?->id ?? null,
        ]);

        return view('cliente.sat.cart', [
            'items'       => $items,
            'cartItems'   => $items,
            'cartCount'   => $cart['count'],
            'cartTotalMb' => $cart['weight_mb'],
            'cartTotal'   => $cart['subtotal'],
            'cartSummary' => [
                'count'     => $cart['count'],
                'subtotal'  => $cart['subtotal'],
                'weight_mb' => $cart['weight_mb'],
            ],
        ]);
    }

    // =============================
    // 2) API /cart/list
    // =============================

    public function list(Request $request): JsonResponse
    {
        $cart = $this->buildCartData();

        return response()->json([
            'ok'   => true,
            'cart' => [
                'ids'       => $cart['ids'],
                'rows'      => $cart['rows'],
                'count'     => $cart['count'],
                'subtotal'  => $cart['subtotal'],
                'weight_mb' => $cart['weight_mb'],
            ],
        ]);
    }

    // =============================
    // 3) API /cart/add
    // =============================

    public function add(Request $request): JsonResponse
    {
        // Flujo especial bóveda
        $type = (string) $request->input('type', '');
        if ($type === 'vault') return $this->addVaultToCart($request);

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $downloadId = trim((string) $request->input('download_id', ''));
        if ($downloadId === '') {
            return response()->json(['ok' => false, 'msg' => 'Falta el ID de descarga.'], 422);
        }

        // Seguridad: valida pertenencia a cuenta si existe columna cuenta_id
        $q = SatDownload::query()->where('id', $downloadId);

        try {
            $model  = new SatDownload();
            $conn   = $model->getConnectionName();
            $table  = $model->getTable();
            $schema = Schema::connection($conn);

            if ($cuentaId && $schema->hasColumn($table, 'cuenta_id')) {
                $q->where('cuenta_id', $cuentaId);
            }
        } catch (\Throwable) {
            // no-op
        }

        $download = $q->first();

        if (!$download) {
            return response()->json([
                'ok'  => false,
                'msg' => 'No se encontró la descarga especificada (o no pertenece a tu cuenta).',
            ], 404);
        }

        $ids = $this->getCartIds();
        if (!$ids->contains($downloadId)) $ids->push($downloadId);

        $this->saveCartIds($ids);

        $cart = $this->buildCartData();

        Log::info('[SAT:CART] add', [
            'cuenta_id' => $cuentaId,
            'user_id'   => $userId,
            'ids_new'   => [$downloadId],
            'ids_all'   => $cart['ids'],
            'count'     => $cart['count'],
            'total'     => $cart['subtotal'],
        ]);

        return response()->json([
            'ok'   => true,
            'msg'  => 'Carrito actualizado.',
            'cart' => [
                'ids'       => $cart['ids'],
                'rows'      => $cart['rows'],
                'count'     => $cart['count'],
                'subtotal'  => $cart['subtotal'],
                'weight_mb' => $cart['weight_mb'],
            ],
        ]);
    }

    // =============================
    // 4) API /cart/remove
    // =============================

    public function remove(Request $request, $id = null)
    {
        $rawInputId = $request->input('download_id');
        $routeId    = $id ?? $request->route('id');
        $queryId    = $request->query('id');

        $qs           = $request->query();
        $firstKey     = is_array($qs) && count($qs) ? array_key_first($qs) : null;
        $firstKeyAsId = $firstKey && !str_starts_with((string) $firstKey, '_') ? $firstKey : null;

        $downloadId = trim((string) (
            $rawInputId ?? $routeId ?? $queryId ?? $firstKeyAsId ?? ''
        ));

        Log::info('[SAT:CART] remove request', [
            'input_id'        => $rawInputId,
            'route_id'        => $routeId,
            'query_id'        => $queryId,
            'first_key'       => $firstKey,
            'download_id_fin' => $downloadId,
            'full_query'      => $qs,
        ]);

        if ($downloadId === '') {
            $msg = 'Falta el ID de descarga.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'msg' => $msg], 422);
            }

            return redirect()->route('cliente.sat.cart.index')->with('error', $msg);
        }

        $ids = $this->getCartIds()->reject(fn ($currentId) => (string) $currentId === $downloadId);
        $this->saveCartIds($ids);

        $cart = $this->buildCartData();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'   => true,
                'msg'  => 'Carrito actualizado.',
                'cart' => [
                    'ids'       => $cart['ids'],
                    'rows'      => $cart['rows'],
                    'count'     => $cart['count'],
                    'subtotal'  => $cart['subtotal'],
                    'weight_mb' => $cart['weight_mb'],
                ],
            ]);
        }

        return redirect()->route('cliente.sat.cart.index')->with('success', 'Paquete eliminado del carrito.');
    }

    // =============================
    // 5) API /cart/clear
    // =============================

    public function clear(Request $request): JsonResponse
    {
        $this->saveCartIds(collect());

        return response()->json([
            'ok'   => true,
            'msg'  => 'Carrito vacío.',
            'cart' => [
                'ids'       => [],
                'rows'      => [],
                'count'     => 0,
                'subtotal'  => 0.0,
                'weight_mb' => 0.0,
            ],
        ]);
    }

    // =============================
    // 6) API /cart/checkout (Stripe)
    // =============================

    public function checkout(Request $request)
    {
        $cart = $this->buildCartData();

        if ($cart['count'] <= 0 || $cart['subtotal'] <= 0) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'msg' => 'Tu carrito está vacío o no tiene importe.'], 422);
            }

            return redirect()->route('cliente.sat.cart.index')->with('error', 'Tu carrito está vacío o no tiene importe.');
        }

        $user = $this->cu();
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'msg' => 'Debes iniciar sesión para pagar.'], 401);
            }

            return redirect()->route('cliente.login')->with('info', 'Debes iniciar sesión para pagar.');
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $amountMx = (int) round($cart['subtotal'] * 100);

            $successUrl = route('cliente.sat.cart.success');
            $cancelUrl  = route('cliente.sat.cart.cancel');

            $session = StripeSession::create([
                'mode'        => 'payment',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => $cancelUrl,
                'line_items'  => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'mxn',
                        'unit_amount'  => $amountMx,
                        'product_data' => [
                            'name'        => 'Descargas SAT (carrito)',
                            'description' => 'Paquetes de descargas SAT desde Pactopia360',
                        ],
                    ],
                ]],
                'metadata' => [
                    'type'      => 'sat_cart',
                    'cuenta_id' => $this->cuentaId(),
                    'user_id'   => $user->id ?? null,
                    'cart_ids'  => implode(',', $cart['ids']),
                ],
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'           => true,
                    'checkout_url' => $session->url,
                    'session_id'   => $session->id,
                ]);
            }

            return redirect()->away($session->url);

        } catch (\Throwable $e) {
            Log::error('[SAT:CART] checkout error', ['error' => $e->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'No se pudo iniciar el pago. Intenta de nuevo o contacta soporte.',
                ], 500);
            }

            return redirect()->route('cliente.sat.cart.index')
                ->with('error', 'No se pudo iniciar el pago. Intenta de nuevo o contacta soporte.');
        }
    }

    // =============================
    // 7) /cart/success y /cart/cancel
    // =============================

    public function success(Request $request)
    {
        $sessionId = trim((string) $request->query('session_id', ''));

        if ($sessionId !== '') {
            try {
            $stripeSecret = (string) config('services.stripe.secret', '');
            if (trim($stripeSecret) === '') {
                Log::error('[SAT:CART] stripe secret missing (services.stripe.secret)');
                if ($request->expectsJson()) {
                    return response()->json([
                        'ok'  => false,
                        'msg' => 'Pago no disponible: falta configuración de Stripe.',
                    ], 503);
                }
                return redirect()->route('cliente.sat.cart.index')
                    ->with('error', 'Pago no disponible: falta configuración de Stripe.');
            }

            Stripe::setApiKey($stripeSecret);


                $session = StripeSession::retrieve($sessionId);

                $status        = $session->status ?? null;
                $paymentStatus = $session->payment_status ?? null;
                $metadata      = $session->metadata ?? null;
                $type          = $metadata->type ?? null;
                $cartIdsRaw    = $metadata->cart_ids ?? '';

                $ids = [];
                if (!empty($cartIdsRaw)) {
                    $ids = preg_split('/[,\s]+/', (string) $cartIdsRaw, -1, PREG_SPLIT_NO_EMPTY);
                }

                $isPaid = (
                    ($status && in_array($status, ['complete', 'completed'], true)) ||
                    ($paymentStatus && strtolower((string) $paymentStatus) === 'paid')
                );

                if ($type === 'sat_cart' && $isPaid && !empty($ids)) {
                    $this->markDownloadsAsPaid($ids, $sessionId);
                }

                Log::info('[SAT:CART] success', [
                    'session_id'     => $sessionId,
                    'status'         => $status,
                    'payment_status' => $paymentStatus,
                    'type'           => $type,
                    'ids'            => $ids,
                    'is_paid'        => $isPaid,
                ]);

            } catch (\Throwable $e) {
                Log::error('[SAT:CART] success stripe error', [
                    'session_id' => $sessionId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->saveCartIds(collect());

        return redirect()
            ->route('cliente.sat.index')
            ->with('success', 'Pago realizado correctamente. Tus paquetes quedarán listos para descarga.');
    }

    public function cancel(Request $request)
    {
        return redirect()
            ->route('cliente.sat.cart.index')
            ->with('info', 'El pago fue cancelado. Puedes intentarlo de nuevo cuando gustes.');
    }
}
