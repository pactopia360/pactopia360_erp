<?php

namespace App\Services\Facturotopia;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class EmisoresApi
{
    protected string $base;
    protected ?string $token;

    public function __construct()
    {
        $this->base  = rtrim(config('services.facturotopia.base'), '/');
        $this->token = config('services.facturotopia.token');
    }

    /** POST /emisores */
    public function create(array $payload): array
    {
        $resp = Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->post("{$this->base}/emisores", $payload);

        if ($resp->failed()) {
            throw new RequestException($resp);
        }
        return $resp->json();
    }
}
