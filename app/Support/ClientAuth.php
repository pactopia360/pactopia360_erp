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

        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = str_replace("\n", '', $s);

        return trim($s);
    }

    public static function make(string $plain): string
    {
        $norm = self::normalizePassword($plain);
        return Hash::make($norm);
    }

    public static function check(string $plain, string $stored): bool
    {
        $stored = (string) $stored;
        if ($stored === '') return false;

        $norm = self::normalizePassword($plain);

        if (Str::startsWith($stored, '$2y$')) {
            return password_verify($norm, $stored);
        }

        if (Str::startsWith($stored, '$argon2')) {
            try {
                return Hash::check($norm, $stored);
            } catch (\Throwable $e) {
                return Hash::driver('argon')->check($norm, $stored);
            }
        }

        return hash_equals($stored, $norm);
    }

    /**
     * Resuelve el admin_account_id (mysql_admin.accounts.id) desde el contexto cliente.
     *
     * Retorna: [int $adminAccountId, string $src]
     *
     * Estrategia (orden):
     * 1) Sesión: client.account_id / account_id
     * 2) Sesión: client.cuenta_id / cuenta_id:
     *    - si es numérico: mysql_clientes.cuentas_cliente.id -> admin_account_id
     *    - si NO es numérico (UUID): resolver por email/rfc contra cuentas_cliente
     * 3) Auth user (web): email/rfc -> cuentas_cliente.admin_account_id
     * 4) Fallback admin: mysql_admin.accounts por email/rfc
     */
    public static function resolveAdminAccountId(): array
    {
        try {
            $sess = session();

            // ---------------------------
            // 1) Ya resuelto en sesión
            // ---------------------------
            $raw = $sess->get('client.account_id') ?? $sess->get('account_id') ?? $sess->get('client_account_id');
            $aid = self::toInt($raw);
            if ($aid > 0) {
                return [$aid, 'session.account_id'];
            }

            // Conexiones (compat: clients vs clientes)
            $cliConn = (string) (config('p360.conn.clients')
                ?? config('p360.conn.clientes')
                ?? 'mysql_clientes');

            $admConn = (string) (config('p360.conn.admin')
                ?? config('p360.conn.admins')
                ?? 'mysql_admin');

            // ---------------------------
            // 2) Intentar por sesión client.cuenta_id / cuenta_id
            // ---------------------------
            $cuentaIdRaw = (string) ($sess->get('client.cuenta_id') ?? $sess->get('cuenta_id') ?? '');
            $cuentaIdRaw = trim($cuentaIdRaw);

            if ($cuentaIdRaw !== '') {
                // 2.A) Si es numérico: buscar por id directo
                if (ctype_digit($cuentaIdRaw)) {
                    $aid2 = self::findAdminAccountIdInCuentasClienteById($cliConn, (int) $cuentaIdRaw);
                    if ($aid2 > 0) {
                        self::rememberAdminAccountId($aid2);
                        return [$aid2, 'cuentas_cliente.id'];
                    }
                } else {
                    // 2.B) Si NO es numérico (UUID), NO intentes where(id=uuid) porque tu tabla NO tiene uuid.
                    // En su lugar: resolver por email/rfc (si podemos obtenerlos).
                    [$email, $rfc] = self::readEmailRfcFromAuthOrSession($sess);
                    $aid2b = self::findAdminAccountIdInCuentasClienteByEmailOrRfc($cliConn, $email, $rfc);

                    if ($aid2b > 0) {
                        self::rememberAdminAccountId($aid2b);
                        return [$aid2b, 'cuentas_cliente.by_email_or_rfc'];
                    }

                    // Log útil: esto explica EXACTO el síntoma que tienes
                    Log::warning('[ClientAuth] cuenta_id no numérico y no se pudo mapear por email/rfc', [
                        'cuenta_id_raw' => $cuentaIdRaw,
                        'email' => $email,
                        'rfc' => $rfc,
                        'cliConn' => $cliConn,
                    ]);
                }
            }

            // ---------------------------
            // 3) Auth user (web) -> cuentas_cliente por email/rfc
            // ---------------------------
            [$email, $rfc] = self::readEmailRfcFromAuthOrSession($sess);
            if ($email !== '' || $rfc !== '') {
                $aid3 = self::findAdminAccountIdInCuentasClienteByEmailOrRfc($cliConn, $email, $rfc);
                if ($aid3 > 0) {
                    self::rememberAdminAccountId($aid3);
                    return [$aid3, 'cuentas_cliente.auth_user'];
                }
            }

            // ---------------------------
            // 4) Fallback admin.accounts
            // ---------------------------
            if ($email !== '' || $rfc !== '') {
                $aid4 = self::findAdminAccountIdByEmailOrRfc($email, $rfc, $admConn);
                if ($aid4 > 0) {
                    self::rememberAdminAccountId($aid4);
                    return [$aid4, 'admin.accounts.by_email_or_rfc'];
                }
            }

            return [0, 'unresolved'];
        } catch (\Throwable $e) {
            Log::error('[ClientAuth] resolveAdminAccountId error', [
                'e' => $e->getMessage(),
            ]);
            return [0, 'error'];
        }
    }

    // =========================================================
    // Helpers
    // =========================================================

    private static function toInt(mixed $v): int
    {
        if ($v === null) return 0;
        if (is_int($v)) return $v > 0 ? $v : 0;
        if (is_numeric($v)) {
            $i = (int) $v;
            return $i > 0 ? $i : 0;
        }
        if (is_string($v)) {
            $v = trim($v);
            if ($v !== '' && is_numeric($v)) {
                $i = (int) $v;
                return $i > 0 ? $i : 0;
            }
        }
        return 0;
    }

    /**
     * Lee email/rfc desde auth('web') o sesión (compat).
     * Retorna: [email_lower, rfc_upper]
     */
    private static function readEmailRfcFromAuthOrSession($sess): array
    {
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

        if ($email === '') {
            $email = strtolower(trim((string) ($sess->get('client.email') ?? $sess->get('email') ?? '')));
        }
        if ($rfc === '') {
            $rfc = strtoupper(trim((string) ($sess->get('client.rfc') ?? $sess->get('rfc') ?? '')));
        }

        return [$email, $rfc];
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
     * mysql_clientes.cuentas_cliente -> admin_account_id por ID numérico.
     */
    private static function findAdminAccountIdInCuentasClienteById(string $cliConn, int $id): int
    {
        if ($id <= 0) return 0;

        try {
            // si no existe la tabla en ese conn, aborta rápido
            if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) return 0;

            $aid = (int) (DB::connection($cliConn)->table('cuentas_cliente')
                ->where('id', $id)
                ->value('admin_account_id') ?? 0);

            return $aid > 0 ? $aid : 0;
        } catch (\Throwable $e) {
            Log::warning('[ClientAuth] findAdminAccountIdInCuentasClienteById error', [
                'cliConn' => $cliConn,
                'id' => $id,
                'e' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * mysql_clientes.cuentas_cliente -> admin_account_id por email/rfc (tu tabla SÍ tiene columnas rfc/email).
     */
    private static function findAdminAccountIdInCuentasClienteByEmailOrRfc(string $cliConn, string $email, string $rfc): int
    {
        try {
            if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) return 0;

            $q = DB::connection($cliConn)->table('cuentas_cliente')->select(['admin_account_id']);

            $didWhere = false;

            // Preferir RFC (más único)
            if ($rfc !== '') {
                $q->whereRaw('UPPER(rfc)=?', [$rfc]);
                $didWhere = true;
            } elseif ($email !== '') {
                $q->whereRaw('LOWER(email)=?', [$email]);
                $didWhere = true;
            }

            if (!$didWhere) return 0;

            $row = $q->first();
            $aid = (int) ($row->admin_account_id ?? 0);

            return $aid > 0 ? $aid : 0;
        } catch (\Throwable $e) {
            Log::warning('[ClientAuth] findAdminAccountIdInCuentasClienteByEmailOrRfc error', [
                'cliConn' => $cliConn,
                'email' => $email,
                'rfc' => $rfc,
                'e' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Busca accounts.id en mysql_admin.accounts por RFC o email (si existen columnas).
     */
    private static function findAdminAccountIdByEmailOrRfc(string $email, string $rfc, ?string $admConn = null): int
    {
        $admConn = $admConn ?: 'mysql_admin';

        if (!Schema::connection($admConn)->hasTable('accounts')) return 0;

        try {
            $cols = Schema::connection($admConn)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

            // Preferencia: RFC
            if ($rfc !== '' && $has('rfc')) {
                $id = (int) (DB::connection($admConn)->table('accounts')
                    ->whereRaw('UPPER(rfc)=?', [$rfc])
                    ->value('id') ?? 0);

                if ($id > 0) return $id;
            }

            // Luego email
            if ($email !== '' && $has('email')) {
                $id = (int) (DB::connection($admConn)->table('accounts')
                    ->whereRaw('LOWER(email)=?', [$email])
                    ->value('id') ?? 0);

                if ($id > 0) return $id;
            }
        } catch (\Throwable $e) {
            Log::warning('[ClientAuth][findAdminAccountIdByEmailOrRfc] error', [
                'admConn' => $admConn,
                'email' => $email,
                'rfc' => $rfc,
                'e' => $e->getMessage(),
            ]);
        }

        return 0;
    }
}
