<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Auth\UsuarioAdministrativo as User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UsuariosController extends CrudController
{
    /** @var class-string<User> */
    protected string $model = User::class;

    protected string $routeBase = 'admin.usuarios';
    protected array  $titles    = [
        'index'  => 'Usuarios Admin',
        'create' => 'Nuevo usuario',
        'edit'   => 'Editar usuario',
    ];

    protected array $columns = [
        ['key'=>'id',            'label'=>'ID',     'width'=>'64',  'class'=>'text-muted'],
        ['key'=>'nombre',        'label'=>'Nombre'],
        ['key'=>'email',         'label'=>'Correo'],
        ['key'=>'rol',           'label'=>'Rol',    'type'=>'badge'],
        ['key'=>'es_superadmin', 'label'=>'SA',     'type'=>'boolean','align'=>'center','width'=>'68'],
        ['key'=>'activo',        'label'=>'Activo', 'type'=>'boolean','align'=>'center','width'=>'86'],
        ['key'=>'created_at',    'label'=>'Alta',   'type'=>'date','width'=>'120'],
    ];

    protected array $fields = [
        ['name'=>'nombre',                 'label'=>'Nombre',                         'type'=>'text',     'required'=>true,  'max'=>255],
        ['name'=>'email',                  'label'=>'Correo',                         'type'=>'email',    'required'=>true,  'max'=>255],
        ['name'=>'password',               'label'=>'Contraseña',                     'type'=>'password', 'placeholder'=>'Dejar vacío para no cambiar'],
        ['name'=>'rol',                    'label'=>'Rol',                            'type'=>'select',   'options'=>'@roles', 'required'=>true],
        ['name'=>'es_superadmin',          'label'=>'Superadministrador',             'type'=>'switch',   'show_if'=>'@can_super'],
        ['name'=>'activo',                 'label'=>'Activo',                         'type'=>'switch'],
        ['name'=>'force_password_change',  'label'=>'Forzar cambio de contraseña',    'type'=>'switch'],
    ];

    protected array $rulesStore = [
        'nombre'        => 'required|string|max:255',
        'email'         => 'required|email|max:255|unique:usuario_administrativos,email',
        'password'      => 'required|string|min:8',
        'rol'           => 'nullable|string|max:40',
        'activo'        => 'sometimes|boolean',
        'es_superadmin' => 'sometimes|boolean',
        'force_password_change' => 'sometimes|boolean',
    ];

    protected array $rulesUpdate = [
        'nombre'        => 'required|string|max:255',
        'email'         => 'required|email|max:255',
        'password'      => 'nullable|string|min:8',
        'rol'           => 'nullable|string|max:40',
        'activo'        => 'sometimes|boolean',
        'es_superadmin' => 'sometimes|boolean',
        'force_password_change' => 'sometimes|boolean',
    ];

    public function __construct()
    {
        $parent = get_parent_class($this);
        if ($parent && method_exists($parent, '__construct')) {
            parent::__construct();
        }
        $this->inflate();
    }

    private function inflate(): void
    {
        $roles = [
            ['value'=>'admin',   'label'=>'Admin'],
            ['value'=>'ventas',  'label'=>'Ventas'],
            ['value'=>'soporte', 'label'=>'Soporte'],
        ];
        $isSuper = (bool) optional(auth('admin')->user())->es_superadmin;

        foreach ($this->fields as &$f) {
            if (($f['options'] ?? null) === '@roles') {
                $f['options'] = $roles;
            }
            if (($f['show_if'] ?? null) === '@can_super') {
                $f['hidden'] = !$isSuper;
            }
        }
        unset($f);
    }

    /** Listado propio con barra de filtros/orden y paginación */
    public function index(Request $request): ViewContract
    {
        // OJO: no usamos Gate aquí para respetar el bypass de permisos en local (perm_mw en rutas).

        $q = trim((string) $request->query('q',''));
        $rol = trim((string) $request->query('rol',''));
        $sa  = $request->query('sa', '');
        $ac  = $request->query('activo','');
        $sort = $request->query('sort','created_at');
        $dir  = strtolower($request->query('dir','desc')) === 'asc' ? 'asc' : 'desc';
        $pp   = (int) $request->query('pp', 20);
        if (!in_array($pp, [10,20,50,100], true)) $pp = 20;
        if (!in_array($sort, ['id','nombre','email','rol','created_at'], true)) $sort = 'created_at';

        $query = User::query();

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                $w->where('nombre','like','%'.$q.'%')
                  ->orWhere('email','like','%'.$q.'%');
            });
        }
        if ($rol !== '') $query->where('rol', $rol);
        if ($sa !== '')   $query->where('es_superadmin', (int) $sa);
        if ($ac !== '')   $query->where('activo', (int) $ac);

        $query->orderBy($sort, $dir);
        $items = $query->paginate($pp)->withQueryString();

        $roles = [
            ['value'=>'admin',   'label'=>'Admin'],
            ['value'=>'ventas',  'label'=>'Ventas'],
            ['value'=>'soporte', 'label'=>'Soporte'],
        ];

        return view('admin.usuarios.index', [
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,

            'items'     => $items,
            'filters'   => compact('q','rol','sa','ac','sort','dir','pp'),
            'roles'     => $roles,
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        Gate::authorize('perm', 'usuarios_admin.crear');

        $data = $req->validate($this->rulesStore, [], [
            'nombre'   => 'Nombre',
            'email'    => 'Correo',
            'password' => 'Contraseña',
            'rol'      => 'Rol',
        ]);

        $data['activo']                = array_key_exists('activo', $data) ? (bool) $data['activo'] : 1;
        $data['es_superadmin']         = array_key_exists('es_superadmin', $data) ? (bool) $data['es_superadmin'] : 0;
        $data['force_password_change'] = array_key_exists('force_password_change', $data) ? (bool) $data['force_password_change'] : 1;
        if (empty($data['rol'])) $data['rol'] = 'admin';

        try {
            User::create($data); // password se castea a hashed en el modelo
            return redirect()->route('admin.usuarios.index')->with('ok', 'Usuario creado correctamente.');
        } catch (\Throwable $e) {
            Log::error('[Usuarios.store] '.$e->getMessage(), ['ex'=>$e]);
            return back()->withInput()->with('error', 'No se pudo crear el usuario.');
        }
    }

    public function update(Request $req, $usuario): RedirectResponse
    {
        Gate::authorize('perm', 'usuarios_admin.editar');

        /** @var User $user */
        $user = User::findOrFail($usuario);

        $rules = $this->rulesUpdate;
        $rules['email'] = ['required','email','max:255', Rule::unique('usuario_administrativos','email')->ignore($user->id)];

        $data = $req->validate($rules, [], [
            'nombre'   => 'Nombre',
            'email'    => 'Correo',
            'password' => 'Contraseña',
            'rol'      => 'Rol',
        ]);

        if (empty($data['password'])) unset($data['password']);
        foreach (['activo','es_superadmin','force_password_change'] as $k) {
            if (array_key_exists($k, $data)) $data[$k] = (bool) $data[$k];
        }
        if (empty($data['rol'])) $data['rol'] = $user->rol ?: 'admin';

        // Proteger al último superadmin
        if ($user->es_superadmin) {
            $quitanSuper = array_key_exists('es_superadmin', $data) && !$data['es_superadmin'];
            $desactiva   = array_key_exists('activo', $data) && !$data['activo'];
            if ($quitanSuper || $desactiva) {
                $otros = User::where('es_superadmin', 1)->where('id','!=',$user->id)->count();
                if ($otros === 0) {
                    return back()->withInput()->with('error', 'No puedes desactivar ni despromover al único superadministrador.');
                }
            }
        }

        try {
            $user->fill($data)->save();
            return redirect()->route('admin.usuarios.index')->with('ok', 'Usuario actualizado correctamente.');
        } catch (\Throwable $e) {
            Log::error('[Usuarios.update] '.$e->getMessage(), ['ex'=>$e]);
            return back()->withInput()->with('error', 'No se pudo actualizar el usuario.');
        }
    }

    public function destroy($usuario): RedirectResponse
    {
        Gate::authorize('perm', 'usuarios_admin.eliminar');

        /** @var User $user */
        $user = User::findOrFail($usuario);

        if ($user->es_superadmin) {
            $otros = User::where('es_superadmin', 1)->where('id','!=',$user->id)->count();
            if ($otros === 0) {
                return back()->with('error', 'No puedes eliminar al único superadministrador.');
            }
        }

        try {
            $user->delete();
            return redirect()->route('admin.usuarios.index')->with('ok', 'Usuario eliminado.');
        } catch (\Throwable $e) {
            Log::error('[Usuarios.destroy] '.$e->getMessage(), ['ex'=>$e]);
            return back()->with('error', 'No se pudo eliminar el usuario.');
        }
    }
}
