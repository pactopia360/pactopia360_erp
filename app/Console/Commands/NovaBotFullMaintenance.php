<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NovaBotFullMaintenance extends Command
{
    /**
     * Nombre del comando para Artisan.
     * Uso: php artisan novabot:full-maintenance
     */
    protected $signature = 'novabot:full-maintenance
        {--skip-migrate : Omite ejecutar migraciones}
        {--skip-seed : Omite ejecutar seeders}
        {--no-optimize-clear : Omite limpiar cachés (optimize:clear, config/route/view)}
        {--git : Muestra estado simple de Git al inicio y fin}
        {--force : Forzar operaciones peligrosas (respeta reglas de artisan)}
    ';

    protected $description = 'NovaBot: mantenimiento integral de Pactopia360 ERP (diagnóstico, cachés, migraciones multi-DB y seeders condicionales).';

    /** Inicio de la ejecución (para medir duración) */
    protected float $startTime;

    /** Conexiones nombradas según convención del proyecto */
    protected string $connAdmin = 'mysql_admin';
    protected string $connClientes = 'mysql_clientes';

    public function handle(): int
    {
        $this->startTime = microtime(true);

        $this->banner();

        if ($this->option('git')) {
            $this->section('⚙️ Git: estado inicial');
            $this->simpleGitStatus();
        }

        $this->section('🧪 Diagnóstico rápido del entorno');
        $this->diagnosticoEntorno();

        $this->section('🔌 Conexiones a base de datos');
        $okAdmin    = $this->probarConexion($this->connAdmin);
        $okClientes = $this->probarConexion($this->connClientes);

        if (! $okAdmin || ! $okClientes) {
            $this->warn('⚠️ NovaBot sugiere revisar las variables .env y config/database.php antes de continuar.');
        }

        if (! $this->option('no-optimize-clear')) {
            $this->section('🧹 Limpieza de cachés');
            $this->runArtisanSafe('optimize:clear');
            $this->runArtisanSafe('config:clear');
            $this->runArtisanSafe('route:clear');
            $this->runArtisanSafe('view:clear');
        } else {
            $this->comment('⏭️ Omitido por --no-optimize-clear');
        }

        $this->section('🗂 Migraciones por carpeta y conexión');
        if (! $this->option('skip-migrate')) {
            $this->migrarPorCarpeta();
        } else {
            $this->comment('⏭️ Migraciones omitidas por --skip-migrate');
        }

        $this->section('🌱 Seeders (opcionales y seguros)');
        if (! $this->option('skip-seed')) {
            $this->sembrarSiExiste();
        } else {
            $this->comment('⏭️ Seeders omitidos por --skip-seed');
        }

        $this->section('🧰 Verificaciones finales');
        $this->verificarPermisosEscritura();

        if ($this->option('git')) {
            $this->section('⚙️ Git: estado final');
            $this->simpleGitStatus();
        }

        $this->resumenFinal();

        return self::SUCCESS;
    }

    /* ===================== UTILIDADES ===================== */

    protected function banner(): void
    {
        $this->line('');
        $this->line($this->colorize('cyan', '╔══════════════════════════════════════════════════════════════╗'));
        $this->line($this->colorize('cyan', '║                 ') . $this->colorize('green', '🧠 NovaBot :: Pactopia360 ERP') . $this->colorize('cyan', '                  ║'));
        $this->line($this->colorize('cyan', '╠══════════════════════════════════════════════════════════════╣'));
        $this->line($this->colorize('cyan', '║  📌 Proyecto: ') . $this->colorize('yellow', 'pactopia360_erp') . str_repeat(' ', 35) . $this->colorize('cyan', '║'));
        $this->line($this->colorize('cyan', '║  📂 Ruta:     ') . $this->colorize('yellow', base_path()) . str_repeat(' ', max(0, 30 - strlen(base_path()))) . $this->colorize('cyan', '║'));
        $this->line($this->colorize('cyan', '║  🌐 Repo:     ') . $this->colorize('yellow', 'github.com/marcopadilla2719-ui/pactopia360_erp') . $this->colorize('cyan', '         ║'));
        $this->line($this->colorize('cyan', '╠══════════════════════════════════════════════════════════════╣'));
        $this->line($this->colorize('cyan', '║  🛠 Stack: PHP 8.3 + Laravel 12 + MySQL 9.x + Tailwind/Livewire     ║'));
        $this->line($this->colorize('cyan', '║  🗄 Bases: Admin: p360v1_admin | Clientes: p360v1_clientes          ║'));
        $this->line($this->colorize('cyan', '╚══════════════════════════════════════════════════════════════╝'));
        $this->line('');
    }

    protected function section(string $title): void
    {
        $this->line('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info($title);
    }

    protected function colorize(string $color, string $text): string
    {
        $colors = [
            'red' => '0;31', 'green' => '0;32', 'yellow' => '1;33',
            'blue' => '0;34', 'magenta' => '0;35', 'cyan' => '0;36', 'white' => '1;37',
        ];
        $code = $colors[$color] ?? '0';
        return "\033[" . $code . "m{$text}\033[0m";
    }

    protected function diagnosticoEntorno(): void
    {
        $php = PHP_VERSION;
        $laravel = app()->version();
        $env = app()->environment();
        $path = base_path();

        $this->line("• PHP:      <info>{$php}</info>");
        $this->line("• Laravel:  <info>{$laravel}</info>");
        $this->line("• Entorno:  <info>{$env}</info>");
        $this->line("• BasePath: <info>{$path}</info>");

        $exts = ['mbstring', 'openssl', 'pdo_mysql', 'ctype', 'json', 'curl', 'fileinfo', 'tokenizer', 'xml'];
        $faltantes = [];
        foreach ($exts as $ext) {
            if (!extension_loaded($ext)) {
                $faltantes[] = $ext;
            }
        }
        if ($faltantes) {
            $this->warn('⚠️ Extensiones PHP faltantes: ' . implode(', ', $faltantes));
        } else {
            $this->line('✅ Extensiones PHP requeridas: OK');
        }
    }

    protected function probarConexion(string $connection): bool
    {
        try {
            $db = DB::connection($connection);
            $dbname = $db->getDatabaseName();
            $version = $db->selectOne('select version() as v');
            $this->line("• {$connection} → BD: <info>{$dbname}</info> | MySQL: <info>" . ($version->v ?? 'desconocido') . '</info>');
            $db->select('select 1'); // prueba mínima
            $this->line("  └── ✅ Conexión OK");
            return true;
        } catch (\Throwable $e) {
            $this->error("  └── ❌ Error de conexión [{$connection}]: {$e->getMessage()}");
            return false;
        }
    }

    protected function migrarPorCarpeta(): void
    {
        $baseAdmin    = base_path('database/migrations/admin');
        $baseClientes = base_path('database/migrations/clientes');

        // Admin
        if (File::isDirectory($baseAdmin) && count(File::files($baseAdmin)) > 0) {
            $this->line('• Migrando carpeta <info>database/migrations/admin</info> en conexión <info>' . $this->connAdmin . '</info> …');
            $this->runArtisanSafe('migrate', [
                '--path'     => 'database/migrations/admin',
                '--database' => $this->connAdmin,
                '--force'    => $this->option('force'),
            ]);
        } else {
            $this->comment('⏭️ No se detectaron migraciones en database/migrations/admin');
        }

        // Clientes
        if (File::isDirectory($baseClientes) && count(File::files($baseClientes)) > 0) {
            $this->line('• Migrando carpeta <info>database/migrations/clientes</info> en conexión <info>' . $this->connClientes . '</info> …');
            $this->runArtisanSafe('migrate', [
                '--path'     => 'database/migrations/clientes',
                '--database' => $this->connClientes,
                '--force'    => $this->option('force'),
            ]);
        } else {
            $this->comment('⏭️ No se detectaron migraciones en database/migrations/clientes');
        }
    }

    protected function sembrarSiExiste(): void
    {
        // Por seguridad, solo ejecutamos si las clases existen.
        $seeders = [
            ['class' => '\\Database\\Seeders\\AdminSeeder',    'db' => $this->connAdmin,    'label' => 'AdminSeeder (admin)'],
            ['class' => '\\Database\\Seeders\\ClientesSeeder', 'db' => $this->connClientes, 'label' => 'ClientesSeeder (clientes)'],
        ];

        $ejecutado = false;

        foreach ($seeders as $s) {
            if (class_exists($s['class'])) {
                $this->line('• Ejecutando seeder <info>' . $s['label'] . '</info> …');
                $this->runArtisanSafe('db:seed', [
                    '--class'    => ltrim($s['class'], '\\'),
                    '--database' => $s['db'],
                    '--force'    => $this->option('force'),
                ]);
                $ejecutado = true;
            }
        }

        if (! $ejecutado) {
            $this->comment('⏭️ No se encontraron seeders disponibles (AdminSeeder / ClientesSeeder).');
        }
    }

    protected function verificarPermisosEscritura(): void
    {
        $paths = [
            storage_path(),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $p) {
            if (!File::exists($p)) {
                $this->warn("⚠️ Falta crear directorio: {$p}");
                continue;
            }
            if (!is_writable($p)) {
                $this->warn("⚠️ No escribible: {$p}");
            } else {
                $this->line("✅ OK: {$p}");
            }
        }
    }

    protected function resumenFinal(): void
    {
        $secs = microtime(true) - $this->startTime;
        $this->line('');
        $this->line($this->colorize('green', '✅ NovaBot: mantenimiento finalizado sin errores fatales.'));
        $this->line($this->colorize('green', '⏱ Duración: ') . number_format($secs, 2) . 's');
        $this->line($this->colorize('yellow', 'Sugerencias:'));
        $this->line('• Revisa tu app en: ' . $this->colorize('white', config('app.url', 'http://localhost')));
        $this->line('• Si faltan migraciones en admin/clientes, colócalas en sus carpetas y vuelve a ejecutar.');
        $this->line('');
        $this->line($this->colorize('cyan', '— 🧠 NovaBot siempre a tus órdenes.'));
        $this->line('');
    }

    protected function runArtisanSafe(string $command, array $params = []): void
    {
        try {
            // Usamos $this->call para ver output en tiempo real en consola.
            $exit = $this->call($command, $params);
            if ($exit === 0) {
                $this->line("  └── ✅ {$command} OK");
            } else {
                $this->warn("  └── ⚠️ {$command} terminó con código {$exit}");
            }
        } catch (\Throwable $e) {
            $this->error("  └── ❌ {$command} falló: {$e->getMessage()}");
        }
    }

    protected function simpleGitStatus(): void
    {
        // Herramienta mínima: no falla si no hay git disponible.
        try {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $cmd = $isWindows ? 'git --version' : 'git --version';
            @exec($cmd, $out, $rc);
            if ($rc !== 0) {
                $this->comment('⏭️ Git no disponible en PATH o no es un repo.');
                return;
            }

            @exec('git rev-parse --is-inside-work-tree 2>NUL', $inside, $rc2);
            if ($rc2 !== 0 || !isset($inside[0]) || trim($inside[0]) !== 'true') {
                $this->comment('⏭️ No es un repositorio Git.');
                return;
            }

            @exec('git branch --show-current 2>NUL', $branch, $rc3);
            @exec('git status --porcelain=v1 2>NUL', $status, $rc4);

            $b = isset($branch[0]) ? trim($branch[0]) : '(desconocida)';
            $this->line('• Rama actual: <info>' . $b . '</info>');
            if (empty($status)) {
                $this->line('• Working tree: <info>limpio</info>');
            } else {
                $this->warn('• Cambios sin commit:');
                foreach ($status as $s) {
                    $this->line('   - ' . $s);
                }
            }
        } catch (\Throwable $e) {
            $this->comment('⏭️ Omitido (Git): ' . $e->getMessage());
        }
    }
}
