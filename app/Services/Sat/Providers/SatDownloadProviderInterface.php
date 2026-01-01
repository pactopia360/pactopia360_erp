<?php

declare(strict_types=1);

namespace App\Services\Sat\Providers;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;

interface SatDownloadProviderInterface
{
    public function name(): string;

    /** Si el proveedor puede operar para este request (env/config/feature flags). */
    public function isAvailable(): bool;

    /** Solicita al SAT/proveedor y devuelve request_id externo (provider_ref). */
    public function requestPackages(
        SatCredential $cred,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $tipo
    ): ProviderResult;

    /** Descarga/arma ZIP en $zipAbs (ruta absoluta). */
    public function downloadPackage(
        SatCredential $cred,
        SatDownload $download,
        string $zipAbs
    ): ProviderResult;
}
