<?php

declare(strict_types=1);

namespace App\Services\Billing\Facturotopia;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class FacturotopiaClient
{
    private string $adm;

    private string $mode;
    private string $base;
    private string $token;
    private string $flow;
    private string $emisorId;
    private string $tenancy;
    private string $tenancyHeader;

    private int $networkRetryMaxAttempts = 3;
    private int $networkRetrySleepMs = 700;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $this->mode           = 'sandbox';
        $this->base           = '';
        $this->token          = '';
        $this->flow           = 'api_comprobantes';
        $this->emisorId       = '';
        $this->tenancy        = '';
        $this->tenancyHeader  = 'X-Tenancy';

        $this->bootConfig();
    }

    /**
     * Config básica de conexión API.
     */
    public function hasApiConfig(): bool
    {
        return $this->base !== '' && $this->token !== '';
    }

    /**
     * Compatibilidad hacia atrás.
     * Para emitir comprobantes exigimos:
     * - base
     * - token
     * - tenancy
     * - emisor_id (solo en api_comprobantes)
     */
    public function isConfigured(): bool
    {
        if (!$this->hasApiConfig()) {
            return false;
        }

        if ($this->tenancy === '') {
            return false;
        }

        if ($this->flow === 'api_comprobantes' && $this->emisorId === '') {
            return false;
        }

        return true;
    }

    /**
     * Para endpoints generales como emisores/receptores.
     */
    public function isApiReady(): bool
    {
        return $this->hasApiConfig() && $this->tenancy !== '';
    }

    /**
     * @return array<string,string>
     */
    public function resolvedConfig(): array
    {
        return [
            'mode'           => $this->mode,
            'base'           => $this->base,
            'flow'           => $this->flow,
            'emisor_id'      => $this->emisorId,
            'tenancy'        => $this->tenancy,
            'tenancy_header' => $this->tenancyHeader,
            'token'          => $this->token !== ''
                ? substr($this->token, 0, 6) . '***' . substr($this->token, -4)
                : '',
        ];
    }

    public function configuredEmisorId(): string
    {
        return $this->emisorId;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * =========================================================================
     * COMPROBANTES
     * =========================================================================
     */

    public function createComprobante(array $payload): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no está configurado correctamente. Revisa base, token, tenancy y emisor_id.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($this->flow !== 'api_comprobantes') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El flujo xml_timbrado aún no está implementado en este cliente.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->post('/api/comprobantes', $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractInvoiceData((array) ($response['json'] ?? [])),
        ];
    }

    public function downloadPdf(?string $url, string $uuid): ?string
    {
        return $this->downloadGeneratedFile((string) $url, $uuid, 'pdf');
    }

    public function downloadXml(?string $url, string $uuid): ?string
    {
        return $this->downloadGeneratedFile((string) $url, $uuid, 'xml');
    }

    /**
     * =========================================================================
     * EMISORES
     * =========================================================================
     */

    public function listEmisores(int $limit = 100, ?string $page = null): array
    {
        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $query = [
            'limit' => max(1, min(250, $limit)),
        ];

        if ($page !== null && trim($page) !== '') {
            $query['page'] = trim($page);
        }

        $response = $this->get('/api/emisores', $query);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractEmisoresList((array) ($response['json'] ?? [])),
        ];
    }

    public function getEmisor(string $id): array
    {
        $id = trim($id);

        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($id === '') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El id del emisor es obligatorio.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->get('/api/emisores/' . rawurlencode($id));

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractEmisorData((array) ($response['json'] ?? [])),
        ];
    }

    public function createEmisor(array $payload): array
    {
        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->post('/api/emisores', $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractEmisorData((array) ($response['json'] ?? [])),
        ];
    }

    public function updateEmisor(string $id, array $payload): array
    {
        $id = trim($id);

        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($id === '') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El id del emisor es obligatorio.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->put('/api/emisores/' . rawurlencode($id), $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractEmisorData((array) ($response['json'] ?? [])),
        ];
    }

    public function updateEmisorStatus(string $id, string $status, string $comentario): array
    {
        $id = trim($id);

        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($id === '') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El id del emisor es obligatorio.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $payload = [
            'status'     => trim($status),
            'comentario' => trim($comentario),
        ];

        $response = $this->patch('/api/emisores/' . rawurlencode($id) . '/status', $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => (array) ($response['json'] ?? []),
        ];
    }

    /**
     * =========================================================================
     * RECEPTORES
     * =========================================================================
     */

    public function listReceptores(int $limit = 100, ?string $page = null): array
    {
        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $query = [
            'limit' => max(1, min(250, $limit)),
        ];

        if ($page !== null && trim($page) !== '') {
            $query['page'] = trim($page);
        }

        $response = $this->get('/api/receptores', $query);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractReceptoresList((array) ($response['json'] ?? [])),
        ];
    }

    public function getReceptor(string $id): array
    {
        $id = trim($id);

        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($id === '') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El id del receptor es obligatorio.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->get('/api/receptores/' . rawurlencode($id));

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractReceptorData((array) ($response['json'] ?? [])),
        ];
    }

    public function createReceptor(array $payload): array
    {
        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->post('/api/receptores', $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractReceptorData((array) ($response['json'] ?? [])),
        ];
    }

    public function updateReceptor(string $id, array $payload): array
    {
        $id = trim($id);

        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($id === '') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El id del receptor es obligatorio.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $response = $this->put('/api/receptores/' . rawurlencode($id), $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => $this->extractReceptorData((array) ($response['json'] ?? [])),
        ];
    }

    public function updateReceptorStatus(string $id, string $status, string $comentario): array
    {
        $id = trim($id);

        if (!$this->isApiReady()) {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'Facturotopia no tiene base, token o tenancy configurados.',
                'json'    => [],
                'data'    => [],
            ];
        }

        if ($id === '') {
            return [
                'ok'      => false,
                'status'  => 0,
                'message' => 'El id del receptor es obligatorio.',
                'json'    => [],
                'data'    => [],
            ];
        }

        $payload = [
            'status'     => trim($status),
            'comentario' => trim($comentario),
        ];

        $response = $this->patch('/api/receptores/' . rawurlencode($id) . '/status', $payload);

        if (!$response['ok']) {
            return $response + ['data' => []];
        }

        return $response + [
            'data' => (array) ($response['json'] ?? []),
        ];
    }

    /**
     * =========================================================================
     * CONFIG
     * =========================================================================
     */

    private function bootConfig(): void
    {
        $settings = $this->loadBillingSettings();

        $mode = strtolower(trim((string) ($settings['facturotopia_mode'] ?? config('services.facturotopia.mode', 'sandbox'))));
        if (!in_array($mode, ['sandbox', 'production'], true)) {
            $mode = 'sandbox';
        }

        $flow = strtolower(trim((string) ($settings['facturotopia_flow'] ?? 'api_comprobantes')));
        if (!in_array($flow, ['api_comprobantes', 'xml_timbrado'], true)) {
            $flow = 'api_comprobantes';
        }

        $sandboxBase = (string) data_get(config('services.facturotopia'), 'sandbox.base', 'https://api-demo.facturotopia.com');
        $prodBase    = (string) data_get(config('services.facturotopia'), 'production.base', 'https://api.facturotopia.com');

        $sandboxToken = trim((string) (
            $settings['facturotopia_api_key_test']
            ?? data_get(config('services.facturotopia'), 'sandbox.token', config('services.facturotopia.api_key_test', ''))
        ));

        $prodToken = trim((string) (
            $settings['facturotopia_api_key_live']
            ?? data_get(config('services.facturotopia'), 'production.token', config('services.facturotopia.api_key_live', ''))
        ));

        $baseOverride = trim((string) ($settings['facturotopia_base'] ?? ''));

        $base = $baseOverride !== ''
            ? $baseOverride
            : ($mode === 'production' ? $prodBase : $sandboxBase);

        $this->mode      = $mode;
        $this->flow      = $flow;
        $this->base      = $this->normalizeBase($base);
        $this->token     = $mode === 'production' ? $prodToken : $sandboxToken;
        $this->emisorId  = trim((string) ($settings['facturotopia_emisor_id'] ?? ''));

        $this->tenancy = trim((string) (
            $settings['facturotopia_tenancy']
            ?? data_get(config('services.facturotopia'), 'tenancy', '')
        ));

        $headerOverride = trim((string) (
            $settings['facturotopia_tenancy_header']
            ?? data_get(config('services.facturotopia'), 'tenancy_header', '')
        ));

        $this->tenancyHeader = $headerOverride !== '' ? $headerOverride : 'X-Tenancy';
    }

    /**
     * @return array<string,string>
     */
    private function loadBillingSettings(): array
    {
        try {
            if (!Schema::connection($this->adm)->hasTable('billing_settings')) {
                return [];
            }

            $rows = DB::connection($this->adm)
                ->table('billing_settings')
                ->pluck('value', 'key');

            $out = [];
            foreach ($rows as $k => $v) {
                $out[(string) $k] = is_string($v) ? $v : (string) $v;
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('[FACTUROTOPIA] loadBillingSettings failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeBase(string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            return '';
        }

        $base = rtrim($base, '/');

        if (str_ends_with(strtolower($base), '/api')) {
            $base = substr($base, 0, -4);
            $base = rtrim($base, '/');
        }

        return $base;
    }

    /**
     * Orden de prueba de autenticación.
     * Primero intenta con el valor configurado en BD si existe,
     * luego prueba el alterno automáticamente.
     *
     * @return array<int,string>
     */
    private function authSchemes(): array
    {
        $settings = $this->loadBillingSettings();

        $preferred = strtolower(trim((string) (
            $settings['facturotopia_auth_scheme']
            ?? data_get(config('services.facturotopia'), 'auth_scheme', 'bearer')
        )));

        $schemes = [];

        if ($preferred === 'apikey') {
            $schemes = ['ApiKey', 'Bearer'];
        } else {
            $schemes = ['Bearer', 'ApiKey'];
        }

        return array_values(array_unique($schemes));
    }

    private function buildHeadersForScheme(string $scheme): array
    {
        $scheme = trim($scheme) !== '' ? trim($scheme) : 'Bearer';

        $headers = [
            'Authorization' => $scheme . ' ' . trim($this->token),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        if ($this->tenancy !== '') {
            $headers['X-Tenancy']    = $this->tenancy;
            $headers['X-Tenant']     = $this->tenancy;
            $headers['X-Tenant-Key'] = $this->tenancy;

            if ($this->tenancyHeader !== '') {
                $headers[$this->tenancyHeader] = $this->tenancy;
            }
        }

        return $headers;
    }

    private function httpWithScheme(string $scheme): PendingRequest
    {
        return Http::withHeaders($this->buildHeadersForScheme($scheme))
            ->asJson()
            ->timeout(90)
            ->connectTimeout(20);
    }

    private function buildUrl(string $uri, array $query = []): string
    {
        $url = $this->base . '/' . ltrim($uri, '/');

        if ($this->tenancy !== '') {
            if (!array_key_exists('tenancy', $query) || trim((string) $query['tenancy']) === '') {
                $query['tenancy'] = $this->tenancy;
            }
        }

        if (!empty($query)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        return $url;
    }

    private function http(): PendingRequest
    {
        return $this->httpWithScheme($this->authSchemes()[0] ?? 'Bearer');
    }

    /**
     * =========================================================================
     * HTTP WRAPPERS
     * =========================================================================
     */

    private function get(string $uri, array $query = []): array
    {
        $url = $this->buildUrl($uri, $query);

        $lastJson = [];
        $lastStatus = 0;
        $lastMessage = 'Error no especificado de Facturotopia.';

        foreach ($this->authSchemes() as $scheme) {
            try {
                $res = $this->requestWithRetry('GET', $scheme, $url);

                $json = [];
                try {
                    $json = $res->json() ?: [];
                } catch (Throwable $e) {
                    $json = [];
                }

                if ($res->successful()) {
                    Log::info('[FACTUROTOPIA] GET ok', [
                        'url'             => $url,
                        'status'          => $res->status(),
                        'auth_scheme'     => $scheme,
                        'tenancy_present' => $this->tenancy !== '',
                    ]);

                    return [
                        'ok'      => true,
                        'status'  => $res->status(),
                        'json'    => $json,
                        'message' => '',
                    ];
                }

                Log::warning('[FACTUROTOPIA] GET failed', [
                    'url'             => $url,
                    'status'          => $res->status(),
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'body'            => $json,
                ]);

                $msg = $this->extractErrorMessage($json, $res->body());

                $lastJson = $json;
                $lastStatus = $res->status();
                $lastMessage = $msg;

                $unauth = strtolower((string) data_get($json, 'code', '')) === 'unauthenticated'
                    || $res->status() === 401
                    || str_contains(strtolower($msg), 'unauthenticated');

                if ($unauth) {
                    continue;
                }

                return [
                    'ok'      => false,
                    'status'  => $res->status(),
                    'json'    => $json,
                    'message' => $msg,
                ];
            } catch (Throwable $e) {
                Log::error('[FACTUROTOPIA] GET exception', [
                    'url'             => $url,
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'error'           => $e->getMessage(),
                ]);

                $lastJson = [];
                $lastStatus = 0;
                $lastMessage = $this->normalizeThrowableMessage($e);
            }
        }

        return [
            'ok'      => false,
            'status'  => $lastStatus,
            'json'    => $lastJson,
            'message' => $lastMessage,
        ];
    }

    private function post(string $uri, array $payload): array
    {
        $url = $this->buildUrl($uri);

        $lastJson = [];
        $lastStatus = 0;
        $lastMessage = 'Error no especificado de Facturotopia.';

        foreach ($this->authSchemes() as $scheme) {
            try {
                $res = $this->requestWithRetry('POST', $scheme, $url, $payload);

                $json = [];
                try {
                    $json = $res->json() ?: [];
                } catch (Throwable $e) {
                    $json = [];
                }

                if ($res->successful()) {
                    Log::info('[FACTUROTOPIA] POST ok', [
                        'url'             => $url,
                        'status'          => $res->status(),
                        'auth_scheme'     => $scheme,
                        'tenancy_present' => $this->tenancy !== '',
                    ]);

                    return [
                        'ok'      => true,
                        'status'  => $res->status(),
                        'json'    => $json,
                        'message' => '',
                    ];
                }

                $msg = $this->extractErrorMessage($json, $res->body());

                Log::warning('[FACTUROTOPIA] POST failed', [
                    'url'               => $url,
                    'status'            => $res->status(),
                    'auth_scheme'       => $scheme,
                    'tenancy_present'   => $this->tenancy !== '',
                    'tenancy_value'     => $this->tenancy !== '' ? $this->tenancy : null,
                    'tenancy_header'    => $this->tenancyHeader,
                    'emisor_id_present' => $this->emisorId !== '',
                    'payload'           => $payload,
                    'body'              => $json,
                ]);

                $lastJson = $json;
                $lastStatus = $res->status();
                $lastMessage = $msg;

                $unauth = strtolower((string) data_get($json, 'code', '')) === 'unauthenticated'
                    || $res->status() === 401
                    || str_contains(strtolower($msg), 'unauthenticated');

                if ($unauth) {
                    continue;
                }

                return [
                    'ok'      => false,
                    'status'  => $res->status(),
                    'json'    => $json,
                    'message' => $msg,
                ];
            } catch (Throwable $e) {
                Log::error('[FACTUROTOPIA] POST exception', [
                    'url'             => $url,
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'error'           => $e->getMessage(),
                ]);

                $lastJson = [];
                $lastStatus = 0;
                $lastMessage = $this->normalizeThrowableMessage($e);
            }
        }

        return [
            'ok'      => false,
            'status'  => $lastStatus,
            'json'    => $lastJson,
            'message' => $lastMessage,
        ];
    }

    private function put(string $uri, array $payload): array
    {
        $url = $this->buildUrl($uri);

        $lastJson = [];
        $lastStatus = 0;
        $lastMessage = 'Error no especificado de Facturotopia.';

        foreach ($this->authSchemes() as $scheme) {
            try {
                $res = $this->requestWithRetry('PUT', $scheme, $url, $payload);

                $json = [];
                try {
                    $json = $res->json() ?: [];
                } catch (Throwable $e) {
                    $json = [];
                }

                if ($res->successful()) {
                    Log::info('[FACTUROTOPIA] PUT ok', [
                        'url'             => $url,
                        'status'          => $res->status(),
                        'auth_scheme'     => $scheme,
                        'tenancy_present' => $this->tenancy !== '',
                    ]);

                    return [
                        'ok'      => true,
                        'status'  => $res->status(),
                        'json'    => $json,
                        'message' => '',
                    ];
                }

                $msg = $this->extractErrorMessage($json, $res->body());

                Log::warning('[FACTUROTOPIA] PUT failed', [
                    'url'             => $url,
                    'status'          => $res->status(),
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'tenancy_value'   => $this->tenancy !== '' ? $this->tenancy : null,
                    'tenancy_header'  => $this->tenancyHeader,
                    'payload'         => $payload,
                    'body'            => $json,
                ]);

                $lastJson = $json;
                $lastStatus = $res->status();
                $lastMessage = $msg;

                $unauth = strtolower((string) data_get($json, 'code', '')) === 'unauthenticated'
                    || $res->status() === 401
                    || str_contains(strtolower($msg), 'unauthenticated');

                if ($unauth) {
                    continue;
                }

                return [
                    'ok'      => false,
                    'status'  => $res->status(),
                    'json'    => $json,
                    'message' => $msg,
                ];
            } catch (Throwable $e) {
                Log::error('[FACTUROTOPIA] PUT exception', [
                    'url'             => $url,
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'error'           => $e->getMessage(),
                ]);

                $lastJson = [];
                $lastStatus = 0;
                $lastMessage = $this->normalizeThrowableMessage($e);
            }
        }

        return [
            'ok'      => false,
            'status'  => $lastStatus,
            'json'    => $lastJson,
            'message' => $lastMessage,
        ];
    }

    private function patch(string $uri, array $payload): array
    {
        $url = $this->buildUrl($uri);

        $lastJson = [];
        $lastStatus = 0;
        $lastMessage = 'Error no especificado de Facturotopia.';

        foreach ($this->authSchemes() as $scheme) {
            try {
                $res = $this->requestWithRetry('PATCH', $scheme, $url, $payload);

                $json = [];
                try {
                    $json = $res->json() ?: [];
                } catch (Throwable $e) {
                    $json = [];
                }

                if ($res->successful()) {
                    Log::info('[FACTUROTOPIA] PATCH ok', [
                        'url'             => $url,
                        'status'          => $res->status(),
                        'auth_scheme'     => $scheme,
                        'tenancy_present' => $this->tenancy !== '',
                    ]);

                    return [
                        'ok'      => true,
                        'status'  => $res->status(),
                        'json'    => $json,
                        'message' => '',
                    ];
                }

                $msg = $this->extractErrorMessage($json, $res->body());

                Log::warning('[FACTUROTOPIA] PATCH failed', [
                    'url'             => $url,
                    'status'          => $res->status(),
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'tenancy_value'   => $this->tenancy !== '' ? $this->tenancy : null,
                    'tenancy_header'  => $this->tenancyHeader,
                    'payload'         => $payload,
                    'body'            => $json,
                ]);

                $lastJson = $json;
                $lastStatus = $res->status();
                $lastMessage = $msg;

                $unauth = strtolower((string) data_get($json, 'code', '')) === 'unauthenticated'
                    || $res->status() === 401
                    || str_contains(strtolower($msg), 'unauthenticated');

                if ($unauth) {
                    continue;
                }

                return [
                    'ok'      => false,
                    'status'  => $res->status(),
                    'json'    => $json,
                    'message' => $msg,
                ];
            } catch (Throwable $e) {
                Log::error('[FACTUROTOPIA] PATCH exception', [
                    'url'             => $url,
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'error'           => $e->getMessage(),
                ]);

                $lastJson = [];
                $lastStatus = 0;
                $lastMessage = $this->normalizeThrowableMessage($e);
            }
        }

        return [
            'ok'      => false,
            'status'  => $lastStatus,
            'json'    => $lastJson,
            'message' => $lastMessage,
        ];
    }

    private function getBinary(string $uri): array
    {
        $url = $this->buildUrl($uri);

        $lastStatus = 0;
        $lastMessage = 'No se pudo descargar archivo de Facturotopia.';

        foreach ($this->authSchemes() as $scheme) {
            try {
                $res = $this->requestWithRetry('GET', $scheme, $url, null, true);

                if ($res->successful()) {
                    return [
                        'ok'      => true,
                        'status'  => $res->status(),
                        'body'    => $res->body(),
                        'headers' => $res->headers(),
                        'message' => '',
                    ];
                }

                $lastStatus = $res->status();
                $lastMessage = trim($res->body()) !== '' ? trim($res->body()) : 'No se pudo descargar archivo de Facturotopia.';

                if ($res->status() === 401) {
                    continue;
                }

                return [
                    'ok'      => false,
                    'status'  => $res->status(),
                    'body'    => '',
                    'headers' => [],
                    'message' => $lastMessage,
                ];
            } catch (Throwable $e) {
                Log::error('[FACTUROTOPIA] GET binary exception', [
                    'url'             => $url,
                    'auth_scheme'     => $scheme,
                    'tenancy_present' => $this->tenancy !== '',
                    'error'           => $e->getMessage(),
                ]);

                $lastStatus = 0;
                $lastMessage = $this->normalizeThrowableMessage($e);
            }
        }

        return [
            'ok'      => false,
            'status'  => $lastStatus,
            'body'    => '',
            'headers' => [],
            'message' => $lastMessage,
        ];
    }

    private function extractErrorMessage(array $json, string $fallbackBody = ''): string
    {
        $message = (string) (
            data_get($json, 'message')
            ?: data_get($json, 'error')
            ?: data_get($json, 'errors.0.message')
            ?: data_get($json, 'errors.0')
            ?: $fallbackBody
        );

        $message = trim($message);

        return $message !== '' ? $message : 'Error no especificado de Facturotopia.';
    }

    /**
     * =========================================================================
     * PARSERS
     * =========================================================================
     */

    /**
     * @param array<string,mixed> $json
     * @return array<string,mixed>
     */
    private function extractInvoiceData(array $json): array
    {
        $uuid = (string) (
            data_get($json, 'uuid')
            ?: data_get($json, 'data.uuid')
            ?: data_get($json, 'data.attributes.uuid')
            ?: data_get($json, 'cfdi_uuid')
            ?: data_get($json, 'data.cfdi_uuid')
            ?: ''
        );

        $pdfUrl = (string) (
            data_get($json, 'pdf_url')
            ?: data_get($json, 'data.pdf_url')
            ?: data_get($json, 'data.attributes.pdf_url')
            ?: data_get($json, 'links.pdf')
            ?: ''
        );

        $xmlUrl = (string) (
            data_get($json, 'xml_url')
            ?: data_get($json, 'data.xml_url')
            ?: data_get($json, 'data.attributes.xml_url')
            ?: data_get($json, 'links.xml')
            ?: ''
        );

        $remoteId = (string) (
            data_get($json, 'id')
            ?: data_get($json, 'data.id')
            ?: data_get($json, 'data.attributes.id')
            ?: ''
        );

        return [
            'uuid'      => trim($uuid),
            'pdf_url'   => trim($pdfUrl),
            'xml_url'   => trim($xmlUrl),
            'remote_id' => trim($remoteId),
            'raw'       => $json,
        ];
    }

    /**
     * @param array<string,mixed> $json
     * @return array<string,mixed>
     */
    private function extractEmisorData(array $json): array
    {
        $data = data_get($json, 'data');

        if (is_array($data)) {
            $src = $data;
        } else {
            $src = $json;
        }

        return [
            'id'           => (string) data_get($src, 'id', ''),
            'razon_social' => (string) data_get($src, 'razon_social', ''),
            'grupo'        => (string) data_get($src, 'grupo', ''),
            'grupo_id'     => (string) data_get($src, 'grupo_id', ''),
            'rfc'          => (string) data_get($src, 'rfc', ''),
            'email'        => (string) data_get($src, 'email', ''),
            'regimen'      => (string) data_get($src, 'regimen', ''),
            'direccion'    => data_get($src, 'direccion'),
            'status'       => (string) data_get($src, 'status', ''),
            'raw'          => $src,
           ];
    }

    /**
     * @param array<string,mixed> $json
     * @return array<int,array<string,mixed>>
     */
    private function extractEmisoresList(array $json): array
    {
        $candidates = [
            data_get($json, 'data'),
            data_get($json, 'items'),
            $json,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return array_map(function ($item) {
                    $src = is_array($item) ? $item : [];

                    return [
                        'id'           => (string) data_get($src, 'id', ''),
                        'razon_social' => (string) data_get($src, 'razon_social', ''),
                        'grupo'        => (string) data_get($src, 'grupo', ''),
                        'rfc'          => (string) data_get($src, 'rfc', ''),
                        'email'        => (string) data_get($src, 'email', ''),
                        'regimen'      => (string) data_get($src, 'regimen', ''),
                        'direccion'    => data_get($src, 'direccion'),
                        'status'       => (string) data_get($src, 'status', ''),
                        'raw'          => $src,
                    ];
                }, $candidate);
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $json
     * @return array<string,mixed>
     */
    private function extractReceptorData(array $json): array
    {
        $data = data_get($json, 'data');

        if (is_array($data)) {
            $src = $data;
        } else {
            $src = $json;
        }

        return [
            'id'           => (string) (data_get($src, 'id', data_get($json, 'id', ''))),
            'razon_social' => (string) data_get($src, 'razon_social', ''),
            'grupo'        => (string) data_get($src, 'grupo', ''),
            'grupo_id'     => (string) data_get($src, 'grupo_id', ''),
            'rfc'          => (string) data_get($src, 'rfc', ''),
            'email'        => (string) data_get($src, 'email', ''),
            'direccion'    => data_get($src, 'direccion'),
            'status'       => (string) data_get($src, 'status', ''),
            'raw'          => $json,
        ];
    }

    /**
     * @param array<string,mixed> $json
     * @return array<int,array<string,mixed>>
     */
    private function extractReceptoresList(array $json): array
    {
        $candidates = [
            data_get($json, 'data'),
            data_get($json, 'items'),
            $json,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return array_map(function ($item) {
                    $src = is_array($item) ? $item : [];

                    return [
                        'id'           => (string) data_get($src, 'id', ''),
                        'razon_social' => (string) data_get($src, 'razon_social', ''),
                        'grupo'        => (string) data_get($src, 'grupo', ''),
                        'grupo_id'     => (string) data_get($src, 'grupo_id', ''),
                        'rfc'          => (string) data_get($src, 'rfc', ''),
                        'email'        => (string) data_get($src, 'email', ''),
                        'direccion'    => data_get($src, 'direccion'),
                        'status'       => (string) data_get($src, 'status', ''),
                        'raw'          => $src,
                    ];
                }, $candidate);
            }
        }

        return [];
    }

    /**
     * =========================================================================
     * DESCARGAS CFDI
     * =========================================================================
     */

    private function downloadGeneratedFile(string $url, string $uuid, string $kind): ?string
    {
        $kind = strtolower($kind);
        if (!in_array($kind, ['pdf', 'xml'], true)) {
            return null;
        }

        if (trim($url) !== '') {
            try {
                $res = $this->requestWithRetry('GET', $this->authSchemes()[0] ?? 'Bearer', $url, null, true);

                if ($res->successful() && trim($res->body()) !== '') {
                    return $res->body();
                }
            } catch (Throwable $e) {
                Log::warning('[FACTUROTOPIA] direct file download failed', [
                    'kind'            => $kind,
                    'url'             => $url,
                    'tenancy_present' => $this->tenancy !== '',
                    'error'           => $this->normalizeThrowableMessage($e),
                ]);
            }
        }

        if (trim($uuid) === '') {
            return null;
        }

        $endpoints = $kind === 'pdf'
            ? [
                '/api/comprobantes/' . $uuid . '/pdf',
                '/api/comprobantes/' . $uuid . '/archivo/pdf',
            ]
            : [
                '/api/comprobantes/' . $uuid . '/xml',
                '/api/comprobantes/' . $uuid . '/archivo/xml',
            ];

        foreach ($endpoints as $uri) {
            $file = $this->getBinary($uri);
            if (($file['ok'] ?? false) === true && trim((string) ($file['body'] ?? '')) !== '') {
                return (string) $file['body'];
            }
        }

        return null;
    }

    /**
     * =========================================================================
     * RETRIES DE RED
     * =========================================================================
     */

    private function requestWithRetry(
        string $method,
        string $scheme,
        string $url,
        ?array $payload = null,
        bool $raw = false
    ): Response {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->networkRetryMaxAttempts) {
            $attempt++;

            try {
                $request = $this->httpWithScheme($scheme);

                $response = match (strtoupper($method)) {
                    'GET'   => $request->get($url),
                    'POST'  => $request->post($url, $payload ?? []),
                    'PUT'   => $request->put($url, $payload ?? []),
                    'PATCH' => $request->patch($url, $payload ?? []),
                    default => throw new \RuntimeException('Método HTTP no soportado: ' . $method),
                };

                if ($attempt > 1) {
                    Log::info('[FACTUROTOPIA] retry success', [
                        'method'       => strtoupper($method),
                        'url'          => $url,
                        'auth_scheme'  => $scheme,
                        'attempt'      => $attempt,
                        'status'       => $response->status(),
                    ]);
                }

                return $response;
            } catch (Throwable $e) {
                $lastException = $e;

                $shouldRetry = $this->shouldRetryThrowable($e);

                Log::warning('[FACTUROTOPIA] network retry candidate', [
                    'method'        => strtoupper($method),
                    'url'           => $url,
                    'auth_scheme'   => $scheme,
                    'attempt'       => $attempt,
                    'max_attempts'  => $this->networkRetryMaxAttempts,
                    'should_retry'  => $shouldRetry,
                    'error'         => $e->getMessage(),
                ]);

                if (!$shouldRetry || $attempt >= $this->networkRetryMaxAttempts) {
                    throw $e;
                }

                usleep($this->networkRetrySleepMs * 1000);
            }
        }

        throw $lastException instanceof Throwable
            ? $lastException
            : new \RuntimeException('Fallo de red no especificado hacia Facturotopia.');
    }

    private function shouldRetryThrowable(Throwable $e): bool
    {
        $message = strtolower(trim($e->getMessage()));

        if ($message === '') {
            return false;
        }

        return str_contains($message, 'could not resolve host')
            || str_contains($message, 'temporary failure in name resolution')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection reset by peer')
            || str_contains($message, 'operation timed out')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'network is unreachable')
            || str_contains($message, 'ssl connect error')
            || str_contains($message, 'recv failure')
            || str_contains($message, 'send failure')
            || str_contains($message, 'couldn\'t connect to server')
            || str_contains($message, 'server returned nothing');
    }

    private function normalizeThrowableMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        $lower   = strtolower($message);

        if (
            str_contains($lower, 'could not resolve host')
            || str_contains($lower, 'temporary failure in name resolution')
        ) {
            return 'No se pudo resolver el host de Facturotopia. Verifica DNS o vuelve a intentar en unos segundos.';
        }

        if (
            str_contains($lower, 'failed to connect')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'network is unreachable')
            || str_contains($lower, 'couldn\'t connect to server')
        ) {
            return 'No se pudo establecer conexión con Facturotopia. Verifica tu red y vuelve a intentar.';
        }

        if (
            str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'operation timed out')
        ) {
            return 'La conexión con Facturotopia excedió el tiempo de espera. Intenta nuevamente.';
        }

        return $message !== '' ? $message : 'Error de red no especificado hacia Facturotopia.';
    }
}