<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Http;

class FacturotopiaService
{
    protected string $base;
    protected string $token;

    public function __construct()
    {
        $mode = config('services.facturotopia.mode');

        $this->base  = config("services.facturotopia.$mode.base");
        $this->token = config("services.facturotopia.$mode.token");
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);
    }

    public function createInvoice(array $payload)
    {
        return $this->client()
            ->post($this->base.'/invoices', $payload)
            ->json();
    }

    public function downloadXml(string $uuid)
    {
        return $this->client()
            ->get($this->base."/invoices/$uuid/xml");
    }

    public function downloadPdf(string $uuid)
    {
        return $this->client()
            ->get($this->base."/invoices/$uuid/pdf");
    }
}