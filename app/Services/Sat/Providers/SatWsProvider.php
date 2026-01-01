<?php

declare(strict_types=1);

namespace App\Services\Sat\Providers;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use App\Services\Sat\SatWsGateway;

final class SatWsProvider implements SatDownloadProviderInterface
{
    public function __construct(private readonly SatWsGateway $satws) {}

    public function name(): string { return 'satws'; }

    public function isAvailable(): bool
    {
        return extension_loaded('soap') && extension_loaded('openssl');
    }

    public function requestPackages(SatCredential $cred, \DateTimeImmutable $from, \DateTimeImmutable $to, string $tipo): ProviderResult
    {
        try {
            $reqId = $this->satws->solicitar($cred, $from, $to, $tipo);
            if (!$reqId) {
                return ProviderResult::fail($this->name(), 'TEMPORARY', 'SATWS no devolvió request_id');
            }
            return ProviderResult::ok($this->name(), (string) $reqId);
        } catch (\Throwable $e) {
            $code = $this->normalizeExceptionCode($e);
            return ProviderResult::fail($this->name(), $code, $e->getMessage(), [
                'exception' => get_class($e),
            ]);
        }
    }

    public function downloadPackage(SatCredential $cred, SatDownload $download, string $zipAbs): ProviderResult
    {
        try {
            $this->satws->descargar($cred, $download, $zipAbs);
            return ProviderResult::ok($this->name(), (string)($download->request_id ?? null));
        } catch (\Throwable $e) {
            $code = $this->normalizeExceptionCode($e);
            return ProviderResult::fail($this->name(), $code, $e->getMessage(), [
                'exception' => get_class($e),
                'download_id' => (string) $download->id,
            ]);
        }
    }

    private function normalizeExceptionCode(\Throwable $e): string
    {
        $m = strtolower($e->getMessage());

        if (str_contains($m, 'timed out') || str_contains($m, 'timeout')) return 'TIMEOUT';
        if (str_contains($m, 'could not connect') || str_contains($m, 'network')) return 'NETWORK';
        if (str_contains($m, '503') || str_contains($m, 'unavailable')) return 'UPSTREAM_503';
        if (str_contains($m, 'rechazó') || str_contains($m, 'rechazo')) return 'TEMPORARY';

        return 'EXCEPTION';
    }
}
