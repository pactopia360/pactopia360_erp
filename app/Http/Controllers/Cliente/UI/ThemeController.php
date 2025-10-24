<?php

namespace App\Http\Controllers\Cliente\UI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function switch(Request $request)
    {
        $next = $request->input('theme');
        if (!in_array($next, ['light','dark'], true)) {
            $next = 'dark';
        }
        // Persistir en sesión
        $request->session()->put('client_ui.theme', $next);

        return response()->json(['ok' => true, 'theme' => $next]);
    }
}
