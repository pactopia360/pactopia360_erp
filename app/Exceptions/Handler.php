<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Handler extends ExceptionHandler
{
    /**
     * Registra los callbacks de reporteo.
     */
    public function register(): void
    {
        //
    }

    /**
     * Cuando el usuario no está autenticado (sesión expirada / inactividad),
     * redirige al login correcto según prefijo/guard.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 1) Si la URL actual es admin o cliente, decide por prefijo
        if ($this->isAdminRequest($request)) {
            return redirect()->guest($this->adminLoginUrl());
        }
        if ($this->isClienteRequest($request)) {
            return redirect()->guest($this->clienteLoginUrl());
        }

        // 2) Si no es por prefijo, decide por guard(es) que fallaron
        $guards = $exception->guards();
        if (in_array('admin', $guards, true)) {
            return redirect()->guest($this->adminLoginUrl());
        }
        // Por defecto mandamos a cliente (guard/web)
        return redirect()->guest($this->clienteLoginUrl());
    }

    private function isAdminRequest(Request $request): bool
    {
        return $request->is('admin') || $request->is('admin/*');
    }

    private function isClienteRequest(Request $request): bool
    {
        return $request->is('cliente') || $request->is('cliente/*');
    }

    private function adminLoginUrl(): string
    {
        if (Route::has('admin.login')) {
            return route('admin.login');
        }
        // Fallback duro si no existe la ruta nombrada (evitar /login genérico)
        return url('/admin/login');
    }

    private function clienteLoginUrl(): string
    {
        if (Route::has('cliente.login')) {
            return route('cliente.login');
        }
        // Fallback duro si no existe la ruta nombrada (evitar /login genérico)
        return url('/cliente/login');
    }
}
