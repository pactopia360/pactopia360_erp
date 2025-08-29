<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    public function index()
    {
        // Entra directo al editor
        return redirect()->route('admin.perfil.edit');
    }

    public function edit(Request $request)
    {
        $u = auth('admin')->user();

        return view('admin.perfil.edit', [
            'u'     => $u,
            'prefs' => [
                'theme'   => session('ui.theme', 'light'),
                'density' => session('ui.density', 'normal'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $u = auth('admin')->user();

        $data = $request->validate([
            'nombre'     => ['required','string','max:120'],
            'email'      => [
                'required','email','max:190',
                // ajusta el nombre de tabla si difiere
                Rule::unique('usuario_administrativos','email')->ignore($u->id),
            ],
            'ui_theme'   => ['nullable','in:light,dark'],
            'ui_density' => ['nullable','in:normal,compact'],
        ]);

        // Preferencias → sesión (efecto inmediato)
        if (isset($data['ui_theme']))   session(['ui.theme'   => $data['ui_theme']]);
        if (isset($data['ui_density'])) session(['ui.density' => $data['ui_density']]);

        // Persistir datos básicos
        $u->nombre = $data['nombre'];
        $u->email  = $data['email'];

        // Si existen columnas para guardar preferencias, persístelas
        if (Schema::hasColumn('usuario_administrativos','ui_theme'))   $u->ui_theme   = $data['ui_theme']   ?? $u->ui_theme;
        if (Schema::hasColumn('usuario_administrativos','ui_density')) $u->ui_density = $data['ui_density'] ?? $u->ui_density;

        $u->save();

        return back()->with('status', 'Perfil actualizado');
    }

    public function password(Request $request)
    {
        $u = auth('admin')->user();

        $request->validate([
            'current_password' => ['required','current_password:admin'],
            'password'         => ['required','string','min:8','confirmed'],
        ]);

        $u->password = Hash::make($request->password);
        if (Schema::hasColumn('usuario_administrativos','force_password_change')) {
            $u->force_password_change = 0;
        }
        $u->save();

        return back()->with('status', 'Contraseña actualizada');
    }
}
