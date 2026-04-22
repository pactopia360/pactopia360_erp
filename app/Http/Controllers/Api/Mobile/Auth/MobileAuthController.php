<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\UsuarioCuenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login'       => ['required', 'string', 'max:190'],
            'password'    => ['required', 'string', 'max:190'],
            'device_name' => ['nullable', 'string', 'max:190'],
        ]);

        $loginInput = trim((string) ($data['login'] ?? ''));
        $password   = (string) ($data['password'] ?? '');
        $deviceName = trim((string) ($data['device_name'] ?? 'mobile_cliente'));

        if ($loginInput === '' || $password === '') {
            return $this->errorResponse(
                'Credenciales incompletas.',
                'INVALID_CREDENTIALS',
                422
            );
        }

        $normalizedEmail = Str::lower($loginInput);
        $normalizedRfc   = $this->normalizeRfc($loginInput);
        $isRfcCandidate  = $this->looksLikeRfc($loginInput);

        try {
            $user = null;
            $cuenta = null;

            /*
            |--------------------------------------------------------------------------
            | 1) Intento por email exacto
            |--------------------------------------------------------------------------
            */
            $user = UsuarioCuenta::on('mysql_clientes')
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->first();

            if ($user) {
                $cuenta = $this->resolveCuentaFromUser($user);
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Intento por RFC padre -> cuenta -> usuarios de esa cuenta
            |--------------------------------------------------------------------------
            */
            if ((!$user || !$cuenta) && $isRfcCandidate && $normalizedRfc !== '') {
                $cuenta = CuentaCliente::on('mysql_clientes')
                    ->whereRaw('UPPER(TRIM(rfc_padre)) = ?', [$normalizedRfc])
                    ->first();

                if ($cuenta) {
                    $user = $this->resolveUserForCuentaAndPassword(
                        cuentaId: (string) ($cuenta->id ?? ''),
                        password: $password
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Fallback: si escribieron RFC pero existe usuario con email igual al RFC
            |--------------------------------------------------------------------------
            */
            if (!$user && $isRfcCandidate && $normalizedRfc !== '') {
                $user = UsuarioCuenta::on('mysql_clientes')
                    ->where(function ($q) use ($normalizedRfc) {
                        $q->whereRaw('UPPER(TRIM(email)) = ?', [$normalizedRfc])
                          ->orWhereRaw('UPPER(TRIM(nombre)) = ?', [$normalizedRfc]);
                    })
                    ->first();

                if ($user) {
                    $cuenta = $this->resolveCuentaFromUser($user);
                }
            }

            if (!$user) {
                return $this->errorResponse(
                    'Usuario no encontrado.',
                    'USER_NOT_FOUND',
                    404
                );
            }

            if (!$cuenta) {
                $cuenta = $this->resolveCuentaFromUser($user);
            }

            if (!$cuenta) {
                return $this->errorResponse(
                    'La cuenta del usuario no está configurada correctamente.',
                    'ACCOUNT_NOT_FOUND',
                    422
                );
            }

            if (!$this->passwordMatches($password, (string) ($user->password ?? ''))) {
                return $this->errorResponse(
                    'Contraseña incorrecta.',
                    'INVALID_PASSWORD',
                    422
                );
            }

            if (!$this->isCuentaActive($cuenta)) {
                return $this->errorResponse(
                    'Tu cuenta no está activa.',
                    'ACCOUNT_INACTIVE',
                    403,
                    [
                        'estado_cuenta' => (string) ($cuenta->estado_cuenta ?? ''),
                    ]
                );
            }

            if ($this->isCuentaBlocked($cuenta)) {
                return $this->errorResponse(
                    'Tu cuenta está bloqueada por pago pendiente.',
                    'ACCOUNT_BLOCKED',
                    403,
                    [
                        'estado_cuenta'    => (string) ($cuenta->estado_cuenta ?? ''),
                        'modo_cobro'       => (string) ($cuenta->modo_cobro ?? ''),
                        'next_invoice_date'=> $this->safeDate($cuenta->next_invoice_date ?? null),
                        'is_blocked'       => 1,
                    ]
                );
            }

            if (!$this->isUserActive($user)) {
                return $this->errorResponse(
                    'Tu usuario está inactivo.',
                    'USER_INACTIVE',
                    403
                );
            }

            $user->tokens()->where('name', $deviceName)->delete();

            $token = $user->createToken($deviceName, ['mobile'])->plainTextToken;

            return response()->json([
                'ok'   => true,
                'msg'  => 'Login correcto.',
                'data' => [
                    'token' => $token,
                    'user'  => [
                        'id'        => $user->id,
                        'nombre'    => (string) ($user->nombre ?? ''),
                        'email'     => (string) ($user->email ?? ''),
                        'cuenta_id' => (string) ($user->cuenta_id ?? ''),
                    ],
                    'account' => [
                        'id'              => (string) ($cuenta->id ?? ''),
                        'nombre_comercial'=> (string) ($cuenta->nombre_comercial ?? ''),
                        'razon_social'    => (string) ($cuenta->razon_social ?? ''),
                        'rfc_padre'       => (string) ($cuenta->rfc_padre ?? ''),
                        'estado_cuenta'   => (string) ($cuenta->estado_cuenta ?? ''),
                        'modo_cobro'      => (string) ($cuenta->modo_cobro ?? ''),
                        'is_blocked'      => (int) ((int) ($cuenta->is_blocked ?? 0) === 1),
                        'next_invoice_date' => $this->safeDate($cuenta->next_invoice_date ?? null),
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[MOBILE_AUTH_LOGIN] Error', [
                'login' => $loginInput,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Ocurrió un error al iniciar sesión.',
                'LOGIN_FAILED',
                500
            );
        }
    }

    public function me(Request $request): JsonResponse
    {
        /** @var UsuarioCuenta|null $user */
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse(
                'No autenticado.',
                'UNAUTHENTICATED',
                401
            );
        }

        $cuenta = $this->resolveCuentaFromUser($user);

        return response()->json([
            'ok'   => true,
            'msg'  => 'Sesión válida.',
            'data' => [
                'user' => [
                    'id'        => $user->id,
                    'nombre'    => (string) ($user->nombre ?? ''),
                    'email'     => (string) ($user->email ?? ''),
                    'cuenta_id' => (string) ($user->cuenta_id ?? ''),
                ],
                'account' => $cuenta ? [
                    'id'              => (string) ($cuenta->id ?? ''),
                    'nombre_comercial'=> (string) ($cuenta->nombre_comercial ?? ''),
                    'razon_social'    => (string) ($cuenta->razon_social ?? ''),
                    'rfc_padre'       => (string) ($cuenta->rfc_padre ?? ''),
                    'estado_cuenta'   => (string) ($cuenta->estado_cuenta ?? ''),
                    'modo_cobro'      => (string) ($cuenta->modo_cobro ?? ''),
                    'is_blocked'      => (int) ((int) ($cuenta->is_blocked ?? 0) === 1),
                    'next_invoice_date' => $this->safeDate($cuenta->next_invoice_date ?? null),
                ] : null,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var UsuarioCuenta|null $user */
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse(
                'No autenticado.',
                'UNAUTHENTICATED',
                401
            );
        }

        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json([
            'ok'  => true,
            'msg' => 'Sesión cerrada correctamente.',
        ], 200);
    }

    private function resolveCuentaFromUser(UsuarioCuenta $user): ?CuentaCliente
    {
        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return null;
        }

        return CuentaCliente::on('mysql_clientes')
            ->where('id', $cuentaId)
            ->first();
    }

    private function resolveUserForCuentaAndPassword(string $cuentaId, string $password): ?UsuarioCuenta
    {
        if ($cuentaId === '') {
            return null;
        }

        $users = UsuarioCuenta::on('mysql_clientes')
            ->where('cuenta_id', $cuentaId)
            ->get();

        if ($users->isEmpty()) {
            return null;
        }

        $preferred = $users->sortBy(function ($user) {
            $rol = Str::lower(trim((string) ($user->rol ?? '')));
            $isOwner = (int) ($user->is_owner ?? 0) === 1;
            $isAdmin = (int) ($user->is_admin ?? 0) === 1;

            if ($isOwner || $isAdmin || in_array($rol, ['owner', 'admin', 'propietario'], true)) {
                return 0;
            }

            return 1;
        });

        foreach ($preferred as $user) {
            if ($this->passwordMatches($password, (string) ($user->password ?? ''))) {
                return $user;
            }
        }

        return null;
    }

    private function passwordMatches(string $plain, string $hashed): bool
    {
        if ($plain === '' || $hashed === '') {
            return false;
        }

        return Hash::check($plain, $hashed);
    }

    private function isCuentaBlocked(CuentaCliente $cuenta): bool
    {
        return (int) ($cuenta->is_blocked ?? 0) === 1;
    }

    private function isCuentaActive(CuentaCliente $cuenta): bool
    {
        $estado = Str::lower(trim((string) ($cuenta->estado_cuenta ?? 'activa')));
        return !in_array($estado, ['inactiva', 'cancelada', 'suspendida'], true);
    }

    private function isUserActive(UsuarioCuenta $user): bool
    {
        if (isset($user->activo)) {
            return (int) $user->activo === 1;
        }

        if (isset($user->status)) {
            $status = Str::lower(trim((string) $user->status));
            return !in_array($status, ['inactive', 'inactivo', 'disabled', 'bloqueado'], true);
        }

        return true;
    }

    private function looksLikeRfc(string $value): bool
    {
        $rfc = $this->normalizeRfc($value);
        return (bool) preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc);
    }

    private function normalizeRfc(string $value): string
    {
        $value = Str::upper(trim($value));
        return preg_replace('/[^A-Z0-9Ñ&]/u', '', $value) ?? '';
    }

    private function safeDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function errorResponse(
        string $msg,
        string $code,
        int $status,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'ok'   => false,
            'msg'  => $msg,
            'code' => $code,
        ], $extra), $status);
    }
}