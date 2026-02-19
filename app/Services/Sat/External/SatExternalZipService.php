<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\External\SatExternalZipService.php

declare(strict_types=1);

namespace App\Services\Sat\External;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use App\Mail\Cliente\Sat\ExternalZipInviteMail;


final class SatExternalZipService
{
    public function externalZipInvite(Request $request, string $trace): array
    {
        $t0 = microtime(true);

        $data = $request->validate([
            'email'     => ['required', 'string', 'email:rfc,dns', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
        ]);

        $email = trim((string) ($data['email'] ?? ''));
        $ref   = trim((string) ($data['reference'] ?? ''));

        $accountId = $this->resolveClientAccountIdBigint($request);
        if (!$accountId || $accountId <= 0) {

            $dbg = null;
            try {
                if (config('app.debug')) {
                    $u = null;
                    try { $u = $request->user(); } catch (\Throwable) {}

                    $cu = null;
                    try { $cu = $u?->cuenta ?? null; if (is_array($cu)) $cu = (object)$cu; } catch (\Throwable) {}

                    $dbg = [
                        'has_user' => (bool) $u,
                        'user_email' => $u?->email ?? null,
                        'user_user_code' => $u?->user_code ?? null,
                        'user_cuenta_id' => $u?->cuenta_id ?? null,
                        'user_account_id' => $u?->account_id ?? null,
                        'user_admin_account_id' => $u?->admin_account_id ?? null,
                        'embedded_cuenta_id' => is_object($cu) ? ($cu->id ?? null) : null,
                        'embedded_admin_account_id' => is_object($cu) ? ($cu->admin_account_id ?? null) : null,
                        'session_cuenta_id' => session('cuenta_id') ?? null,
                        'session_admin_account_id' => session('admin_account_id') ?? null,
                        'req_cuenta_id' => $request->input('cuenta_id'),
                        'req_admin_account_id' => $request->input('admin_account_id'),
                        'req_rfc' => $request->input('rfc') ?? $request->input('rfc_externo'),
                        'req_email' => $request->input('email') ?? $request->input('email_externo'),
                    ];
                }
            } catch (\Throwable) {}

            $out = [
                'ok' => false,
                'code' => 422,
                'msg' => 'No se pudo resolver tu cuenta cliente. (APP_DEBUG te mostrará el motivo en debug)',
            ];
            if ($dbg) $out['debug'] = $dbg;

            try{
                Log::warning('external_zip_register_account_unresolved', [
                    'trace_id' => $trace,
                    'debug' => $dbg,
                ]);
            }catch(\Throwable){}


            return $out;
        }

        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        try {
            $schema = Schema::connection($conn);
            if (!$schema->hasTable($table)) {
                return ['ok' => false, 'code' => 500, 'msg' => 'No existe la tabla external_fiel_uploads.'];
            }

            $cols = [];
            try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }
            $has = static fn(string $c): bool => in_array($c, $cols, true);

            $cfgDb   = (string) (config("database.connections.{$conn}.database") ?? '');
            $tableFq = ($cfgDb !== '') ? ($cfgDb . '.' . $table) : $table;

            $token = bin2hex(random_bytes(32));

            // Status defensivo (porque no sabemos si aceptas "invited")
            $status = 'invited';
            if ($has('status')) {
                // Si tu sistema solo maneja estos, usamos pending
                $allowed = ['uploaded','processing','done','ready','error','failed','rejected','approved','review','pending','invited'];
                if (!in_array($status, $allowed, true)) $status = 'pending';
            } else {
                $status = null;
            }

            // URL de invitación:
            // - Si tienes rutas firmadas para upload externo, úsala aquí.
            // - Como no me diste esa ruta, generamos una URL "placeholder" coherente.
            //   Cambia 'cliente.sat.external.zip.public' por tu ruta real si existe.
            $inviteUrl = null;
            try {
                if (function_exists('route') && \Illuminate\Support\Facades\Route::has('cliente.sat.external.zip.public')) {
                    $inviteUrl = URL::temporarySignedRoute(
                        'cliente.sat.external.zip.public',
                        now()->addHours(24),
                        ['token' => $token]
                    );
                } else {
                    // Fallback NO firmado: solo para que no se rompa el flujo
                    $base = rtrim((string) config('app.url'), '/');
                    $inviteUrl = $base . '/cliente/sat/external/public?token=' . urlencode($token);
                }
            } catch (\Throwable) {
                $base = rtrim((string) config('app.url'), '/');
                $inviteUrl = $base . '/cliente/sat/external/public?token=' . urlencode($token);
            }

            $row = [
                'account_id'    => (int) $accountId,
                'email_externo' => $email,
                'reference'     => ($ref !== '' ? $ref : null),
                'token'         => $token,
                'status'        => $status,
                'uploaded_at'   => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            // Solo columnas existentes
            $safe = [];
            foreach ($row as $k => $v) {
                if ($has($k)) $safe[$k] = $v;
            }

            // meta opcional
            if ($has('meta')) {
                $meta = [
                    'source'     => 'external_zip_invite',
                    'trace_id'   => $trace,
                    'ip'         => $request->ip(),
                    'ua'         => (string) $request->userAgent(),
                    'invite_url' => $inviteUrl,
                    'expires_at' => now()->addHours(24)->toIso8601String(),
                ];
                $safe['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            // Insert
            $id = (int) DB::connection($conn)->table($tableFq)->insertGetId($safe);

            $ms = (int) round((microtime(true) - $t0) * 1000);

            return [
                'ok'   => true,
                'code' => 200,
                'msg'  => 'Invitación ZIP creada correctamente.',
                'data' => [
                    'id'         => $id,
                    'account_id' => (int) $accountId,
                    'email'      => $email,
                    'reference'  => ($ref !== '' ? $ref : null),
                    'token'      => $token,
                    'invite_url' => $inviteUrl,
                    'ms'         => $ms,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('external_zip_invite_failed', [
                'trace_id'   => $trace,
                'account_id' => (int) $accountId,
                'email'      => $email,
                'error'      => $e->getMessage(),
            ]);

            return ['ok' => false, 'code' => 500, 'msg' => 'Error al crear invitación ZIP.'];
        }
    }

    public function externalZipRegister(Request $request, string $trace): array
    {
        $t0 = microtime(true);

        // 50MB en KB para validator
        $maxKb = 51200;

        $data = $request->validate([
            'rfc'           => ['nullable', 'string', 'max:13'],
            'rfc_externo'   => ['nullable', 'string', 'max:13'],

            'fiel_pass'     => ['nullable', 'string', 'max:120'],
            'fiel_password' => ['nullable', 'string', 'max:120'],
            'password'      => ['nullable', 'string', 'max:120'],

            'reference'     => ['nullable', 'string', 'max:190'],
            'ref'           => ['nullable', 'string', 'max:190'],
            'referencia'    => ['nullable', 'string', 'max:190'],

            'notes'         => ['nullable', 'string', 'max:800'],
            'nota'          => ['nullable', 'string', 'max:800'],

            'email_externo' => ['nullable', 'email', 'max:190'],
            'email'         => ['nullable', 'email', 'max:190'],

            'razon_social'  => ['nullable', 'string', 'max:190'],
            'razonSocial'   => ['nullable', 'string', 'max:190'],

            'zip'           => ['nullable', 'file', 'mimes:zip', 'max:' . $maxKb],
            'archivo_zip'   => ['nullable', 'file', 'mimes:zip', 'max:' . $maxKb],
        ]);

        $rfc   = strtoupper(trim((string) ($data['rfc'] ?? $data['rfc_externo'] ?? '')));
        $pass  = trim((string) ($data['fiel_pass'] ?? $data['fiel_password'] ?? $data['password'] ?? ''));
        $ref   = trim((string) ($data['reference'] ?? $data['ref'] ?? $data['referencia'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? $data['nota'] ?? ''));
        $emailExterno = trim((string) ($data['email_externo'] ?? $data['email'] ?? ''));
        $razonSocial  = trim((string) ($data['razon_social'] ?? $data['razonSocial'] ?? ''));

        if ($rfc === '' || !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
            return ['ok' => false, 'code' => 422, 'msg' => 'RFC inválido.'];
        }
        if ($pass === '') {
            return ['ok' => false, 'code' => 422, 'msg' => 'Contraseña FIEL requerida.'];
        }

        $zipFile = $request->file('zip') ?: $request->file('archivo_zip');
        if (!$zipFile || !$zipFile->isValid()) {
            return ['ok' => false, 'code' => 422, 'msg' => 'Archivo ZIP inválido.'];
        }

        $ext = strtolower((string) $zipFile->getClientOriginalExtension());
        if ($ext !== 'zip') {
            return ['ok' => false, 'code' => 422, 'msg' => 'El archivo debe ser ZIP.'];
        }

        // Tamaño max 50MB (doble-check defensivo)
        try {
            $maxBytes = 50 * 1024 * 1024;
            $size = (int) $zipFile->getSize();
            if ($size <= 0 || $size > $maxBytes) {
                return ['ok' => false, 'code' => 422, 'msg' => 'El ZIP excede el tamaño permitido (50MB).'];
            }
        } catch (\Throwable) {}

        $accountId = $this->resolveClientAccountIdBigint($request);
        if (!$accountId || $accountId <= 0) {

            $dbg = null;
            try {
                if (config('app.debug')) {
                    $u = null;
                    try { $u = $request->user(); } catch (\Throwable) {}

                    $cu = null;
                    try { $cu = $u?->cuenta ?? null; if (is_array($cu)) $cu = (object)$cu; } catch (\Throwable) {}

                    $dbg = [
                        'has_user' => (bool) $u,
                        'user_class' => $u ? get_class($u) : null,
                        'user_id' => $u?->id ?? null,
                        'user_email' => $u?->email ?? null,

                        'user_user_code' => $u?->user_code ?? null,
                        'user_cuenta_id' => $u?->cuenta_id ?? null,
                        'user_account_id' => $u?->account_id ?? null,
                        'user_admin_account_id' => $u?->admin_account_id ?? null,

                        'embedded_cuenta_id' => is_object($cu) ? ($cu->id ?? null) : null,
                        'embedded_admin_account_id' => is_object($cu) ? ($cu->admin_account_id ?? null) : null,

                        'session_cuenta_id' => session('cuenta_id') ?? null,
                        'session_admin_account_id' => session('admin_account_id') ?? null,
                        'session_account_id' => session('account_id') ?? null,
                        'session_user_code' => session('user_code') ?? null,

                        'req_cuenta_id' => $request->input('cuenta_id'),
                        'req_admin_account_id' => $request->input('admin_account_id'),
                        'req_account_id' => $request->input('account_id'),
                        'req_rfc' => $request->input('rfc') ?? $request->input('rfc_externo'),
                        'req_email' => $request->input('email') ?? $request->input('email_externo'),
                    ];
                }
            } catch (\Throwable) {}

            try{
                Log::warning('external_zip_register_account_unresolved', [
                    'trace_id' => $trace,
                    'debug' => $dbg,
                ]);
            }catch(\Throwable){}

            $out = [
                'ok' => false,
                'code' => 422,
                'msg' => 'No se pudo resolver tu cuenta cliente. (APP_DEBUG te mostrará el motivo en debug)',
            ];
            if ($dbg) $out['debug'] = $dbg;

            return $out;
        }


        $disk = config('filesystems.disks.private') ? 'private' : 'local';
        $dir  = "fiel/external/" . (int) $accountId;

        $safeRfc = preg_replace('/[^A-Z0-9]/', '', $rfc);
        $rand    = strtoupper(Str::random(10));
        $ts      = now()->format('Ymd_His');
        $name    = "FIEL-{$safeRfc}-{$ts}-{$rand}.zip";

        $path = '';
        try {
            $path = (string) $zipFile->storeAs($dir, $name, $disk);
            if ($path === '') {
                return ['ok' => false, 'code' => 500, 'msg' => 'No se pudo guardar el ZIP.'];
            }
        } catch (\Throwable $e) {
            Log::error('external_fiel_zip_store_failed', [
                'trace_id' => $trace,
                'account_id' => (int) $accountId,
                'rfc' => $rfc,
                'e' => $e->getMessage(),
            ]);
            return ['ok' => false, 'code' => 500, 'msg' => 'Error al guardar el ZIP.'];
        }

        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        try {
            $schema = Schema::connection($conn);

            if (!$schema->hasTable($table)) {
                try { Storage::disk($disk)->delete($path); } catch (\Throwable) {}
                return ['ok' => false, 'code' => 500, 'msg' => 'No existe la tabla external_fiel_uploads.'];
            }

            $cols = [];
            try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }
            $has = static fn(string $c): bool => in_array($c, $cols, true);

            $token = bin2hex(random_bytes(32));

            $row = [
                'account_id'    => (int) $accountId,
                'email_externo' => ($emailExterno !== '' ? $emailExterno : null),
                'reference'     => ($ref !== '' ? $ref : null),
                'rfc'           => $rfc,
                'razon_social'  => ($razonSocial !== '' ? $razonSocial : null),

                'token'         => $token,

                'file_path'     => $path,
                'file_name'     => (string) $zipFile->getClientOriginalName(),
                'file_size'     => (int) $zipFile->getSize(),
                'mime'          => (string) $zipFile->getClientMimeType(),

                'fiel_password' => Crypt::encryptString($pass),
                'status'        => 'uploaded',
                'uploaded_at'   => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $safe = [];
            foreach ($row as $k => $v) if ($has($k)) $safe[$k] = $v;

            if ($has('meta')) {
                $meta = [
                    'source'        => 'external_zip_register',
                    'trace_id'      => $trace,
                    'ip'            => $request->ip(),
                    'ua'            => (string) $request->userAgent(),
                    'notes'         => ($notes !== '' ? $notes : null),
                    'original_name' => (string) $zipFile->getClientOriginalName(),
                    'disk'          => $disk,
                ];
                $safe['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            $id = (int) DB::connection($conn)->table($table)->insertGetId($safe);

            $ms = (int) round((microtime(true) - $t0) * 1000);

            return [
                'ok' => true,
                'code' => 200,
                'msg' => 'FIEL externa cargada correctamente.',
                'data' => [
                    'id' => $id,
                    'account_id' => (int) $accountId,
                    'rfc' => $rfc,
                    'file_path' => $path,
                    'ms' => $ms,
                ],
            ];
        } catch (\Throwable $e) {
            try { if ($path !== '') Storage::disk($disk)->delete($path); } catch (\Throwable) {}

            Log::error('external_fiel_upload_failed', [
                'trace_id' => $trace,
                'account_id' => (int) $accountId,
                'rfc' => $rfc,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'code' => 500, 'msg' => 'Error al guardar FIEL externa.'];
        }
    }

    public function externalZipList(Request $request, string $trace): array
    {
        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        $limit  = (int) ($request->query('limit', 50));
        $limit  = ($limit <= 0) ? 50 : min($limit, 200);

        $offset = (int) ($request->query('offset', 0));
        if ($offset < 0) $offset = 0;

        $status = trim((string) $request->query('status', ''));
        $q      = trim((string) $request->query('q', ''));

        if (mb_strlen($q) > 120) $q = mb_substr($q, 0, 120);

        if ($status !== '') {
            $allowed = ['uploaded','processing','done','ready','error','failed','rejected','approved','review','pending'];
            if (!in_array(strtolower($status), $allowed, true)) $status = '';
        }

        $accountId = $this->resolveClientAccountIdBigint($request);
        if (!$accountId || $accountId <= 0) {

            $dbg = null;
            try {
                if (config('app.debug')) {
                    $u = null;
                    try { $u = $request->user(); } catch (\Throwable) {}
                    $cu = null;
                    try { $cu = $u?->cuenta ?? null; if (is_array($cu)) $cu = (object)$cu; } catch (\Throwable) {}

                    $dbg = [
                        'has_user' => (bool) $u,
                        'user_id' => $u?->id ?? null,
                        'user_email' => $u?->email ?? null,
                        'user_cuenta_id' => $u?->cuenta_id ?? null,
                        'user_admin_account_id' => $u?->admin_account_id ?? null,
                        'embedded_cuenta_id' => is_object($cu) ? ($cu->id ?? null) : null,
                        'embedded_admin_account_id' => is_object($cu) ? ($cu->admin_account_id ?? null) : null,
                        'session_cuenta_id' => session('cuenta_id') ?? null,
                        'session_admin_account_id' => session('admin_account_id') ?? null,
                        'session_account_id' => session('account_id') ?? null,
                    ];
                }
            } catch (\Throwable) {}

            try{
                Log::warning('external_zip_list_account_unresolved', [
                    'trace_id' => $trace,
                    'debug' => $dbg,
                ]);
            }catch(\Throwable){}

            $out = ['ok'=>false,'code'=>422,'msg'=>'No se pudo resolver tu cuenta cliente. (APP_DEBUG te mostrará debug)','rows'=>[],'count'=>0];
            if ($dbg) $out['debug'] = $dbg;

            return $out;
        }


        try {
            $schema = Schema::connection($conn);
            if (!$schema->hasTable($table)) {
                return ['ok'=>false,'code'=>500,'msg'=>'No existe la tabla external_fiel_uploads.','rows'=>[],'count'=>0];
            }

            $cols = [];
            try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }
            $has = static fn(string $c): bool => in_array($c, $cols, true);

            $cfgDb   = (string) (config("database.connections.{$conn}.database") ?? '');
            $tableFq = ($cfgDb !== '') ? ($cfgDb . '.' . $table) : $table;

            $user = null;
            try { $user = $request->user(); } catch (\Throwable) {}

            $embeddedAdmin = 0;
            try {
                $cu = $user?->cuenta ?? null;
                if (is_array($cu)) $cu = (object) $cu;
                if (is_object($cu) && !empty($cu->admin_account_id)) {
                    $v = trim((string) $cu->admin_account_id);
                    if ($v !== '' && ctype_digit($v)) $embeddedAdmin = (int) $v;
                }
            } catch (\Throwable) {}

            $accountIds = array_values(array_unique(array_filter([
                (int) $accountId, // ya viene resuelto por resolveClientAccountIdBigint() -> admin_account_id real
                (int) $embeddedAdmin,
                (int) (session('admin_account_id') ?? 0),
                (int) (session('account_id') ?? 0), // por si tu sesión guarda el admin aquí
                (int) ($request->input('admin_account_id') ?? 0),
                (int) ($request->input('account_id') ?? 0),
                (int) (($user?->admin_account_id ?? 0)),
                (int) (($user?->account_id ?? 0)),
            ], fn($v) => is_int($v) && $v > 0)));


            $base = DB::connection($conn)->table($tableFq);

            $qb = (clone $base)->whereIn('account_id', $accountIds ?: [(int)$accountId]);

            if ($status !== '' && $has('status')) $qb->where('status', $status);

            if ($q !== '') {
                $qb->where(function ($w) use ($q, $has) {
                    $like = "%{$q}%";
                    if ($has('rfc'))           $w->orWhere('rfc', 'like', $like);
                    if ($has('file_name'))     $w->orWhere('file_name', 'like', $like);
                    if ($has('reference'))     $w->orWhere('reference', 'like', $like);
                    if ($has('email_externo')) $w->orWhere('email_externo', 'like', $like);
                    if ($has('razon_social'))  $w->orWhere('razon_social', 'like', $like);
                });
            }

            $count = (int) (clone $qb)->count();

            $select = array_values(array_filter([
                $has('id') ? 'id' : null,
                $has('account_id') ? 'account_id' : null,
                $has('email_externo') ? 'email_externo' : null,
                $has('reference') ? 'reference' : null,
                $has('rfc') ? 'rfc' : null,
                $has('razon_social') ? 'razon_social' : null,
                $has('file_path') ? 'file_path' : null,
                $has('file_name') ? 'file_name' : null,
                $has('file_size') ? 'file_size' : null,
                $has('mime') ? 'mime' : null,
                $has('uploaded_at') ? 'uploaded_at' : null,
                $has('status') ? 'status' : null,
                $has('created_at') ? 'created_at' : null,
            ]));
            if (empty($select)) $select = ['id'];

            $rows = $qb
                ->orderByDesc($has('id') ? 'id' : ($has('created_at') ? 'created_at' : 'id'))
                ->offset($offset)
                ->limit($limit)
                ->get($select);

            $rowsArr = [];
            foreach ($rows as $r) $rowsArr[] = (array) $r;

            return [
                'ok' => true,
                'code' => 200,
                'rows' => $rowsArr,
                'count' => $count,
                'limit' => $limit,
                'offset' => $offset,
            ];
        } catch (\Throwable $e) {
            Log::error('external_zip_list_failed', [
                'trace_id'   => $trace,
                'account_id' => (int) $accountId,
                'error'      => $e->getMessage(),
            ]);

            return ['ok'=>false,'code'=>500,'msg'=>'Error al consultar lista de ZIP externos.','rows'=>[],'count'=>0];
        }
    }

    /**
     * Resolver account_id BIGINT real de mysql_clientes.accounts
     *
     * En este proyecto:
     * - accounts.id                  => BIGINT real (lo que necesita external_fiel_uploads.account_id)
     * - cuentas_cliente.id           => UUID/varchar(36) (lo que usa el portal)
     * - cuentas_cliente.admin_account_id => BIGINT puente hacia accounts.id
     */
    public function resolveClientAccountIdBigint(Request $request): ?int
    {
        // En este flujo, account_id de external_fiel_uploads debe ser el ID BIGINT del "admin account"
        // (mysql_admin.accounts.id), porque cuentas_cliente.admin_account_id apunta ahí.
        $cliConn  = 'mysql_clientes';
        $admConn  = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $user = null;
        try { $user = $request->user(); } catch (\Throwable) {}

        $existsInAdminAccounts = static function (int $id) use ($admConn): bool {
            try { return DB::connection($admConn)->table('accounts')->where('id', $id)->exists(); }
            catch (\Throwable) { return false; }
        };

        $isUuid = static function (string $s): bool {
            return (bool) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $s
            );
        };

        // ==========================================================
        // 0) DIRECTO: admin_account_id / account_id numérico (request/session/user)
        //    Validar SIEMPRE contra mysql_admin.accounts
        // ==========================================================
        $direct = [];

        // request
        try {
            foreach (['admin_account_id', 'account_id'] as $k) {
                $v = trim((string) $request->input($k, ''));
                if ($v !== '' && ctype_digit($v)) $direct[] = (int) $v;
            }
        } catch (\Throwable) {}

        // session
        try {
            foreach (['admin_account_id', 'account_id'] as $k) {
                $v = trim((string) session($k, ''));
                if ($v !== '' && ctype_digit($v)) $direct[] = (int) $v;
            }
        } catch (\Throwable) {}

        // user fields
        try {
            if ($user) {
                foreach (['admin_account_id', 'account_id'] as $k) {
                    if (isset($user->{$k}) && $user->{$k} !== null) {
                        $v = trim((string) $user->{$k});
                        if ($v !== '' && ctype_digit($v)) $direct[] = (int) $v;
                    }
                }
            }
        } catch (\Throwable) {}

        // embedded cuenta->admin_account_id (tu debug lo trae: embedded_admin_account_id=15)
        try {
            $cu = $user?->cuenta ?? null;
            if (is_array($cu)) $cu = (object) $cu;
            if (is_object($cu) && isset($cu->admin_account_id)) {
                $v = trim((string) ($cu->admin_account_id ?? ''));
                if ($v !== '' && ctype_digit($v)) $direct[] = (int) $v;
            }
        } catch (\Throwable) {}

        $direct = array_values(array_unique(array_filter($direct, fn($x) => is_int($x) && $x > 0)));
        foreach ($direct as $id) {
            if ($existsInAdminAccounts($id)) return $id;
        }

        // ==========================================================
        // 1) OBTENER UUID (cuentas_cliente.id) desde request/session/user
        // ==========================================================
        $uuid = '';

        // request
        try {
            foreach (['cuenta_id','cuenta_uuid','cuenta','cuenta_cliente_id','account_uuid','accountId'] as $k) {
                $v = trim((string) $request->input($k, ''));
                if ($v !== '' && $isUuid($v)) { $uuid = $v; break; }
            }
        } catch (\Throwable) {}

        // session
        if ($uuid === '') {
            try {
                foreach ([
                    'cuenta_id','cuenta_uuid','cuenta','cuenta_cliente_id',
                    'account_uuid','accountId',
                    'p360.cuenta_id','p360.cuenta_uuid'
                ] as $k) {
                    $v = trim((string) session($k, ''));
                    if ($v !== '' && $isUuid($v)) { $uuid = $v; break; }
                }
            } catch (\Throwable) {}
        }

        // user fields típicos
        if ($uuid === '') {
            try {
                if ($user) {
                    foreach (['cuenta_id','cuenta_uuid','account_uuid'] as $k) {
                        if (!empty($user->{$k})) {
                            $v = trim((string) $user->{$k});
                            if ($v !== '' && $isUuid($v)) { $uuid = $v; break; }
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        // embedded cuenta->id
        if ($uuid === '') {
            try {
                $cu = $user?->cuenta ?? null;
                if (is_array($cu)) $cu = (object) $cu;
                if (is_object($cu) && !empty($cu->id)) {
                    $v = trim((string) $cu->id);
                    if ($v !== '' && $isUuid($v)) $uuid = $v;
                }
            } catch (\Throwable) {}
        }

        // ==========================================================
        // 2) UUID -> cuentas_cliente.admin_account_id -> validar contra mysql_admin.accounts
        // ==========================================================
        if ($uuid !== '') {
            try {
                if (Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
                    $val = DB::connection($cliConn)
                        ->table('cuentas_cliente')
                        ->where('id', $uuid)
                        ->value('admin_account_id');

                    $val = trim((string) ($val ?? ''));
                    if ($val !== '' && ctype_digit($val)) {
                        $aid = (int) $val;
                        if ($aid > 0 && $existsInAdminAccounts($aid)) return $aid;
                    }
                }
            } catch (\Throwable) {}
        }

        // ==========================================================
        // 3) EMAIL -> cuentas_cliente.email -> admin_account_id -> validar admin.accounts
        //    fallback: mysql_admin.accounts.correo_contacto
        // ==========================================================
        $email = '';
        try { $email = trim((string) $request->input('email', $request->input('email_externo', ''))); } catch (\Throwable) {}
        if ($email === '' && $user) {
            try { $email = trim((string) ($user->email ?? '')); } catch (\Throwable) {}
        }

        if ($email !== '' && str_contains($email, '@')) {
            try {
                if (Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
                    $val = DB::connection($cliConn)
                        ->table('cuentas_cliente')
                        ->where('email', $email)
                        ->value('admin_account_id');

                    $val = trim((string) ($val ?? ''));
                    if ($val !== '' && ctype_digit($val)) {
                        $aid = (int) $val;
                        if ($aid > 0 && $existsInAdminAccounts($aid)) return $aid;
                    }
                }
            } catch (\Throwable) {}

            try {
                $id = DB::connection($admConn)->table('accounts')->where('correo_contacto', $email)->value('id');
                if ($id && (int) $id > 0) return (int) $id;
            } catch (\Throwable) {}
        }

        // ==========================================================
        // 4) RFC -> cuentas_cliente.rfc -> admin_account_id -> validar admin.accounts
        //    fallback: mysql_admin.accounts.rfc
        // ==========================================================
        $rfc = trim((string) $request->input('rfc', $request->input('rfc_externo', '')));
        $rfc = strtoupper($rfc);

        if ($rfc !== '') {
            try {
                if (Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
                    $val = DB::connection($cliConn)
                        ->table('cuentas_cliente')
                        ->where('rfc', $rfc)
                        ->value('admin_account_id');

                    $val = trim((string) ($val ?? ''));
                    if ($val !== '' && ctype_digit($val)) {
                        $aid = (int) $val;
                        if ($aid > 0 && $existsInAdminAccounts($aid)) return $aid;
                    }
                }
            } catch (\Throwable) {}

            try {
                $id = DB::connection($admConn)->table('accounts')->where('rfc', $rfc)->value('id');
                if ($id && (int) $id > 0) return (int) $id;
            } catch (\Throwable) {}
        }

        // ==========================================================
        // 5) user_code -> mysql_admin.accounts.user_code
        // ==========================================================
        $userCode = '';
        try { if ($user) $userCode = trim((string) ($user->user_code ?? '')); } catch (\Throwable) {}
        if ($userCode === '') {
            try { $userCode = trim((string) session('user_code', '')); } catch (\Throwable) {}
        }

        if ($userCode !== '') {
            try {
                $id = DB::connection($admConn)->table('accounts')->where('user_code', $userCode)->value('id');
                if ($id && (int) $id > 0) return (int) $id;
            } catch (\Throwable) {}
        }

        return null;
    }
}
