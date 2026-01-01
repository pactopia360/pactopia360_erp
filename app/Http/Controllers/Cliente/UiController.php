<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UiController extends Controller
{
    /**
     * POST /cliente/ui/demo-mode
     * Guarda en sesión el modo DEMO para que PHP lo lea.
     *
     * Body JSON:
     * { "demo": true|false }
     */
    public function demoMode(Request $request): JsonResponse
    {
        // Acepta JSON o form-data
        $demoRaw = $request->input('demo', false);

        // Normaliza boolean
        $demo = filter_var($demoRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($demo === null) {
            $demo = false;
        }

        // UNIFICADO: misma llave que MiCuentaController
        session(['client_ui.demo_mode' => $demo ? 1 : 0]);

        return response()->json([
            'ok'   => true,
            'demo' => (bool) $demo,
        ]);
    }

    /**
     * GET /cliente/ui/demo-mode (opcional)
     * Útil para confirmar estado.
     */
    public function demoModeGet(Request $request): JsonResponse
    {
        $demo = (bool) session('client_ui.demo_mode', false);

        return response()->json([
            'ok'   => true,
            'demo' => $demo,
        ]);
    }
}
