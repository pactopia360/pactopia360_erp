<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\External\ExternalStoreService.php

declare(strict_types=1);

namespace App\Services\Sat\External;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ExternalStoreService
{
    /**
     * Maneja el POST firmado para registrar RFC+CSD (external store).
     *
     * Nota importante:
     * - Para no acoplar a una clase concreta, recibimos $credentialsService (el que hoy es $this->service)
     *   y solo exigimos que tenga upsertCredentials($cuentaIdNorm, $rfc, $cer, $key, $password).
     */
    public function handle(Request $request, mixed $credentialsService): Response
    {
        // ======================================================
        // 1) Signed URL hard-check
        // ======================================================
        if (!method_exists($request, 'hasValidSignature') || !$request->hasValidSignature()) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'Firma inválida o expirada.'], 403)
                : response('Firma inválida o expirada.', 403);
        }

        // ======================================================
        // 2) Query params (SOT) + cuenta_id variants
        // ======================================================
        $email = trim((string) $request->query('email', ''));

        $cuentaId = $this->resolveCuentaIdFromQuery($request);
        $inv      = trim((string) $request->query('inv', ''));

        if ($email === '' || $cuentaId === null || $cuentaId === '') {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'Parámetros incompletos.'], 422)
                : response('Parámetros incompletos.', 422);
        }

        $cuentaIdNorm = ctype_digit((string) $cuentaId) ? (string) ((int) $cuentaId) : (string) $cuentaId;

        // ======================================================
        // 3) Validación BODY (multipart/form-data)
        // ======================================================
        $data = $request->validate([
            'rfc'          => ['required', 'string', 'min:12', 'max:13'],
            'alias'        => ['nullable', 'string', 'max:190'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'cer'          => ['required', 'file', 'max:5120'], // 5MB
            'key'          => ['required', 'file', 'max:5120'], // 5MB
            'key_password' => ['nullable', 'string', 'max:120'],
            'pwd'          => ['nullable', 'string', 'max:120'],
            'note'         => ['nullable', 'string', 'max:500'],
            'confirm'      => ['accepted'],
        ], [
            'confirm.accepted' => 'Debes confirmar la autorización para registrar este RFC.',
        ]);

        $rfc = strtoupper(trim((string) $data['rfc']));

        if (!preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'RFC inválido.'], 422)
                : response('RFC inválido.', 422);
        }

        $alias = trim((string) ($data['alias'] ?? $data['razon_social'] ?? ''));
        $note  = trim((string) ($data['note'] ?? ''));

        $cer = $request->file('cer');
        $key = $request->file('key');

        // Extensiones strict
        if (!$cer || strtolower((string) $cer->getClientOriginalExtension()) !== 'cer') {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'El archivo .cer no es válido.'], 422)
                : response('El archivo .cer no es válido.', 422);
        }
        if (!$key || strtolower((string) $key->getClientOriginalExtension()) !== 'key') {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'El archivo .key no es válido.'], 422)
                : response('El archivo .key no es válido.', 422);
        }

        $password = (string) ($data['key_password'] ?? $data['pwd'] ?? '');

        // ======================================================
        // 4) Persistencia (mysql_clientes) reusando upsertCredentials
        // ======================================================
        try {
            $resp = DB::connection('mysql_clientes')->transaction(function () use (
                $request, $cuentaIdNorm, $email, $inv, $rfc, $alias, $note, $cer, $key, $password, $credentialsService
            ) {
                if (!is_object($credentialsService) || !method_exists($credentialsService, 'upsertCredentials')) {
                    throw new \RuntimeException('credentialsService inválido (no tiene upsertCredentials).');
                }

                $cred = $credentialsService->upsertCredentials(
                    $cuentaIdNorm,
                    $rfc,
                    $cer,
                    $key,
                    $password
                );

                // Alias
                if ($alias !== '') {
                    try { $cred->razon_social = $alias; } catch (\Throwable) {}
                    try { if (property_exists($cred, 'alias')) $cred->alias = $alias; } catch (\Throwable) {}
                }

                // Flags externos + password + meta merge
                try {
                    $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                    $table  = $cred->getTable();
                    $schema = Schema::connection($conn);

                    if ($schema->hasColumn($table, 'estatus'))      $cred->estatus      = 'pending';
                    if ($schema->hasColumn($table, 'status'))       $cred->status       = 'pending';
                    if ($schema->hasColumn($table, 'validado'))     $cred->validado     = 0;
                    if ($schema->hasColumn($table, 'validated_at')) $cred->validated_at = null;

                    // password cifrada (si upsert no la cifra)
                    if ($password !== '') {
                        $enc = null;
                        try { $enc = encrypt($password); } catch (\Throwable) { $enc = base64_encode($password); }

                        if ($schema->hasColumn($table, 'key_password_enc')) {
                            $cred->key_password_enc = $enc;
                            if ($schema->hasColumn($table, 'key_password')) $cred->key_password = null;
                        } elseif ($schema->hasColumn($table, 'key_password')) {
                            $cred->key_password = $enc;
                        }
                    }

                    // meta merge
                    if ($schema->hasColumn($table, 'meta')) {
                        $meta = [];
                        $current = $cred->meta ?? null;

                        if (is_array($current)) $meta = $current;
                        elseif (is_string($current) && $current !== '') {
                            $tmp = json_decode($current, true);
                            if (is_array($tmp)) $meta = $tmp;
                        }

                        $meta['source']         = 'external_register';
                        $meta['external_email'] = $email;
                        $meta['invite_id']      = ($inv !== '' ? $inv : null);
                        $meta['note']           = ($note !== '' ? $note : null);
                        $meta['ip']             = (string) $request->ip();
                        $meta['ua']             = (string) $request->userAgent();
                        $meta['updated_at']     = now()->toDateTimeString();

                        $meta['stored'] = [
                            'cer' => data_get($cred, 'cer_path'),
                            'key' => data_get($cred, 'key_path'),
                        ];

                        $cred->meta = $meta;
                    }
                } catch (\Throwable) {
                    // no-op
                }

                $cred->save();

                return ['rfc' => $rfc];
            });

            $msg = 'RFC y CSD registrados. Ya puedes cerrar esta pestaña.';

            return $request->wantsJson()
                ? response()->json(['ok' => true, 'msg' => $msg, 'rfc' => (string)($resp['rfc'] ?? '')], 200)
                : response($msg, 200);

        } catch (\Throwable $e) {
            Log::error('[SAT][externalStore] Error', [
                'cuenta_id' => $cuentaIdNorm ?? null,
                'email'     => $email ?? null,
                'inv'       => $inv ?? null,
                'rfc'       => $rfc ?? null,
                'err'       => $e->getMessage(),
            ]);

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'msg' => 'No se pudo registrar.'], 500)
                : response('No se pudo registrar.', 500);
        }
    }

    /* ==========================================================
     * ✅ INVITE EXTERNO: genera signed URL y manda correo
     * (para SatDescargaController@externalInvite)
     * ========================================================== */
    public function invite(Request $request, object $user, string $trace, string $cuentaId): JsonResponse
    {
        $email = trim((string) $request->input('email', ''));
        $note  = trim((string) $request->input('note', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'msg' => 'Correo inválido.', 'trace_id' => $trace], 422);
        }

        // Rate limit simple por IP (opcional)
        try {
            $ip   = (string) $request->ip();
            $key  = 'sat_ext_invite:' . sha1($ip);
            $hits = (int) cache()->get($key, 0);

            if ($hits >= 10) {
                return response()->json(['ok' => false, 'msg' => 'Demasiadas solicitudes. Intenta más tarde.', 'trace_id' => $trace], 429);
            }
            cache()->put($key, $hits + 1, now()->addMinutes(10));
        } catch (\Throwable) {
            // no-op
        }

        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'No se pudo determinar la cuenta que invita.', 'trace_id' => $trace], 422);
        }

        $inviteId  = (string) Str::ulid();
        $expiresAt = now()->addDays(7);

        try {
            $signedUrl = URL::temporarySignedRoute(
                'cliente.sat.external.register',
                $expiresAt,
                [
                    'email'     => $email,
                    'cuenta_id' => $cuentaId,
                    'inv'       => $inviteId,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[SAT][externalInvite] signedRoute error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'email'     => $email,
                'err'       => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo generar el link de invitación. Revisa rutas/URL.',
                'trace_id' => $trace,
            ], 500);
        }

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $accountName = (string) ($cuenta?->razon_social ?? $cuenta?->nombre ?? $user?->name ?? 'Pactopia360');
        $appName     = (string) (config('app.name') ?: 'Pactopia360');
        $subject     = $appName . ' · Invitación para registrar RFC/CSD (SAT)';

        $lines = [];
        $lines[] = "Hola,";
        $lines[] = "";
        $lines[] = "Se te invitó desde {$accountName} para registrar tu RFC y tu CSD (archivos .cer/.key) en {$appName}.";
        $lines[] = "";
        $lines[] = "Enlace (válido hasta " . $expiresAt->format('Y-m-d H:i') . "):";
        $lines[] = $signedUrl;
        $lines[] = "";
        if ($note !== '') {
            $lines[] = "Nota:";
            $lines[] = $note;
            $lines[] = "";
        }
        $lines[] = "Si no esperabas este correo, ignóralo.";

        try {
            Mail::raw(implode("\n", $lines), function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('[SAT][externalInvite] mail error', [
                'trace_id' => $trace,
                'email'    => $email,
                'err'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'Se generó el link, pero falló el envío de correo. Revisa configuración MAIL.',
                'url'      => $signedUrl,
                'trace_id' => $trace,
            ], 500);
        }

        return response()->json([
            'ok'       => true,
            'msg'      => 'Invitación enviada.',
            'url'      => $signedUrl,
            'exp'      => $expiresAt->toIso8601String(),
            'trace_id' => $trace,
        ]);
    }

    /* ==========================================================
     * ✅ REGISTRO ZIP FIEL EXTERNO (AUTH)
     * Tabla: mysql_clientes.external_fiel_uploads
     * ========================================================== */
    public function zipRegister(Request $request, string $trace): JsonResponse
    {
        $t0 = microtime(true);

        $rfc = strtoupper(trim((string) $request->input('rfc', $request->input('rfc_externo', ''))));
        $pass = trim((string) $request->input('fiel_pass', $request->input('fiel_password', $request->input('password', ''))));
        $ref = trim((string) $request->input('reference', $request->input('ref', $request->input('referencia', ''))));
        $notes = trim((string) $request->input('notes', $request->input('nota', '')));
        $emailExterno = trim((string) $request->input('email_externo', $request->input('email', '')));
        $razonSocial  = trim((string) $request->input('razon_social', $request->input('razonSocial', '')));

        if ($rfc === '' || !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
            return response()->json(['ok' => false, 'msg' => 'RFC inválido.', 'trace_id' => $trace], 422);
        }
        if ($pass === '') {
            return response()->json(['ok' => false, 'msg' => 'Contraseña FIEL requerida.', 'trace_id' => $trace], 422);
        }

        if (!$request->hasFile('zip') && $request->hasFile('archivo_zip')) {
            $request->files->set('zip', $request->file('archivo_zip'));
        }

        if (!$request->hasFile('zip') || !$request->file('zip') || !$request->file('zip')->isValid()) {
            return response()->json(['ok' => false, 'msg' => 'Archivo ZIP inválido.', 'trace_id' => $trace], 422);
        }

        $zip = $request->file('zip');

        if (strtolower((string) $zip->getClientOriginalExtension()) !== 'zip') {
            return response()->json(['ok' => false, 'msg' => 'El archivo debe ser ZIP.', 'trace_id' => $trace], 422);
        }

        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        $accountId = $this->resolveClientAccountIdBigint($request);
        if (!$accountId || $accountId <= 0) {
            Log::warning('external_zip_register_no_account', [
                'trace_id' => $trace,
                'ip' => $request->ip(),
                'ua' => (string) $request->userAgent(),
                'user_id' => optional($request->user())->id ?? null,
                'user_email' => optional($request->user())->email ?? null,
                'session_account_id' => session('account_id'),
                'session_cuenta_id' => session('cuenta_id'),
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo resolver tu cuenta cliente. Cierra sesión e inicia de nuevo o contacta soporte.',
                'trace_id' => $trace,
            ], 422);
        }

        // ✅ PRIVADO
        $disk = config('filesystems.disks.private') ? 'private' : 'local';
        $dir  = "fiel/external/{$accountId}";

        // límite tamaño 50MB
        try {
            $maxBytes = 50 * 1024 * 1024;
            $size = (int) $zip->getSize();
            if ($size <= 0 || $size > $maxBytes) {
                return response()->json(['ok' => false, 'msg' => 'El ZIP excede el tamaño permitido (50MB).', 'trace_id' => $trace], 422);
            }
        } catch (\Throwable) {
            // no-op
        }

        $safeRfc = preg_replace('/[^A-Z0-9]/', '', $rfc);
        $rand    = strtoupper(substr(sha1((string) microtime(true)), 0, 12));
        $name    = "FIEL-{$safeRfc}-{$rand}.zip";

        try {
            $path = $zip->storeAs($dir, $name, $disk);
            if (!$path) {
                return response()->json(['ok' => false, 'msg' => 'No se pudo guardar el ZIP.', 'trace_id' => $trace], 500);
            }
        } catch (\Throwable $e) {
            Log::error('external_fiel_zip_store_failed', [
                'trace_id' => $trace,
                'account_id' => (int) $accountId,
                'rfc' => $rfc,
                'e' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'msg' => 'Error al guardar el ZIP.', 'trace_id' => $trace], 500);
        }

        try {
            $schema = Schema::connection($conn);
            if (!$schema->hasTable($table)) {
                return response()->json(['ok' => false, 'msg' => 'No existe la tabla external_fiel_uploads.', 'trace_id' => $trace], 500);
            }

            $token = bin2hex(random_bytes(32));

            $meta = [
                'source'        => 'external_zip_register',
                'ip'            => $request->ip(),
                'ua'            => (string) $request->userAgent(),
                'notes'         => $notes,
                'original_name' => $zip->getClientOriginalName(),
            ];

            $row = [
                'account_id'    => (int) $accountId,
                'email_externo' => ($emailExterno !== '' ? $emailExterno : null),
                'reference'     => ($ref !== '' ? $ref : null),
                'rfc'           => $rfc,
                'razon_social'  => ($razonSocial !== '' ? $razonSocial : null),
                'token'         => $token,
                'file_path'     => $path,
                'file_name'     => $zip->getClientOriginalName(),
                'file_size'     => (int) $zip->getSize(),
                'mime'          => (string) $zip->getClientMimeType(),
                'fiel_password' => Crypt::encryptString($pass),
                'status'        => 'uploaded',
                'uploaded_at'   => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $safe = [];
            foreach ($row as $k => $v) {
                try { if ($schema->hasColumn($table, $k)) $safe[$k] = $v; } catch (\Throwable) {}
            }

            try {
                if ($schema->hasColumn($table, 'meta')) {
                    $safe['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
                }
            } catch (\Throwable) {}

            $id = DB::connection($conn)->table($table)->insertGetId($safe);

            $ms = (int) round((microtime(true) - $t0) * 1000);

            Log::info('external_zip_register_saved', [
                'trace_id' => $trace,
                'id' => (int) $id,
                'account_id' => (int) $accountId,
                'rfc' => $rfc,
                'file' => $path,
                'ms' => $ms,
            ]);

            return response()->json([
                'ok'  => true,
                'msg' => 'FIEL externa cargada correctamente.',
                'trace_id' => $trace,
                'data' => [
                    'id'         => (int) $id,
                    'account_id' => (int) $accountId,
                    'rfc'        => $rfc,
                    'file_path'  => $path,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('external_fiel_upload_failed', [
                'trace_id' => $trace,
                'account_id' => (int) $accountId,
                'rfc' => $rfc,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'msg' => 'Error al guardar FIEL externa.', 'trace_id' => $trace], 500);
        }
    }

    /* ==========================================================
     * ✅ LISTADO ZIP EXTERNO (AUTH)
     * Tabla: mysql_clientes.external_fiel_uploads
     * ========================================================== */
    public function zipList(Request $request, string $trace): JsonResponse
    {
        $t0 = microtime(true);

        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        $limit  = (int) ($request->query('limit', 50));
        $limit  = ($limit <= 0) ? 50 : min($limit, 200);

        $offset = (int) ($request->query('offset', 0));
        if ($offset < 0) $offset = 0;

        $status = trim((string) $request->query('status', ''));
        $q      = trim((string) $request->query('q', ''));

        $accountId = $this->resolveClientAccountIdBigint($request);

        if (!$accountId || $accountId <= 0) {
            return response()->json([
                'ok'    => false,
                'msg'   => 'No se pudo resolver tu cuenta cliente. Cierra sesión e inicia de nuevo o contacta soporte.',
                'trace_id' => $trace,
                'rows'  => [],
                'count' => 0,
            ], 422);
        }

        try {
            $schema = Schema::connection($conn);

            $cfgDb = (string) (config("database.connections.{$conn}.database") ?? '');
            $runtimeDb = null;
            try { $runtimeDb = DB::connection($conn)->selectOne('select database() as db')->db ?? null; } catch (\Throwable) { $runtimeDb = null; }

            $tableFq = ($cfgDb !== '') ? ($cfgDb . '.' . $table) : $table;

            if (!$schema->hasTable($table)) {
                return response()->json([
                    'ok'    => false,
                    'msg'   => 'No existe la tabla external_fiel_uploads en la conexión mysql_clientes.',
                    'trace_id' => $trace,
                    'rows'  => [],
                    'count' => 0,
                ], 500);
            }

            $accountIds = [];
            $accountIds[] = (int) $accountId;
            $accountIds[] = (int) (session('account_id') ?? 0);
            $accountIds[] = (int) (session('cuenta_id') ?? 0);

            try {
                $u = $request->user();
                if ($u) {
                    $accountIds[] = (int) ($u->account_id ?? 0);
                    $accountIds[] = (int) ($u->cuenta_id ?? 0);
                }
            } catch (\Throwable) {}

            $accountIds = array_values(array_unique(array_filter($accountIds, fn($v) => is_int($v) && $v > 0)));

            $base = DB::connection($conn)->table($tableFq);

            $qb = (clone $base);
            if (!empty($accountIds)) $qb->whereIn('account_id', $accountIds);
            else $qb->where('account_id', (int) $accountId);

            if ($status !== '') $qb->where('status', $status);

            if ($q !== '') {
                $qb->where(function ($w) use ($q) {
                    $like = "%{$q}%";
                    $w->where('rfc', 'like', $like)
                      ->orWhere('file_name', 'like', $like)
                      ->orWhere('reference', 'like', $like)
                      ->orWhere('email_externo', 'like', $like)
                      ->orWhere('razon_social', 'like', $like);
                });
            }

            $count = (int) (clone $qb)->count();

            // fallback por RFCs del account actual
            if ($count === 0) {
                $rfcs = [];

                try {
                    if (Schema::connection($conn)->hasTable('sat_credentials')
                        && Schema::connection($conn)->hasColumn('sat_credentials', 'account_id')
                        && Schema::connection($conn)->hasColumn('sat_credentials', 'rfc')
                    ) {
                        $rfcs = DB::connection($conn)->table('sat_credentials')
                            ->where('account_id', (int) $accountId)
                            ->whereNotNull('rfc')
                            ->pluck('rfc')
                            ->map(fn($v) => strtoupper(trim((string)$v)))
                            ->filter(fn($v) => $v !== '')
                            ->unique()
                            ->values()
                            ->all();
                    }
                } catch (\Throwable) {
                    $rfcs = [];
                }

                if (empty($rfcs)) {
                    try {
                        if (Schema::connection($conn)->hasTable('companies')
                            && Schema::connection($conn)->hasColumn('companies', 'account_id')
                            && Schema::connection($conn)->hasColumn('companies', 'rfc')
                        ) {
                            $rfcs = DB::connection($conn)->table('companies')
                                ->where('account_id', (int) $accountId)
                                ->whereNotNull('rfc')
                                ->pluck('rfc')
                                ->map(fn($v) => strtoupper(trim((string)$v)))
                                ->filter(fn($v) => $v !== '')
                                ->unique()
                                ->values()
                                ->all();
                        }
                    } catch (\Throwable) {
                        $rfcs = [];
                    }
                }

                if (!empty($rfcs)) {
                    $qb2 = (clone $base)->whereIn('rfc', $rfcs);

                    if ($status !== '') $qb2->where('status', $status);

                    if ($q !== '') {
                        $qb2->where(function ($w) use ($q) {
                            $like = "%{$q}%";
                            $w->where('rfc', 'like', $like)
                              ->orWhere('file_name', 'like', $like)
                              ->orWhere('reference', 'like', $like)
                              ->orWhere('email_externo', 'like', $like)
                              ->orWhere('razon_social', 'like', $like);
                        });
                    }

                    $count2 = (int) (clone $qb2)->count();
                    if ($count2 > 0) {
                        $qb = $qb2;
                        $count = $count2;
                    }
                }
            }

            $rows = $qb
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($limit)
                ->get([
                    'id',
                    'account_id',
                    'email_externo',
                    'reference',
                    'rfc',
                    'razon_social',
                    'file_path',
                    'file_name',
                    'file_size',
                    'mime',
                    'uploaded_at',
                    'status',
                    'created_at',
                ]);

            $rowsArr = [];
            foreach ($rows as $r) $rowsArr[] = (array) $r;

            $ms = (int) round((microtime(true) - $t0) * 1000);

            Log::info('external_zip_list_ok', [
                'trace_id' => $trace,
                'account_id' => (int) $accountId,
                'count'      => $count,
                'rows'       => count($rowsArr),
                'limit'      => $limit,
                'offset'     => $offset,
                'status'     => $status,
                'q'          => $q,
                'ms'         => $ms,
                'cfg_db'     => $cfgDb,
                'runtime_db' => $runtimeDb,
                'table_fq'   => $tableFq,
            ]);

            return response()->json([
                'ok'    => true,
                'trace_id' => $trace,
                'rows'  => $rowsArr,
                'count' => $count,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('external_zip_list_failed', [
                'trace_id' => $trace,
                'account_id' => (int) $accountId,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'msg'   => 'Error al consultar lista de ZIP externos.',
                'trace_id' => $trace,
                'rows'  => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Resolver account_id BIGINT real de mysql_clientes.accounts
     */
    public function resolveClientAccountIdBigint(Request $request): ?int
    {
        $conn = 'mysql_clientes';

        $user = null;
        try { $user = $request->user(); } catch (\Throwable) {}

        $candidates = [];

        // user fields numéricos
        try {
            $directFields = ['account_id','cuenta_id','cuenta','cliente_account_id','client_account_id'];
            if ($user) {
                foreach ($directFields as $f) {
                    if (isset($user->{$f}) && $user->{$f} !== null) {
                        $v = trim((string) $user->{$f});
                        if ($v !== '' && ctype_digit($v)) $candidates[] = (int) $v;
                    }
                }
            }
        } catch (\Throwable) {}

        // sesión numérica
        try {
            $sid = trim((string) session('account_id', ''));
            if ($sid !== '' && ctype_digit($sid)) $candidates[] = (int) $sid;

            $sid2 = trim((string) session('cuenta_id', ''));
            if ($sid2 !== '' && ctype_digit($sid2)) $candidates[] = (int) $sid2;
        } catch (\Throwable) {}

        // request input (por si algún flujo lo manda)
        try {
            $rid = trim((string) $request->input('account_id', $request->input('cuenta_id', '')));
            if ($rid !== '' && ctype_digit($rid)) $candidates[] = (int) $rid;
        } catch (\Throwable) {}

        $candidates = array_values(array_unique(array_filter($candidates, fn($x) => is_int($x) && $x > 0)));

        $existsInAccounts = function (int $id) use ($conn): bool {
            try {
                return DB::connection($conn)->table('accounts')->where('id', $id)->exists();
            } catch (\Throwable) {
                return false;
            }
        };

        foreach ($candidates as $id) {
            if ($existsInAccounts($id)) return $id;
        }

        // fallback por correo_contacto
        $email = '';
        try { $email = $user ? trim((string) ($user->email ?? '')) : ''; } catch (\Throwable) { $email = ''; }

        if ($email !== '' && str_contains($email, '@')) {
            try {
                $id = DB::connection($conn)->table('accounts')->where('correo_contacto', $email)->value('id');
                if ($id && (int)$id > 0) return (int) $id;
            } catch (\Throwable) {}
        }

        // fallback por user_code
        $userCode = '';
        try {
            if ($user) {
                foreach (['user_code', 'code', 'codigo', 'cliente_code'] as $f) {
                    if (!empty($user->{$f})) {
                        $userCode = trim((string) $user->{$f});
                        if ($userCode !== '') break;
                    }
                }
            }
        } catch (\Throwable) {}

        if ($userCode === '') {
            try { $userCode = trim((string) session('user_code', '')); } catch (\Throwable) {}
        }

        if ($userCode !== '') {
            try {
                $id = DB::connection($conn)->table('accounts')->where('user_code', $userCode)->value('id');
                if ($id && (int)$id > 0) return (int) $id;
            } catch (\Throwable) {}
        }

        return null;
    }

    /* ==========================================================
     * Helpers internos
     * ========================================================== */

    private function resolveCuentaIdFromQuery(Request $request): ?string
    {
        foreach (['cuenta_id', 'cuenta', 'account_id', 'account'] as $k) {
            $v = $request->query($k, null);
            if ($v === null) $v = $request->input($k, null);
            if (is_scalar($v)) {
                $s = trim((string) $v);
                if ($s !== '') return $s;
            }
        }
        return null;
    }
}
