<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Usuarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdministrativosController extends Controller
{
    private string $adm;

    /** cache columns per request */
    private array $colCache = [];

    public function __construct()
    {
        $preferred = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->adm = $this->pickAdminConn($preferred);
    }

    private function pickAdminConn(string $preferred): string
    {
        $candidates = array_values(array_unique([$preferred, 'mysql', 'mysql_admin']));

        $totals = [];
        foreach ($candidates as $c) {
            try {
                $schema = DB::connection($c)->getSchemaBuilder();
                if (!$schema->hasTable('usuarios_admin')) {
                    $totals[$c] = -1;
                    continue;
                }
                $totals[$c] = (int) DB::connection($c)->table('usuarios_admin')->count();
            } catch (\Throwable $e) {
                $totals[$c] = -2;
            }
        }

        if (($totals[$preferred] ?? -2) > 0) return $preferred;

        foreach ($candidates as $c) {
            if (($totals[$c] ?? -2) > 0) return $c;
        }

        foreach ($candidates as $c) {
            if (($totals[$c] ?? -2) >= 0) return $c;
        }

        return $preferred ?: 'mysql';
    }

    private function hasCol(string $col): bool
    {
        $key = $this->adm . ':usuarios_admin:' . $col;
        if (array_key_exists($key, $this->colCache)) {
            return (bool) $this->colCache[$key];
        }

        try {
            $ok = Schema::connection($this->adm)->hasColumn('usuarios_admin', $col);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return (bool) ($this->colCache[$key] = $ok);
    }

    private function parsePerms(?string $text): ?string
    {
        $t = trim((string) $text);
        if ($t === '') return null;

        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $parts = preg_split('/[\n,]+/', $t) ?: [];

        $out = [];
        foreach ($parts as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '') $out[] = $p;
        }

        $out = array_values(array_unique($out));
        if (!$out) return null;

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        $qb  = $adm->table('usuarios_admin');

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

        // SELECT tolerante: si no existe la columna, seleccionamos NULL alias
        $select = [
            'id', 'nombre', 'email', 'rol', 'activo',
            $this->hasCol('es_superadmin') ? 'es_superadmin' : DB::raw('0 as es_superadmin'),
            $this->hasCol('force_password_change') ? 'force_password_change' : DB::raw('0 as force_password_change'),
            $this->hasCol('last_login_at') ? 'last_login_at' : DB::raw('NULL as last_login_at'),
            $this->hasCol('last_login_ip') ? 'last_login_ip' : DB::raw('NULL as last_login_ip'),
            $this->hasCol('created_at') ? 'created_at' : DB::raw('NULL as created_at'),
            $this->hasCol('updated_at') ? 'updated_at' : DB::raw('NULL as updated_at'),
        ];

        if ($this->hasCol('permisos')) {
            $select[] = 'permisos';
        } else {
            $select[] = DB::raw('NULL as permisos');
        }

        $rows = $qb
            ->select($select)
            ->orderByDesc($this->hasCol('es_superadmin') ? 'es_superadmin' : 'id')
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
            'cols'  => [
                'permisos' => $this->hasCol('permisos'),
                'es_superadmin' => $this->hasCol('es_superadmin'),
                'force_password_change' => $this->hasCol('force_password_change'),
                'last_login_at' => $this->hasCol('last_login_at'),
                'last_login_ip' => $this->hasCol('last_login_ip'),
            ],
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
        $row = (object)[
            'id' => null,
            'nombre' => '',
            'email' => '',
            'rol' => 'usuario',
            'activo' => 1,
            'es_superadmin' => 0,
            'force_password_change' => 0,
            'permisos' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
        ];

        return view('admin.usuarios.administrativos.form', [
            'mode' => 'create',
            'row'  => $row,
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'nombre' => ['required','string','max:150'],
            'email'  => ['required','email','max:190'],
            'rol'    => ['nullable','string','max:30'],
            'activo' => ['required','in:0,1'],
            'es_superadmin' => ['required','in:0,1'],
            'force_password_change' => ['nullable','in:0,1'],
            'password' => ['required','string','min:10'],
            'permisos_text' => ['nullable','string','max:5000'],
        ]);

        $adm = DB::connection($this->adm);

        $exists = (int) $adm->table('usuarios_admin')->where('email', $data['email'])->count();
        if ($exists > 0) {
            return back()->withInput()->with('err', 'Ya existe un usuario con ese email.');
        }

        $permsJson = $this->parsePerms($data['permisos_text'] ?? null);

        $ins = [
            'nombre' => $data['nombre'],
            'email'  => $data['email'],
            'password' => Hash::make($data['password']),
            'rol'    => (string)($data['rol'] ?? 'usuario'),
            'activo' => (int) $data['activo'],
            'remember_token' => null,
        ];

        if ($this->hasCol('permisos')) {
            $ins['permisos'] = $permsJson;
        }

        if ($this->hasCol('es_superadmin')) {
            $ins['es_superadmin'] = (int) $data['es_superadmin'];
        }

        if ($this->hasCol('force_password_change')) {
            $ins['force_password_change'] = (int)($data['force_password_change'] ?? 0);
        }

        if ($this->hasCol('last_login_at')) {
            $ins['last_login_at'] = null;
        }

        if ($this->hasCol('last_login_ip')) {
            $ins['last_login_ip'] = null;
        }

        if ($this->hasCol('created_at')) {
            $ins['created_at'] = now();
        }
        if ($this->hasCol('updated_at')) {
            $ins['updated_at'] = now();
        }

        $adm->table('usuarios_admin')->insert($ins);

        return redirect()->route('admin.usuarios.administrativos.index')->with('ok', 'Usuario creado.');
    }

    public function edit(int $id): View
    {
        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        // si no existe permisos, agrega prop para que el blade no falle
        if (!property_exists($row, 'permisos')) {
            $row->permisos = null;
        }

        return view('admin.usuarios.administrativos.form', [
            'mode' => 'edit',
            'row'  => $row,
        ]);
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        $data = $req->validate([
            'nombre' => ['required','string','max:150'],
            'email'  => ['required','email','max:190'],
            'rol'    => ['nullable','string','max:30'],
            'activo' => ['required','in:0,1'],
            'es_superadmin' => ['required','in:0,1'],
            'force_password_change' => ['nullable','in:0,1'],
            'password' => ['nullable','string','min:10'],
            'permisos_text' => ['nullable','string','max:5000'],
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

        $permsJson = $this->parsePerms($data['permisos_text'] ?? null);

        $upd = [
            'nombre' => $data['nombre'],
            'email'  => $data['email'],
            'rol'    => (string)($data['rol'] ?? 'usuario'),
            'activo' => (int) $data['activo'],
        ];

        if ($this->hasCol('permisos')) {
            $upd['permisos'] = $permsJson;
        }

        if ($this->hasCol('es_superadmin')) {
            $upd['es_superadmin'] = (int) $data['es_superadmin'];
        }

        if ($this->hasCol('force_password_change')) {
            $upd['force_password_change'] = (int)($data['force_password_change'] ?? (int)($row->force_password_change ?? 0));
        }

        if ($this->hasCol('updated_at')) {
            $upd['updated_at'] = now();
        }

        if (!empty($data['password'])) {
            $upd['password'] = Hash::make($data['password']);

            // Si cambias password manualmente aquí, normalmente NO fuerzas cambio.
            if ($this->hasCol('force_password_change')) {
                $upd['force_password_change'] = (int)($data['force_password_change'] ?? 0);
            }
        }

        $adm->table('usuarios_admin')->where('id', $id)->update($upd);

        return redirect()->route('admin.usuarios.administrativos.edit', $id)->with('ok', 'Usuario actualizado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        $new = ((int) $row->activo === 1) ? 0 : 1;

        $upd = [
            'activo' => $new,
        ];

        if ($this->hasCol('updated_at')) {
            $upd['updated_at'] = now();
        }

        $adm->table('usuarios_admin')->where('id', $id)->update($upd);

        return back()->with('ok', $new ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function resetPassword(Request $req, int $id): RedirectResponse
    {
        $data = $req->validate([
            'password' => ['nullable','string','min:10'],
            'new_password' => ['nullable','string','min:10'],
        ]);

        $adm = DB::connection($this->adm);

        $row = $adm->table('usuarios_admin')->where('id', $id)->first();
        abort_unless($row, 404);

        $plain = (string)($data['password'] ?? $data['new_password'] ?? '');
        if (trim($plain) === '') {
            $plain = Str::random(14);
        }

        $upd = [
            'password' => Hash::make($plain),
        ];

        if ($this->hasCol('force_password_change')) {
            $upd['force_password_change'] = 1;
        }

        if ($this->hasCol('updated_at')) {
            $upd['updated_at'] = now();
        }

        $adm->table('usuarios_admin')->where('id', $id)->update($upd);

        return back()->with('ok', 'Password reseteada. Nueva: '.$plain);
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
