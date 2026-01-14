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

final class AdminUsuariosAdministrativosController extends Controller
{
    private string $conn  = 'mysql_admin';
    private string $table = 'usuarios_admin';

    /** @return array<string,bool> */
    private function boolFlags(): array
    {
        return [
            'activo'                => true,
            'es_superadmin'         => true,
            'force_password_change' => true,
        ];
    }

    private function normalizeBool(mixed $v): int
    {
        return (int) ((bool) (is_string($v) ? (in_array(strtolower(trim($v)), ['1','true','si','sí','yes','on'], true)) : $v));
    }

    private function parsePermisos(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw)) return [];

        $s = trim($raw);
        if ($s === '') return [];

        // JSON
        if (Str::startsWith($s, ['{', '['])) {
            try {
                $j = json_decode($s, true, 512, JSON_THROW_ON_ERROR);
                return is_array($j) ? $j : [];
            } catch (\Throwable) {
                // sigue
            }
        }

        // CSV/lines "a,b,c" o por renglón
        $parts = preg_split('/[\r\n,;]+/', $s) ?: [];
        $parts = array_values(array_filter(array_map(static fn($x) => trim((string)$x), $parts), static fn($x) => $x !== ''));
        return $parts;
    }

    private function permisosToStorage(array $perms): string
    {
        // Guardamos como JSON array de strings (simple y consistente)
        $perms = array_values(array_unique(array_filter(array_map(static fn($x) => trim((string)$x), $perms), static fn($x) => $x !== '')));
        return json_encode($perms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));
        $perPage = (int) $req->get('perPage', 25);
        if (!in_array($perPage, [10, 25, 50, 100, 250], true)) $perPage = 25;

        $role = trim((string) $req->get('rol', ''));
        $onlyActive = (string) $req->get('activo', '') !== '' ? $req->boolean('activo') : null;

        $qb = DB::connection($this->conn)->table($this->table);

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }
        if ($role !== '') {
            $qb->where('rol', $role);
        }
        if ($onlyActive !== null) {
            $qb->where('activo', $onlyActive ? 1 : 0);
        }

        $roles = DB::connection($this->conn)->table($this->table)
            ->select('rol')
            ->whereNotNull('rol')
            ->where('rol', '<>', '')
            ->distinct()
            ->orderBy('rol')
            ->pluck('rol')
            ->values()
            ->all();

        $rows = $qb->orderByDesc('id')->paginate($perPage)->appends([
            'q' => $q,
            'perPage' => $perPage,
            'rol' => $role,
            'activo' => $onlyActive === null ? '' : ($onlyActive ? '1' : '0'),
        ]);

        $rows->setCollection(
            $rows->getCollection()->map(function ($r) {
                $arr = (array) $r;

                return (object) [
                    'id' => (int) ($arr['id'] ?? 0),
                    'nombre' => (string) ($arr['nombre'] ?? ''),
                    'email' => (string) ($arr['email'] ?? ''),
                    'rol' => (string) ($arr['rol'] ?? ''),
                    'activo' => (int) ($arr['activo'] ?? 0),
                    'es_superadmin' => (int) ($arr['es_superadmin'] ?? 0),
                    'force_password_change' => (int) ($arr['force_password_change'] ?? 0),
                    'last_login_at' => $arr['last_login_at'] ?? null,
                    'last_login_ip' => $arr['last_login_ip'] ?? null,
                    'permisos' => $this->parsePermisos($arr['permisos'] ?? null),
                    'raw' => $r,
                ];
            })
        );

        return view('admin.usuarios.administrativos.index', [
            'rows' => $rows,
            'q' => $q,
            'perPage' => $perPage,
            'roles' => $roles,
            'rol' => $role,
            'activo' => $onlyActive,
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.administrativos.form', [
            'mode' => 'create',
            'row'  => (object) [
                'id' => null,
                'nombre' => '',
                'email' => '',
                'rol' => '',
                'activo' => 1,
                'es_superadmin' => 0,
                'force_password_change' => 0,
                'permisos' => [],
                'last_login_at' => null,
                'last_login_ip' => null,
            ],
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'rol' => ['nullable', 'string', 'max:80'],
            'activo' => ['nullable'],
            'es_superadmin' => ['nullable'],
            'force_password_change' => ['nullable'],
            'permisos_text' => ['nullable', 'string'],
        ]);

        $exists = DB::connection($this->conn)->table($this->table)
            ->where('email', $data['email'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['email' => 'Ese correo ya está registrado.'])->withInput();
        }

        $perms = $this->parsePermisos($data['permisos_text'] ?? '');

        $payload = [
            'nombre' => (string) $data['nombre'],
            'email' => (string) $data['email'],
            'password' => Hash::make((string) $data['password']),
            'rol' => (string) ($data['rol'] ?? ''),
            'permisos' => $this->permisosToStorage($perms),
            'activo' => $req->boolean('activo', true) ? 1 : 0,
            'es_superadmin' => $req->boolean('es_superadmin', false) ? 1 : 0,
            'force_password_change' => $req->boolean('force_password_change', false) ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection($this->conn)->table($this->table)->insert($payload);

        return redirect()->route('admin.usuarios.administrativos.index')
            ->with('status', 'Usuario administrativo creado correctamente.');
    }

    public function edit(int $id): View
    {
        $r = DB::connection($this->conn)->table($this->table)->where('id', $id)->first();
        abort_unless($r, 404);

        $arr = (array) $r;

        return view('admin.usuarios.administrativos.form', [
            'mode' => 'edit',
            'row'  => (object) [
                'id' => (int) $arr['id'],
                'nombre' => (string) ($arr['nombre'] ?? ''),
                'email' => (string) ($arr['email'] ?? ''),
                'rol' => (string) ($arr['rol'] ?? ''),
                'activo' => (int) ($arr['activo'] ?? 0),
                'es_superadmin' => (int) ($arr['es_superadmin'] ?? 0),
                'force_password_change' => (int) ($arr['force_password_change'] ?? 0),
                'permisos' => $this->parsePermisos($arr['permisos'] ?? null),
                'last_login_at' => $arr['last_login_at'] ?? null,
                'last_login_ip' => $arr['last_login_ip'] ?? null,
            ],
        ]);
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        $data = $req->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'rol' => ['nullable', 'string', 'max:80'],
            'activo' => ['nullable'],
            'es_superadmin' => ['nullable'],
            'force_password_change' => ['nullable'],
            'permisos_text' => ['nullable', 'string'],
        ]);

        $exists = DB::connection($this->conn)->table($this->table)
            ->where('email', $data['email'])
            ->where('id', '<>', $id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['email' => 'Ese correo ya está registrado.'])->withInput();
        }

        $payload = [
            'nombre' => (string) $data['nombre'],
            'email' => (string) $data['email'],
            'rol' => (string) ($data['rol'] ?? ''),
            'activo' => $req->boolean('activo', true) ? 1 : 0,
            'es_superadmin' => $req->boolean('es_superadmin', false) ? 1 : 0,
            'force_password_change' => $req->boolean('force_password_change', false) ? 1 : 0,
            'permisos' => $this->permisosToStorage($this->parsePermisos($data['permisos_text'] ?? '')),
            'updated_at' => now(),
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make((string) $data['password']);
        }

        DB::connection($this->conn)->table($this->table)->where('id', $id)->update($payload);

        return redirect()->route('admin.usuarios.administrativos.index')
            ->with('status', 'Usuario administrativo actualizado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $r = DB::connection($this->conn)->table($this->table)->where('id', $id)->first();
        abort_unless($r, 404);

        $arr = (array) $r;
        $next = ((int)($arr['activo'] ?? 0)) ? 0 : 1;

        DB::connection($this->conn)->table($this->table)
            ->where('id', $id)
            ->update([
                'activo' => $next,
                'updated_at' => now(),
            ]);

        return back()->with('status', $next ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function resetPassword(Request $req, int $id): RedirectResponse
    {
        $new = trim((string) $req->get('new_password', ''));
        if ($new === '') {
            $new = Str::password(length: 14, letters: true, mixedCase: true, numbers: true, symbols: false);
        }
        if (mb_strlen($new) < 8) {
            return back()->withErrors(['new_password' => 'La contraseña debe tener al menos 8 caracteres.']);
        }

        DB::connection($this->conn)->table($this->table)
            ->where('id', $id)
            ->update([
                'password' => Hash::make($new),
                'force_password_change' => 1,
                'updated_at' => now(),
            ]);

        return back()->with('status', "Password reseteado. Nueva contraseña: {$new}");
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::connection($this->conn)->table($this->table)->where('id', $id)->delete();

        return redirect()->route('admin.usuarios.administrativos.index')
            ->with('status', 'Usuario eliminado.');
    }
}
