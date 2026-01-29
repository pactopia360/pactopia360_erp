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

    /** Tabla real a usar (LOCAL: usuario_administrativos | PROD: usuarios_admin) */
    private string $table;

    /** cache columns per request */
    private array $colCache = [];

    public function __construct()
    {
        $preferred = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->adm = $this->pickAdminConn($preferred);

        // ✅ SOT: si existe tabla LOCAL, esa es la de auth en tu modelo UsuarioAdministrativo
        $schema = Schema::connection($this->adm);
        $this->table = $schema->hasTable('usuario_administrativos')
            ? 'usuario_administrativos'
            : 'usuarios_admin';
    }

    private function pickAdminConn(string $preferred): string
    {
        $candidates = array_values(array_unique([$preferred, 'mysql_admin', 'mysql']));

        foreach ($candidates as $c) {
            try {
                DB::connection($c)->getPdo();
                return $c;
            } catch (\Throwable $e) {
                // continue
            }
        }

        return $preferred ?: 'mysql_admin';
    }

    private function hasCol(string $col): bool
    {
        $key = $this->adm . ':' . $this->table . ':' . $col;
        if (array_key_exists($key, $this->colCache)) {
            return (bool) $this->colCache[$key];
        }

        try {
            $ok = Schema::connection($this->adm)->hasColumn($this->table, $col);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return (bool) ($this->colCache[$key] = $ok);
    }

    private function normalizeIdForQuery(string $id)
    {
        try {
            $colType = Schema::connection($this->adm)->getColumnType($this->table, 'id');
            if (in_array($colType, ['integer', 'bigint'], true)) {
                return (int) $id;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $id;
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

    private function normalizeEstatus(?string $v, int $activo, int $isBlocked): string
    {
        // Prioridades:
        // 1) si está bloqueado => bloqueado
        // 2) si activo=0 => inactivo
        // 3) default => activo
        if ($isBlocked === 1) return 'bloqueado';
        if ($activo === 0) return 'inactivo';

        $st = strtolower(trim((string) $v));
        if ($st === '' || $st === 'null') return 'activo';

        // normaliza variantes comunes
        $map = [
            'active' => 'activo',
            'enabled' => 'activo',
            'ok' => 'activo',
            'activa' => 'activo',
            'habilitado' => 'activo',
            'habilitada' => 'activo',
            'inactive' => 'inactivo',
            'disabled' => 'inactivo',
            'blocked' => 'bloqueado',
            'block' => 'bloqueado',
        ];
        if (isset($map[$st])) $st = $map[$st];

        // allowlist final
        $allowed = ['activo','inactivo','bloqueado'];
        return in_array($st, $allowed, true) ? $st : 'activo';
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
        $qb  = $adm->table($this->table);

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($rol !== '' && $rol !== 'todos' && $this->hasCol('rol')) {
            $qb->where('rol', $rol);
        }

        if (($estado === '0' || $estado === '1') && $this->hasCol('activo')) {
            $qb->where('activo', (int) $estado);
        }

        $select = [
            'id',
            $this->hasCol('nombre') ? 'nombre' : DB::raw('NULL as nombre'),
            $this->hasCol('email') ? 'email' : DB::raw('NULL as email'),
            $this->hasCol('rol') ? 'rol' : DB::raw("'usuario' as rol"),
            $this->hasCol('activo') ? 'activo' : DB::raw('1 as activo'),
            $this->hasCol('estatus') ? 'estatus' : DB::raw('NULL as estatus'),
            $this->hasCol('is_blocked') ? 'is_blocked' : DB::raw('0 as is_blocked'),
            $this->hasCol('es_superadmin') ? 'es_superadmin' : DB::raw('0 as es_superadmin'),
            $this->hasCol('force_password_change') ? 'force_password_change' : DB::raw('0 as force_password_change'),
            $this->hasCol('last_login_at') ? 'last_login_at' : DB::raw('NULL as last_login_at'),
            $this->hasCol('last_login_ip') ? 'last_login_ip' : DB::raw('NULL as last_login_ip'),
            $this->hasCol('created_at') ? 'created_at' : DB::raw('NULL as created_at'),
            $this->hasCol('updated_at') ? 'updated_at' : DB::raw('NULL as updated_at'),
            $this->hasCol('permisos') ? 'permisos' : DB::raw('NULL as permisos'),
        ];

        $order1 = $this->hasCol('es_superadmin') ? 'es_superadmin' : 'id';

        $rows = $qb
            ->select($select)
            ->orderByDesc($order1)
            ->orderBy($this->hasCol('nombre') ? 'nombre' : 'id')
            ->paginate($perPage)
            ->withQueryString();

        $roles = [];
        if ($this->hasCol('rol')) {
            $roles = $adm->table($this->table)
                ->select('rol')
                ->whereNotNull('rol')
                ->where('rol', '!=', '')
                ->distinct()
                ->orderBy('rol')
                ->pluck('rol')
                ->values()
                ->all();
        }

        $debug = [
            'conn'  => $this->adm,
            'db'    => (string) $adm->getDatabaseName(),
            'table' => $this->table,
            'total' => (int) $adm->table($this->table)->count(),
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
            'estatus' => 'activo',
            'is_blocked' => 0,
            'es_superadmin' => 0,
            'force_password_change' => 0,
            'permisos' => null,
        ];

        return view('admin.usuarios.administrativos.form', [
            'mode' => 'create',
            'row'  => $row,
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        $rules = [
            'nombre' => ['required','string','max:150'],
            'email'  => ['required','email','max:190'],
            'rol'    => ['nullable','string','max:30'],
            'activo' => ['required','in:0,1'],
            'es_superadmin' => ['required','in:0,1'],
            'force_password_change' => ['nullable','in:0,1'],
            'password' => ['required','string','min:10'],
            'permisos_text' => ['nullable','string','max:5000'],
        ];

        if ($this->hasCol('estatus')) {
            $rules['estatus'] = ['nullable','string','max:32'];
        }
        if ($this->hasCol('is_blocked')) {
            $rules['is_blocked'] = ['nullable','in:0,1'];
        }

        $data = $req->validate($rules);

        $adm = DB::connection($this->adm);

        $email = strtolower(trim($data['email']));
        $exists = (int) $adm->table($this->table)->whereRaw('LOWER(email)=?', [$email])->count();
        if ($exists > 0) {
            return back()->withInput()->with('err', 'Ya existe un usuario con ese email.');
        }

        $permsJson = $this->parsePerms($data['permisos_text'] ?? null);

        $activo    = (int)($data['activo'] ?? 1);
        $isBlocked = (int)($data['is_blocked'] ?? 0);
        $estatus   = $this->normalizeEstatus($data['estatus'] ?? null, $activo, $isBlocked);

        $ins = [
            // ✅ LOCAL: uuid, PROD: AI (no lo mandamos)
            'nombre'   => $data['nombre'],
            'email'    => $email,
            'password' => Hash::make((string)$data['password']),
        ];

        // LOCAL: usuario_administrativos usa UUID string normalmente
        // ✅ Decide por tipo real de columna (no por nombre de tabla)
        // - Si id es integer/bigint (AI) => NO mandamos id
        // - Si id es string/uuid => mandamos UUID
        try {
            $colType = Schema::connection($this->adm)->getColumnType($this->table, 'id'); // 'integer'|'bigint'|'string'...
            $idIsNumeric = in_array($colType, ['integer', 'bigint'], true);

            if (!$idIsNumeric) {
                $ins['id'] = (string) Str::uuid();
            }
        } catch (\Throwable $e) {
            // Fallback seguro para producción: no mandamos id
        }


        if ($this->hasCol('rol')) {
            $ins['rol'] = (string)($data['rol'] ?? 'usuario');
        }

        if ($this->hasCol('activo')) {
            $ins['activo'] = $activo;
        }

        if ($this->hasCol('estatus')) {
            $ins['estatus'] = $estatus; // ✅ nunca NULL
        }

        if ($this->hasCol('is_blocked')) {
            $ins['is_blocked'] = $isBlocked;
        }

        if ($this->hasCol('es_superadmin')) {
            $ins['es_superadmin'] = (int) $data['es_superadmin'];
        }

        if ($this->hasCol('force_password_change')) {
            $ins['force_password_change'] = (int)($data['force_password_change'] ?? 0);
        }

        if ($this->hasCol('permisos')) {
            $ins['permisos'] = $permsJson;
        }

        if ($this->hasCol('remember_token')) {
            $ins['remember_token'] = null;
        }

        if ($this->hasCol('created_at')) $ins['created_at'] = now();
        if ($this->hasCol('updated_at')) $ins['updated_at'] = now();

        $adm->table($this->table)->insert($ins);

        return redirect()->route('admin.usuarios.administrativos.index')->with('ok', 'Usuario creado.');
    }

    public function edit(string $id): View
{
    $adm = DB::connection($this->adm);

    $qid = $this->normalizeIdForQuery($id);
    $row = $adm->table($this->table)->where('id', $qid)->first();

    abort_unless($row, 404);

    if (!property_exists($row, 'permisos')) {
        $row->permisos = null;
    }

    // ✅ normaliza estatus vacío para evitar bloqueos sorpresa
    if ($this->hasCol('estatus')) {
        $activo    = (int) ($row->activo ?? 1);
        $isBlocked = (int) ($row->is_blocked ?? 0);
        $row->estatus = $this->normalizeEstatus($row->estatus ?? null, $activo, $isBlocked);
    }

    return view('admin.usuarios.administrativos.form', [
        'mode' => 'edit',
        'row'  => $row,
    ]);
}

    public function update(Request $req, string $id): RedirectResponse
    {
        $rules = [
            'nombre' => ['required','string','max:150'],
            'email'  => ['required','email','max:190'],
            'rol'    => ['nullable','string','max:30'],
            'activo' => ['required','in:0,1'],
            'es_superadmin' => ['required','in:0,1'],
            'force_password_change' => ['nullable','in:0,1'],
            'password' => ['nullable','string','min:10'],
            'permisos_text' => ['nullable','string','max:5000'],
        ];

        if ($this->hasCol('estatus')) {
            $rules['estatus'] = ['nullable','string','max:32'];
        }
        if ($this->hasCol('is_blocked')) {
            $rules['is_blocked'] = ['nullable','in:0,1'];
        }

        $data = $req->validate($rules);

        $adm = DB::connection($this->adm);

        $qid = $this->normalizeIdForQuery($id);
        $row = $adm->table($this->table)->where('id', $qid)->first();

        abort_unless($row, 404);

        $email = strtolower(trim($data['email']));
        $emailExists = (int) $adm->table($this->table)
            ->whereRaw('LOWER(email)=?', [$email])
            ->where('id', '!=', $id)
            ->count();

        if ($emailExists > 0) {
            return back()->withInput()->with('err', 'Ese email ya lo usa otro usuario.');
        }

        $permsJson = $this->parsePerms($data['permisos_text'] ?? null);

        $activo    = (int)($data['activo'] ?? (int)($row->activo ?? 1));
        $isBlocked = (int)($data['is_blocked'] ?? (int)($row->is_blocked ?? 0));
        $estatus   = $this->normalizeEstatus($data['estatus'] ?? ($row->estatus ?? null), $activo, $isBlocked);

        $upd = [
            'nombre' => $data['nombre'],
            'email'  => $email,
        ];

        if ($this->hasCol('rol')) {
            $upd['rol'] = (string)($data['rol'] ?? 'usuario');
        }

        if ($this->hasCol('activo')) {
            $upd['activo'] = $activo;
        }

        if ($this->hasCol('is_blocked')) {
            $upd['is_blocked'] = $isBlocked;
        }

        if ($this->hasCol('estatus')) {
            $upd['estatus'] = $estatus;
        }

        if ($this->hasCol('es_superadmin')) {
            $upd['es_superadmin'] = (int) $data['es_superadmin'];
        }

        if ($this->hasCol('force_password_change')) {
            $upd['force_password_change'] = (int)($data['force_password_change'] ?? (int)($row->force_password_change ?? 0));
        }

        if ($this->hasCol('permisos')) {
            $upd['permisos'] = $permsJson;
        }

        if (!empty($data['password'])) {
            $upd['password'] = Hash::make((string)$data['password']);
            if ($this->hasCol('force_password_change')) {
                $upd['force_password_change'] = (int)($data['force_password_change'] ?? 0);
            }
        }

        if ($this->hasCol('updated_at')) $upd['updated_at'] = now();

        $qid = $this->normalizeIdForQuery($id);
        $row = $adm->table($this->table)->where('id', $qid)->first();


        return redirect()->route('admin.usuarios.administrativos.edit', $id)->with('ok', 'Usuario actualizado.');
    }

    public function toggle(string $id): RedirectResponse
    {
        $adm = DB::connection($this->adm);

        $row = $adm->table($this->table)->where('id', $id)->first();
        abort_unless($row, 404);

        $new = ((int)($row->activo ?? 1) === 1) ? 0 : 1;

        $upd = [];
        if ($this->hasCol('activo')) $upd['activo'] = $new;

        // Si se reactiva, desbloquea (si existe columna)
        if ($this->hasCol('is_blocked') && $new === 1) {
            $upd['is_blocked'] = 0;
        }

        // estatus coherente
        if ($this->hasCol('estatus')) {
            $upd['estatus'] = $new ? 'activo' : 'inactivo';
        }

        if ($this->hasCol('updated_at')) $upd['updated_at'] = now();

        $adm->table($this->table)->where('id', $id)->update($upd);

        return back()->with('ok', $new ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function resetPassword(Request $req, string $id): RedirectResponse
    {
        $data = $req->validate([
            'password'     => ['nullable','string','min:10'],
            'new_password' => ['nullable','string','min:10'],
        ]);

        $adm = DB::connection($this->adm);

        $qid = $this->normalizeIdForQuery($id);

        $row = $adm->table($this->table)->where('id', $qid)->first();
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

        $adm->table($this->table)->where('id', $qid)->update($upd);

        return back()->with('ok', 'Password reseteada. Nueva: '.$plain);
    }



    public function destroy(string $id): RedirectResponse
    {
        $adm = DB::connection($this->adm);

        if ((string) auth('admin')->id() === (string) $id) {
            return back()->with('err', 'No puedes eliminar tu propio usuario.');
        }

        $adm->table($this->table)->where('id', $id)->delete();

        return back()->with('ok', 'Usuario eliminado.');
    }
}
