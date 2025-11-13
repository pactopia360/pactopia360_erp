<?php

namespace App\Jobs;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SatAutoProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?string $cuentaId = null) {}

    public function handle(SatDownloadService $svc): void
    {
        // 1) Promover pendientes → listo (simulación); en real, llamarías APIs SAT
        $q = SatDownload::query()->whereIn('status',['pending','processing']);
        if ($this->cuentaId) $q->where('cuenta_id', $this->cuentaId);
        $pend = $q->orderBy('created_at')->limit(50)->get();

        foreach ($pend as $dl) {
            try {
                $cred = SatCredential::where('cuenta_id',$dl->cuenta_id)->where('rfc',$dl->rfc)->first();
                if (!$cred) continue;
                // Aquí podrías hacer polling real; en stub, intentamos descargar
                $svc->downloadPackage($cred, $dl, $dl->package_id);
            } catch (\Throwable $e) {
                Log::warning('[SatAutoProcessJob] error download', ['id'=>$dl->id,'ex'=>$e->getMessage()]);
            }
        }

        Log::info('[SatAutoProcessJob] tick', ['count'=>count($pend)]);
    }
}
