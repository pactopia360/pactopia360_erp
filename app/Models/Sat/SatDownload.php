<?php

declare(strict_types=1);

namespace App\Models\Sat;

/**
 * Alias de compatibilidad para código legado que usa App\Models\Sat\SatDownload
 * El modelo real vive en App\Models\Cliente\SatDownload
 */
class SatDownload extends \App\Models\Cliente\SatDownload
{
    // Intencionalmente vacío
}
