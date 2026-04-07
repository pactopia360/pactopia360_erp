<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Admin\SatDiscountCode;
use App\Models\Admin\SatPriceRule;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\SatUserCfdi;
use App\Models\Cliente\SatUserMetadataUpload;
use App\Models\Cliente\SatUserReportUpload;
use App\Models\Cliente\SatUserXmlUpload;
use App\Models\Cliente\VaultFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

final class SatOpsController extends Controller
{
    public function index(Request $request): View
    {
        $now = now();
        $last30 = $now->copy()->subDays(30);

        /*
        |--------------------------------------------------------------------------
        | KPIs reales del dashboard
        |--------------------------------------------------------------------------
        */
        $kpis = [
            'rfcs_total' => $this->safeCount(fn () => SatCredential::query()->count()),
            'rfcs_validos' => $this->safeCount(function () {
                return SatCredential::query()
                    ->get()
                    ->filter(fn (SatCredential $row) => $this->isCredentialValidated($row))
                    ->count();
            }),

            'credenciales_total' => $this->safeCount(fn () => SatCredential::query()->count()),
            'credenciales_con_archivos' => $this->safeCount(function () {
                return SatCredential::query()
                    ->get()
                    ->filter(fn (SatCredential $row) => $this->hasCredentialFiles($row))
                    ->count();
            }),

            'descargas_total' => $this->safeCount(fn () => SatDownload::query()->count()),
            'descargas_30d' => $this->safeCount(fn () => SatDownload::query()->where('created_at', '>=', $last30)->count()),

            'metadata_total' => $this->safeCount(fn () => SatUserMetadataUpload::query()->count()),
            'xml_total' => $this->safeCount(fn () => SatUserXmlUpload::query()->count()),
            'reportes_total' => $this->safeCount(fn () => SatUserReportUpload::query()->count()),
            'cfdi_total' => $this->safeCount(fn () => SatUserCfdi::query()->count()),
            'vault_total' => $this->safeCount(fn () => VaultFile::query()->count()),

            'precios_total' => $this->safeCount(fn () => SatPriceRule::query()->count()),
            'precios_activos' => $this->safeCount(fn () => SatPriceRule::query()->where('active', 1)->count()),

            'descuentos_total' => $this->safeCount(fn () => SatDiscountCode::query()->count()),
            'descuentos_activos' => $this->safeCount(fn () => SatDiscountCode::query()->where('active', 1)->count()),
        ];

        /*
        |--------------------------------------------------------------------------
        | Resumen superior
        |--------------------------------------------------------------------------
        */
        $summary = [
            [
                'label' => 'RFCs',
                'value' => number_format($kpis['rfcs_total']),
                'hint'  => number_format($kpis['rfcs_validos']) . ' validados',
            ],
            [
                'label' => 'Credenciales',
                'value' => number_format($kpis['credenciales_total']),
                'hint'  => number_format($kpis['credenciales_con_archivos']) . ' con archivos',
            ],
            [
                'label' => 'Descargas',
                'value' => number_format($kpis['descargas_total']),
                'hint'  => number_format($kpis['descargas_30d']) . ' últimos 30 días',
            ],
            [
                'label' => 'Cobertura SAT',
                'value' => 'ADMIN',
                'hint'  => 'Operación interna',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Tarjetas del dashboard
        |--------------------------------------------------------------------------
        */
        $cards = [
            [
                'title'   => 'RFC Master',
                'desc'    => 'Alta, edición, seguimiento y control operativo de RFCs administrativos.',
                'icon'    => '🧾',
                'tone'    => 'blue',
                'url'     => $this->routeOrHash('admin.sat.ops.rfcs.index'),
                'action'  => 'Administrar RFC',
                'enabled' => Route::has('admin.sat.ops.rfcs.index'),
                'metric'  => number_format($kpis['rfcs_total']),
                'meta'    => number_format($kpis['rfcs_validos']) . ' validados',
            ],
            [
                'title'   => 'FIEL / Credenciales',
                'desc'    => 'Control de CER, KEY, validación, descarga y limpieza de credenciales SAT.',
                'icon'    => '🔐',
                'tone'    => 'violet',
                'url'     => $this->routeOrHash('admin.sat.ops.credentials.index'),
                'action'  => 'Ver credenciales',
                'enabled' => Route::has('admin.sat.ops.credentials.index'),
                'metric'  => number_format($kpis['credenciales_total']),
                'meta'    => number_format($kpis['credenciales_con_archivos']) . ' con archivos',
            ],
            [
                'title'   => 'Descargas',
                'desc'    => 'Operación de metadata, XML, reportes, CFDI indexados y archivos de descarga.',
                'icon'    => '📦',
                'tone'    => 'emerald',
                'url'     => $this->routeOrHash('admin.sat.ops.downloads.index'),
                'action'  => 'Ir a descargas',
                'enabled' => Route::has('admin.sat.ops.downloads.index'),
                'metric'  => number_format($kpis['descargas_total']),
                'meta'    => number_format($kpis['descargas_30d']) . ' últimos 30 días',
            ],
            [
                'title'   => 'Precios SAT',
                'desc'    => 'Reglas de precio activas, rangos, tarifa plana y estructura de cobro.',
                'icon'    => '💳',
                'tone'    => 'amber',
                'url'     => $this->routeOrHash('admin.sat.prices.index'),
                'action'  => 'Configurar precios',
                'enabled' => Route::has('admin.sat.prices.index'),
                'metric'  => number_format($kpis['precios_total']),
                'meta'    => number_format($kpis['precios_activos']) . ' activas',
            ],
            [
                'title'   => 'Descuentos',
                'desc'    => 'Códigos promocionales, reglas de vigencia, alcance y activación.',
                'icon'    => '🏷️',
                'tone'    => 'rose',
                'url'     => $this->routeOrHash('admin.sat.discounts.index'),
                'action'  => 'Gestionar descuentos',
                'enabled' => Route::has('admin.sat.discounts.index'),
                'metric'  => number_format($kpis['descuentos_total']),
                'meta'    => number_format($kpis['descuentos_activos']) . ' activos',
            ],
            [
                'title'   => 'Pagos SAT',
                'desc'    => 'Seguimiento administrativo del módulo de pagos y cobros relacionados.',
                'icon'    => '💰',
                'tone'    => 'sky',
                'url'     => $this->routeOrHash('admin.sat.ops.payments.index'),
                'action'  => 'Ver pagos',
                'enabled' => Route::has('admin.sat.ops.payments.index'),
                'metric'  => number_format($kpis['descargas_total']),
                'meta'    => 'Operación SAT',
            ],
            [
                'title'   => 'Solicitudes manuales',
                'desc'    => 'Gestión de solicitudes manuales, especiales o fuera del flujo automático.',
                'icon'    => '🛠️',
                'tone'    => 'slate',
                'url'     => $this->routeOrHash('admin.sat.ops.manual.index'),
                'action'  => 'Abrir módulo',
                'enabled' => Route::has('admin.sat.ops.manual.index'),
                'metric'  => number_format($kpis['metadata_total'] + $kpis['xml_total'] + $kpis['reportes_total']),
                'meta'    => 'Cargas procesables',
            ],
            [
                'title'   => 'Accesos a Bóveda',
                'desc'    => 'Control de accesos, asignación de usuarios y relación con bóveda SAT.',
                'icon'    => '📚',
                'tone'    => 'indigo',
                'url'     => $this->routeOrHash('admin.billing.vault_access.index'),
                'action'  => 'Administrar accesos',
                'enabled' => Route::has('admin.billing.vault_access.index'),
                'metric'  => number_format($kpis['vault_total']),
                'meta'    => 'Archivos en bóveda',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Cobertura operativa
        |--------------------------------------------------------------------------
        */
        $coverage = [
            [
                'name' => 'RFC / cuentas SAT',
                'pct'  => $kpis['rfcs_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['rfcs_total']),
            ],
            [
                'name' => 'FIEL / credenciales',
                'pct'  => $kpis['credenciales_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['credenciales_total']),
            ],
            [
                'name' => 'Metadata',
                'pct'  => $kpis['metadata_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['metadata_total']),
            ],
            [
                'name' => 'XML CFDI',
                'pct'  => $kpis['xml_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['xml_total']),
            ],
            [
                'name' => 'Reportes',
                'pct'  => $kpis['reportes_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['reportes_total']),
            ],
            [
                'name' => 'CFDI indexados',
                'pct'  => $kpis['cfdi_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['cfdi_total']),
            ],
            [
                'name' => 'Precios SAT',
                'pct'  => $kpis['precios_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['precios_total']),
            ],
            [
                'name' => 'Descuentos',
                'pct'  => $kpis['descuentos_total'] > 0 ? 100 : 0,
                'value'=> number_format($kpis['descuentos_total']),
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Bloques de actividad
        |--------------------------------------------------------------------------
        */
        $activity = [
            [
                'label' => 'Metadata',
                'value' => number_format($kpis['metadata_total']),
                'hint'  => 'Cargas metadata',
            ],
            [
                'label' => 'XML',
                'value' => number_format($kpis['xml_total']),
                'hint'  => 'Cargas XML',
            ],
            [
                'label' => 'Reportes',
                'value' => number_format($kpis['reportes_total']),
                'hint'  => 'Reportes cargados',
            ],
            [
                'label' => 'CFDI',
                'value' => number_format($kpis['cfdi_total']),
                'hint'  => 'CFDI indexados',
            ],
            [
                'label' => 'Bóveda',
                'value' => number_format($kpis['vault_total']),
                'hint'  => 'Archivos resguardados',
            ],
        ];

        return view('admin.sat.ops.index', [
            'summary'  => $summary,
            'cards'    => $cards,
            'coverage' => $coverage,
            'activity' => $activity,
            'kpis'     => $kpis,
            'generatedAt' => Carbon::now(),
        ]);
    }

    private function routeOrHash(string $name): string
    {
        return Route::has($name) ? route($name) : '#';
    }

    private function safeCount(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function isCredentialValidated(SatCredential $row): bool
    {
        $meta = is_array($row->meta) ? $row->meta : [];

        $estatus = strtolower((string) ($row->estatus ?? ''));
        $estatusOperativo = strtolower((string) ($row->estatus_operativo ?? ($meta['estatus_operativo'] ?? '')));

        return !empty($row->validado)
            || !empty($row->validated_at)
            || in_array($estatus, ['ok', 'valido', 'válido', 'validado', 'valid', 'activo', 'active'], true)
            || in_array($estatusOperativo, ['validated', 'validado', 'activo', 'active'], true);
    }

    private function hasCredentialFiles(SatCredential $row): bool
    {
        $hasLegacyFiles = filled($row->cer_path ?? null) && filled($row->key_path ?? null);

        $hasFiel = (
            filled($row->fiel_cer_path ?? null)
            && filled($row->fiel_key_path ?? null)
            && filled($row->fiel_password_enc ?? null)
        ) || $hasLegacyFiles;

        $hasCsd = (
            filled($row->csd_cer_path ?? null)
            && filled($row->csd_key_path ?? null)
        );

        return $hasFiel || $hasCsd || $hasLegacyFiles;
    }
}