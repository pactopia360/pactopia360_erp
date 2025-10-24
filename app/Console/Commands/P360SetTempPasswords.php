<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class P360SetTempPasswords extends Command
{
    protected $signature = 'p360:clientes:set-temp-passwords
        {--rfc= : RFC (o ID) de la cuenta padre a afectar; si se omite y se usa --all, aplicará a todas}
        {--all : Aplica a todas las cuentas (ignora --rfc si está vacío)}
        {--password= : Contraseña temporal en texto plano a asignar}
        {--only-owners : Solo propietarios/owners}
        {--activate-users : Marca usuarios como activos}
        {--activate-accounts : Marca cuentas como activas}
        {--chunk=500 : Tamaño de lote para actualizar}
    ';

    protected $description = 'Asigna password_temp (hash) a usuarios por RFC (o a todos), con opciones para activar usuarios/cuentas.';

    public function handle(): int
    {
        $conn = 'mysql_clientes';

        // Validaciones básicas
        $plain = (string) ($this->option('password') ?? '');
        if ($plain === '') {
            $this->error('Debes indicar --password="MiPasswordTemporal"');
            return self::FAILURE;
        }
        if (strlen($plain) < 6) {
            $this->error('La contraseña temporal debe tener al menos 6 caracteres.');
            return self::FAILURE;
        }

        $isAll   = (bool) $this->option('all');
        $rfcIn   = (string) ($this->option('rfc') ?? '');
        $chunk   = (int) ($this->option('chunk') ?? 500);
        $owners  = (bool) $this->option('only-owners');
        $actUsers= (bool) $this->option('activate-users');
        $actAccs = (bool) $this->option('activate-accounts');

        // Detectar columna RFC en cuentas_cliente (rfc_padre|rfc|rfc_cliente|tax_id)
        $rfcCol = $this->detectRfcColumn($conn);

        // Resolver cuentas a afectar
        $cuentasQ = DB::connection($conn)->table('cuentas_cliente')->select('id', $rfcCol.' as rfc_padre', 'estado_cuenta', 'created_at', 'updated_at');

        if (!$isAll) {
            if ($rfcIn === '') {
                $this->error('Debes pasar --rfc=... o usar --all');
                return self::FAILURE;
            }
            $rfcUpper = Str::upper($rfcIn);
            $rfcSan   = $this->sanitizeRfc($rfcUpper);
            $cuentasQ->where(function($q) use ($rfcCol, $rfcUpper, $rfcSan) {
                $q->whereRaw("UPPER($rfcCol)=?", [$rfcUpper])
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER('.$rfcCol.')," ",""),"-",""),"_",""),".",""),"/","") = ?', [$rfcSan]);
            });
        }

        $cuentas = $cuentasQ->get();
        if ($cuentas->isEmpty()) {
            $this->warn('No se encontraron cuentas con los criterios proporcionados.');
            return self::SUCCESS;
        }

        $this->info("Cuentas a procesar: {$cuentas->count()}");
        $affectedUsers = 0;
        $affectedAccs  = 0;

        $hash = Hash::make($plain);

        // Procesar cuentas
        foreach ($cuentas as $c) {
            // Seleccionar usuarios de la cuenta
            $usersQ = DB::connection($conn)->table('usuarios_cuenta')->where('cuenta_id', $c->id);
            if ($owners) {
                $usersQ->where(function ($q) {
                    $q->whereIn(DB::raw('LOWER(rol)'), ['owner','dueño','propietario','admin_owner'])
                      ->orWhereIn(DB::raw('LOWER(tipo)'), ['owner','dueño','propietario','admin_owner']);
                });
            }

            $usersQ->orderBy('id');

            $usersQ->chunkById($chunk, function($chunkRows) use (&$affectedUsers, $conn, $hash, $actUsers) {
                $ids = $chunkRows->pluck('id')->all();
                if (empty($ids)) return;

                // Actualiza password_temp y limpia password_plain (si existe)
                $upd = [
                    'password_temp' => $hash,
                    'updated_at'    => now(),
                ];

                // Si la columna password_plain existe, la limpiamos
                if (Schema::connection($conn)->hasColumn('usuarios_cuenta', 'password_plain')) {
                    $upd['password_plain'] = null;
                }

                if ($actUsers) {
                    // Activar usuarios si hay columna correspondiente
                    if (Schema::connection($conn)->hasColumn('usuarios_cuenta', 'activo')) {
                        $upd['activo'] = 1;
                    } elseif (Schema::connection($conn)->hasColumn('usuarios_cuenta', 'is_active')) {
                        $upd['is_active'] = 1;
                    } elseif (Schema::connection($conn)->hasColumn('usuarios_cuenta', 'status')) {
                        $upd['status'] = 'activo';
                    }
                }

                DB::connection($conn)->table('usuarios_cuenta')->whereIn('id', $ids)->update($upd);
                $affectedUsers += count($ids);
            }, 'id');

            // Activar cuenta si se pidió
            if ($actAccs && Schema::connection($conn)->hasColumn('cuentas_cliente', 'estado_cuenta')) {
                DB::connection($conn)->table('cuentas_cliente')
                    ->where('id', $c->id)
                    ->update(['estado_cuenta' => 'activo', 'updated_at' => now()]);
                $affectedAccs++;
            }
        }

        $this->info("Usuarios actualizados (password_temp): {$affectedUsers}");
        if ($actAccs) {
            $this->info("Cuentas activadas: {$affectedAccs}");
        }
        $this->line('Listo. Ahora inicia sesión con el RFC + la contraseña temporal.');
        $this->line('En el primer login, se promoverá a password y se pedirá cambio de contraseña.');

        return self::SUCCESS;
    }

    private function detectRfcColumn(string $conn): string
    {
        foreach (['rfc_padre', 'rfc', 'rfc_cliente', 'tax_id'] as $c) {
            try {
                if (Schema::connection($conn)->hasColumn('cuentas_cliente', $c)) return $c;
            } catch (\Throwable $e) {}
        }
        return 'rfc_padre';
    }

    private function sanitizeRfc(string $raw): string
    {
        $u = Str::upper($raw);
        return preg_replace('/[^A-Z0-9&Ñ]+/u', '', $u) ?? '';
    }
}
