<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClienteQaNormalize extends Command
{
    protected $signature = 'cliente:qa-normalize
        {--password= : Password a asignar (por defecto QA_DEFAULT_PASSWORD o "pt NkB8e8S6B;")}
        {--all-users : Aplicar a todos los usuarios de cada cuenta (no sólo owners)}
        {--reset : Forzar re-hash para todos aunque ya tengan hash}
        {--force : Permitir ejecutarlo fuera de local/dev/testing}';

    protected $description = 'Normaliza datos para login por RFC en QA: activa cuentas/usuarios y unifica password.';

    /** Conexión de clientes */
    private string $cli = 'mysql_clientes';

    public function handle(): int
    {
        // Seguridad: por defecto sólo en local/dev/testing
        if (!app()->environment(['local','development','testing']) && !$this->option('force')) {
            $this->error('Este comando sólo corre en local/dev/testing. Usa --force bajo tu propio riesgo.');
            return self::FAILURE;
        }

        $plain = (string)($this->option('password')
            ?: env('QA_DEFAULT_PASSWORD')
            ?: 'pt NkB8e8S6B;'); // default cómodo en QA
        $this->info('Usando password: "'.$plain.'"');

        $applyToAllUsers = (bool)$this->option('all-users');
        $forceReset      = (bool)$this->option('reset');

        // Trae usuarios objetivo
        $q = DB::connection($this->cli)->table('usuarios_cuenta as u')
            ->join('cuentas_cliente as c', 'c.id', '=', 'u.cuenta_id')
            ->select(
                'u.id', 'u.cuenta_id', 'u.email', 'u.password', 'u.tipo', 'u.activo',
                'c.estado_cuenta', 'c.rfc_padre'
            );

        if (!$applyToAllUsers) {
            $q->where('u.tipo', 'owner'); // por defecto sólo owners
        }

        $rows = $q->get();
        if ($rows->isEmpty()) {
            $this->warn('No se encontraron usuarios para normalizar.');
            return self::SUCCESS;
        }

        $now = now();
        $affUsers   = 0;
        $affCuentas = 0;
        $cuentaIds  = [];

        foreach ($rows as $r) {
            $needsReset = $forceReset
                || empty($r->password)
                || strlen((string)$r->password) < 30
                || !str_starts_with((string)$r->password, '$2'); // esperamos bcrypt $2y$

            $upd = [
                'activo'               => 1,
                'must_change_password' => 0,
                'updated_at'           => $now,
            ];
            if ($needsReset) {
                $upd['password'] = Hash::make($plain);
            }

            $done = DB::connection($this->cli)->table('usuarios_cuenta')
                ->where('id', $r->id)
                ->update($upd);

            if ($done) {
                $affUsers += $done;
                $cuentaIds[] = $r->cuenta_id;
            }
        }

        // Activa las cuentas involucradas
        if (!empty($cuentaIds)) {
            $cuentaIds = array_values(array_unique($cuentaIds));
            $affCuentas = DB::connection($this->cli)->table('cuentas_cliente')
                ->whereIn('id', $cuentaIds)
                ->update(['estado_cuenta' => 'activo', 'updated_at' => $now]);
        }

        $this->table(['Métrica','Valor'], [
            ['Usuarios actualizados', $affUsers],
            ['Cuentas activadas',     $affCuentas],
        ]);

        $this->info('Normalización QA terminada.');
        return self::SUCCESS;
    }
}
