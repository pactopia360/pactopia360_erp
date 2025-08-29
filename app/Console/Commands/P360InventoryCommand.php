<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class P360InventoryCommand extends Command
{
    /**
     * Nombre del comando en Artisan.
     *
     * @var string
     */
    protected $signature = 'p360:inventory {--write= : Ruta del archivo JSON de salida}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Genera un inventario de rutas, migraciones, vistas y assets del proyecto Pactopia360.';

    /**
     * Ejecutar el comando.
     */
    public function handle()
    {
        $this->info('[P360] Generando inventario del proyecto...');

        // --- Rutas ---
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri'    => $route->uri(),
                'name'   => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
            ];
        })->values();

        // --- Migraciones ---
        $migrations = File::exists(database_path('migrations'))
            ? collect(File::allFiles(database_path('migrations')))
                ->map(fn($f) => $f->getRelativePathname())
            : collect();

        // --- Vistas ---
        $views = File::exists(resource_path('views'))
            ? collect(File::allFiles(resource_path('views')))
                ->map(fn($f) => $f->getRelativePathname())
            : collect();

        // --- Assets admin ---
        $assets = File::exists(public_path('assets/admin'))
            ? collect(File::allFiles(public_path('assets/admin')))
                ->map(fn($f) => $f->getRelativePathname())
            : collect();

        $inventory = [
            'timestamp'  => now()->toDateTimeString(),
            'routes'     => $routes,
            'migrations' => $migrations,
            'views'      => $views,
            'assets'     => $assets,
        ];

        // Si se pasa la opción --write guarda el JSON
        if ($path = $this->option('write')) {
            $fullPath = base_path($path);
            File::put($fullPath, json_encode($inventory, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            $this->info("[P360] Inventario escrito en: $fullPath");
        } else {
            $this->line(json_encode($inventory, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
