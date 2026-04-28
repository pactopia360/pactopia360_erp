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

        return [
            'account_id' => (int) $adminAccountId,
            'env' => $activeEnv,
            'status' => (string) data_get($facturotopia, 'status', 'pendiente'),
            'customer_id' => (string) data_get($facturotopia, 'customer_id', ''),
            'user' => (string) data_get($facturotopia, 'auth.user', ''),
            'password' => $password,
            'base_url' => rtrim((string) data_get(
                $facturotopia,
                "{$activeEnv}.base_url",
                config("services.facturotopia.{$activeEnv}.base_url", '')
            ), '/'),
            'api_key' => (string) data_get(
                $facturotopia,
                "{$activeEnv}.api_key",
                config("services.facturotopia.{$activeEnv}.api_key", '')
            ),
        ];
    }

    public function client(array $config)
    {
        $apiKey = (string) ($config['api_key'] ?? '');

        return Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'X-API-KEY' => $apiKey,
            ]);
    }

    public function request(int|string $adminAccountId, string $method, string $endpoint, array $payload = [], ?string $env = null): Response
    {
        $config = $this->configForAccount($adminAccountId, $env);

        if (empty($config['base_url'])) {
            throw new \RuntimeException('Facturotopia no tiene base_url configurado para ' . ($config['env'] ?? 'sandbox') . '.');
        }

        if (empty($config['api_key'])) {
            throw new \RuntimeException('Facturotopia no tiene API key configurada para ' . ($config['env'] ?? 'sandbox') . '.');
        }

        $url = $config['base_url'] . '/' . ltrim($endpoint, '/');

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
                'body' => 'Falta base_url o API key.',
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
        $endpoint = (string) config('services.facturotopia.endpoints.register_emisor', 'registrar-emisor');

        $response = $this->request($adminAccountId, 'POST', $endpoint, $payload, $env);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
            'body' => $response->body(),
        ];
    }

    public function timbrarCfdi(int|string $adminAccountId, array $payload, ?string $env = null): array
    {
        $endpoint = (string) config('services.facturotopia.endpoints.timbrar_cfdi', 'timbrar-cfdi');

        $response = $this->request($adminAccountId, 'POST', $endpoint, $payload, $env);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
            'body' => $response->body(),
        ];
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