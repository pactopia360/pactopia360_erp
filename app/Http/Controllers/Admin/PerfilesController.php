<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerfilesController extends Controller
{
    protected string $routeBase = 'admin.perfiles';
    protected array $titles = [
        'index'  => 'Perfiles',
        'create' => 'Nuevo Perfil',
        'edit'   => 'Editar Perfil',
    ];

    public function index(Request $req)
    {
        $F = [
            'q'    => trim((string)$req->get('q','')),
            'ac'   => $req->get('ac',''),
            'sort' => $req->get('sort','created_at'),
            'dir'  => $req->get('dir','desc'),
            'pp'   => (int)($req->get('pp',20)),
        ];

        $q = Profile::query()
            ->when($F['q'] !== '', fn($qq) => $qq->where(function($w) use ($F){
                $w->where('clave','like',"%{$F['q']}%")
                  ->orWhere('nombre','like',"%{$F['q']}%");
            }))
            ->when($F['ac'] !== '', fn($qq) => $qq->where('activo', $F['ac'] === '1'))
            ->withCount(['permissions as permisos_count']);

        $allowedSort = ['clave','nombre','activo','created_at','updated_at'];
        $sort = in_array($F['sort'], $allowedSort, true) ? $F['sort'] : 'created_at';
        $dir  = strtolower($F['dir']) === 'asc' ? 'asc' : 'desc';

        $items = $q->orderBy($sort, $dir)->paginate($F['pp'])->withQueryString();

        $stats = [
            'total'             => $items->total(),
            'activos'           => (clone $q)->where('activo',true)->count(),
            'promedio_permisos' => round(((clone $q)->avg(DB::raw('(select count(*) from perfil_permiso where perfil_id = perfiles.id)')) ?? 0), 1),
        ];

        return view('admin.perfiles.index', [
            'items'     => $items,
            'filters'   => $F,
            'stats'     => $stats,
            'routeBase' => $this->routeBase,
            'titles'    => $this->titles,
        ]);
    }

    public function create()
    {
        return view('admin.perfiles.form', [
            'item'      => new Profile(),
            'routeBase' => $this->routeBase,
            'titles'    => $this->titles,
        ]);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'clave'       => 'required|string|max:100|unique:perfiles,clave',
            'nombre'      => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'activo'      => 'boolean',
        ]);
        $data['activo'] = (bool)($data['activo'] ?? true);

        $perfil = Profile::create($data);
        return redirect()->route($this->routeBase.'.edit', $perfil)->with('status','Perfil creado');
    }

    public function edit(Profile $perfil)
    {
        return view('admin.perfiles.form', [
            'item'      => $perfil,
            'routeBase' => $this->routeBase,
            'titles'    => $this->titles,
        ]);
    }

    public function update(Request $req, Profile $perfil)
    {
        $data = $req->validate([
            'clave'       => 'required|string|max:100|unique:perfiles,clave,'.$perfil->id,
            'nombre'      => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'activo'      => 'boolean',
        ]);
        $data['activo'] = (bool)($data['activo'] ?? false);

        $perfil->update($data);
        return back()->with('status','Perfil actualizado');
    }

    public function destroy(Profile $perfil)
    {
        $perfil->delete();
        return redirect()->route($this->routeBase.'.index')->with('status','Perfil eliminado');
    }

    // ---- Acciones auxiliares (AJAX) ----

    public function bulk(Request $req)
    {
        $data = $req->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:perfiles,id',
            'action' => 'required|string|in:activate,deactivate,delete',
        ]);

        $q = Profile::whereIn('id', $data['ids']);
        match ($data['action']) {
            'activate'   => $q->update(['activo'=>true]),
            'deactivate' => $q->update(['activo'=>false]),
            'delete'     => $q->delete(),
        };

        return response()->json(['ok'=>true]);
    }

    public function toggle(Request $req)
    {
        $v = $req->validate([
            'id'    => 'required|integer|exists:perfiles,id',
            'field' => 'required|string|in:activo',
            'value' => 'required|boolean',
        ]);
        $perfil = Profile::findOrFail($v['id']);
        $perfil->{$v['field']} = $v['value'];
        $perfil->save();

        return response()->json(['ok'=>true]);
    }

    // GET ?id= — devuelve el árbol de permisos por grupo
    public function permissions(Request $req)
    {
        $id = (int)$req->query('id');
        $perfil = Profile::findOrFail($id);

        $perms = Permission::where('activo',true)->orderBy('grupo')->orderBy('clave')->get()
            ->groupBy('grupo')
            ->map(function($items,$grupo){
                return [
                    'key'   => $grupo,
                    'title' => ucfirst($grupo),
                    'items' => $items->map(fn($p)=>[
                        'id'    => $p->id,
                        'key'   => $p->clave,
                        'label' => $p->label,
                    ])->values(),
                ];
            })->values();

        $assigned = $perfil->permissions()->pluck('permiso_id')->all();

        return response()->json([
            'groups'   => $perms,
            'assigned' => $assigned,
        ]);
    }

    // POST {id, perms[]}
    public function permissionsSave(Request $req)
    {
        $v = $req->validate([
            'id'    => 'required|integer|exists:perfiles,id',
            'perms' => 'array',
            'perms.*' => 'integer|exists:permisos,id',
        ]);

        $perfil = Profile::findOrFail($v['id']);
        $perfil->permissions()->sync($v['perms'] ?? []);

        return response()->json(['ok'=>true]);
    }

    // CSV simple
    public function export(Request $req): StreamedResponse
    {
        $q = Profile::query()
            ->when($req->filled('q'), fn($qq)=> $qq->where(fn($w)=>$w->where('clave','like','%'.$req->q.'%')->orWhere('nombre','like','%'.$req->q.'%')))
            ->when($req->filled('ac'), fn($qq)=> $qq->where('activo', $req->ac=='1'))
            ->withCount('permissions as permisos');

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=perfiles.csv',
        ];

        return response()->stream(function() use ($q){
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($out, ['ID','Clave','Nombre','Activo','Permisos','Creado','Actualizado']);
            $q->chunk(500, function($rows) use ($out){
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id, $r->clave, $r->nombre,
                        $r->activo ? 'Si':'No',
                        $r->permisos ?? $r->permisos_count ?? 0,
                        $r->created_at, $r->updated_at
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }
}
