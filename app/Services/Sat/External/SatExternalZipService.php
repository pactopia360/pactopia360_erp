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

            try {
                Log::warning('external_zip_invite_account_unresolved', [
                    'trace_id' => $trace,
                    'debug'    => $dbg,
                ]);
            } catch (\Throwable) {}

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
            $expiresAt = now()->addHours(24);

            // Status defensivo
            $status = $has('status') ? 'invited' : null;

            /**
             * ✅ CRÍTICO: generar URL FIRMADA (signed) con expiración
             * Si tu ruta tiene middleware('signed'), esto elimina el 403 INVALID SIGNATURE.
             */
            $inviteUrl = null;
                try {
                    // ✅ NUEVO: mandar a la pantalla ZIP (GET) firmada
                    if (function_exists('route') && \Illuminate\Support\Facades\Route::has('cliente.sat.external.zip.register.form')) {
                        $inviteUrl = URL::temporarySignedRoute(
                            'cliente.sat.external.zip.register.form',
                            $expiresAt,
                            ['token' => $token]
                        );
                    } else {
                        // fallback (no recomendado si la ruta exige signature)
                        $base = rtrim((string) config('app.url'), '/');
                        $inviteUrl = $base . '/cliente/sat/external/zip/register?token=' . urlencode($token);
                    }
                } catch (\Throwable) {
                    $base = rtrim((string) config('app.url'), '/');
                    $inviteUrl = $base . '/cliente/sat/external/zip/register?token=' . urlencode($token);
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
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
                $safe['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            $id = (int) DB::connection($conn)->table($tableFq)->insertGetId($safe);

            // ✅ Enviar correo
            $mailSent  = false;
            $mailError = null;

            try {
                Log::info('external_zip_invite_mail_attempt', [
                    'trace_id'   => $trace,
                    'account_id' => (int) $accountId,
                    'to'         => $email,
                    'invite_url' => $inviteUrl,
                    'row_id'     => $id,
                ]);

                $mailable = new ExternalZipInviteMail(
                    inviteUrl: $inviteUrl,
                    reference: ($ref !== '' ? $ref : null),
                    traceId: $trace,
                    expiresAt: $expiresAt->toDateTimeString(),
                );

                // Laravel moderno: send() es suficiente
                Mail::to($email)->send($mailable);

                $mailSent = true;

                Log::info('external_zip_invite_mail_sent', [
                    'trace_id' => $trace,
                    'to'       => $email,
                    'row_id'   => $id,
                ]);
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();

                Log::error('external_zip_invite_mail_failed', [
                    'trace_id'   => $trace,
                    'account_id' => (int) $accountId,
                    'to'         => $email,
                    'row_id'     => $id,
                    'error'      => $mailError,
                ]);
            }

            $ms = (int) round((microtime(true) - $t0) * 1000);

            $resp = [
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
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'mail_sent'  => $mailSent,
                    'ms'         => $ms,
                ],
            ];

            if (!$mailSent) {
                $resp['msg'] = 'Invitación ZIP creada, pero no se pudo enviar el correo.';
                if (config('app.debug')) {
                    $resp['data']['mail_error'] = $mailError;
                }
            }

            return $resp;

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
            // token debe venir del link firmado (?token=...)
            'token'         => ['nullable', 'string', 'max:80'],

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

        // token desde query (preferente) o body
        $token = trim((string) ($request->query('token', '') ?: ($data['token'] ?? '')));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['ok' => false, 'code' => 422, 'msg' => 'Token inválido o faltante.'];
        }

        $rfc   = strtoupper(trim((string) ($data['rfc'] ?? $data['rfc_externo'] ?? '')));
        $pass  = trim((string) ($data['fiel_pass'] ?? $data['fiel_password'] ?? $data['password'] ?? ''));
        $refIn = trim((string) ($data['reference'] ?? $data['ref'] ?? $data['referencia'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? $data['nota'] ?? ''));
        $emailExternoIn = trim((string) ($data['email_externo'] ?? $data['email'] ?? ''));
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

        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        // ✅ Resolver por TOKEN (invited row) — el invitado no está autenticado
        try {
            if (!Schema::connection($conn)->hasTable($table)) {
                return ['ok' => false, 'code' => 500, 'msg' => 'No existe la tabla external_fiel_uploads.'];
            }
        } catch (\Throwable) {
            return ['ok' => false, 'code' => 500, 'msg' => 'No se pudo validar esquema de external_fiel_uploads.'];
        }

        $schema = Schema::connection($conn);
        $cols = [];
        try { $cols = $schema->getColumnListing($table); } catch (\Throwable) { $cols = []; }
        $has = static fn(string $c): bool => in_array($c, $cols, true);

        $cfgDb   = (string) (config("database.connections.{$conn}.database") ?? '');
        $tableFq = ($cfgDb !== '') ? ($cfgDb . '.' . $table) : $table;

        $inviteRow = null;
        try {
            $inviteRow = DB::connection($conn)->table($tableFq)->where('token', $token)->first();
        } catch (\Throwable $e) {
            Log::error('external_zip_register_token_lookup_failed', [
                'trace_id' => $trace,
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'code' => 500, 'msg' => 'No se pudo validar la invitación.'];
        }

        if (!$inviteRow) {
            return ['ok' => false, 'code' => 404, 'msg' => 'Invitación no encontrada o ya no válida.'];
        }

        $accountId = (int) ($inviteRow->account_id ?? 0);
        if ($accountId <= 0) {
            return ['ok' => false, 'code' => 422, 'msg' => 'Invitación inválida (sin cuenta asociada).'];
        }

        // Reutiliza referencia/email guardados en invitación si no vienen en form
        $ref = $refIn !== '' ? $refIn : trim((string) ($inviteRow->reference ?? ''));
        $emailExterno = $emailExternoIn !== '' ? $emailExternoIn : trim((string) ($inviteRow->email_externo ?? ''));

        $disk = config('filesystems.disks.private') ? 'private' : 'local';
        $dir  = "fiel/external/" . $accountId;

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
                'account_id' => $accountId,
                'rfc' => $rfc,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'code' => 500, 'msg' => 'Error al guardar el ZIP.'];
        }

        // ✅ Actualizar el registro invitado (NO insertar uno nuevo)
        try {
            $update = [
                'rfc'           => $rfc,
                'razon_social'  => ($razonSocial !== '' ? $razonSocial : null),

                'file_path'     => $path,
                'file_name'     => (string) $zipFile->getClientOriginalName(),
                'file_size'     => (int) $zipFile->getSize(),
                'mime'          => (string) $zipFile->getClientMimeType(),

                'fiel_password' => Crypt::encryptString($pass),
                'status'        => 'uploaded',
                'uploaded_at'   => now(),
                'updated_at'    => now(),

                // si el formulario trae email/reference, guarda también
                'email_externo' => ($emailExterno !== '' ? $emailExterno : null),
                'reference'     => ($ref !== '' ? $ref : null),
            ];

            $safeUpdate = [];
            foreach ($update as $k => $v) {
                if ($has($k)) $safeUpdate[$k] = $v;
            }

            if ($has('meta')) {
                $meta = [];
                try {
                    $metaRaw = $inviteRow->meta ?? null;
                    if (is_string($metaRaw) && $metaRaw !== '') {
                        $tmp = json_decode($metaRaw, true);
                        if (is_array($tmp)) $meta = $tmp;
                    }
                } catch (\Throwable) {}

                $meta['source']        = 'external_zip_register';
                $meta['trace_id']      = $trace;
                $meta['ip']            = $request->ip();
                $meta['ua']            = (string) $request->userAgent();
                $meta['notes']         = ($notes !== '' ? $notes : null);
                $meta['original_name'] = (string) $zipFile->getClientOriginalName();
                $meta['disk']          = $disk;

                $safeUpdate['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            DB::connection($conn)->table($tableFq)->where('token', $token)->update($safeUpdate);

            $ms = (int) round((microtime(true) - $t0) * 1000);

            return [
                'ok' => true,
                'code' => 200,
                'msg' => 'FIEL externa cargada correctamente.',
                'data' => [
                    'account_id' => $accountId,
                    'rfc' => $rfc,
                    'file_path' => $path,
                    'token' => $token,
                    'ms' => $ms,
                ],
            ];
        } catch (\Throwable $e) {
            try { if ($path !== '') Storage::disk($disk)->delete($path); } catch (\Throwable) {}

            Log::error('external_fiel_upload_update_failed', [
                'trace_id'   => $trace,
                'account_id' => $accountId,
                'token'      => $token,
                'rfc'        => $rfc,
                'error'      => $e->getMessage(),
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
            $allowed = ['uploaded','processing','done','ready','error','failed','rejected','approved','review','pending','invited'];
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

            try {
                Log::warning('external_zip_list_account_unresolved', [
                    'trace_id' => $trace,
                    'debug'    => $dbg,
                ]);
            } catch (\Throwable) {}

            $out = [
                'ok'   => false,
                'code' => 422,
                'msg'  => 'No se pudo resolver tu cuenta cliente. (APP_DEBUG te mostrará debug)',
                'rows' => [],
                'count'=> 0,
            ];
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
                (int) $accountId,
                (int) $embeddedAdmin,
                (int) (session('admin_account_id') ?? 0),
                (int) (session('account_id') ?? 0),
                (int) ($request->input('admin_account_id') ?? 0),
                (int) ($request->input('account_id') ?? 0),
                (int) (($user?->admin_account_id ?? 0)),
                (int) (($user?->account_id ?? 0)),
            ], fn($v) => is_int($v) && $v > 0)));

            $base = DB::connection($conn)->table($tableFq);
            $qb = (clone $base)->whereIn('account_id', $accountIds ?: [(int) $accountId]);

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
                'ok'     => true,
                'code'   => 200,
                'rows'   => $rowsArr,
                'count'  => $count,
                'limit'  => $limit,
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
     * Resolver account_id BIGINT real (mysql_admin.accounts.id) para el portal cliente.
     *
     * En este proyecto:
     * - accounts.id (admin)               => BIGINT real
     * - cuentas_cliente.id (clientes)     => UUID/varchar(36)
     * - cuentas_cliente.admin_account_id  => BIGINT puente hacia accounts.id
     */
    public function resolveClientAccountIdBigint(Request $request): ?int
    {
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

        // 0) DIRECTO numérico (request/session/user)
        $direct = [];

        try {
            foreach (['admin_account_id', 'account_id'] as $k) {
                $v = trim((string) $request->input($k, ''));
                if ($v !== '' && ctype_digit($v)) $direct[] = (int) $v;
            }
        } catch (\Throwable) {}

        try {
            foreach (['admin_account_id', 'account_id'] as $k) {
                $v = trim((string) session($k, ''));
                if ($v !== '' && ctype_digit($v)) $direct[] = (int) $v;
            }
        } catch (\Throwable) {}

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

        // 1) UUID de cuentas_cliente desde request/session/user
        $uuid = '';

        try {
            foreach (['cuenta_id','cuenta_uuid','cuenta','cuenta_cliente_id','account_uuid','accountId'] as $k) {
                $v = trim((string) $request->input($k, ''));
                if ($v !== '' && $isUuid($v)) { $uuid = $v; break; }
            }
        } catch (\Throwable) {}

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

        // 2) UUID -> admin_account_id
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

        // 3) EMAIL fallback
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

        // 4) RFC fallback
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

        // 5) user_code fallback
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
