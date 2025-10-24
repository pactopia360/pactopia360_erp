<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class MarketplaceController extends Controller
{
    public function index(): View
    {
        // Por ahora “placeholder” limpio; luego conectamos catálogo real.
        return view('cliente.marketplace', [
            'notifCount' => view()->shared('notifCount') ?? 0,
            'chatCount'  => view()->shared('chatCount')  ?? 0,
            'cartCount'  => 0,
        ]);
    }
}
