<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VaultController extends Controller
{
    /**
     * Vista principal de Bóveda Fiscal.
     * - Inyecta credenciales (para el combo de RFC)
     * - Inyecta __VAULT_BOOT con un arreglo de CFDI (demo en local)
     */
    public function index(Request $request)
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        // Lista de RFCs disponibles para filtros (trae tu origen real si ya lo tienes)
        // A falta de modelo concreto, usamos lo que venga ligado a la cuenta o arreglo vacío.
        $credList = is_iterable($cuenta?->satCredenciales ?? null)
            ? $cuenta->satCredenciales
            : ($request->get('credList', []) ?? []);

        // Datos iniciales para la tabla (vacío en prod, demo en local/dev/test)
        $bootData = [];
        if (app()->environment(['local','development','testing'])) {
            $bootData = $this->fakeItems($credList);
        }

        return view('cliente.sat.vault', [
            'credList' => $this->normalizeCreds($credList),
            'bootData' => $bootData, // <- lo recogemos en la vista y lo exponemos como window.__VAULT_BOOT
        ]);
    }

    /**
     * Normaliza credenciales a arreglo simple con 'rfc' y 'razon_social'
     */
    private function normalizeCreds($credList): array
    {
        $out = [];
        foreach ((array) $credList as $c) {
            $rfc   = is_array($c) ? ($c['rfc'] ?? null) : ($c->rfc ?? null);
            $razon = is_array($c) ? ($c['razon_social'] ?? null) : ($c->razon_social ?? null);
            if ($rfc) {
                $out[] = [
                    'rfc' => strtoupper((string) $rfc),
                    'razon_social' => (string) ($razon ?? '—'),
                    'validado' => (bool) (is_array($c) ? ($c['validado'] ?? false) : ($c->validado ?? false)),
                ];
            }
        }
        return $out;
    }

    /**
     * Genera datos de prueba para la bóveda (solo en local/dev/test)
     */
    private function fakeItems($credList): array
    {
        $rfcs = collect($this->normalizeCreds($credList))
            ->pluck('rfc')
            ->filter()
            ->values()
            ->all();

        if (empty($rfcs)) {
            $rfcs = ['XAXX010101000', 'COSC8001137NA'];
        }

        $razones = ['Demo, S.A. de C.V.', 'Ejemplo Comercial, S.A.', 'Servicios Prueba, S.C.'];
        $now     = now();

        $items = [];
        for ($i = 0; $i < 80; $i++) {
            $tipo   = ($i % 2 === 0) ? 'emitidos' : 'recibidos';
            $fecha  = $now->copy()->subDays(rand(0, 150))->format('Y-m-d');
            $rfc    = $rfcs[array_rand($rfcs)];
            $razon  = $razones[array_rand($razones)];
            $sub    = rand(5000, 350000) / 100;
            $iva    = round($sub * 0.16, 2);
            $total  = round($sub + $iva, 2);
            $uuid   = strtoupper(bin2hex(random_bytes(8)));

            $items[] = [
                'fecha'    => $fecha,
                'tipo'     => $tipo,     // 'emitidos' | 'recibidos'
                'rfc'      => $rfc,
                'razon'    => $razon,
                'uuid'     => $uuid,
                'subtotal' => $sub,
                'iva'      => $iva,
                'total'    => $total,
            ];
        }

        return $items;
    }
}
