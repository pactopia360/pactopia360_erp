<?php

declare(strict_types=1);

namespace App\Services\Sat\Providers;

final class ProviderResult
{
    public function __construct(
        public bool $ok,
        public string $provider,
        public ?string $providerRef = null,

        // normalización de error
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $errorContext = [],

        // metadatos útiles (timings, warnings)
        public array $meta = []
    ) {}

    public static function ok(string $provider, ?string $providerRef = null, array $meta = []): self
    {
        return new self(true, $provider, $providerRef, null, null, [], $meta);
    }

    public static function fail(string $provider, string $code, string $message, array $ctx = [], array $meta = []): self
    {
        return new self(false, $provider, null, $code, $message, $ctx, $meta);
    }
}
