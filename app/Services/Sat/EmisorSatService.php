<?php

namespace App\Services\Sat;

class EmisorSatService
{
    public function extractFromCsd(string $cerPath, string $keyPath, string $password): array
    {
        // TODO: implementar contra tu proveedor o OpenSSL.
        // Devuelve rfc, razon_social, regimen, serie, vigencia_hasta, etc.
        return [
            'rfc' => null,
            'razon_social' => null,
            'regimen_fiscal' => null,
            'csd_serie' => null,
            'csd_vigencia_hasta' => null,
        ];
    }
}
