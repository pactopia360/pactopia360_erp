<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Usuarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class AdministrativosController extends Controller
{
    private string $adm;

    public function __construct()
    {
        // Preferencia por config, pero vamos a auto-detectar si no hay datos
        $preferred = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $this->adm = $this->pickAdminConn($preferred);
    }

    /**
     * Detecta en cuál conexión está la tabla usuarios_admin y dónde hay datos.
     */
    private function pickAdminConn(string $preferred): string
    {
        $candidates = array_values(array_unique([$preferred, 'mysql', 'mysql_admin']));

        $totals = [];
        foreach ($candidates as $c) {
            try {
                $schema = DB::connection($c)->getSchemaBuilder();
                if (!$schema->hasTable('usuarios_admin')) {
                    $totals[$c] = -1; // no existe tabla
                    continue;
                }
                $totals[$c] = (int) DB::connection($c)->table('usuarios_admin')->count();
            } catch (\Throwable $e) {
                $totals[$c] = -2; // conexión falló
            }
        }

        // Si la preferida tiene datos, úsala.
        if (($totals[$preferred] ?? -2) > 0) {
            return $preferred;
        }

        // Si alguna otra tiene datos, usa esa.
        foreach ($candidates as $c) {
            if (($totals[$c] ?? -2) > 0) {
                return $c;
            }
        }

        // Si ninguna tiene datos pero la tabla existe en alguna, usa la primera que exista.
        foreach ($candidates as $c) {
            if (($totals[$c] ?? -2) >= 0) {
                return $c;
            }
        }

        // Último fallback
        return $preferred ?: 'mysql';
    }

    public function index(Request $req): View
    {
        $q       = trim((string) $req->get('q', ''));
        $rol     = trim((string) $req->get('rol', 'todos'));
        $estado  = trim((string) $req->get('estado', '')); // ''|'1'|'0'
        $perPage = (int) $req->get('perPage', 25);

        $allowedPerPage = [25, 50, 100, 250, 500];
        if (!in_array($perPage, $allowedPerPage, true)) $perPage = 25;

        $adm = DB::connection($this->adm);

        $qb = $adm->table('usuarios_admin');

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($rol !== '' && $rol !== 'todos') {
            $qb->where('rol', $rol);
        }

        if ($estado === '0' || $estado === '1') {
            $qb->where('activo', (int) $estado);
        }

        $rows = $qb
            ->select([
                'id','nombre','email','rol','activo','es_superadmin',
                'force_password_change','last_login_at','last_login_ip',
                'created_at','updated_at',
            ])
            ->orderByDesc('es_superadmin')
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();

        $roles = $adm->table('usuarios_admin')
            ->select('rol')
            ->whereNotNull('rol')
            ->where('rol', '!=', '')
            ->distinct()
            ->orderBy('rol')
            ->pluck('rol')
            ->values()
            ->all();

        $debug = [
            'conn'  => $this->adm,
            'db'    => (string) $adm->getDatabaseName(),
            'total' => (int) $adm->table('usuarios_admin')->count(),
            'auth'  => [
                'id'    => auth('admin')->id(),
                'email' => (string) (auth('admin')->user()?->email ?? ''),
            ],
        ];

        return view('admin.usuarios.administrativos.index', [
            'rows'    => $rows,
            'roles'   => $roles,
            'filters' => [
                'q'       => $q,
                'rol'     => $rol,
                'estado'  => $estado,
                'perPage' => $perPage,
            ],
            'debug' => $debug,
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.administrativos.form', [
            'mode' => 'create',
            'row'  => null,
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'nombre' => ['required','string','max:190'],
            'email'  => ['required','email','max:190'],
            'rol'    => ['nullable','string','max:60'],
            'activo' => ['required','in:0,1'],
            'es_superadmin' => ['required','in:0,1'],
            'password' => ['required','string','min:10'],
        ]);

        $adm = DB::connection($this->adm);

        $exists = (int) $adm->table('usuarios_admin')->where('email', $data['email'])->count();
        if ($exists > 0) {
            return back()->withInput()->with('err', 'Ya existe un usuario con ese email.');
        }

        $adm->table('usuarios_admin')->insert([
            'nombre' => $data['nombre'],
            'email'  => $data['email'],
            'password' => Hash::make($data['password']),
            'rol'    => $data['rol'] ?? '',
            'permisos' => null,
            'activo' => (int) $data['activo'],
            'es_superadmin' => (int) $data['es_superadmin'],
            'force_password_change' => 0,
            'last_login_at' => null,
            'last_login_ip' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.usuarios.administrativos.index')->with('ok', 'Usuario creado.');
    }

    public function edit(int $id): View
    {
        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        return view('admin.usuarios.administrativos.form', [
            'mode' => 'edit',
            'row'  => $row,
        ]);
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        $data = $req->validate([
            'nombre' => ['required','string','max:190'],
            'email'  => ['required','email','max:190'],
            'rol'    => ['nullable','string','max:60'],
            'activo' => ['required','in:0,1'],
            'es_superadmin' => ['required','in:0,1'],
            'password' => ['nullable','string','min:10'],
        ]);

        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        $emailExists = (int) $adm->table('usuarios_admin')
            ->where('email', $data['email'])
            ->where('id', '!=', $id)
            ->count();

        if ($emailExists > 0) {
            return back()->withInput()->with('err', 'Ese email ya lo usa otro usuario.');
        }

        $upd = [
            'nombre' => $data['nombre'],
            'email'  => $data['email'],
            'rol'    => $data['rol'] ?? '',
            'activo' => (int) $data['activo'],
            'es_superadmin' => (int) $data['es_superadmin'],
            'updated_at' => now(),
        ];

        if (!empty($data['password'])) {
            $upd['password'] = Hash::make($data['password']);
            $upd['force_password_change'] = 0;
        }

        $adm->table('usuarios_admin')->where('id', $id)->update($upd);

        return redirect()->route('admin.usuarios.administrativos.index')->with('ok', 'Usuario actualizado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        $new = ((int) $row->activo === 1) ? 0 : 1;

        $adm->table('usuarios_admin')->where('id', $id)->update([
            'activo' => $new,
            'updated_at' => now(),
        ]);

        return back()->with('ok', $new ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function resetPassword(Request $req, int $id): RedirectResponse
    {
        $data = $req->validate([
            'password' => ['required','string','min:10'],
            'force_password_change' => ['nullable','in:0,1'],
        ]);

        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        $adm->table('usuarios_admin')->where('id', $id)->update([
            'password' => Hash::make($data['password']),
            'force_password_change' => (int) ($data['force_password_change'] ?? 1),
            'updated_at' => now(),
        ]);

        return back()->with('ok', 'Password reseteada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $adm = DB::connection($this->adm);

        if ((int) auth('admin')->id() === (int) $id) {
            return back()->with('err', 'No puedes eliminar tu propio usuario.');
        }

        $adm->table('usuarios_admin')->where('id', $id)->delete();

        return back()->with('ok', 'Usuario eliminado.');
    }
}
