<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Cliente\MiCuentaController;
use App\Models\Cliente\CuentaCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class MobileAccountController extends Controller
{
    private MiCuentaController $miCuenta;

    public function __construct()
    {
        $this->miCuenta = app(MiCuentaController::class);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $account = CuentaCliente::query()
                ->where('id', $user->cuenta_id)
                ->first();

            return response()->json([
                'ok'   => true,
                'data' => [
                    'user' => [
                        'id'        => (string) ($user->id ?? ''),
                        'cuenta_id' => (string) ($user->cuenta_id ?? ''),
                        'nombre'    => (string) ($user->nombre ?? ''),
                        'email'     => (string) ($user->email ?? ''),
                    ],
                    'account' => [
                        'id'                  => (string) ($account->id ?? ''),
                        'nombre_comercial'    => (string) ($account->nombre_comercial ?? ''),
                        'razon_social'        => (string) ($account->razon_social ?? ''),
                        'rfc_padre'           => (string) ($account->rfc_padre ?? ''),
                        'plan'                => (string) ($account->plan_actual ?? $account->plan ?? ''),
                        'modo_cobro'          => (string) ($account->modo_cobro ?? ''),
                        'estado_cuenta'       => (string) ($account->estado_cuenta ?? ''),
                        'is_blocked'          => (bool) ($account->is_blocked ?? false),
                        'espacio_asignado_mb' => (int) ($account->espacio_asignado_mb ?? 0),
                        'hits_asignados'      => (int) ($account->hits_asignados ?? 0),
                        'max_usuarios'        => (int) ($account->max_usuarios ?? 0),
                        'max_empresas'        => (int) ($account->max_empresas ?? 0),
                        'next_invoice_date'   => optional($account->next_invoice_date)?->format('Y-m-d'),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo cargar el perfil.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function payments(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            Auth::shouldUse('web');
            Auth::guard('web')->setUser($user);

            $response = $this->miCuenta->pagos($request);

            if (method_exists($response, 'getData')) {
                $payload = (array) $response->getData(true);

                return response()->json([
                    'ok'   => (bool) ($payload['ok'] ?? true),
                    'data' => [
                        'rows' => $payload['rows'] ?? [],
                    ],
                ]);
            }

            return response()->json([
                'ok'   => true,
                'data' => [
                    'rows' => [],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudieron cargar los pagos.',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }
}