<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

final class SatDashboardController extends Controller
{
    /**
     * Resolver cuenta_id de forma robusta para portal cliente.
     * (En algunos setups cuenta_id no está en el user directo, sino en relación/otros campos)
     */
    private function resolveCuentaId($user): int
    {
        if (!$user) return 0;

        // 1) Campos directos típicos
        foreach (['cuenta_id', 'account_id', 'client_account_id'] as $k) {
            try {
                if (isset($user->{$k}) && (int)$user->{$k} > 0) return (int)$user->{$k};
            } catch (\Throwable $e) {}
        }

        // 2) Relación cuenta (como usas en Blade: $user->cuenta)
        try {
            $cuenta = $user->cuenta ?? null;
            if ($cuenta) {
                foreach (['id', 'cuenta_id', 'account_id'] as $k) {
                    try {
                        if (isset($cuenta->{$k}) && (int)$cuenta->{$k} > 0) return (int)$cuenta->{$k};
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {}

        // 3) Fallback: nada
        return 0;
    }

    private function emptyPayload(Carbon $fromC, Carbon $toC): array
    {
        // Construye labels/values en cero (para que Chart.js sí pinte algo consistente)
        $labels = [];
        $values = [];

        $cursor = $fromC->copy();
        while ($cursor->lte($toC)) {
            $labels[] = $cursor->format('d/m');
            $values[] = 0;
            $cursor->addDay();
        }

        return [
            'ok' => true,
            'kpi' => [
                'start'      => 0,
                'created'    => 0,
                'available'  => 0,
                'downloaded' => 0,
            ],
            'serie' => [
                'labels' => $labels,
                'values' => $values,
            ],
        ];
    }

    public function stats(Request $request): JsonResponse
    {
        $user = Auth::guard('web')->user();
        $cuentaId = $this->resolveCuentaId($user);

        // Rango
        $from = (string)$request->query('from', '');
        $to   = (string)$request->query('to', '');

        try {
            $fromC = $from !== '' ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
            $toC   = $to   !== '' ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();
        } catch (\Throwable $e) {
            // Si mandan fechas inválidas, aquí sí es correcto 422
            return response()->json(['ok' => false, 'msg' => 'Rango de fechas inválido.'], 422);
        }

        // Si NO se pudo resolver cuenta, NO romper gráfica:
        // cuenta nueva / sesión incompleta -> devolver ceros (200 OK).
        if ($cuentaId <= 0) {
        // Cuenta nueva / sesión sin cuenta asociada aún:
        // NO romper el dashboard: regresar ceros en 200 OK.
        $labels = [];
        $values = [];

        $from = (string)$request->query('from', '');
        $to   = (string)$request->query('to', '');

        try {
            $fromC = $from !== '' ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
            $toC   = $to   !== '' ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();
        } catch (\Throwable $e) {
            $fromC = now()->subDays(30)->startOfDay();
            $toC   = now()->endOfDay();
        }

        $cursor = $fromC->copy();
        while ($cursor->lte($toC)) {
            $labels[] = $cursor->format('d/m');
            $values[] = 0;
            $cursor->addDay();
        }

        return response()->json([
            'ok' => true,
            'kpi' => [
                'start'      => 0,
                'created'    => 0,
                'available'  => 0,
                'downloaded' => 0,
            ],
            'serie' => [
                'labels' => $labels,
                'values' => $values,
            ],
        ], 200);
    }


        // IMPORTANTE: TODO FILTRADO POR CUENTA
        $base = SatDownload::query()->where('cuenta_id', $cuentaId);

        // KPI
        $start = (clone $base)->where('created_at', '<', $fromC)->count();

        $created = (clone $base)
            ->whereBetween('created_at', [$fromC, $toC])
            ->count();

        // “disponibles” y “descargados”: ajusta a tus columnas reales si difieren
        $available = (clone $base)
            ->where(function ($q) {
                $q->whereIn('status', ['ready', 'available', 'done'])
                  ->orWhereNotNull('zip_path');
            })
            ->count();

        $downloaded = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('downloaded_at')
                  ->orWhereIn('status', ['downloaded']);
            })
            ->count();

        // Serie diaria para chart (por created_at en rango)
        $rows = (clone $base)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
            ->whereBetween('created_at', [$fromC, $toC])
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->d] = (int)$r->n;
        }

        $labels = [];
        $values = [];

        $cursor = $fromC->copy();
        while ($cursor->lte($toC)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $values[] = $map[$key] ?? 0;
            $cursor->addDay();
        }

        return response()->json([
            'ok' => true,
            'kpi' => [
                'start'      => $start,
                'created'    => $created,
                'available'  => $available,
                'downloaded' => $downloaded,
            ],
            'serie' => [
                'labels' => $labels,
                'values' => $values,
            ],
        ]);
    }
}
