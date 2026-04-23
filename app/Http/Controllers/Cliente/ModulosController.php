<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class ModulosController extends Controller
{
    /**
     * Contexto base para todos los módulos del cliente.
     * Aquí después puedes inyectar KPIs, banderas, permisos o datos por cuenta.
     */
    private function baseData(): array
    {
        $user = Auth::guard('web')->user();

        return [
            'clienteUser' => $user,
            'cuenta'      => $user->cuenta ?? null,
        ];
    }

    public function crm(): View
    {
        return view('cliente.modulos.crm', $this->baseData());
    }

    public function inventario(): View
    {
        return view('cliente.modulos.inventario', $this->baseData());
    }

    public function ventas(): View
    {
        return view('cliente.modulos.ventas', $this->baseData());
    }

    public function reportes(): View
    {
        return view('cliente.modulos.reportes', $this->baseData());
    }

    public function rh(): View
    {
        return view('cliente.modulos.rh', $this->baseData());
    }

    public function timbres(): View
    {
        return view('cliente.modulos.timbres', $this->baseData());
    }
}