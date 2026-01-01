<?php

declare(strict_types=1);

namespace App\Services\Sat;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;

final class SatWsGateway
{
    public function solicitar(SatCredential $cred, \DateTimeImmutable $from, \DateTimeImmutable $to, string $tipo): ?string
    {
        // MUEVE aquí la lógica de satwsSolicitarProvider()
        // Debe NO depender de SatDownloadBalancer ni SatProviderRegistry.
        return null; // reemplaza con tu implementación real
    }

    public function descargar(SatCredential $cred, SatDownload $download, string $zipAbs): void
    {
        // MUEVE aquí la lógica de satwsDescargarProvider()
        // Igual: cero dependencia del orchestrator.
    }
}
