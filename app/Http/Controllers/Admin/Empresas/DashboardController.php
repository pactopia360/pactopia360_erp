<?php

namespace App\Http\Controllers\Admin\Empresas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard: Pactopia360
     */
    public function pactopia360(Request $request)
    {
        $empresa = [
            'slug'  => 'pactopia360',
            'name'  => 'Pactopia360',
            'desc'  => 'Panel administrativo central de Pactopia360.',
            'color' => '#4f46e5',
            'icon'  => '🏢',
        ];

        return view('admin.empresas.pactopia360.dashboard', compact('empresa'));
    }

    /**
     * Dashboard: Pactopia
     */
    public function pactopia(Request $request)
    {
        $empresa = [
            'slug'  => 'pactopia',
            'name'  => 'Pactopia',
            'desc'  => 'Operación interna y comercial de Pactopia.',
            'color' => '#0ea5e9',
            'icon'  => '🏢',
        ];

        return view('admin.empresas.pactopia.dashboard', compact('empresa'));
    }

    /**
     * Dashboard: Waretek México
     */
    public function waretekMx(Request $request)
    {
        $empresa = [
            'slug'  => 'waretek-mx',
            'name'  => 'Waretek México',
            'desc'  => 'Backoffice y finanzas de Waretek MX.',
            'color' => '#22c55e',
            'icon'  => '🏢',
        ];

        return view('admin.empresas.waretek-mx.dashboard', compact('empresa'));
    }
}
