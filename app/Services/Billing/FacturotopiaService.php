<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FacturotopiaService
{
    public function configForAccount(int|string $adminAccountId, ?string $env = null): array
    {
        $account = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', (int) $adminAccountId)
            ->first(['id', 'meta']);

        $meta = [];

        if ($account && ! empty($account->meta)) {
            $decoded = json_decode((string) $account->meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $facturotopia = (array) data_get($meta, 'facturotopia', []);

        $activeEnv = $env ?: (string) data_get($facturotopia, 'env', config('services.facturotopia.mode', 'sandbox'));
        $activeEnv = in_array($activeEnv, ['sandbox', 'production'], true) ? $activeEnv : 'sandbox';

        $password = '';

        try {
            $encrypted = (string) data_get($facturotopia, 'auth.password_encrypted', '');
            if ($encrypted !== '') {
                $password = Crypt::decryptString($encrypted);
            }
        } catch (\Throwable $e) {
            $password = '';
        }

        $baseUrl = (string) data_get(
            $facturotopia,
            "{$activeEnv}.base_url",
            data_get(
                $facturotopia,
                "{$activeEnv}.base",
                config("services.facturotopia.{$activeEnv}.base_url", config("services.facturotopia.{$activeEnv}.base", ''))
            )
        );

        $apiKey = (string) data_get(
            $facturotopia,
            "{$activeEnv}.api_key",
            data_get(
                $facturotopia,
                "{$activeEnv}.token",
                config("services.facturotopia.{$activeEnv}.api_key", config("services.facturotopia.{$activeEnv}.token", ''))
            )
        );

        return [
            'account_id' => (int) $adminAccountId,
            'env' => $activeEnv,
            'status' => (string) data_get($facturotopia, 'status', 'pendiente'),
            'customer_id' => (string) data_get($facturotopia, 'customer_id', ''),
            'user' => (string) data_get($facturotopia, 'auth.user', ''),
            'password' => $password,
            'base_url' => rtrim($baseUrl, '/'),
            'api_key' => $apiKey,
        ];
    }

   public function client(array $config)
    {
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        return Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Apikey ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    public function request(int|string $adminAccountId, string $method, string $endpoint, array $payload = [], ?string $env = null): Response
    {
        $config = $this->configForAccount($adminAccountId, $env);

        if (empty($config['base_url'])) {
            throw new \RuntimeException('Facturotopia no tiene base_url configurado para ' . ($config['env'] ?? 'sandbox') . '.');
        }

        if (empty($config['api_key'])) {
            throw new \RuntimeException('Facturotopia no tiene API key/token configurado para ' . ($config['env'] ?? 'sandbox') . '.');
        }

        $url = $config['base_url'] . '/' . ltrim($endpoint, '/');

        \Log::warning('Facturotopia.request.debug', [
            'env' => $config['env'] ?? null,
            'method' => strtoupper($method),
            'base_url' => $config['base_url'] ?? null,
            'endpoint' => $endpoint,
            'url' => $url,
            'has_api_key' => !empty($config['api_key'] ?? ''),
            'customer_id' => $config['customer_id'] ?? null,
        ]);

        return match (strtoupper($method)) {
            'GET' => $this->client($config)->get($url, $payload),
            'POST' => $this->client($config)->post($url, $payload),
            'PUT' => $this->client($config)->put($url, $payload),
            'PATCH' => $this->client($config)->patch($url, $payload),
            'DELETE' => $this->client($config)->delete($url, $payload),
            default => throw new \InvalidArgumentException('Método HTTP no soportado: ' . $method),
        };
    }

    public function testConnection(int|string $adminAccountId, ?string $env = null): array
    {
        $config = $this->configForAccount($adminAccountId, $env);

        if (empty($config['base_url']) || empty($config['api_key'])) {
            return [
                'ok' => false,
                'env' => $config['env'],
                'base_url' => $config['base_url'],
                'has_api_key' => ! empty($config['api_key']),
                'has_user' => ! empty($config['user']),
                'has_password' => ! empty($config['password']),
                'customer_id' => $config['customer_id'],
                'status' => null,
                'body' => 'Falta base_url o API key/token.',
                'response_ms' => 0,
            ];
        }

        $startedAt = microtime(true);
        $response = $this->client($config)->get($config['base_url']);

        return [
            'ok' => $response->successful() || in_array($response->status(), [401, 403, 404, 405], true),
            'env' => $config['env'],
            'base_url' => $config['base_url'],
            'has_api_key' => ! empty($config['api_key']),
            'has_user' => ! empty($config['user']),
            'has_password' => ! empty($config['password']),
            'customer_id' => $config['customer_id'],
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
            'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function registerEmisor(int|string $adminAccountId, array $payload, ?string $env = null): array
    {
        $endpoint = (string) config('services.facturotopia.endpoints.register_emisor', 'api/emisores');
        $response = $this->request($adminAccountId, 'POST', $endpoint, $payload, $env);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
            'body' => $response->body(),
        ];
    }

    public function listEmisores(int|string $adminAccountId, ?string $env = null): array
{
    $endpoint = (string) config('services.facturotopia.endpoints.emisores_list', 'api/emisores');

    $response = $this->request($adminAccountId, 'GET', $endpoint, [], $env);

    return [
        'ok' => $response->successful(),
        'status' => $response->status(),
        'data' => $response->json(),
        'body' => $response->body(),
    ];
}

public function consultarEmisor(int|string $adminAccountId, string $emisorId, ?string $env = null): array
{
    $endpoint = str_replace(
        '{id}',
        rawurlencode($emisorId),
        (string) config('services.facturotopia.endpoints.emisor_show', 'api/emisores/{id}')
    );

    $response = $this->request($adminAccountId, 'GET', $endpoint, [], $env);

    return [
        'ok' => $response->successful(),
        'status' => $response->status(),
        'data' => $response->json(),
        'body' => $response->body(),
    ];
}

public function actualizarEmisor(int|string $adminAccountId, string $emisorId, array $payload, ?string $env = null): array
{
    $endpoint = str_replace(
        '{id}',
        rawurlencode($emisorId),
        (string) config('services.facturotopia.endpoints.emisor_update', 'api/emisores/{id}')
    );

    $response = $this->request($adminAccountId, 'PUT', $endpoint, $payload, $env);

    return [
        'ok' => $response->successful(),
        'status' => $response->status(),
        'data' => $response->json(),
        'body' => $response->body(),
    ];
}

    public function timbrarCfdi(int|string $adminAccountId, array $payload, ?string $env = null): array
{
    $endpoint = (string) config('services.facturotopia.endpoints.timbrar_cfdi', 'api/comprobantes');

    try {
        $response = $this->request($adminAccountId, 'POST', $endpoint, $payload, $env);

        $body = $response->body();
        $json = $response->json();

        if (! is_array($json)) {
            $json = [];
        }

        $ok = $response->successful();

        return [
            'ok' => $ok,
            'status' => $response->status(),
            'endpoint' => $endpoint,
            'data' => $json,
            'body' => $body,
            'message' => $ok
                ? 'CFDI timbrado correctamente.'
                : $this->resolveFacturotopiaErrorMessage($json, $body, $response->status()),
        ];
    } catch (\Throwable $e) {
        report($e);

        return [
            'ok' => false,
            'status' => 0,
            'endpoint' => $endpoint,
            'data' => [],
            'body' => '',
            'message' => 'Error al conectar con Facturotopia: ' . $e->getMessage(),
        ];
    }
}

protected function resolveFacturotopiaErrorMessage(array $json, string $body, int $status): string
{
    foreach ([
        'message',
        'mensaje',
        'error',
        'Error',
        'descripcion',
        'description',
        'detail',
        'errors.0.message',
        'errors.0',
    ] as $key) {
        $value = data_get($json, $key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    $body = trim($body);

    if ($body !== '') {
        return mb_substr($body, 0, 800);
    }

    return 'Facturotopia rechazó el timbrado. HTTP: ' . $status;
}

    public function consultarUuid(int|string $adminAccountId, string $uuid, ?string $env = null): array
    {
        $endpoint = str_replace('{uuid}', $uuid, (string) config('services.facturotopia.endpoints.consultar_uuid', 'cfdi/{uuid}'));
        $response = $this->request($adminAccountId, 'GET', $endpoint, [], $env);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
            'body' => $response->body(),
        ];
    }

    public function descargarXml(int|string $adminAccountId, string $uuid, ?string $env = null): Response
    {
        $endpoint = str_replace('{uuid}', $uuid, (string) config('services.facturotopia.endpoints.xml', 'cfdi/{uuid}/xml'));
        return $this->request($adminAccountId, 'GET', $endpoint, [], $env);
    }

    public function descargarPdf(int|string $adminAccountId, string $uuid, ?string $env = null): Response
    {
        $endpoint = str_replace('{uuid}', $uuid, (string) config('services.facturotopia.endpoints.pdf', 'cfdi/{uuid}/pdf'));
        return $this->request($adminAccountId, 'GET', $endpoint, [], $env);
    }
}