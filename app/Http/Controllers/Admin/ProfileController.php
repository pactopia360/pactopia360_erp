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

        // Preparar nombres de tabla/BD para que la regla unique consulte la BD correcta
        $table = $u->getTable(); // p.ej. 'usuario_administrativos'
        $db    = $u->getConnection()->getDatabaseName(); // p.ej. 'p360v1_admin'
        $tblQualified = $db ? ($db . '.' . $table) : $table;

        // Campos opcionales si existen
        $hasCodigo   = Schema::hasColumn($table, 'codigo_usuario');
        $hasUiTheme  = Schema::hasColumn($table, 'ui_theme');
        $hasUiDense  = Schema::hasColumn($table, 'ui_density');

        // Validación
        $rules = [
            'nombre'     => ['required','string','max:120'],
            'email'      => [
                'required','email','max:190',
                Rule::unique($tblQualified, 'email')->ignore($u->getKey(), $u->getKeyName()),
            ],
            'ui_theme'   => ['nullable','in:light,dark'],
            'ui_density' => ['nullable','in:normal,compact'],
        ];

        if ($hasCodigo) {
            $rules['codigo_usuario'] = [
                'nullable','string','max:60',
                Rule::unique($tblQualified, 'codigo_usuario')->ignore($u->getKey(), $u->getKeyName()),
            ];
        }

        $data = $request->validate($rules);

        // Preferencias → sesión (efecto inmediato)
        if (isset($data['ui_theme']))   session(['ui.theme'   => $data['ui_theme']]);
        if (isset($data['ui_density'])) session(['ui.density' => $data['ui_density']]);

        // Persistir datos básicos (normalizando)
        $u->nombre = trim((string)$data['nombre']);
        $u->email  = mb_strtolower(trim((string)$data['email']));

        if ($hasCodigo && array_key_exists('codigo_usuario', $data)) {
            $u->codigo_usuario = $data['codigo_usuario'] !== null
                ? trim((string)$data['codigo_usuario'])
                : $u->codigo_usuario;
        }

        // Guardar preferencias si existen columnas
        if ($hasUiTheme && array_key_exists('ui_theme', $data)) {
            $u->ui_theme = $data['ui_theme'] ?? $u->ui_theme;
        }
        if ($hasUiDense && array_key_exists('ui_density', $data)) {
            $u->ui_density = $data['ui_density'] ?? $u->ui_density;
        }

        $u->save();

        return back()->with('status', 'Perfil actualizado');
    }

    public function password(Request $request)
    {
        $u = auth('admin')->user();
        $table = $u->getTable();

        $request->validate([
            'current_password' => ['required','current_password:admin'],
            'password'         => ['required','string','min:8','confirmed'],
        ]);

        $u->password = Hash::make($request->password);

        // Si existe flag de forzar cambio, apágalo
        if (Schema::hasColumn($table,'force_password_change')) {
            $u->force_password_change = 0;
        }
        // Si existe marca de último cambio, escríbela
        if (Schema::hasColumn($table,'last_password_change_at')) {
            $u->last_password_change_at = now();
        }

        $u->save();

        return back()->with('status', 'Contraseña actualizada');
    }
}
