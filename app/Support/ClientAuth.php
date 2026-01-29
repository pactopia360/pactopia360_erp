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
     * Resuelve el account_id (mysql_admin.accounts.id) para el cliente autenticado.
     *
     * Prioridad:
     * 1) Session keys (verify/paywall/client/account)
     * 2) Session cuenta_id -> mysql_clientes.cuentas_cliente -> admin_account_id/account_id/email/rfc
     * 3) Fallback: si hay email/rfc en sesión, buscar en mysql_admin.accounts
     *
     * @return array{0:int,1:string}  [accountId, src]
     */
    public static function resolveAdminAccountId(): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cx  = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        // 1) Candidates directos desde sesión
        $candidates = [
            Session::get('verify.account_id'),
            Session::get('paywall.account_id'),
            Session::get('client.account_id'),
            Session::get('account_id'),
            Session::get('client_account_id'),
        ];

        foreach ($candidates as $v) {
            $aid = (int) ($v ?? 0);
            if ($aid > 0) {
                return [$aid, 'session'];
            }
        }

        // 2) Resolver por cuenta_id
        $cuentaId = (string) (Session::get('client.cuenta_id') ?? Session::get('cuenta_id') ?? '');
        $cuentaId = trim($cuentaId);

        if ($cuentaId !== '' && Schema::connection($cx)->hasTable('cuentas_cliente')) {
            try {
                $cols = Schema::connection($cx)->getColumnListing('cuentas_cliente');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

                // Selección segura según columnas existentes
                $select = [];
                foreach (['id', 'email', 'rfc', 'admin_account_id', 'account_id', 'accountId'] as $c) {
                    if ($has($c)) $select[] = $c;
                }
                if (empty($select)) $select = ['id'];

                $row = DB::connection($cx)->table('cuentas_cliente')
                    ->where($has('id') ? 'id' : $cols[0], $cuentaId)
                    ->first($select);

                if ($row) {
                    // 2a) si ya trae admin_account_id/account_id (ideal)
                    $rawAid =
                        ($has('admin_account_id') ? ($row->admin_account_id ?? null) : null) ??
                        ($has('account_id') ? ($row->account_id ?? null) : null) ??
                        ($has('accountid') ? ($row->accountId ?? null) : null);

                    $aid = (int) ($rawAid ?? 0);
                    if ($aid > 0) {
                        self::rememberAdminAccountId($aid);
                        return [$aid, 'cuentas_cliente.direct'];
                    }

                    // 2b) lookup por email/rfc hacia mysql_admin.accounts
                    $email = $has('email') ? strtolower(trim((string) ($row->email ?? ''))) : '';
                    $rfc   = $has('rfc') ? strtoupper(trim((string) ($row->rfc ?? ''))) : '';

                    $aid = self::findAdminAccountIdByEmailOrRfc($adm, $email, $rfc);
                    if ($aid > 0) {
                        self::rememberAdminAccountId($aid);
                        return [$aid, 'cuentas_cliente.lookup'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ClientAuth][resolveAdminAccountId] cuentas_cliente error', [
                    'cuenta_id' => $cuentaId,
                    'e' => $e->getMessage(),
                ]);
            }
        }

        // 3) Fallback final por sesión (si existe)
        $email = strtolower(trim((string) (Session::get('client.email') ?? Session::get('email') ?? '')));
        $rfc   = strtoupper(trim((string) (Session::get('client.rfc') ?? Session::get('rfc') ?? '')));

        $aid = self::findAdminAccountIdByEmailOrRfc($adm, $email, $rfc);
        if ($aid > 0) {
            self::rememberAdminAccountId($aid);
            return [$aid, 'fallback.email_rfc'];
        }

        return [0, 'unresolved'];
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
