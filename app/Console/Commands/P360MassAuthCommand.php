<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class P360MassAuthCommand extends Command
{
    protected $signature = 'p360:clientes:mass-auth
        {--rfc= : RFC (o ID) de la cuenta padre; si omites y usas --all, afecta a todas}
        {--all : Afecta a todas las cuentas}
        {--password= : Contraseña temporal en texto plano a asignar (obligatoria)}
        {--only-owners : Solo a usuarios owner/propietario}
        {--activate-users : Marca usuarios como activos}
        {--activate-accounts : Marca cuentas como activas}
        {--verify-admin : En mysql_admin.accounts marca email/phone verificados y desbloquea}
        {--test : Tras aplicar, ejecuta verificación de login sobre los usuarios afectados}
        {--json : Devuelve resumen en JSON (además de la salida normal)}
        {--chunk=2000 : Tamaño de lote para procesar usuarios}
        {--force : Permite ejecutar fuera de local/dev/testing (no recomendado)}
    ';

    protected $description = 'Aplica password masivo (password_temp si existe; si no, password) y opcionalmente verifica logins para todos los usuarios afectados.';

    private string $connCli   = 'mysql_clientes';
    private string $connAdmin = 'mysql_admin';

    public function handle(): int
    {
        if (!app()->environment(['local', 'development', 'testing']) && !$this->option('force')) {
            $this->error('Por seguridad, este comando solo corre en local/dev/testing. Usa --force bajo tu propio riesgo.');
            return self::FAILURE;
        }

        $plain = (string) ($this->option('password') ?? '');
        if ($plain === '' || strlen($plain) < 6) {
            $this->error('Debes indicar --password con al menos 6 caracteres.');
            return self::FAILURE;
        }

        $isAll         = (bool) $this->option('all');
        $rfcIn         = (string) ($this->option('rfc') ?? '');
        $onlyOwners    = (bool) $this->option('only-owners');
        $activateUsers = (bool) $this->option('activate-users');
        $activateAccs  = (bool) $this->option('activate-accounts');
        $verifyAdmin   = (bool) $this->option('verify-admin');
        $doTest        = (bool) $this->option('test');
        $asJson        = (bool) $this->option('json');
        $chunk         = max(200, (int) $this->option('chunk')); // mínimo 200

        // -------- detectar columnas en usuarios_cuenta --------
        $cols = $this->detectUserColumns();
        if (!$cols['has_password_temp'] && !$cols['has_password']) {
            $this->error("Tu tabla usuarios_cuenta no tiene ni 'password_temp' ni 'password'. No se puede continuar.");
            return self::FAILURE;
        }
        $mode = $cols['has_password_temp'] ? 'password_temp' : 'password';
        $this->info("Modo de escritura: {$mode}" . ($mode === 'password' ? ' (no existe password_temp en tu esquema)' : ''));

        // -------- detectar columna RFC en cuentas_cliente --------
        $rfcCol = $this->detectRfcColumn($this->connCli);

        // -------- resolver cuentas objetivo --------
        $cuentasQ = DB::connection($this->connCli)->table('cuentas_cliente')
            ->select('id', $rfcCol.' as rfc_val', 'estado_cuenta', 'created_at', 'updated_at');

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

        $this->info("Cuentas objetivo: {$cuentas->count()}");

        $hash = Hash::make($plain);

        $summary = [
            'accounts_processed'     => 0,
            'users_updated'          => 0,
            'accounts_activated'     => 0,
            'admin_accounts_touched' => 0,
            'mode'                   => $mode,
            'test' => [
                'executed'      => false,
                'users_checked' => 0,
                'would_login'   => 0,
                'by_field'      => ['password' => 0, 'password_temp' => 0, 'password_plain' => 0],
            ],
        ];

        foreach ($cuentas as $c) {
            $summary['accounts_processed']++;

            // 1) usuarios de la cuenta
            $usersQ = DB::connection($this->connCli)->table('usuarios_cuenta')->where('cuenta_id', $c->id);
            if ($onlyOwners) {
                $usersQ->where(function ($q) {
                    $q->whereIn(DB::raw('LOWER(rol)'),  ['owner','dueño','propietario','admin_owner'])
                      ->orWhereIn(DB::raw('LOWER(tipo)'), ['owner','dueño','propietario','admin_owner']);
                });
            }
            $usersQ->orderBy('id');

            $usersQ->chunk($chunk, function($rows) use (&$summary, $hash, $activateUsers, $cols, $mode) {
                $ids = collect($rows)->pluck('id')->all();
                if (empty($ids)) return;

                $upd = ['updated_at' => now()];

                if ($mode === 'password_temp') {
                    $upd['password_temp'] = $hash;
                    if ($cols['has_password_plain']) $upd['password_plain'] = null;
                } else {
                    // escribir directo en password
                    $upd['password'] = $hash;
                    if ($cols['has_password_plain']) $upd['password_plain'] = null;
                    if ($cols['has_must_change_password']) $upd['must_change_password'] = 1;
                }

                if ($activateUsers) {
                    if ($cols['has_activo'])      $upd['activo']    = 1;
                    elseif ($cols['has_is_active']) $upd['is_active'] = 1;
                    elseif ($cols['has_status'])    $upd['status']    = 'activo';
                }

                DB::connection($this->connCli)->table('usuarios_cuenta')->whereIn('id', $ids)->update($upd);
                $summary['users_updated'] += count($ids);
            });

            // 2) activar cuenta
            if ($activateAccs && Schema::connection($this->connCli)->hasColumn('cuentas_cliente', 'estado_cuenta')) {
                DB::connection($this->connCli)->table('cuentas_cliente')
                    ->where('id', $c->id)
                    ->update(['estado_cuenta' => 'activo', 'updated_at' => now()]);
                $summary['accounts_activated']++;
            }

            // 3) verificar/desbloquear admin
            if ($verifyAdmin) {
                $touched = $this->touchAdminAccount($c->rfc_val);
                $summary['admin_accounts_touched'] += $touched ? 1 : 0;
            }

            // 4) test
            if ($doTest) {
                $summary['test']['executed'] = true;

                DB::connection($this->connCli)->table('usuarios_cuenta')
                    ->select('id','email',
                        $cols['has_password'] ? 'password' : DB::raw('NULL as password'),
                        $cols['has_password_temp'] ? 'password_temp' : DB::raw('NULL as password_temp'),
                        $cols['has_password_plain'] ? 'password_plain' : DB::raw('NULL as password_plain')
                    )
                    ->where('cuenta_id', $c->id)
                    ->orderBy('id')
                    ->chunk($chunk, function($chunkRows) use (&$summary, $plain) {
                        foreach ($chunkRows as $u) {
                            $summary['test']['users_checked']++;

                            $matchedField = $this->wouldLoginWith($u, $plain);
                            if ($matchedField) {
                                $summary['test']['would_login']++;
                                if (isset($summary['test']['by_field'][$matchedField])) {
                                    $summary['test']['by_field'][$matchedField]++;
                                }
                            }
                        }
                    });
            }
        }

        // salida
        $this->line('');
        $this->info('Resumen:');
        $this->line('  Modo:                ' . $summary['mode']);
        $this->line('  Cuentas procesadas:  ' . $summary['accounts_processed']);
        $this->line('  Usuarios actualizados: ' . $summary['users_updated']);
        if ($activateAccs)  $this->line('  Cuentas activadas:   ' . $summary['accounts_activated']);
        if ($verifyAdmin)   $this->line('  Admin acc. tocadas:  ' . $summary['admin_accounts_touched']);

        if ($summary['test']['executed']) {
            $this->line('');
            $this->info('Verificación (post-aplicación):');
            $this->line('  Usuarios verificados: ' . $summary['test']['users_checked']);
            $this->line('  Coincidencias login:  ' . $summary['test']['would_login']);
            $this->line('  Por campo: password=' . $summary['test']['by_field']['password']
                . ', password_temp=' . $summary['test']['by_field']['password_temp']
                . ', password_plain=' . $summary['test']['by_field']['password_plain']);
        }

        if ($asJson) {
            $this->line('');
            $this->line(json_encode(['ok'=>true,'summary'=>$summary], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    private function detectUserColumns(): array
    {
        $c = $this->connCli;
        $hasPwd       = Schema::connection($c)->hasColumn('usuarios_cuenta', 'password');
        $hasPwdTemp   = Schema::connection($c)->hasColumn('usuarios_cuenta', 'password_temp');
        $hasPwdPlain  = Schema::connection($c)->hasColumn('usuarios_cuenta', 'password_plain');
        $hasMcp       = Schema::connection($c)->hasColumn('usuarios_cuenta', 'must_change_password');
        $hasActivo    = Schema::connection($c)->hasColumn('usuarios_cuenta', 'activo');
        $hasIsActive  = Schema::connection($c)->hasColumn('usuarios_cuenta', 'is_active');
        $hasStatus    = Schema::connection($c)->hasColumn('usuarios_cuenta', 'status');

        return [
            'has_password'              => $hasPwd,
            'has_password_temp'         => $hasPwdTemp,
            'has_password_plain'        => $hasPwdPlain,
            'has_must_change_password'  => $hasMcp,
            'has_activo'                => $hasActivo,
            'has_is_active'             => $hasIsActive,
            'has_status'                => $hasStatus,
        ];
    }

    private function wouldLoginWith(object $u, string $plain): ?string
    {
        // password
        if (isset($u->password) && $u->password !== null && $u->password !== '') {
            $pwd = (string) $u->password;
            $ok  = (Str::startsWith($pwd, '$2y$') || Str::startsWith($pwd, '$argon2')) ? Hash::check($plain, $pwd) : hash_equals($pwd, $plain);
            if ($ok) return 'password';
        }
        // password_temp
        if (isset($u->password_temp) && $u->password_temp !== null && $u->password_temp !== '') {
            $tmp = (string) $u->password_temp;
            $ok  = (Str::startsWith($tmp, '$2y$') || Str::startsWith($tmp, '$argon2')) ? Hash::check($plain, $tmp) : hash_equals($tmp, $plain);
            if ($ok) return 'password_temp';
        }
        // password_plain
        if (isset($u->password_plain) && $u->password_plain !== null && $u->password_plain !== '') {
            if (hash_equals((string) $u->password_plain, $plain)) return 'password_plain';
        }
        return null;
    }

    private function detectRfcColumn(string $conn): string
    {
        foreach (['rfc_padre', 'rfc', 'rfc_cliente', 'tax_id'] as $c) {
            try { if (Schema::connection($conn)->hasColumn('cuentas_cliente', $c)) return $c; } catch (\Throwable $e) {}
        }
        return 'rfc_padre';
    }

    private function sanitizeRfc(string $raw): string
    {
        $u = Str::upper($raw);
        return preg_replace('/[^A-Z0-9&Ñ]+/u', '', $u) ?? '';
    }

    private function touchAdminAccount(?string $rfcVal): bool
    {
        $rfc = Str::upper((string) $rfcVal);
        if ($rfc === '') return false;

        $q = DB::connection($this->connAdmin)->table('accounts');
        $q->where(function($qq) use ($rfc) {
            $qq->whereRaw('UPPER(id)=?', [$rfc])
               ->orWhereRaw('UPPER(COALESCE(rfc, ""))=?', [$rfc]);
        });

        $upd = ['updated_at' => now()];
        if ($this->adminHas('email_verified_at')) $upd['email_verified_at'] = now();
        if ($this->adminHas('phone_verified_at')) $upd['phone_verified_at'] = now();
        if ($this->adminHas('is_blocked'))       $upd['is_blocked'] = 0;

        $count = $q->update($upd);
        return $count > 0;
    }

    private function adminHas(string $col): bool
    {
        try { return Schema::connection($this->connAdmin)->hasColumn('accounts', $col); }
        catch (\Throwable $e) { return false; }
    }
}
