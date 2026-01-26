<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Registra los callbacks de reporteo / render.
     */
    public function register(): void
    {
        // ✅ Manejo explícito de links firmados inválidos/expirados.
        // Evita que el usuario termine en /cliente/login con mensajes confusos.
        $this->renderable(function (InvalidSignatureException $e, Request $request) {

            // APIs / AJAX
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'El enlace no es válido o ya expiró.',
                    'code'    => 'invalid_signature',
                ], 403);
            }

            // Intenta renderizar una vista bonita si existe
            $view = 'errors.link_expired';
            if (view()->exists($view)) {
                return response()->view($view, [
                    'path'   => $request->path(),
                    'url'    => $request->fullUrl(),
                    'area'   => $this->isAdminRequest($request) ? 'admin' : ($this->isClienteRequest($request) ? 'cliente' : 'publico'),
                    'login'  => $this->isAdminRequest($request) ? $this->adminLoginUrl() : $this->clienteLoginUrl(),
                ], 403);
            }

            // Fallback mínimo (nunca rompe)
            $login = $this->isAdminRequest($request) ? $this->adminLoginUrl() : $this->clienteLoginUrl();

            return response()->make(
                '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                 <title>Enlace inválido</title></head>
                 <body style="font-family:system-ui;margin:24px;">
                   <h2>El enlace no es válido o ya expiró</h2>
                   <p>Vuelve a solicitar una nueva invitación o inicia sesión.</p>
                   <p><a href="'.e($login).'">Ir a iniciar sesión</a></p>
                 </body></html>',
                403,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });
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
