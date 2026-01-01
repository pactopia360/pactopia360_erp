<?php

namespace App\Jobs;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as ECollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;


class SatAutoProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $cuentaId = null,
        public readonly ?string $downloadId = null,
        public readonly int $limit = 50
    ) {
        $this->onQueue('sat');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('job:sat:auto-process'))->dontRelease()];
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(SatDownloadService $svc): void
    {
        $trace = (string)($this->downloadId ?: ($this->cuentaId ?: 'GLOBAL')) . ':' . now()->format('YmdHis');

        $q = SatDownload::query();

        if ($this->downloadId) {
            $q->where(function ($w) {
                $w->where('id', $this->downloadId);

                // si existe download_id en tabla, también permitir buscar por ahí
                try {
                    if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'download_id')) {
                        $w->orWhere('download_id', $this->downloadId);
                    }
                } catch (\Throwable $e) {
                    // no-op
                }
            });
        } else {
            if ($this->cuentaId) {
                $q->where('cuenta_id', $this->cuentaId);
            }

            // IMPORTANTÍSIMO: incluir PAID
            $q->whereIn('status', [
                'PAID', 'paid',
                'pending', 'processing',
                'created', 'requested',
                'ready', 'done', 'listo',
            ]);

            $q->where(function ($w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

            $q->orderByRaw('CASE WHEN zip_path IS NULL OR zip_path = "" THEN 0 ELSE 1 END ASC')
              ->orderBy('created_at', 'asc');
        }

        /** @var ECollection|SatDownload[] $list */
        $list = $q->limit(max(1, (int)$this->limit))->get();

        Log::info('[SatAutoProcessJob] Start', [
            'trace'     => $trace,
            'cuenta_id' => $this->cuentaId,
            'downloadId'=> $this->downloadId,
            'count'     => $list->count(),
        ]);

        $processed = 0;

        foreach ($list as $dl) {
            try {
                // Si no hay force_process y ya existe zip_path, skip (en modo batch)
                if (!$this->downloadId && !$this->hasForceProcess($dl)) {
                    $zip = trim((string)($dl->zip_path ?? ''));
                    if ($zip !== '') {
                        continue;
                    }
                }

                // Solo si está pagada
                if (!$this->isPaid($dl)) {
                    continue;
                }

                $cred = SatCredential::query()
                    ->where('cuenta_id', (string)$dl->cuenta_id)
                    ->where('rfc', strtoupper((string)$dl->rfc))
                    ->first();

                if (!$cred) {
                    Log::warning('[SatAutoProcessJob] No credential', [
                        'trace' => $trace,
                        'dl'    => (string)$dl->id,
                        'rfc'   => (string)$dl->rfc,
                    ]);
                    continue;
                }

                // Marcar intento
                $dl->last_checked_at = now();
                $dl->attempts        = (int)($dl->attempts ?? 0) + 1;
                $dl->save();

                // Si no hay request_id/package_id, intentar arrancar request
                if (empty($dl->request_id) && empty($dl->package_id)) {
                    $new = $this->requestForExisting($svc, $cred, $dl);

                    // Si creó un registro nuevo, enlazar al original (si existe download_id)
                    if ($new instanceof SatDownload && (string)$new->id !== (string)$dl->id) {
                        $this->linkOriginalToNew($dl, $new, $trace);
                        $dl = $new;
                    }
                }

                // Intentar descargar/armar ZIP
                $result = $svc->downloadPackage($cred, $dl, $dl->package_id);

                Log::info('[SatAutoProcessJob] downloadPackage', [
                    'trace'    => $trace,
                    'dl'       => (string)$dl->id,
                    'status'   => $result->status ?? null,
                    'zip_path' => $result->zip_path ?? null,
                ]);

                // Limpiar force_process si ya hay zip_path
                $dlFresh = SatDownload::query()->find((string)$dl->id);
                if ($dlFresh && !empty($dlFresh->zip_path)) {
                    $this->clearForceProcess($dlFresh);
                }

                $processed++;
            } catch (\Throwable $e) {
                try {
                    $dl->error_message = $e->getMessage();
                    $dl->last_checked_at = now();
                    $dl->attempts = (int)($dl->attempts ?? 0) + 1;
                    $dl->save();
                } catch (\Throwable $ignored) {
                    // no-op
                }

                Log::warning('[SatAutoProcessJob] Error', [
                    'trace' => $trace,
                    'dl'    => (string)($dl->id ?? ''),
                    'ex'    => $e->getMessage(),
                ]);
            }
        }

        Log::info('[SatAutoProcessJob] Done', [
            'trace'     => $trace,
            'processed' => $processed,
        ]);
    }

    private function isPaid(SatDownload $dl): bool
    {
        if (!empty($dl->paid_at)) return true;

        $status = strtolower((string)($dl->status ?? ''));
        return in_array($status, ['paid', 'pagado'], true);
    }

    private function hasForceProcess(SatDownload $dl): bool
    {
        $meta = $this->decodeMeta($dl->meta ?? null);
        return (bool)($meta['force_process'] ?? false);
    }

    private function clearForceProcess(SatDownload $dl): void
    {
        $meta = $this->decodeMeta($dl->meta ?? null);
        if (empty($meta['force_process'])) return;

        $meta['force_process'] = false;
        $meta['force_process_cleared_at'] = now()->toDateTimeString();

        try {
            $dl->meta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $dl->save();
        } catch (\Throwable $e) {
            // no-op
        }
    }

    private function decodeMeta($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];

        $s = trim($raw);

        try {
            $j = json_decode($s, true);
            if (is_array($j)) return $j;
        } catch (\Throwable $e) {
            // no-op
        }

        $s2 = str_replace(["\r", "\n", "\t"], ' ', $s);
        try {
            $j2 = json_decode($s2, true);
            if (is_array($j2)) return $j2;
        } catch (\Throwable $e) {
            // no-op
        }

        return [];
    }

    private function requestForExisting(SatDownloadService $svc, SatCredential $cred, SatDownload $dl): ?SatDownload
    {
        $from = Carbon::parse((string)$dl->date_from);
        $to   = Carbon::parse((string)$dl->date_to);

        $tipo = strtolower((string)$dl->tipo);
        if (!in_array($tipo, ['emitidos', 'recibidos'], true)) {
            $tipo = 'emitidos';
        }

        if (method_exists($svc, 'requestPackagesForExisting')) {
            return $svc->requestPackagesForExisting($cred, $dl);
        }

        if (method_exists($svc, 'requestPackages')) {
            try {
                return $svc->requestPackages($cred, $from, $to, $tipo, $dl);
            } catch (\Throwable $e) {
                return $svc->requestPackages($cred, $from, $to, $tipo);
            }
        }

        return null;
    }

    private function linkOriginalToNew(SatDownload $original, SatDownload $new, string $trace): void
    {
        try {
            $schema = Schema::connection('mysql_clientes');

            $payload = [];

            if ($schema->hasColumn('sat_downloads', 'download_id')) {
                $payload['download_id'] = (string)$new->id;
            }

            if ($schema->hasColumn('sat_downloads', 'request_id') && empty($original->request_id) && !empty($new->request_id)) {
                $payload['request_id'] = (string)$new->request_id;
            }

            if ($schema->hasColumn('sat_downloads', 'package_id') && empty($original->package_id) && !empty($new->package_id)) {
                $payload['package_id'] = (string)$new->package_id;
            }

            if (!empty($payload)) {
                SatDownload::query()
                    ->where('id', (string)$original->id)
                    ->update(array_merge($payload, ['updated_at' => now()]));
            }

            Log::info('[SatAutoProcessJob] Linked original->new', [
                'trace'    => $trace,
                'original' => (string)$original->id,
                'new'      => (string)$new->id,
                'payload'  => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SatAutoProcessJob] linkOriginalToNew failed', [
                'trace' => $trace,
                'ex'    => $e->getMessage(),
            ]);
        }
    }

    private function persistLinkOnOriginal(string $originalId, string $newId, array $payload = []): void
{
    $orig = SatDownload::query()->where('id', $originalId)->first();
    if (!$orig) return;

    // Normaliza columnas JSON posibles (meta_json / meta / payload_json / payload)
    $metaRaw    = (string) ($orig->meta_json ?? $orig->meta ?? '');
    $payloadRaw = (string) ($orig->payload_json ?? $orig->payload ?? '');

    $meta    = json_decode($metaRaw, true);
    $payload0 = json_decode($payloadRaw, true);

    if (!is_array($meta)) $meta = [];
    if (!is_array($payload0)) $payload0 = [];

    // Guarda en ambos lados para máxima compatibilidad
    $meta['linked_download_id'] = $newId;
    $meta['new_download_id']    = $newId;
    $meta['download_id']        = $meta['download_id'] ?? $newId;

    $payload0['linked_download_id'] = $newId;
    $payload0['download_id']        = $payload0['download_id'] ?? $newId;

    // Si tu $payload trae request_id etc, lo preservamos
    foreach ($payload as $k => $v) {
        $payload0[$k] = $v;
    }

    // Persistir en las columnas existentes
    if (isset($orig->meta_json)) {
        $orig->meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
    } elseif (isset($orig->meta)) {
        $orig->meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    if (isset($orig->payload_json)) {
        $orig->payload_json = json_encode($payload0, JSON_UNESCAPED_UNICODE);
    } elseif (isset($orig->payload)) {
        $orig->payload = json_encode($payload0, JSON_UNESCAPED_UNICODE);
    }

    // Opcional: marca el original para que el sistema sepa que es “alias”
    if (isset($orig->status) && in_array($orig->status, ['pending','uploading','requested'], true)) {
        $orig->status = 'linked';
    }

    $orig->save();

    Log::info('[SatAutoProcessJob] Persisted original->new link on original', [
        'original' => $originalId,
        'new'      => $newId,
    ]);
}
}
