<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SmartLoginController extends Controller
{
    /**
     * Despachador de /login que detecta el contexto (cliente/admin) y redirige al login adecuado.
     *
     * Reglas en orden de prioridad:
     *  1) Si la URL "intended" de sesión contiene "/cliente/" → login cliente.
     *  2) Si el Referer proviene de "/cliente/" → login cliente.
     *  3) Si existe cookie "p360_client_session" → login cliente.
     *  4) Fallback → login admin (compatibilidad con enlaces antiguos).
     */
    public function __invoke(Request $request)
    {
        // 1) Intended
        $intended = (string) ($request->session()->get('url.intended') ?? '');
        if ($intended !== '' && str_contains($intended, '/cliente/')) {
            return redirect()->route('cliente.login');
        }

        // 2) Referer
        $ref = (string) ($request->headers->get('referer') ?? '');
        if ($ref !== '' && str_contains($ref, '/cliente/')) {
            return redirect()->route('cliente.login');
        }

        // 3) Cookie de sesión cliente (aislada por ClientSessionConfig)
        if ($request->cookies->has('p360_client_session')) {
            return redirect()->route('cliente.login');
        }

        // 4) Fallback → admin
        return redirect()->route('admin.login');
    }
}
