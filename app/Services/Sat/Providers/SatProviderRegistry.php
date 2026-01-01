<?php

declare(strict_types=1);

namespace App\Services\Sat\Providers;

final class SatProviderRegistry
{
    /** @var array<string, SatDownloadProviderInterface> */
    private array $map = [];

    /** @param iterable<SatDownloadProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $p) {
            $this->map[$p->name()] = $p;
        }
    }

    /** @return SatDownloadProviderInterface[] */
    public function ordered(): array
    {
        $order = config('services.sat.download.providers', ['satws']);
        $out   = [];

        foreach ($order as $name) {
            if (isset($this->map[$name])) {
                $out[] = $this->map[$name];
            }
        }

        // si config trae algo inválido, caemos a todos los disponibles
        if (!$out) {
            $out = array_values($this->map);
        }

        return $out;
    }

    public function get(string $name): ?SatDownloadProviderInterface
    {
        return $this->map[$name] ?? null;
    }
}
