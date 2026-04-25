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
            $user = Auth::guard('web')->user();

            $planRaw = strtolower((string) (
                $user->plan
                ?? $user->tipo_plan
                ?? $user->tipo_cuenta
                ?? $user->plan_actual
                ?? 'free'
            ));

            $isPro = in_array($planRaw, ['pro', 'premium', 'empresa', 'business', 'business_pro'], true);

            $summary = [
                'plan'       => $isPro ? 'PRO' : strtoupper($planRaw ?: 'FREE'),
                'plan_raw'   => $planRaw ?: 'free',
                'plan_norm'  => $isPro ? 'pro' : ($planRaw ?: 'free'),
                'is_pro'     => $isPro,
                'cycle'      => null,
                'estado'     => 'activa',
                'blocked'    => false,
                'timbres'    => 0,
                'billing'    => [],
                'amount_mxn' => 0,
            ];

            $accountFeatures = [
                'is_pro'             => $isPro,
                'blocked'            => false,

                'cfdi_manual'        => true,
                'cfdi_emitidos'      => true,
                'cfdi_descargas'     => true,
                'cfdi_cancelacion'   => true,
                'catalogos'          => true,

                'cfdi_masivo'        => $isPro,
                'excel_templates'    => $isPro,
                'batch_processing'   => $isPro,
                'nomina_masiva'      => $isPro,
                'cfdi_nomina_pro'    => $isPro,
                'rep_masivo'         => $isPro,
                'carta_porte_masiva' => $isPro,
                'api_integrations'   => $isPro,
                'automation_rules'   => $isPro,
            ];

            $view->with([
                'summary'         => $summary,
                'plan'            => $isPro ? 'PRO' : strtoupper($planRaw ?: 'FREE'),
                'planKey'         => $isPro ? 'pro' : ($planRaw ?: 'free'),
                'isPro'           => $isPro,
                'accountFeatures' => $accountFeatures,
            ]);
        });
    }
}