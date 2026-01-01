<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\SatCredential;
use App\Services\Sat\SatDownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

class SatCartController extends Controller
{
    public function __construct(
        private readonly SatDownloadService $service
    ) {}

    // =============================
    // Helpers internos
    // =============================

    protected function cu()
    {
        return auth('web')->user();
    }

    protected function cuentaId(): ?string
    {
        $u = $this->cu();
        if (!$u) {
            return null;
        }
        return (string)($u->cuenta_id ?? $u->account_id ?? null);
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
    protected function getCartIds(): \Illuminate\Support\Collection
    {
        $raw = Session::get($this->cartKey(), []);
        return collect($raw)
            ->filter()
            ->map(fn ($v) => (string)$v)
            ->unique()
            ->values();
    }

    protected function saveCartIds(\Illuminate\Support\Collection $ids): void
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
            if ($gb <= $val) {
                return $val;
            }
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

            if ($rfc) {
                return strtoupper(trim($rfc));
            }
        }

        return 'XAXX010101000';
    }

    /**
     * Crea un registro SatDownload tipo VAULT con el precio según los GB.
     * OJO: estos registros se usan SOLO para el carrito y para sumar cuota,
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
        $dl->id = (string) Str::uuid();

        if ($schema->hasColumn($table, 'cuenta_id')) {
            $dl->cuenta_id = $cuentaId;
        }
        if ($schema->hasColumn($table, 'tipo')) {
            $dl->tipo = 'VAULT';
        }
        if ($schema->hasColumn($table, 'status')) {
            $dl->status = 'PENDING';
        }
        if ($schema->hasColumn($table, 'rfc')) {
            $dl->rfc = $this->resolveVaultRfc();
        }
        if ($schema->hasColumn($table, 'origen')) {
            $dl->origen = 'VAULT';
        }

        // Guardar GB contratados si existe la columna
        if ($schema->hasColumn($table, 'vault_gb')) {
            $dl->vault_gb = $gbNorm;
        }

        // Costo
        if ($schema->hasColumn($table, 'costo')) {
            $dl->costo = $price;
        }
        if ($schema->hasColumn($table, 'costo_mxn')) {
            $dl->costo_mxn = $price;
        }

        // Alias / nombre bonito para que se vea bien en el carrito
        $label = "Bóveda fiscal {$gbNorm} GB (nube)";
        if ($schema->hasColumn($table, 'alias')) {
            $dl->alias = $label;
        } elseif ($schema->hasColumn($table, 'nombre')) {
            $dl->nombre = $label;
        }

        // Peso estimado 0 MB (no aplica para cuota ni peso)
        if ($schema->hasColumn($table, 'peso_mb')) {
            $dl->peso_mb = 0;
        }

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
     * Devuelve el SatDownload creado o null si no se pudo.
     */
    protected function addVaultItemToCartFromGb(int $gb, string $mode = 'activate'): ?SatDownload
    {
        if ($gb <= 0) {
            return null;
        }

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        try {
            // 1) Crear registro tipo VAULT en sat_downloads
            $dl = $this->createVaultDownload($gb, $mode);

            // 2) Agregar al carrito en sesión
            $ids = $this->getCartIds();
            if (!$ids->contains((string) $dl->id)) {
                $ids->push((string) $dl->id);
            }
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
     * Se usa cuando el front manda /cart/add vía AJAX.
     */
    protected function addVaultToCart(Request $request): JsonResponse
    {
        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $rawPayload = $request->all();

        Log::info('[SAT:CART] add vault request raw', [
            'payload'   => $rawPayload,
            'cuenta_id' => $cuentaId,
            'user_id'   => $userId,
        ]);

        // Aceptamos tanto "gb" como "vault_gb" por si viene de distintos lados
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

            if (!$dl) {
                throw new \RuntimeException('No se pudo crear el item de bóveda.');
            }

            // Recalcular carrito
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
     * Marca un conjunto de SatDownload como pagados **y**
     * registra el uso de bóveda en base al tamaño del ZIP (solo descargas con ZIP).
     * Además, si hay items tipo VAULT, suma la cuota de almacenamiento a la cuenta.
     */
    protected function markDownloadsAsPaid(array $downloadIds, ?string $stripeSessionId = null): void
    {
        $downloadIds = array_values(array_unique(array_filter($downloadIds)));
        if (empty($downloadIds)) {
            return;
        }

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $model   = new SatDownload();
        $conn    = $model->getConnectionName();
        $table   = $model->getTable();
        $schema  = Schema::connection($conn);

        $hasIsPaid    = $schema->hasColumn($table, 'is_paid');
        $hasPaidAt    = $schema->hasColumn($table, 'paid_at');
        $hasStatus    = $schema->hasColumn($table, 'status');
        $hasCuentaId  = $schema->hasColumn($table, 'cuenta_id');
        $hasExpiresAt = $schema->hasColumn($table, 'expires_at');
        $hasIsExpired = $schema->hasColumn($table, 'is_expired');

        $now  = now();
        $rows = collect(); // descargas actualizadas

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

            /** @var \Illuminate\Support\Collection $rowsFound */
            $rowsFound = $query->get();

            foreach ($rowsFound as $row) {
                if ($hasIsPaid) {
                    $row->is_paid = 1;
                }

                if ($hasPaidAt && empty($row->paid_at)) {
                    $row->paid_at = $now;
                }

                // Vigencia (solo si aplican columnas)
                if ($hasExpiresAt) {
                    $row->expires_at = $now->copy()->addDays(15);
                }
                if ($hasIsExpired) {
                    $row->is_expired = 0;
                }

                if ($hasStatus) {
                    $current = strtolower((string)($row->status ?? ''));
                    if (!in_array($current, ['paid', 'pagado'], true)) {
                        $row->status = 'PAID';
                    }
                }

                $row->save();
            }

            $rows = $rowsFound;

            Log::info('[SAT:CART] marked paid', [
                'cuenta_id'    => $cuentaId,
                'user_id'      => $userId,
                'download_ids' => $downloadIds,
                'session_id'   => $stripeSessionId,
                'rows_updated' => $rowsFound->pluck('id')->all(),
            ]);
        });

        // === 1) Actualizar uso de bóveda SOLO para descargas con ZIP ===
        if ($rows->isNotEmpty()) {
            try {
                $zipRows = $rows->filter(function ($r) {
                    $zip = $r->zip_path ?? null;
                    return $zip !== null && $zip !== '';
                });

                if ($zipRows->isNotEmpty()) {
                    $this->service->registerVaultUsageForDownloads($zipRows);
                }
            } catch (\Throwable $e) {
                Log::warning('[SAT:CART] Error registrando uso de bóveda tras pago', [
                    'download_ids' => $downloadIds,
                    'ex'           => $e->getMessage(),
                ]);
            }
        }

        // === 2) Sumar cuota de bóveda para items tipo VAULT (no son descargas) ===
        if ($rows->isNotEmpty()) {
            $totalVaultGb = 0;

            foreach ($rows as $row) {
                $tipoLower = strtolower((string)($row->tipo ?? $row->origen ?? ''));

                // Detectar registros de bóveda
                if (
                    str_contains($tipoLower, 'vault') ||
                    str_contains($tipoLower, 'boveda') ||
                    str_contains($tipoLower, 'bóveda')
                ) {
                    $gb = 0;

                    // 1) Si existe columna vault_gb, usamos ese valor
                    try {
                        $rowConn  = $row->getConnectionName();
                        $rowTable = $row->getTable();
                        if (Schema::connection($rowConn)->hasColumn($rowTable, 'vault_gb')) {
                            $gb = (int)($row->vault_gb ?? 0);
                        }
                    } catch (\Throwable $e) {
                        // ignoramos, intentamos parsear alias
                    }

                    // 2) Si no hay vault_gb, intentamos parsear desde alias ("... 10 GB ...")
                    if ($gb <= 0) {
                        $source = (string)($row->alias ?? $row->nombre ?? '');
                        if (preg_match('/(\d+)\s*gb/i', $source, $m)) {
                            $gb = (int)$m[1];
                        }
                    }

                    if ($gb > 0) {
                        $totalVaultGb += $gb;
                    }
                }
            }

            if ($totalVaultGb > 0) {
                $user   = $this->cu();
                $cuenta = null;

                // Intentar obtener la cuenta desde la relación del usuario
                if ($user && method_exists($user, 'cuenta')) {
                    $cuenta = $user->cuenta;
                }

                if ($cuenta) {
                    try {
                        $connCuenta = $cuenta->getConnectionName();
                        $tableCuenta = $cuenta->getTable();

                        if (Schema::connection($connCuenta)->hasColumn($tableCuenta, 'vault_quota_gb')) {
                            $current = (float)($cuenta->vault_quota_gb ?? 0);
                            $cuenta->vault_quota_gb = $current + $totalVaultGb;

                            // Activar fecha de activación si existe el campo y no estaba
                            if (
                                Schema::connection($connCuenta)->hasColumn($tableCuenta, 'vault_activated_at') &&
                                empty($cuenta->vault_activated_at)
                            ) {
                                $cuenta->vault_activated_at = now();
                            }

                            $cuenta->save();

                            Log::info('[SAT:CART] vault quota updated', [
                                'cuenta_id'     => $cuenta->id ?? null,
                                'user_id'       => $userId,
                                'added_gb'      => $totalVaultGb,
                                'new_quota_gb'  => $cuenta->vault_quota_gb,
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

    // =============================
    // Descarga ZIP (usa servicio y encola bóveda)
    // =============================

    public function downloadZip(string $id): StreamedResponse
    {
        $user   = $this->cu();
        $cuenta = $this->cuentaId(); // simplificamos

        /** @var SatDownload|null $dl */
        $dl = SatDownload::query()
            ->where('id', $id)
            ->where('cuenta_id', $cuenta)
            ->firstOrFail();

        if (!$this->service->canClientDownload($dl)) {
            abort(403, 'La descarga no está disponible.');
        }

        $zipRel = $dl->zip_path ?? '';
        $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
        $disk     = \Storage::disk($diskName);

        if (!$zipRel || !$disk->exists($zipRel)) {
            abort(404, 'Archivo ZIP no encontrado.');
        }

        $dl->downloaded_at = now();
        $dl->save();

        try {
            $this->service->enqueueVaultIngestion($dl);
        } catch (\Throwable $e) {
            \Log::warning('[SAT] No se pudo encolar ingreso a bóveda', [
                'download_id' => $dl->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $fileName = $dl->zip_name ?? ('sat_'.$dl->id.'.zip');

        return new StreamedResponse(function () use ($disk, $zipRel) {
            $stream = $disk->readStream($zipRel);
            fpassthru($stream);
        }, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Carga los SatDownload del carrito y arma resumen.
     * IMPORTANT: Si el costo no existe en BD (0/null), lo calcula igual que el front:
     * costo = peso_mb * price_per_mb (default 100 MXN/MB).
     */
    protected function buildCartData(): array
    {
        $ids = $this->getCartIds();

        if ($ids->isEmpty()) {
            return [
                'ids'        => [],
                'rows'       => [],
                'count'      => 0,
                'subtotal'   => 0.0,
                'weight_mb'  => 0.0,
            ];
        }

        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        // Precio por MB y default de MB si no hay peso/bytes
        $pricePerMb = (float) config('services.sat.download.price_per_mb', 100.00);
        $defaultMb  = (float) config('services.sat.download.default_size_mb', 1.0);

        $q = SatDownload::query()->whereIn('id', $ids->all());

        // Evita cruzar cuentas si existe cuenta_id en tabla
        try {
            $model   = new SatDownload();
            $conn    = $model->getConnectionName();
            $table   = $model->getTable();
            $schema  = Schema::connection($conn);
            if ($cuentaId && $schema->hasColumn($table, 'cuenta_id')) {
                $q->where('cuenta_id', $cuentaId);
            }
        } catch (\Throwable $e) {
            // si falla schema check, no rompemos
        }

        /** @var \Illuminate\Support\Collection $rows */
        $rows = $q->get();

        // mantener orden según la sesión
        $order = $ids->all();
        $rows  = $rows->sortBy(function ($row) use ($order) {
            $pos = array_search((string) $row->id, $order, true);
            return $pos === false ? 999999 : $pos;
        })->values();

        $subtotal = 0.0;
        $weightMb = 0.0;

        // Normaliza rows a algo consumible por tu JS (id, costo, peso, etc.)
        $normalized = [];

        foreach ($rows as $r) {
            // ===== 1) Peso MB =====
            $mb = (float) (
                $r->peso_mb
                ?? $r->peso
                ?? $r->size_mb
                ?? 0
            );

            if ($mb <= 0) {
                // fallback por bytes si existen (ajusta nombres si tus columnas difieren)
                $bytes = (float) (
                    $r->size_bytes
                    ?? $r->peso_bytes
                    ?? $r->zip_bytes
                    ?? 0
                );
                if ($bytes > 0) {
                    $mb = $bytes / 1024 / 1024;
                }
            }

            if ($mb <= 0) {
                $mb = $defaultMb; // default para no quedar en 0
            }

            // ===== 2) Costo =====
            $cost = (float) (
                $r->costo_mxn
                ?? $r->costo
                ?? $r->costo_total_mxn
                ?? $r->subtotal_mxn
                ?? 0
            );

            // fallback: mismo criterio que UI
            if ($cost <= 0) {
                $cost = round($mb * $pricePerMb, 2);
            }

            $weightMb += $mb;
            $subtotal += $cost;

            $normalized[] = [
                'id'    => (string) $r->id,
                'costo' => $cost,
                'peso'  => round($mb, 4),
                'tipo'  => $r->tipo ?? null,
                'rfc'   => $r->rfc ?? null,
                'alias' => $r->alias ?? ($r->nombre ?? null),
                'status'=> $r->status ?? null,
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
            'ids'        => $ids->all(),
            'rows'       => $normalized, // <-- OJO: ahora es array normalizado, no modelos
            'count'      => $count,
            'subtotal'   => round($subtotal, 2),
            'weight_mb'  => round($weightMb, 4),
        ];
    }


    // Limpieza de expirados
    protected function cleanupExpiredHistory(): void
    {
        $now    = now();
        $cuenta = $this->cuentaId() ?? null;

        $model  = new SatDownload();
        $conn   = $model->getConnectionName();
        $table  = $model->getTable();
        $schema = Schema::connection($conn);

        $hasIsPaid    = $schema->hasColumn($table, 'is_paid');
        $hasExpiresAt = $schema->hasColumn($table, 'expires_at');
        $hasIsExpired = $schema->hasColumn($table, 'is_expired');
        $hasCuentaId  = $schema->hasColumn($table, 'cuenta_id');

        $base = $model->newQuery();
        if ($hasCuentaId && $cuenta) {
            $base->where('cuenta_id', $cuenta);
        }

        $deletedUnpaid = 0;
        $deletedPaid   = 0;

        $qUnpaid = (clone $base)->where('created_at', '<', $now->copy()->subHours(12));

        if ($hasIsPaid) {
            $qUnpaid->where(function ($q) {
                $q->whereNull('is_paid')
                  ->orWhere('is_paid', 0);
            });
        }

        $deletedUnpaid = $qUnpaid->delete();

        if ($hasIsPaid && $hasExpiresAt) {
            $qPaid = (clone $base)
                ->where('is_paid', 1)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now);

            $deletedPaid = $qPaid->delete();
        }

        if ($hasIsExpired && $hasExpiresAt) {
            (clone $base)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->update(['is_expired' => 1]);
        }

        Log::info('[SAT:cleanupExpiredHistory] Descargas expiradas eliminadas', [
            'cuenta_id' => $cuenta,
            'deleted'   => $deletedUnpaid + $deletedPaid,
            'unpaid'    => $deletedUnpaid,
            'paid'      => $deletedPaid,
        ]);
    }

        // =============================
    // 1) Vista /cliente/sat/cart
    // =============================

    public function index(Request $request): View
    {
        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        // ===== 1) Manejar ?vault_gb=XX (venimos desde el dashboard SAT) =====
        $addedVault = null;

        if ($request->filled('vault_gb')) {
            $rawGb = (int) $request->input('vault_gb', 0);

            Log::info('[SAT:CART] index with vault_gb param', [
                'cuenta_id' => $cuentaId,
                'user_id'   => $userId,
                'vault_gb'  => $rawGb,
            ]);

            if ($rawGb > 0) {
                // Lo tratamos como "upgrade" (ampliar bóveda existente)
                $addedVault = $this->addVaultItemToCartFromGb($rawGb, 'upgrade');

                if ($addedVault) {
                    // Mensaje para que el usuario vea que sí se agregó algo
                    Session::flash(
                        'success',
                        'Se agregó la ampliación de bóveda (+' .
                        ($addedVault->vault_gb ?? $rawGb) .
                        ' Gb) a tu carrito SAT.'
                    );
                } else {
                    Session::flash(
                        'error',
                        'No se pudo agregar la ampliación de bóveda al carrito. Intenta de nuevo.'
                    );
                }
            }
        }

        // ===== 2) Construir resumen de carrito normalmente =====
        $cart = $this->buildCartData();

        Log::info('[SAT:CART] index', [
            'cuenta_id'      => $cuentaId,
            'user_id'        => $userId,
            'count'          => $cart['count'],
            'total'          => $cart['subtotal'],
            'added_vault_id' => $addedVault?->id ?? null,
        ]);

        $items = $cart['rows'];

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
    // 2) API /cart/list  (JS sync)
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
        // Flujo especial para bóveda fiscal (type = vault)
        $type = (string) $request->input('type', '');
        if ($type === 'vault') {
            return $this->addVaultToCart($request);
        }

        // Flujo normal: agregar una descarga SAT ya existente por ID
        $cuentaId = $this->cuentaId();
        $userId   = optional($this->cu())->id ?? null;

        $downloadId = (string)$request->input('download_id', '');
        if (!$downloadId) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Falta el ID de descarga.',
            ], 422);
        }

        $download = SatDownload::query()
            ->where('id', $downloadId)
            ->first();

        if (!$download) {
            return response()->json([
                'ok'  => false,
                'msg' => 'No se encontró la descarga especificada.',
            ], 404);
        }

        $ids = $this->getCartIds();

        if (!$ids->contains($downloadId)) {
            $ids->push($downloadId);
        }

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

        $qs         = $request->query();
        $firstKey   = is_array($qs) && count($qs) ? array_key_first($qs) : null;
        $firstKeyAsId = $firstKey && !str_starts_with($firstKey, '_') ? $firstKey : null;

        $downloadId = (string)(
            $rawInputId
            ?? $routeId
            ?? $queryId
            ?? $firstKeyAsId
            ?? ''
        );
        $downloadId = trim($downloadId);

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
                return response()->json([
                    'ok'  => false,
                    'msg' => $msg,
                ], 422);
            }

            return redirect()
                ->route('cliente.sat.cart.index')
                ->with('error', $msg);
        }

        $ids = $this->getCartIds()->reject(
            fn ($currentId) => (string)$currentId === $downloadId
        );

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

        return redirect()
            ->route('cliente.sat.cart.index')
            ->with('success', 'Paquete eliminado del carrito.');
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
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Tu carrito está vacío o no tiene importe.',
                ], 422);
            }

            return redirect()
                ->route('cliente.sat.cart.index')
                ->with('error', 'Tu carrito está vacío o no tiene importe.');
        }

        $user = $this->cu();
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Debes iniciar sesión para pagar.',
                ], 401);
            }

            return redirect()
                ->route('cliente.login')
                ->with('info', 'Debes iniciar sesión para pagar.');
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
            Log::error('[SAT:CART] checkout error', [
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'No se pudo iniciar el pago. Intenta de nuevo o contacta soporte.',
                ], 500);
            }

            return redirect()
                ->route('cliente.sat.cart.index')
                ->with('error', 'No se pudo iniciar el pago. Intenta de nuevo o contacta soporte.');
        }
    }

    // =============================
    // 7) /cart/success y /cart/cancel
    // =============================

    public function success(Request $request)
    {
        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId !== '') {
            try {
                Stripe::setApiKey(config('services.stripe.secret'));

                /** @var \Stripe\Checkout\Session $session */
                $session = StripeSession::retrieve($sessionId);

                $status        = $session->status ?? null;
                $paymentStatus = $session->payment_status ?? null;
                $metadata      = $session->metadata ?? null;
                $type          = $metadata->type ?? null;
                $cartIdsRaw    = $metadata->cart_ids ?? '';

                $ids = [];
                if (!empty($cartIdsRaw)) {
                    $ids = preg_split('/[,\s]+/', (string)$cartIdsRaw, -1, PREG_SPLIT_NO_EMPTY);
                }

                $isPaid = (
                    ($status && in_array($status, ['complete', 'completed'], true)) ||
                    ($paymentStatus && strtolower($paymentStatus) === 'paid')
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
