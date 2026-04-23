<?php

namespace App\Providers;

use App\Http\Controllers\Cliente\HomeController;
use App\Http\Middleware\AdminSessionConfig;
use App\Http\Middleware\ClientSessionConfig;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Sanctum\PersonalAccessToken;
use App\Services\Sat\Providers\Provider2Stub;
use App\Services\Sat\Providers\SatDownloadProviderInterface;
use App\Services\Sat\Providers\SatProviderRegistry;
use App\Services\Sat\Providers\SatWsProvider;
use App\Services\Sat\SatDownloadBalancer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([SatWsProvider::class, Provider2Stub::class], 'sat.download.providers');

        $this->app->singleton(SatProviderRegistry::class, function ($app) {
            return new SatProviderRegistry($app->tagged('sat.download.providers'));
        });

        $this->app->singleton(
            SatDownloadBalancer::class,
            fn ($app) => new SatDownloadBalancer($app->make(SatProviderRegistry::class))
        );
    }

    public function boot(): void
    {
        Route::aliasMiddleware('account.active', EnsureAccountIsActive::class);
        Route::aliasMiddleware('session.cliente', ClientSessionConfig::class);
        Route::aliasMiddleware('session.admin', AdminSessionConfig::class);

        Schema::defaultStringLength(191);

        // ✅ Cargar migraciones separadas (admin / clientes)
        $this->loadMigrationsFrom([
            database_path('migrations_admin'),
            database_path('migrations_clientes'),
        ]);

        // 🔥 Sanctum usando conexión clientes
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /**
         * ==========================================================
         * RESUMEN GLOBAL DE CUENTA PARA TODO EL PORTAL CLIENTE
         * ==========================================================
         *
         * Objetivo:
         * - No depender de que cada controlador mande $summary/$plan/$isPro
         * - Unificar FREE / PRO / blocked / timbres en todos los módulos
         * - Hacer que los cambios de Admin se reflejen en vivo en portal cliente
         *
         * Variables compartidas:
         * - $summary
         * - $plan
         * - $planKey
         * - $isPro
         * - $accountFeatures
         */
        View::composer(['cliente.*', 'layouts.cliente'], function ($view) {
            $summary = [
                'plan'       => 'free',
                'plan_raw'   => 'free',
                'plan_norm'  => 'free',
                'is_pro'     => false,
                'cycle'      => null,
                'estado'     => 'activa',
                'blocked'    => false,
                'timbres'    => 0,
                'billing'    => [],
                'amount_mxn' => 0,
            ];

            $plan = 'FREE';
            $planKey = 'free';
            $isPro = false;

            try {
                /**
                 * Solo intentar resolver summary si hay usuario autenticado del portal cliente.
                 * El proyecto ya usa guard web en cliente/Home y Facturación.
                 */
                $user = Auth::guard('web')->user();

                if ($user) {
                    /** @var \App\Http\Controllers\Cliente\HomeController $home */
                    $home = app(HomeController::class);

                    if (method_exists($home, 'buildAccountSummary')) {
                        $resolved = $home->buildAccountSummary();

                        if (is_array($resolved) && !empty($resolved)) {
                            $summary = array_merge($summary, $resolved);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // No romper vistas cliente si algo falla al resolver el resumen.
                report($e);
            }

            $summaryPlanNorm = strtolower((string) ($summary['plan_norm'] ?? $summary['plan'] ?? 'free'));

            $isPro = array_key_exists('is_pro', $summary)
                ? (bool) $summary['is_pro']
                : in_array($summaryPlanNorm, ['pro', 'premium', 'empresa', 'business'], true);

            if ($isPro) {
                $plan = 'PRO';
                $planKey = 'pro';
            } else {
                $plan = strtoupper((string) ($summary['plan'] ?? 'FREE'));
                $plan = $plan !== '' ? $plan : 'FREE';
                $planKey = strtolower((string) ($summary['plan_norm'] ?? $summary['plan'] ?? 'free'));
            }

            /**
             * Feature flags globales para TODO el portal cliente.
             * Regla de negocio:
             * - Todo lo masivo solo PRO
             * - Nómina masiva solo PRO
             * - Excel / plantillas / lotes solo PRO
             */
            $accountFeatures = [
                'is_pro'            => $isPro,
                'blocked'           => (bool) ($summary['blocked'] ?? false),

                // base
                'cfdi_manual'       => true,
                'cfdi_emitidos'     => true,
                'cfdi_descargas'    => true,
                'cfdi_cancelacion'  => true,
                'catalogos'         => true,

                // pro only
                'cfdi_masivo'       => $isPro,
                'excel_templates'   => $isPro,
                'batch_processing'  => $isPro,
                'nomina_masiva'     => $isPro,
                'cfdi_nomina_pro'   => $isPro,
                'rep_masivo'        => $isPro,
                'carta_porte_masiva'=> $isPro,
                'api_integrations'  => $isPro,
                'automation_rules'  => $isPro,
            ];

            $view->with([
                'summary'         => $summary,
                'plan'            => $plan,
                'planKey'         => $planKey,
                'isPro'           => $isPro,
                'accountFeatures' => $accountFeatures,
            ]);
        });
    }
}