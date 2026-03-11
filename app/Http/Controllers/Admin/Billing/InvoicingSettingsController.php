<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class InvoicingSettingsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(): View
    {
        $settings = $this->defaults();

        try {
            if (Schema::connection($this->adm)->hasTable('billing_settings')) {
                $rows = DB::connection($this->adm)
                    ->table('billing_settings')
                    ->pluck('value', 'key');

                foreach ($rows as $k => $v) {
                    $settings[(string) $k] = is_string($v) ? $v : (string) $v;
                }
            }
        } catch (Throwable $e) {
            // no romper la pantalla por errores de lectura
        }

        $resolved = $this->resolveEffectiveConfig($settings);

        return view('admin.billing.invoicing.settings.index', [
            'settings' => $settings,
            'resolved' => $resolved,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'facturotopia_mode'         => 'required|string|in:sandbox,production',
            'facturotopia_flow'         => 'required|string|in:api_comprobantes,xml_timbrado',
            'facturotopia_base'         => 'nullable|string|max:255',
            'facturotopia_api_key_test' => 'nullable|string|max:500',
            'facturotopia_api_key_live' => 'nullable|string|max:500',
            'facturotopia_emisor_id'    => 'nullable|string|max:120',
            'email_from'                => 'nullable|email:rfc,dns|max:255',
        ], [
            'facturotopia_mode.in' => 'El modo debe ser sandbox o production.',
            'facturotopia_flow.in' => 'El flujo debe ser api_comprobantes o xml_timbrado.',
        ]);

        if (!Schema::connection($this->adm)->hasTable('billing_settings')) {
            return back()->withErrors([
                'settings' => 'No existe la tabla billing_settings. Corre primero la migración correspondiente.',
            ])->withInput();
        }

        $payload = [
            'facturotopia_mode'         => trim((string) ($data['facturotopia_mode'] ?? 'sandbox')),
            'facturotopia_flow'         => trim((string) ($data['facturotopia_flow'] ?? 'api_comprobantes')),
            'facturotopia_base'         => $this->normalizeBaseUrl((string) ($data['facturotopia_base'] ?? '')),
            'facturotopia_api_key_test' => trim((string) ($data['facturotopia_api_key_test'] ?? '')),
            'facturotopia_api_key_live' => trim((string) ($data['facturotopia_api_key_live'] ?? '')),
            'facturotopia_emisor_id'    => trim((string) ($data['facturotopia_emisor_id'] ?? '')),
            'email_from'                => trim((string) ($data['email_from'] ?? '')),
        ];

        try {
            foreach ($payload as $key => $value) {
                DB::connection($this->adm)
                    ->table('billing_settings')
                    ->updateOrInsert(
                        ['key' => $key],
                        [
                            'value'      => $value !== '' ? $value : null,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
            }

            return back()->with('ok', 'Configuración guardada correctamente.');
        } catch (Throwable $e) {
            return back()->withErrors([
                'settings' => 'No se pudo guardar la configuración: ' . $e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * @return array<string,string>
     */
    private function defaults(): array
    {
        $sandboxBase = rtrim((string) data_get(config('services.facturotopia'), 'sandbox.base', 'https://api-demo.facturotopia.com'), '/');
        $prodBase    = rtrim((string) data_get(config('services.facturotopia'), 'production.base', 'https://api.facturotopia.com'), '/');

        return [
            'facturotopia_mode'         => (string) config('services.facturotopia.mode', 'sandbox'),
            'facturotopia_flow'         => 'api_comprobantes',
            'facturotopia_base'         => '',
            'facturotopia_api_key_test' => (string) (
                data_get(config('services.facturotopia'), 'sandbox.token')
                ?: config('services.facturotopia.api_key_test', '')
            ),
            'facturotopia_api_key_live' => (string) (
                data_get(config('services.facturotopia'), 'production.token')
                ?: config('services.facturotopia.api_key_live', '')
            ),
            'facturotopia_emisor_id'    => '',
            'email_from'                => (string) config('mail.from.address', ''),
            '__sandbox_base_default'    => $sandboxBase !== '' ? $sandboxBase : 'https://api-demo.facturotopia.com',
            '__production_base_default' => $prodBase !== '' ? $prodBase : 'https://api.facturotopia.com',
        ];
    }

    /**
     * @param array<string,string> $settings
     * @return array<string,string>
     */
    private function resolveEffectiveConfig(array $settings): array
    {
        $mode = trim((string) ($settings['facturotopia_mode'] ?? 'sandbox'));
        if (!in_array($mode, ['sandbox', 'production'], true)) {
            $mode = 'sandbox';
        }

        $base = trim((string) ($settings['facturotopia_base'] ?? ''));
        if ($base === '') {
            $base = $mode === 'production'
                ? (string) ($settings['__production_base_default'] ?? 'https://api.facturotopia.com')
                : (string) ($settings['__sandbox_base_default'] ?? 'https://api-demo.facturotopia.com');
        }

        $base = $this->normalizeBaseUrl($base);

        $apiKey = $mode === 'production'
            ? trim((string) ($settings['facturotopia_api_key_live'] ?? ''))
            : trim((string) ($settings['facturotopia_api_key_test'] ?? ''));

        return [
            'mode'      => $mode,
            'flow'      => trim((string) ($settings['facturotopia_flow'] ?? 'api_comprobantes')),
            'base'      => $base,
            'api_key'   => $apiKey,
            'emisor_id' => trim((string) ($settings['facturotopia_emisor_id'] ?? '')),
            'email_from'=> trim((string) ($settings['email_from'] ?? '')),
        ];
    }

    private function normalizeBaseUrl(string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            return '';
        }

        return rtrim($base, '/');
    }
}