<?php

namespace App\Jobs;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\CuentaCliente;
use App\Notifications\CfdiCanceled;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SatAutoDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;
    public string $trace;

    /** Límite de descargas a revisar por RFC en una corrida */
    public int $perRfcLimit;

    public function __construct(int $perRfcLimit = 50)
    {
        $this->trace       = (string) Str::ulid();
        $this->perRfcLimit = max(1, $perRfcLimit);
        $this->onQueue('sat');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('job:sat:auto-download'))->dontRelease()];
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(SatDownloadService $service): void
    {
        $today = now()->toDateString();

        /** @var ECollection|SatCredential[] $creds */
        $creds = SatCredential::query()
            ->join('cuentas_cliente as c', 'c.id', '=', 'sat_credentials.cuenta_id')
            ->where('sat_credentials.auto_download', true)
            ->whereNotNull('sat_credentials.validated_at')
            ->where('c.plan_actual', 'PRO')
            ->select('sat_credentials.*')
            ->get();

        Log::info('[SatAutoDownloadJob] Start', [
            'trace' => $this->trace,
            'creds' => $creds->count()
        ]);

        foreach ($creds as $cred) {
            $cuentaId = (string) $cred->cuenta_id;
            $rfc      = strtoupper((string) $cred->rfc);

            // 1) Genera solicitudes del día (emitidos/recibidos) si no existen
            $this->ensureRequestsForToday($service, $cred, $today, 'emitidos');
            $this->ensureRequestsForToday($service, $cred, $today, 'recibidos');

            // 2) Descarga paquetes listos (sin zip_path)
            $ready = SatDownload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('rfc', $rfc)
                ->whereIn('status', ['ready', 'done', 'listo'])
                ->where(fn($q) => $q->whereNull('zip_path')->orWhere('zip_path', ''))
                ->orderBy('created_at')
                ->limit($this->perRfcLimit)
                ->get();

            foreach ($ready as $dl) {
                $this->attemptDownload($service, $dl);
            }

            // 3) Forzar procesamiento de compras pagadas marcadas con meta.force_process
            //    Esto cubre tu caso: status=PAID pero request_id/package_id null.
            $forced = SatDownload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('rfc', $rfc)
                ->whereIn('status', ['PAID', 'paid', 'pending', 'processing', 'created', 'requested'])
                ->where(fn($q) => $q->whereNull('zip_path')->orWhere('zip_path', ''))
                ->orderBy('updated_at', 'asc')
                ->limit($this->perRfcLimit)
                ->get()
                ->filter(fn(SatDownload $d) => $this->hasForceProcess($d));

            foreach ($forced as $dl) {
                $this->attemptProcessPaid($service, $cred, $dl);
            }

            // 4) (Opcional) Alertas de cancelación
            if ($cred->alert_canceled ?? false) {
                $this->notifyCanceledCfdis($cred);
            }
        }

        Log::info('[SatAutoDownloadJob] Done', ['trace' => $this->trace]);
    }

    protected function ensureRequestsForToday(SatDownloadService $service, SatCredential $cred, string $date, string $tipo): void
    {
        $exists = SatDownload::query()
            ->where('cuenta_id', (string) $cred->cuenta_id)
            ->where('rfc', strtoupper((string) $cred->rfc))
            ->whereDate('date_from', $date)
            ->whereDate('date_to', $date)
            ->where('tipo', $tipo)
            ->exists();

        if ($exists) return;

        try {
            $from = new \DateTimeImmutable($date . ' 00:00:00');
            $to   = new \DateTimeImmutable($date . ' 23:59:59');

            $dl = $service->requestPackages($cred, $from, $to, $tipo);
            $dl->auto = true;
            $dl->save();

            Log::info('[SatAutoDownloadJob] Requested today', [
                'trace' => $this->trace,
                'rfc'   => $cred->rfc,
                'tipo'  => $tipo,
                'req'   => $dl->request_id
            ]);
        } catch (\Throwable $e) {
            Log::error('[SatAutoDownloadJob] ensureRequestsForToday error', [
                'trace' => $this->trace,
                'rfc'   => $cred->rfc,
                'tipo'  => $tipo,
                'ex'    => $e->getMessage()
            ]);
        }
    }

    protected function attemptDownload(SatDownloadService $service, SatDownload $dl, ?string $pkgId = null): void
    {
        $dl->last_checked_at = now();
        $dl->attempts        = (int) $dl->attempts + 1;
        $dl->save();

        try {
            $cred = SatCredential::query()
                ->where('cuenta_id', $dl->cuenta_id)
                ->where('rfc', $dl->rfc)
                ->firstOrFail();

            $result = $service->downloadPackage($cred, $dl, $pkgId);

            Log::info('[SatAutoDownloadJob] downloadPackage', [
                'trace'  => $this->trace,
                'id'     => $dl->id,
                'status' => $result->status ?? null,
                'zip'    => $result->zip_path ?? null
            ]);
        } catch (\Throwable $e) {
            $dl->error_message = $e->getMessage();
            $dl->save();

            Log::error('[SatAutoDownloadJob] attemptDownload error', [
                'trace' => $this->trace,
                'dl'    => $dl->id,
                'ex'    => $e->getMessage()
            ]);
        }
    }

    /**
     * Procesa una descarga pagada “forzada”.
     * Si no tiene request/package, intenta arrancar request (y enlazar si crea registro nuevo).
     * Luego intenta downloadPackage.
     */
    protected function attemptProcessPaid(SatDownloadService $service, SatCredential $cred, SatDownload $dl): void
    {
        try {
            if (!$this->isPaid($dl)) {
                return;
            }

            $dl->last_checked_at = now();
            $dl->attempts        = (int)($dl->attempts ?? 0) + 1;
            $dl->save();

            // Arrancar request si está “huérfana”
            if (empty($dl->request_id) && empty($dl->package_id)) {
                $new = $this->requestForExisting($service, $cred, $dl);

                if ($new instanceof SatDownload && (string)$new->id !== (string)$dl->id) {
                    $this->linkOriginalToNew($dl, $new);
                    $dl = $new;
                }
            }

            $result = $service->downloadPackage($cred, $dl, $dl->package_id);

            Log::info('[SatAutoDownloadJob] forced downloadPackage', [
                'trace'    => $this->trace,
                'dl'       => (string)$dl->id,
                'status'   => $result->status ?? null,
                'zip_path' => $result->zip_path ?? null,
            ]);

            // Si ya hay zip, apaga la bandera
            $fresh = SatDownload::query()->find((string)$dl->id);
            if ($fresh && !empty($fresh->zip_path)) {
                $this->clearForceProcess($fresh);
            }
        } catch (\Throwable $e) {
            try {
                $dl->error_message = $e->getMessage();
                $dl->save();
            } catch (\Throwable $ignored) {
                // no-op
            }

            Log::warning('[SatAutoDownloadJob] attemptProcessPaid error', [
                'trace' => $this->trace,
                'dl'    => (string)($dl->id ?? ''),
                'ex'    => $e->getMessage(),
            ]);
        }
    }

    protected function notifyCanceledCfdis(SatCredential $cred): void
    {
        $Cfdi = '\App\Models\Cliente\Cfdi';
        if (!class_exists($Cfdi)) return;

        $q = $Cfdi::query()
            ->where(fn($q) => $q
                ->where('emisor_rfc', strtoupper((string) $cred->rfc))
                ->orWhere('receptor_rfc', strtoupper((string) $cred->rfc))
            )
            ->whereIn('status', ['cancelado', 'canceled'])
            ->where('updated_at', '>=', Carbon::now()->subDay());

        $list = $q->limit(50)->get(['uuid', 'emisor_rfc', 'receptor_rfc', 'total', 'fecha']);
        if ($list->isEmpty()) return;

        $to = $cred->alert_email;
        if (!$to) {
            $acc = CuentaCliente::query()->where('id', $cred->cuenta_id)->first();
            $to  = $acc?->correo_contacto ?: $acc?->email ?: null;
        }
        if (!$to) return;

        $razon = $cred->razon_social ?: null;

        foreach ($list as $c) {
            Notification::route('mail', $to)->notify(new CfdiCanceled(
                uuid: (string) $c->uuid,
                emisor: (string) $c->emisor_rfc,
                receptor: (string) $c->receptor_rfc,
                total: (string) $c->total,
                fecha: $c->fecha ? (string) $c->fecha : null,
                rfc: strtoupper((string) $cred->rfc),
                razon: $razon
            ));
        }

        Log::info('[SatAutoDownloadJob] canceled notifications sent', [
            'trace' => $this->trace,
            'rfc'   => $cred->rfc,
            'count' => $list->count()
        ]);
    }

    private function isPaid(SatDownload $dl): bool
    {
        if (!empty($dl->paid_at)) return true;

        $status = strtolower((string)($dl->status ?? ''));
        return in_array($status, ['paid', 'pagado', 'ok', 'active', 'activa', 'activo'], true);
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

    private function linkOriginalToNew(SatDownload $original, SatDownload $new): void
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

            Log::info('[SatAutoDownloadJob] Linked original->new', [
                'trace'    => $this->trace,
                'original' => (string)$original->id,
                'new'      => (string)$new->id,
                'payload'  => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SatAutoDownloadJob] linkOriginalToNew failed', [
                'trace' => $this->trace,
                'ex'    => $e->getMessage(),
            ]);
        }
    }
}
