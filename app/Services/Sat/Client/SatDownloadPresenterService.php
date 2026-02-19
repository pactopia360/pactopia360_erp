<?php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Carbon;

final class SatDownloadPresenterService
{
    public function __construct(
        private readonly SatDownloadMetrics $metrics
    ) {}

    public function transform(
        SatDownload $d,
        array $credMap,
        array $cartIds,
        Carbon $now
    ): array {

        $d = $this->metrics->hydrateDownloadMetrics($d);

        return [
            'id'        => (string)$d->id,
            'rfc'       => strtoupper((string)$d->rfc),
            'alias'     => $credMap[strtoupper((string)$d->rfc)] ?? '',
            'xml_count' => (int)($d->xml_count ?? 0),
            'costo'     => (float)($d->costo ?? 0),
            'size_mb'   => (float)($d->size_mb ?? 0),
            'paid'      => !empty($d->paid_at),
        ];
    }
}
