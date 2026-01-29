<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

final class ClientAuth
{
    /**
     * Normaliza el password de entrada:
     * - Convierte NBSP/ZWSP y espacios raros a espacio regular
     * - Normaliza saltos de línea a \n y luego elimina CR/LF
     * - Trim
     */
    public static function normalizePassword(string $input): string
    {
        // Reemplazos de espacios invisibles / raros
        $map = [
            "\u{00A0}" => ' ', // NBSP
            "\u{2007}" => ' ', // Figure space
            "\u{202F}" => ' ', // Narrow NBSP
            "\u{200B}" => '',  // ZWSP
            "\u{200C}" => '',  // ZWNJ
            "\u{200D}" => '',  // ZWJ
            "\u{FEFF}" => '',  // BOM
        ];
        $s = strtr($input, $map);

        // Normaliza saltos de línea y elimina CR/LF
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = str_replace("\n", '', $s);

        // Colapsa espacios consecutivos al interior (opcional)
        // $s = preg_replace('/[ \t]{2,}/u', ' ', $s);

        return trim($s);
    }

    /**
     * Hashea con el driver por defecto de Laravel (respetando config/hashing.php)
     * Siempre normaliza primero.
     */
    public static function make(string $plain): string
    {
        $norm = self::normalizePassword($plain);
        // Usamos el driver configurado (bcrypt por defecto en tu proyecto)
        return Hash::make($norm);
    }

    /**
     * Verifica un password contra un hash o texto plano heredado.
     * - $2y$ -> password_verify (bcrypt nativo de PHP)
     * - $argon2* -> Hash::check (driver auto)
     * - otro (sin prefijo) -> comparación estricta (legado)
     * Siempre normaliza el input antes de verificar.
     */
    public static function check(string $plain, string $stored): bool
    {
        $stored = (string) $stored;
        if ($stored === '') return false;

        $norm = self::normalizePassword($plain);

        // bcrypt
        if (Str::startsWith($stored, '$2y$')) {
            return password_verify($norm, $stored);
        }

        // argon / argon2id
        if (Str::startsWith($stored, '$argon2')) {
            try {
                return Hash::check($norm, $stored);
            } catch (\Throwable $e) {
                // Fallback por si el driver activo no coincide
                return Hash::driver('argon')->check($norm, $stored);
            }
        }

        // Texto plano legado
        return hash_equals($stored, $norm);
    }

    /**
     * Resuelve el admin_account_id (mysql_admin.accounts.id) desde el contexto cliente.
     *
     * Retorna: [int $adminAccountId, string $src]
     *
     * Estrategia (orden):
     * 1) Sesión: client.account_id / account_id
     * 2) Sesión: client.cuenta_id / cuenta_id -> mysql_clientes.cuentas_cliente.admin_account_id
     * 3) Auth user: email/rfc -> mysql_admin.accounts
     * 4) Sesión: email/rfc (si existiera) -> mysql_admin.accounts
     */
    public static function resolveAdminAccountId(): array
    {
        try {
            $sess = session();

            // 1) Ya resuelto y guardado
            $raw = $sess->get('client.account_id') ?? $sess->get('account_id');
            $aid = (int) ($raw ?? 0);
            if ($aid > 0) {
                return [$aid, 'session.account_id'];
            }

            // 2) Resolver por cuenta_id (UUID) -> cuentas_cliente.admin_account_id
            $cuentaId = (string) ($sess->get('client.cuenta_id') ?? $sess->get('cuenta_id') ?? '');
            if ($cuentaId !== '') {
                $cx = (string) config('p360.conn.clientes', 'mysql_clientes');

                // IMPORTANTE: no uses Schema aquí (route:cache safe y más rápido); sólo intenta query.
                try {
                    $row = \Illuminate\Support\Facades\DB::connection($cx)
                        ->table('cuentas_cliente')
                        ->select(['id', 'admin_account_id'])
                        ->where('id', $cuentaId)
                        ->first();

                    $aid2 = (int) ($row->admin_account_id ?? 0);
                    if ($aid2 > 0) {
                        self::rememberAdminAccountId($aid2);
                        return [$aid2, 'cuentas_cliente.direct'];
                    }
                } catch (\Throwable $e) {
                    // Si por alguna razón esa tabla no existe en ese entorno, seguimos con fallback.
                }
            }

            // 3) Fallback por Auth user (email/rfc) -> mysql_admin.accounts
            $email = '';
            $rfc   = '';
            try {
                $u = auth('web')->user();
                if ($u) {
                    $email = strtolower(trim((string) ($u->email ?? '')));
                    $rfc   = strtoupper(trim((string) ($u->rfc ?? '')));
                }
            } catch (\Throwable) {
                // ignore
            }

            // 4) Fallback por sesión (si se guardó en otro lado)
            if ($email === '') {
                $email = strtolower(trim((string) ($sess->get('client.email') ?? $sess->get('email') ?? '')));
            }
            if ($rfc === '') {
                $rfc = strtoupper(trim((string) ($sess->get('client.rfc') ?? $sess->get('rfc') ?? '')));
            }

            if ($email !== '' || $rfc !== '') {
                $aid3 = self::findAdminAccountIdByEmailOrRfc($email, $rfc);
                if ($aid3 > 0) {
                    self::rememberAdminAccountId($aid3);
                    return [$aid3, 'admin.accounts.by_email_or_rfc'];
                }
            }

            return [0, 'unresolved'];
        } catch (\Throwable $e) {
            return [0, 'error'];
        }
    }


    /**
     * Guarda el account_id resuelto en varios keys por compat.
     */
    private static function rememberAdminAccountId(int $aid): void
    {
        Session::put('client.account_id', $aid);
        Session::put('account_id', $aid);
        Session::put('client_account_id', $aid);
    }

    /**
     * Busca accounts.id en mysql_admin.accounts por RFC o email (si existen columnas).
     */
    private static function findAdminAccountIdByEmailOrRfc(string $admConn, string $email, string $rfc): int
    {
        if (!Schema::connection($admConn)->hasTable('accounts')) return 0;

        try {
            $cols = Schema::connection($admConn)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

            // Preferencia: RFC (más único)
            if ($rfc !== '' && $has('rfc')) {
                $id = (int) (DB::connection($admConn)->table('accounts')
                    ->whereRaw('UPPER(rfc)=?', [$rfc])
                    ->orderByDesc($has('id') ? 'id' : $cols[0])
                    ->value('id') ?? 0);

                if ($id > 0) return $id;
            }

            // Luego email
            if ($email !== '' && $has('email')) {
                $id = (int) (DB::connection($admConn)->table('accounts')
                    ->whereRaw('LOWER(email)=?', [$email])
                    ->orderByDesc($has('id') ? 'id' : $cols[0])
                    ->value('id') ?? 0);

                if ($id > 0) return $id;
            }
        } catch (\Throwable $e) {
            Log::warning('[ClientAuth][findAdminAccountIdByEmailOrRfc] error', [
                'email' => $email,
                'rfc' => $rfc,
                'e' => $e->getMessage(),
            ]);
        }

        return 0;
    }
}
