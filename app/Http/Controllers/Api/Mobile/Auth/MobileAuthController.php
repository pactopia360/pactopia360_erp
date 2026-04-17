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
use Illuminate\Support\Str;

final class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login'       => ['required', 'string', 'max:190'], // email o RFC
            'password'    => ['required', 'string', 'max:190'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $loginInput = trim((string) $data['login']);
        $password   = (string) $data['password'];
        $deviceName = trim((string) ($data['device_name'] ?? 'android-app'));

        $normalizedEmail = mb_strtolower($loginInput);
        $normalizedRfc   = strtoupper(preg_replace('/[^A-Z0-9&Ñ]/u', '', $loginInput) ?: '');

        $user = UsuarioCuenta::query()
            ->where(function ($q) use ($normalizedEmail, $normalizedRfc) {
                $q->whereRaw('LOWER(email) = ?', [$normalizedEmail]);

                if ($normalizedRfc !== '') {
                    $q->orWhereRaw('UPPER(email) = ?', [$normalizedRfc])
                      ->orWhereRaw('UPPER(nombre) = ?', [$normalizedRfc]);
                }
            })
            ->first();

        if (!$user && $normalizedRfc !== '') {
            $cuenta = CuentaCliente::query()
                ->whereRaw('UPPER(rfc_padre) = ?', [$normalizedRfc])
                ->first();

            if ($cuenta) {
                $user = UsuarioCuenta::query()
                    ->where('cuenta_id', (string) $cuenta->id)
                    ->where(function ($q) {
                        $q->whereIn('rol', ['owner', 'propietario', 'dueño'])
                          ->orWhereIn('tipo', ['owner', 'propietario', 'dueño']);
                    })
                    ->orderBy('created_at')
                    ->first();

                if (!$user) {
                    $user = UsuarioCuenta::query()
                        ->where('cuenta_id', (string) $cuenta->id)
                        ->orderBy('created_at')
                        ->first();
                }
            }
        }

        if (!$user) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'Usuario no encontrado.',
                'code' => 'USER_NOT_FOUND',
            ], 404);
        }

        if (!$user->password || !Hash::check($password, (string) $user->password)) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'Credenciales incorrectas.',
                'code' => 'INVALID_CREDENTIALS',
            ], 422);
        }

        $cuenta = CuentaCliente::query()
            ->where('id', (string) $user->cuenta_id)
            ->first();

        if (!$cuenta) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'La cuenta del cliente no fue encontrada.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $isBlocked = (int) ($cuenta->is_blocked ?? 0) === 1;
        $estado    = mb_strtolower(trim((string) ($cuenta->estado_cuenta ?? '')));
        $activo    = (int) ($cuenta->activo ?? 1) === 1;
        $userActivo = (bool) ($user->activo ?? false);

        if (!$activo || !$userActivo) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'La cuenta no está activa.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        if ($isBlocked) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'La cuenta está bloqueada por pago pendiente.',
                'code' => 'ACCOUNT_BLOCKED',
                'data' => [
                    'is_blocked'     => true,
                    'estado_cuenta'  => (string) ($cuenta->estado_cuenta ?? ''),
                    'modo_cobro'     => (string) ($cuenta->modo_cobro ?? ''),
                    'next_invoice_date' => !empty($cuenta->next_invoice_date)
                        ? Carbon::parse($cuenta->next_invoice_date)->toDateString()
                        : null,
                ],
            ], 403);
        }

        if (!in_array($estado, ['activa', 'active', 'ok', 'activa_ok'], true)) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'La cuenta no está habilitada para acceso móvil.',
                'code' => 'ACCOUNT_STATUS_INVALID',
                'data' => [
                    'estado_cuenta' => (string) ($cuenta->estado_cuenta ?? ''),
                ],
            ], 403);
        }

        $emailVerifiedAt = $user->email_verified_at ?? null;
        $phoneVerifiedAt = $user->phone_verified_at ?? null;

        /*
        |--------------------------------------------------------------------------
        | App móvil: acceso sin bloqueo por verificación
        |--------------------------------------------------------------------------
        | El portal móvil no debe rechazar el login si correo/teléfono aún no están
        | verificados. Solo informamos el estado para que Flutter pueda mostrarlo.
        */
        $verification = [
            'email_verified'    => !empty($emailVerifiedAt),
            'email_verified_at' => !empty($emailVerifiedAt)
                ? \Illuminate\Support\Carbon::parse($emailVerifiedAt)->toIso8601String()
                : null,
            'phone_verified'    => !empty($phoneVerifiedAt),
            'phone_verified_at' => !empty($phoneVerifiedAt)
                ? \Illuminate\Support\Carbon::parse($phoneVerifiedAt)->toIso8601String()
                : null,
        ];
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, ['mobile'])->plainTextToken;

        return response()->json([
            'ok'  => true,
            'msg' => 'Login móvil correcto.',
            'data' => [
                'token_type' => 'Bearer',
                'token'      => $token,
                'user'       => [
                    'id'                    => (string) $user->id,
                    'cuenta_id'             => (string) $user->cuenta_id,
                    'nombre'                => (string) ($user->nombre ?? ''),
                    'email'                 => (string) ($user->email ?? ''),
                    'rol'                   => (string) ($user->rol ?? ''),
                    'tipo'                  => (string) ($user->tipo ?? ''),
                    'must_change_password'  => (bool) ($user->must_change_password ?? false),
                    'activo'                => (bool) ($user->activo ?? false),
                ],
                'account'    => [
                    'id'                 => (string) $cuenta->id,
                    'rfc_padre'          => (string) ($cuenta->rfc_padre ?? ''),
                    'razon_social'       => (string) ($cuenta->razon_social ?? ''),
                    'nombre_comercial'   => (string) ($cuenta->nombre_comercial ?? ''),
                    'email'              => (string) ($cuenta->email ?? ''),
                    'plan'               => (string) ($cuenta->plan_actual ?? $cuenta->plan ?? ''),
                    'modo_cobro'         => (string) ($cuenta->modo_cobro ?? ''),
                    'estado_cuenta'      => (string) ($cuenta->estado_cuenta ?? ''),
                    'is_blocked'         => (int) ($cuenta->is_blocked ?? 0) === 1,
                    'admin_account_id'   => !empty($cuenta->admin_account_id) ? (int) $cuenta->admin_account_id : null,
                    'next_invoice_date'  => !empty($cuenta->next_invoice_date)
                        ? Carbon::parse($cuenta->next_invoice_date)->toDateString()
                        : null,
                ],
            ],
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var UsuarioCuenta|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'No autenticado.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $cuenta = CuentaCliente::query()
            ->where('id', (string) $user->cuenta_id)
            ->first();

        $emailVerifiedAt = $user->email_verified_at ?? null;
        $phoneVerifiedAt = $user->phone_verified_at ?? null;

        $verification = [
            'email_verified'    => !empty($emailVerifiedAt),
            'email_verified_at' => !empty($emailVerifiedAt)
                ? \Illuminate\Support\Carbon::parse($emailVerifiedAt)->toIso8601String()
                : null,
            'phone_verified'    => !empty($phoneVerifiedAt),
            'phone_verified_at' => !empty($phoneVerifiedAt)
                ? \Illuminate\Support\Carbon::parse($phoneVerifiedAt)->toIso8601String()
                : null,
        ];

        return response()->json([
            'ok'   => true,
            'data' => [
                'verification' => $verification,
                'user' => [
                    'id'                   => (string) $user->id,
                    'cuenta_id'            => (string) $user->cuenta_id,
                    'nombre'               => (string) ($user->nombre ?? ''),
                    'email'                => (string) ($user->email ?? ''),
                    'rol'                  => (string) ($user->rol ?? ''),
                    'tipo'                 => (string) ($user->tipo ?? ''),
                    'must_change_password' => (bool) ($user->must_change_password ?? false),
                    'activo'               => (bool) ($user->activo ?? false),
                ],
                'account' => $cuenta ? [
                    'id'                => (string) $cuenta->id,
                    'rfc_padre'         => (string) ($cuenta->rfc_padre ?? ''),
                    'razon_social'      => (string) ($cuenta->razon_social ?? ''),
                    'nombre_comercial'  => (string) ($cuenta->nombre_comercial ?? ''),
                    'email'             => (string) ($cuenta->email ?? ''),
                    'plan'              => (string) ($cuenta->plan_actual ?? $cuenta->plan ?? ''),
                    'modo_cobro'        => (string) ($cuenta->modo_cobro ?? ''),
                    'estado_cuenta'     => (string) ($cuenta->estado_cuenta ?? ''),
                    'is_blocked'        => (int) ($cuenta->is_blocked ?? 0) === 1,
                    'admin_account_id'  => !empty($cuenta->admin_account_id) ? (int) $cuenta->admin_account_id : null,
                    'next_invoice_date' => !empty($cuenta->next_invoice_date)
                        ? \Illuminate\Support\Carbon::parse($cuenta->next_invoice_date)->toDateString()
                        : null,
                ] : null,
            ],
        ], 200);
    }
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'No autenticado.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json([
            'ok'  => true,
            'msg' => 'Sesión móvil cerrada correctamente.',
        ], 200);
    }
}