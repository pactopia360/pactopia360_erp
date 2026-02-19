<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatClientContext.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SatClientContext
{
    public function clientGuard(): string
    {
        try {
            $guards = (array) config('auth.guards', []);

            if (array_key_exists('cliente', $guards) && auth()->guard('cliente')->check()) {
                return 'cliente';
            }

            if (array_key_exists('web', $guards) && auth()->guard('web')->check()) {
                return 'web';
            }

            if (array_key_exists('cliente', $guards)) {
                return 'cliente';
            }
        } catch (\Throwable) {
            // no-op
        }

        return 'web';
    }

    public function user(): ?object
    {
        try {
            $g = $this->clientGuard();

            // 1) Guard preferido
            try {
                $u = auth($g)->user();
                if ($u) return $u;
            } catch (\Throwable) {
                // no-op
            }

            // 2) Fallbacks
            foreach (['cliente', 'web'] as $fallback) {
                if ($fallback === $g) continue;
                try {
                    $u = auth($fallback)->user();
                    if ($u) return $u;
                } catch (\Throwable) {
                    // no-op
                }
            }

            // 3) Último recurso
            try {
                $u = auth()->user();
                if ($u) return $u;
            } catch (\Throwable) {
                // no-op
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function resolveCuentaIdFromUser($user): ?string
    {
        try {
            if (!$user) return null;

            // 1) Campos directos comunes
            foreach (['cuenta_id', 'account_id', 'id_cuenta'] as $prop) {
                try {
                    if (isset($user->{$prop}) && $user->{$prop} !== null && (string)$user->{$prop} !== '') {
                        return (string) $user->{$prop};
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }

            // 2) Relación/atributo "cuenta"
            try {
                if (isset($user->cuenta)) {
                    $c = $user->cuenta;

                    if (is_object($c)) {
                        foreach (['id', 'cuenta_id', 'account_id'] as $k) {
                            $v = (string) ($c->{$k} ?? '');
                            if ($v !== '') return $v;
                        }
                    } elseif (is_array($c)) {
                        foreach (['id', 'cuenta_id', 'account_id'] as $k) {
                            $v = (string) ($c[$k] ?? '');
                            if ($v !== '') return $v;
                        }
                    }
                }
            } catch (\Throwable) {
                // no-op
            }

            // 3) Métodos/getters típicos
            foreach (['getCuentaId', 'getAccountId', 'cuentaId', 'accountId'] as $m) {
                try {
                    if (is_object($user) && method_exists($user, $m)) {
                        $v = (string) $user->{$m}();
                        if ($v !== '') return $v;
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }

            // 4) Session fallbacks
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
                try {
                    $v = (string) session($k, '');
                    if ($v !== '') return $v;
                } catch (\Throwable) {
                    // no-op
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function cuentaId(): string
    {
        try {
            $u = $this->user();
            if (!$u) return '';

            $id = (string) ($this->resolveCuentaIdFromUser($u) ?? '');
            $id = trim($id);

            if ($id !== '' && is_numeric($id)) {
                $id = (string) ((int) $id);
            }

            return $id;
        } catch (\Throwable) {
            return '';
        }
    }

    public function trace(): string
    {
        try {
            return (string) Str::ulid();
        } catch (\Throwable) {
            return (string) uniqid('trace_', true);
        }
    }

    public function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->expectsJson()
            || $request->wantsJson()
            || $request->isJson()
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest'
            || strtolower((string) $request->header('X-Requested-With')) === 'fetch'
            || in_array(strtolower((string) $request->header('X-P360-AJAX')), ['1','true','yes','on'], true);
    }
}