<?php

namespace App\Jobs;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\CuentaCliente;
use App\Notifications\CfdiCanceled; // ⬅️ si tu clase está en App\Notifications\Cliente , cámbiala a: use App\Notifications\Cliente\CfdiCanceled;
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
use Illuminate\Support\Str;

use Throwable;

class SatAutoDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ⚠️ No declares $queue; lo trae Queueable y si lo vuelves a declarar choca.
    public int $tries   = 2;
    public int $timeout = 300; // seg
    public string $trace;

    /** Límite de descargas a revisar por RFC en una corrida */
    public int $perRfcLimit;

    public function __construct(int $perRfcLimit = 50)
    {
        $this->trace       = (string) Str::ulid();
        $this->perRfcLimit = max(1, $perRfcLimit);

        // Asignar la cola correctamente
        $this->onQueue('sat');
    }

    /** Evita solapes de este mismo Job */
    public function middleware(): array
    {
        // clave única; expira en 30 min; sin re-liberar
        return [(new WithoutOverlapping('job:sat:auto-download'))->dontRelease()];
    }

    /** Backoff entre reintentos */
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
            ->where('c.plan_actual', 'PRO') // Solo PRO
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

            // 3) (Opcional) Alertas de cancelación
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
        } catch (Throwable $e) {
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
                'status' => $result->status,
                'zip'    => $result->zip_path
            ]);
        } catch (Throwable $e) {
            $dl->error_message = $e->getMessage();
            $dl->save();

            Log::error('[SatAutoDownloadJob] attemptDownload error', [
                'trace' => $this->trace,
                'dl'    => $dl->id,
                'ex'    => $e->getMessage()
            ]);
        }
    }

    protected function notifyCanceledCfdis(SatCredential $cred): void
    {
        $Cfdi = '\App\Models\Cliente\Cfdi';
        if (!class_exists($Cfdi)) return;

        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $Cfdi::query()
            ->where(fn($q) => $q
                ->where('emisor_rfc', strtoupper((string) $cred->rfc))
                ->orWhere('receptor_rfc', strtoupper((string) $cred->rfc))
            )
            ->whereIn('status', ['cancelado', 'canceled'])
            ->where('updated_at', '>=', Carbon::now()->subDay());

        $list = $q->limit(50)->get(['uuid', 'emisor_rfc', 'receptor_rfc', 'total', 'fecha']);
        if ($list->isEmpty()) return;

        // Destinatario
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
}
