<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\Sat\SatExternalPublicController.php
// PACTOPIA360 · SAT External Public Controller (cache-safe)
// ✅ Endpoints:
// - toggleMode()                -> POST /cliente/sat/mode
// - externalInviteFallback()    -> POST /cliente/sat/external/invite (fallback si no existe SatDescargaController::externalInvite)
// - externalInviteGet()         -> GET  /cliente/sat/external/invite (UI humana, evita 405)
// - externalInviteLegacyRedirect() -> GET /cliente/sat/fiel/external/invite (legacy GET → redirect)
// - externalRegisterForm()      -> GET  /cliente/sat/external/register (SIGNED, sin login)
// - externalRegisterStore()     -> POST /cliente/sat/external/register (SIGNED, sin login)
//
// Objetivos:
// ✅ No usar closures en routes (route:cache safe)
// ✅ Validación firmada manual (evita InvalidSignatureException + redirects)
// ✅ Aceptar cuenta por: cuenta | cuenta_id | account | account_id
// ✅ Guardar en mysql_clientes.sat_credentials (SatCredential model)
// ✅ Password cifrada (key_password_enc si existe; si no, key_password)

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SatExternalPublicController extends Controller
{
    /**
     * POST /cliente/sat/mode
     * Toggle cookie sat_mode demo/prod.
     */
    public function toggleMode(Request $request): JsonResponse
    {
        $current = strtolower((string) $request->cookie('sat_mode', 'prod'));
        $next    = ($current === 'demo') ? 'prod' : 'demo';
        $minutes = 60 * 24 * 30;

        return response()
            ->json(['ok' => true, 'mode' => $next])
            ->cookie('sat_mode', $next, $minutes, '/', null, false, false, false, 'lax');
    }

    /**
     * Fallback de invite (solo si NO existe SatDescargaController::externalInvite).
     * POST /cliente/sat/external/invite
     */
    public function externalInviteFallback(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:190'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => $v->errors()->first(),
                'errors'  => $v->errors(),
            ], 422);
        }

        $email = (string) $request->input('email');
        $note  = trim((string) $request->input('note', ''));

        // Detecta cuenta_id del usuario logueado (normalmente auth:web + session.cliente)
        $u = Auth::guard('web')->user();
        $cuentaId = $this->resolveCuentaIdFromSessionOrUser($u);

        if (!is_scalar($cuentaId) || trim((string) $cuentaId) === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo resolver la cuenta del usuario. Cierra sesión e inicia de nuevo o contacta soporte.',
                'errors'  => ['cuenta_id' => ['No se pudo resolver la cuenta del usuario.']],
            ], 422);
        }

        $cuentaId = trim((string) $cuentaId);

        // 72h: URL firmada con email + cuenta_id
        $signedUrl = URL::temporarySignedRoute(
            'cliente.sat.external.register',
            now()->addHours(72),
            [
                'email'     => $email,
                'cuenta_id' => $cuentaId,
            ]
        );

        // Enviar mail básico (si mail está configurado). Si falla, no revienta.
        try {
            $subject = 'Pactopia360 · Invitación para registrar RFC/CSD';
            $body = "Hola,\n\nSe te invitó a registrar RFC/CSD para descargas SAT en Pactopia360.\n\n";
            $body .= "Liga segura:\n{$signedUrl}\n\n";
            if ($note !== '') $body .= "Nota:\n{$note}\n\n";
            $body .= "Si no esperabas este correo, puedes ignorarlo.\n\nPactopia360";

            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            // no-op
        }

        return response()->json([
            'ok'        => true,
            'message'   => 'Invitación generada.',
            'url'       => $signedUrl,
            'cuenta_id' => $cuentaId,
        ]);
    }

    /**
     * GET legacy: /cliente/sat/fiel/external/invite
     * Cache-safe, sin closure: redirige al GET oficial.
     */
    public function externalInviteLegacyRedirect(Request $request): SymfonyResponse
    {
        return redirect()->route('cliente.sat.external.invite.get');
    }

    /**
     * GET /cliente/sat/external/invite
     * Página “humana” para generar invitación (evita 405 en navegador).
     * Nota: el envío real es por POST /external/invite.
     */
    public function externalInviteGet(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        // Si llega por AJAX/JSON, NO devolvemos HTML.
        $accept = strtolower((string) $request->header('accept', ''));
        if ($request->expectsJson() || str_contains($accept, 'application/json')) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Método inválido. Usa POST /cliente/sat/external/invite.',
            ], 405);
        }

        $view = null;
        if (view()->exists('cliente.sat.external.invite')) {
            $view = 'cliente.sat.external.invite';
        } elseif (view()->exists('cliente.sat.external_invite')) {
            $view = 'cliente.sat.external_invite';
        }

        $payload = [
            'post_url' => route('cliente.sat.external.invite'),
            'csrf'     => csrf_token(),
            'email'    => old('email', ''),
            'note'     => old('note', ''),
        ];

        if ($view) {
            return response()->view($view, $payload);
        }

        // Fallback HTML mínimo (no depende de Blade)
        $action = route('cliente.sat.external.invite');

        return response()->make(
            '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
            <title>P360 · Invitación registro SAT</title></head>
            <body style="font-family:system-ui;margin:24px;max-width:720px;">
              <h2>Pactopia360 · Invitación para registro SAT</h2>
              <p>Genera una liga firmada para que el cliente registre su RFC/CSD.</p>
              <form method="POST" action="' . htmlspecialchars($action, ENT_QUOTES, "UTF-8") . '">
                <input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") . '">
                <label style="display:block;margin:12px 0 6px;">Email</label>
                <input name="email" type="email" required maxlength="190" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:10px;">
                <label style="display:block;margin:12px 0 6px;">Nota (opcional)</label>
                <textarea name="note" maxlength="500" style="width:100%;min-height:100px;padding:10px;border:1px solid #ccc;border-radius:10px;"></textarea>
                <div style="margin-top:14px;">
                  <button type="submit" style="padding:10px 14px;border-radius:12px;border:0;background:#111;color:#fff;cursor:pointer;">
                    Generar invitación
                  </button>
                </div>
              </form>
            </body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * GET /cliente/sat/external/register (SIGNED, sin login)
     */
    public function externalRegisterForm(Request $request): SymfonyResponse
    {
        // Validación firmada manual: evita redirects por InvalidSignatureException
        if (method_exists($request, 'hasValidSignature') && !$request->hasValidSignature()) {
            abort(403, 'Enlace inválido o expirado.');
        }

        $email    = (string) $request->query('email', '');
        $cuentaId = $this->pickCuentaIdFromRequest($request);

        $viewName = $this->resolveRegisterViewName();

        $payload = [
            'email'     => $email,
            'cuenta_id' => $cuentaId,
            'success'   => false,
            'saved'     => null,
            'errorsBag' => null,
        ];

        if ($viewName) {
            return response()->view($viewName, $payload);
        }

        // Fallback HTML mínimo
        $qs     = $request->getQueryString();
        $action = $request->url() . ($qs ? ('?' . $qs) : '');

        return response()->make(
            '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>P360 · Registro externo</title></head>
            <body style="font-family:system-ui;margin:24px;">
            <h2>Pactopia360 · Registro externo SAT</h2>
            <p><b>Falta la vista Blade</b>. Crea: <code>resources/views/cliente/sat/external/register.blade.php</code></p>
            <p>Cuenta detectada: <code>' . htmlspecialchars((string) $cuentaId, ENT_QUOTES, "UTF-8") . '</code></p>
            <form method="POST" action="' . $action . '" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="' . csrf_token() . '">
                <p>Correo: <code>' . htmlspecialchars($email, ENT_QUOTES, "UTF-8") . '</code></p>
                <label>RFC <input name="rfc" maxlength="13" required></label><br><br>
                <label>Razón social <input name="razon_social" maxlength="190"></label><br><br>
                <label>.cer <input type="file" name="cer" accept=".cer" required></label><br><br>
                <label>.key <input type="file" name="key" accept=".key" required></label><br><br>
                <label>Contraseña <input type="password" name="key_password" required></label><br><br>
                <label><input type="checkbox" name="accept" value="1"> Acepto</label><br><br>
                <button type="submit">Enviar</button>
            </form>
            </body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * POST /cliente/sat/external/register (SIGNED, sin login)
     * Crea/actualiza SatCredential y guarda CSD.
     */
    public function externalRegisterStore(Request $request)
    {
        if (method_exists($request, 'hasValidSignature') && !$request->hasValidSignature()) {
            abort(403, 'Enlace inválido o expirado.');
        }

        $accept    = strtolower((string) $request->header('accept', ''));
        $format    = strtolower((string) $request->query('format', ''));
        $wantsJson = ($format === 'json') || str_contains($accept, 'application/json');

        $email    = (string) $request->query('email', '');
        $cuentaId = $this->pickCuentaIdFromRequest($request);

        try {
            Log::info('p360_sat_external_register_start', [
                'email'      => $email,
                'cuenta_id'  => $cuentaId,
                'ip'         => $request->ip(),
                'ua'         => (string) $request->userAgent(),
                'qs'         => $request->getQueryString(),
                'accept'     => $accept,
                'xrw'        => (string) $request->header('x-requested-with', ''),
                'wantsJson'  => $wantsJson,
                'env'        => app()->environment(),
            ]);
        } catch (\Throwable) {
        }

        if (!$cuentaId && trim($email) !== '') {
            $cuentaId = $this->resolveCuentaIdFromEmail($email) ?: null;
        }

        if (!$cuentaId) {
            $msg = 'No se pudo resolver la cuenta del registro. Pide al cliente reenviar la invitación o contacta soporte.';

            try {
                Log::warning('p360_sat_external_register_no_account', ['email' => $email, 'ip' => $request->ip()]);
            } catch (\Throwable) {
            }

            if (!$wantsJson) {
                return redirect()->to($request->fullUrl())
                    ->withErrors(['cuenta_id' => $msg])
                    ->withInput($request->except(['key_password', 'cer', 'key']));
            }

            return response()->json(['ok' => false, 'message' => $msg, 'errors' => ['cuenta_id' => [$msg]]], 422);
        }

        $v = Validator::make($request->all(), [
            'rfc'          => ['required', 'string', 'max:13', 'regex:/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'key_password' => ['required', 'string', 'max:120'],
            'cer'          => ['required', 'file', 'max:5120'],
            'key'          => ['required', 'file', 'max:5120'],
            'note'         => ['nullable', 'string', 'max:500'],
            'accept'       => ['accepted'],
        ], [
            'rfc.regex'       => 'El RFC no tiene un formato válido.',
            'accept.accepted' => 'Debes confirmar la autorización para registrar este RFC.',
        ]);

        $cer = $request->file('cer');
        $key = $request->file('key');

        if ($cer && strtolower((string) $cer->getClientOriginalExtension()) !== 'cer') {
            $v->after(fn($validator) => $validator->errors()->add('cer', 'El archivo debe ser .cer'));
        }
        if ($key && strtolower((string) $key->getClientOriginalExtension()) !== 'key') {
            $v->after(fn($validator) => $validator->errors()->add('key', 'El archivo debe ser .key'));
        }

        if ($v->fails()) {
            try {
                Log::info('p360_sat_external_register_validation_failed', ['email' => $email, 'cuenta_id' => $cuentaId, 'first' => $v->errors()->first()]);
            } catch (\Throwable) {
            }

            if (!$wantsJson) {
                return redirect()->to($request->fullUrl())
                    ->withErrors($v->errors())
                    ->withInput($request->except(['key_password', 'cer', 'key']));
            }

            return response()->json(['ok' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $rfc  = strtoupper(trim((string) $request->input('rfc')));
        $rs   = trim((string) $request->input('razon_social', ''));
        $note = trim((string) $request->input('note', ''));
        $pass = (string) $request->input('key_password');

        $cerTmp = $cer?->getRealPath();
        $keyTmp = $key?->getRealPath();

        [$okCsd, $csdError, $csdDetails] = $this->validateCsdPair(
            is_string($cerTmp) ? $cerTmp : '',
            is_string($keyTmp) ? $keyTmp : '',
            $pass
        );

        // Soft mode local si no existe openssl CLI en Windows
        if (!$okCsd && app()->environment('local')) {
            $convErr = (string) ($csdDetails['key']['convert_error'] ?? '');
            $outHead = (string) ($csdDetails['key']['openssl_cli']['output_head'] ?? '');

            $noCli =
                str_contains(strtolower($outHead), 'no se reconoce como un comando') ||
                str_contains(strtolower($outHead), 'not recognized as an internal or external command') ||
                str_contains(strtolower($convErr), 'openssl cli') ||
                str_contains(strtolower($convErr), 'no está disponible');

            if ($noCli) {
                $okCsd = true;
                $csdError = null;
                $csdDetails['key']['soft_mode'] = true;

                try {
                    Log::warning('p360_sat_external_register_soft_mode_local', ['email' => $email, 'cuenta_id' => $cuentaId, 'rfc' => $rfc, 'reason' => 'openssl_cli_missing_windows']);
                } catch (\Throwable) {
                }
            }
        }

        if (!$okCsd) {
            $msg = $csdError ?: 'No se pudo validar el CSD (certificado/llave/contraseña).';

            try {
                Log::info('p360_sat_external_register_csd_failed', ['email' => $email, 'cuenta_id' => $cuentaId, 'rfc' => $rfc, 'msg' => $msg, 'details' => $csdDetails]);
            } catch (\Throwable) {
            }

            if (!$wantsJson) {
                return redirect()->to($request->fullUrl())
                    ->withErrors(['csd' => $msg])
                    ->withInput($request->except(['key_password', 'cer', 'key']));
            }

            return response()->json(['ok' => false, 'message' => $msg, 'errors' => ['csd' => [$msg]], 'details' => $csdDetails], 422);
        }

        // Guardar archivos
        $cerDir = "sat/certs/{$cuentaId}";
        $keyDir = "sat/keys/{$cuentaId}";
        $disk = Storage::disk('public');

        $disk->makeDirectory($cerDir);
        $disk->makeDirectory($keyDir);

        $cerName = Str::random(44) . '.cer';
        $keyName = Str::random(44) . '.key';

        $cerPath = $disk->putFileAs($cerDir, $cer, $cerName);
        $keyPath = $disk->putFileAs($keyDir, $key, $keyName);

        // Persistir SatCredential
        $cred = SatCredential::query()
            ->where('rfc', $rfc)
            ->where('cuenta_id', $cuentaId)
            ->first();

        if (!$cred) {
            $cred = new SatCredential();
            $cred->rfc = $rfc;
        }

        $cred->cuenta_id  = $cuentaId;
        $cred->account_id = $cuentaId;

        if ($rs !== '') $cred->razon_social = $rs;

        $cred->cer_path = $cerPath;
        $cred->key_path = $keyPath;

        try {
            $enc = encrypt($pass);
        } catch (\Throwable) {
            $enc = base64_encode($pass);
        }

        $conn   = $cred->getConnectionName();
        $table  = $cred->getTable();
        $schema = Schema::connection($conn);

        if ($schema->hasColumn($table, 'key_password_enc')) {
            $cred->key_password_enc = $enc;
            if ($schema->hasColumn($table, 'key_password')) $cred->key_password = null;
        } else {
            $cred->key_password = $enc;
        }

        $meta = [
            'source'         => 'external_register',
            'external_email' => $email,
            'external_note'  => $note,
            'ip'             => $request->ip(),
            'ua'             => (string) $request->userAgent(),
            'stored'         => ['cer' => $cerPath, 'key' => $keyPath],
            'registered_at'  => now()->toDateTimeString(),
            'validation'     => ['ok' => true, 'at' => now()->toDateTimeString(), 'details' => $csdDetails],
        ];

        try {
            $oldMeta = is_array($cred->meta) ? $cred->meta : (is_string($cred->meta) ? (json_decode($cred->meta, true) ?: []) : []);
        } catch (\Throwable) {
            $oldMeta = [];
        }
        $cred->meta = array_merge($oldMeta, $meta);

        if ($schema->hasColumn($table, 'validado')) $cred->validado = 1;
        if ($schema->hasColumn($table, 'validated_at')) $cred->validated_at = now();
        if ($schema->hasColumn($table, 'estatus')) $cred->estatus = 'ok';
        elseif ($schema->hasColumn($table, 'status')) $cred->status = 'ok';

        $cred->save();

        $saved = [
            'id'           => (string) $cred->getKey(),
            'cuenta_id'    => $cuentaId,
            'rfc'          => $rfc,
            'razon_social' => $cred->razon_social ?? $rs,
            'path'         => ['cer' => $cerPath, 'key' => $keyPath],
            'soft_mode'    => (bool) ($csdDetails['key']['soft_mode'] ?? false),
        ];

        try {
            Log::info('p360_sat_external_register_saved', [
                'id'        => $saved['id'],
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'email'     => $email,
                'soft_mode' => $saved['soft_mode'],
            ]);
        } catch (\Throwable) {
        }

        if (!$wantsJson) {
            // Fallback duro: además de flash, manda ok/rid en query para que la vista lo muestre aunque no haya sesión.
            $url = $request->fullUrl();
            $sep = str_contains($url, '?') ? '&' : '?';
            $url = $url . $sep . 'ok=1&rid=' . urlencode((string) $saved['id']);

            return redirect()->to($url)
                ->with('ext_reg_success', true)
                ->with('ext_reg_saved', $saved);
        }

        return response()->json(['ok' => true, 'msg' => 'Registro recibido.', 'data' => $saved], 200);
    }

    // ======================================================
    // Helpers
    // ======================================================

    private function resolveRegisterViewName(): ?string
    {
        if (view()->exists('cliente.sat.external.register')) {
            return 'cliente.sat.external.register';
        }
        if (view()->exists('cliente.sat.external_register')) {
            return 'cliente.sat.external_register';
        }
        return null;
    }

    /**
     * Lee cuenta desde querystring soportando:
     * cuenta | cuenta_id | account | account_id
     */
    private function pickCuentaIdFromRequest(Request $request): ?string
    {
        foreach (['cuenta_id', 'cuenta', 'account_id', 'account'] as $k) {
            $v = $request->query($k, null);
            if (is_scalar($v) && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }
        return null;
    }

    private function resolveCuentaIdFromEmail(string $email): ?string
    {
        try {
            $userModel = config('auth.providers.clientes.model');
            if (!is_string($userModel) || !class_exists($userModel)) return null;

            $u = $userModel::query()->where('email', $email)->first();
            if (!$u) return null;

            $candidate = null;

            try { $candidate = $u->cuenta_id ?? null; } catch (\Throwable) {}
            if (!$candidate) { try { $candidate = $u->account_id ?? null; } catch (\Throwable) {} }
            if (!$candidate) { try { $candidate = $u->cuenta?->id ?? null; } catch (\Throwable) {} }
            if (!$candidate) { try { $candidate = $u->account?->id ?? null; } catch (\Throwable) {} }

            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        } catch (\Throwable) {
            // no-op
        }
        return null;
    }

    private function resolveCuentaIdFromSessionOrUser($u): ?string
    {
        $cuentaId = null;

        foreach ([
            'cliente.cuenta_id',
            'cliente.account_id',
            'client.cuenta_id',
            'client.account_id',
            'cuenta_id',
            'account_id',
            'client_cuenta_id',
            'client_account_id',
        ] as $k) {
            $v = session($k);
            if (is_scalar($v) && trim((string) $v) !== '') {
                $cuentaId = trim((string) $v);
                break;
            }
        }

        if (!$cuentaId) {
            try { $v = $u?->cuenta_id ?? null; if (is_scalar($v) && trim((string) $v) !== '') $cuentaId = trim((string) $v); } catch (\Throwable) {}
        }
        if (!$cuentaId) {
            try { $v = $u?->account_id ?? null; if (is_scalar($v) && trim((string) $v) !== '') $cuentaId = trim((string) $v); } catch (\Throwable) {}
        }
        if (!$cuentaId) {
            try { $v = $u?->cuenta?->id ?? null; if (is_scalar($v) && trim((string) $v) !== '') $cuentaId = trim((string) $v); } catch (\Throwable) {}
        }
        if (!$cuentaId) {
            try { $v = $u?->account?->id ?? null; if (is_scalar($v) && trim((string) $v) !== '') $cuentaId = trim((string) $v); } catch (\Throwable) {}
        }

        return $cuentaId;
    }

    /**
     * ✅ Valida inmediatamente:
     * - El .cer es legible (DER o PEM)
     * - El .key puede abrirse con password
     * - El par CERT <-> KEY corresponde
     *
     * @return array{0:bool,1:?string,2:array}
     */
    private function validateCsdPair(string $cerPath, string $keyPath, string $password): array
    {
        $details = [
            'openssl' => extension_loaded('openssl'),
            'cert'    => ['readable' => false],
            'key'     => ['readable' => false],
            'match'   => false,
        ];

        if (!extension_loaded('openssl')) {
            return [false, 'El servidor no tiene habilitado OpenSSL (ext-openssl).', $details];
        }

        if ($cerPath === '' || !is_file($cerPath)) {
            return [false, 'No se encontró el archivo .cer temporal.', $details];
        }
        if ($keyPath === '' || !is_file($keyPath)) {
            return [false, 'No se encontró el archivo .key temporal.', $details];
        }

        $cerRaw = @file_get_contents($cerPath);
        if (!is_string($cerRaw) || $cerRaw === '') {
            return [false, 'No se pudo leer el certificado (.cer).', $details];
        }

        $keyRaw = @file_get_contents($keyPath);
        if (!is_string($keyRaw) || $keyRaw === '') {
            return [false, 'No se pudo leer la llave privada (.key).', $details];
        }

        $certPem = $this->normalizeCertificateToPem($cerRaw);
        if ($certPem === null) {
            return [false, 'El certificado (.cer) no tiene un formato compatible.', $details];
        }

        $details['cert']['readable'] = true;

        // Key: SAT suele entregar .key en DER binario (PKCS#8). Intentamos PEM y si no, DER->PEM vía openssl.
        $keyPem = $this->normalizePrivateKeyToPem($keyRaw, $password, $keyPath, $details);

        if ($keyPem === null) {
            $msg = 'La llave privada (.key) no tiene un formato compatible (PEM/DER) o no se pudo convertir.';
            if (!empty($details['key']['convert_error'])) {
                $msg .= ' ' . (string) $details['key']['convert_error'];
            }
            return [false, $msg, $details];
        }

        // Abrir llave privada con password
        $priv = @openssl_pkey_get_private($keyPem, $password);
        if ($priv === false) {
            // Intento: sin password por si viene sin cifrar
            $priv = @openssl_pkey_get_private($keyPem);
        }

        if ($priv === false) {
            return [false, 'La contraseña es incorrecta o la llave privada (.key) está dañada.', $details];
        }

        $details['key']['readable'] = true;

        // Extraer llave pública del cert
        $pub = @openssl_pkey_get_public($certPem);
        if ($pub === false) {
            return [false, 'No se pudo extraer la llave pública del certificado (.cer).', $details];
        }

        $privDet = @openssl_pkey_get_details($priv);
        $pubDet  = @openssl_pkey_get_details($pub);

        if (!is_array($privDet) || !is_array($pubDet)) {
            return [false, 'No se pudieron leer los detalles de las llaves (OpenSSL).', $details];
        }

        $details['key']['type']  = $privDet['type'] ?? null;
        $details['cert']['type'] = $pubDet['type'] ?? null;

        $match = false;

        if (
            isset($privDet['type'], $pubDet['type']) &&
            (int) $privDet['type'] === OPENSSL_KEYTYPE_RSA &&
            (int) $pubDet['type'] === OPENSSL_KEYTYPE_RSA &&
            isset($privDet['rsa']['n'], $pubDet['rsa']['n'])
        ) {
            $match = hash_equals((string) $privDet['rsa']['n'], (string) $pubDet['rsa']['n']);
            $details['match_method'] = 'rsa_modulus';
        } else {
            $privPub = $privDet['key'] ?? null;
            $certPub = $pubDet['key'] ?? null;

            if (is_string($privPub) && is_string($certPub) && $privPub !== '' && $certPub !== '') {
                $match = hash_equals(trim($privPub), trim($certPub));
                $details['match_method'] = 'pem_public_compare';
            } else {
                $test = 'P360-CSD-PAIR-TEST';
                $sig  = '';
                $signOk = @openssl_sign($test, $sig, $priv, OPENSSL_ALGO_SHA256);
                if ($signOk && is_string($sig) && $sig !== '') {
                    $verify = @openssl_verify($test, $sig, $pub, OPENSSL_ALGO_SHA256);
                    $match = ($verify === 1);
                    $details['match_method'] = 'sign_verify';
                } else {
                    $details['match_method'] = 'unable_to_compare';
                }
            }
        }

        $details['match'] = $match;

        if (!$match) {
            return [false, 'El certificado (.cer) no corresponde a la llave privada (.key).', $details];
        }

        return [true, null, $details];
    }

    /**
     * Convierte DER/PEM de certificado a PEM.
     */
    private function normalizeCertificateToPem(string $raw): ?string
    {
        $trim = ltrim($raw);

        if (str_starts_with($trim, '-----BEGIN CERTIFICATE-----')) {
            return $raw;
        }

        $b64 = base64_encode($raw);
        if (!is_string($b64) || $b64 === '') return null;

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($b64, 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function normalizePrivateKeyToPem(string $raw, string $password, string $keyPath, array &$details): ?string
    {
        $trim = ltrim($raw);

        if (
            str_starts_with($trim, '-----BEGIN PRIVATE KEY-----') ||
            str_starts_with($trim, '-----BEGIN ENCRYPTED PRIVATE KEY-----') ||
            str_starts_with($trim, '-----BEGIN RSA PRIVATE KEY-----') ||
            str_starts_with($trim, '-----BEGIN EC PRIVATE KEY-----')
        ) {
            $details['key']['format'] = 'pem';
            return $raw;
        }

        $details['key']['format'] = 'der_or_unknown';

        if ($keyPath === '' || !is_file($keyPath)) {
            $details['key']['convert_error'] = 'No se encontró el archivo temporal de llave para conversión.';
            return null;
        }

        $pem = $this->convertDerKeyToPemUsingOpenssl($keyPath, $password, $details);

        if (is_string($pem) && trim($pem) !== '' && str_contains($pem, 'BEGIN')) {
            $details['key']['format'] = 'der_converted_to_pem';
            return $pem;
        }

        return null;
    }

    private function convertDerKeyToPemUsingOpenssl(string $keyPath, string $password, array &$details): ?string
    {
        $details['key']['openssl_cli'] = [
            'available'   => false,
            'ran'         => false,
            'cmd'         => null,
            'output_head' => null,
        ];

        if (!function_exists('shell_exec')) {
            $details['key']['convert_error'] = 'shell_exec() no está disponible en este servidor.';
            return null;
        }

        $ver = @shell_exec('openssl version 2>&1');
        if (!is_string($ver) || trim($ver) === '' || stripos($ver, 'openssl') === false) {
            $details['key']['convert_error'] = 'OpenSSL CLI no está disponible en este servidor (no está en PATH).';
            $details['key']['openssl_cli']['output_head'] = is_string($ver) ? substr(trim($ver), 0, 180) : null;
            return null;
        }

        $details['key']['openssl_cli']['available'] = true;

        $outPem = tempnam(sys_get_temp_dir(), 'p360_key_');
        if (!is_string($outPem) || $outPem === '') {
            $details['key']['convert_error'] = 'No se pudo crear archivo temporal para PEM.';
            return null;
        }
        $outPemFile = $outPem . '.pem';

        $inArg   = escapeshellarg($keyPath);
        $outArg  = escapeshellarg($outPemFile);
        $passArg = escapeshellarg('pass:' . $password);

        $cmd = "openssl pkcs8 -inform DER -in {$inArg} -passin {$passArg} -out {$outArg} 2>&1";
        $details['key']['openssl_cli']['ran'] = true;
        $details['key']['openssl_cli']['cmd'] = 'openssl pkcs8 -inform DER -in [..] -passin pass:[..] -out [..]';

        $output = @shell_exec($cmd);
        $details['key']['openssl_cli']['output_head'] = is_string($output) ? substr(trim($output), 0, 180) : null;

        if (!is_file($outPemFile) || filesize($outPemFile) < 80) {
            $details['key']['convert_error'] = 'OpenSSL no pudo convertir la llave (pkcs8). Verifica password o que el .key sea válido.';
            try { @unlink($outPemFile); } catch (\Throwable) {}
            try { @unlink($outPem); } catch (\Throwable) {}
            return null;
        }

        $pem = @file_get_contents($outPemFile);

        try { @unlink($outPemFile); } catch (\Throwable) {}
        try { @unlink($outPem); } catch (\Throwable) {}

        if (!is_string($pem) || trim($pem) === '') {
            $details['key']['convert_error'] = 'La conversión generó PEM vacío.';
            return null;
        }

        return $pem;
    }

    // ======================================================
    // LEGACY / COMPAT (NO recomendado)
    // Si ya usas FielExternalController para estas acciones, estas funciones no deberían usarse.
    // ======================================================

    public function fielDownload(Request $request, $id): StreamedResponse
    {
        $row = \DB::connection('mysql_clientes')
            ->table('sat_external_zips')
            ->where('id', (int) $id)
            ->first();

        if (!$row) abort(404, 'ZIP no encontrado.');

        $disk = $row->disk ?? 'local';
        $path = $row->path ?? ($row->zip_path ?? null);

        if (!$path) abort(404, 'Archivo no disponible.');

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'Archivo no encontrado en almacenamiento.');
        }

        $downloadName = $row->file_name ?? basename($path);

        return Storage::disk($disk)->download($path, $downloadName);
    }

    public function fielUpdate(Request $request, $id): JsonResponse
    {
        $payload = $request->validate([
            'rfc'          => ['nullable', 'string', 'max:13'],
            'razon_social' => ['nullable', 'string', 'max:190'],
            'reference'    => ['nullable', 'string', 'max:120'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $ok = \DB::connection('mysql_clientes')
            ->table('sat_external_zips')
            ->where('id', (int) $id)
            ->update(array_merge($payload, ['updated_at' => now()]));

        if (!$ok) {
            return response()->json(['ok' => false, 'msg' => 'No se pudo actualizar.'], 422);
        }

        return response()->json(['ok' => true, 'msg' => 'Actualizado.']);
    }

    public function fielDestroy(Request $request, $id): JsonResponse
    {
        $row = \DB::connection('mysql_clientes')
            ->table('sat_external_zips')
            ->where('id', (int) $id)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'msg' => 'No encontrado.'], 404);
        }

        $disk = $row->disk ?? 'local';
        $path = $row->path ?? ($row->zip_path ?? null);

        if ($path && Storage::disk($disk)->exists($path)) {
            try { Storage::disk($disk)->delete($path); } catch (\Throwable) {}
        }

        \DB::connection('mysql_clientes')
            ->table('sat_external_zips')
            ->where('id', (int) $id)
            ->delete();

        return response()->json(['ok' => true, 'msg' => 'Eliminado.']);
    }
}
