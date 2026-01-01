<?php

declare(strict_types=1);

namespace App\Services\Sat\Providers;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;

final class Provider2Stub implements SatDownloadProviderInterface
{
    public function name(): string { return 'provider2'; }

    public function isAvailable(): bool
    {
        // aquí validas env/keys
        return (bool) env('PROVIDER2_ENABLED', false);
    }

    public function requestPackages(SatCredential $cred, \DateTimeImmutable $from, \DateTimeImmutable $to, string $tipo): ProviderResult
    {
        return ProviderResult::fail($this->name(), 'TEMPORARY', 'Provider2 no implementado todavía.');
    }

    public function downloadPackage(SatCredential $cred, SatDownload $download, string $zipAbs): ProviderResult
    {
        return ProviderResult::fail($this->name(), 'TEMPORARY', 'Provider2 no implementado todavía.');
    }
}
