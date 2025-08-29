<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class BaseResourceController extends Controller
{
    /** Tabla elegida */
    protected ?string $table = null;
    /** Candidatas de tabla (orden de preferencia) */
    protected array $candidates = [];
    /** Título del módulo (para vistas) */
    protected string $title = 'Módulo';
    /** Base de rutas nombradas, ej: admin.clientes */
    protected string $routeBase = 'admin.module';
    /** Nombre del parámetro resource, ej: cliente, plan, pago */
    protected string $resourceParam = 'id';
    /** Columnas a ocultar en listados y formularios */
    protected array $hiddenCols = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    /** Columnas de sólo lectura (no se editan) */
    protected array $readonlyCols = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct()
    {
        foreach ($this->candidates as $t) {
            if (Schema::hasTable($t)) { $this->table = $t; break; }
        }
    }

    // ===== Helpers =================================================================

    protected function exists(): bool { return $this->table !== null; }

    protected function pk(): string
    {
        // Heurística simple
        $cols = $this->columns();
        if (in_array('id', $cols, true)) return 'id';
        if (in_array($this->resourceParam.'_id', $cols, true)) return $this->resourceParam.'_id';
        // fallback
        return 'id';
    }

    protected function columns(): array
    {
        if (!$this->exists()) return [];
        return Schema::getColumnListing($this->table);
    }

    protected function listableColumns(): array
    {
        $cols = array_values(array_filter($this->columns(), function ($c) {
            $lc = strtolower($c);
            if (in_array($lc, $this->hiddenCols, true)) return false;
            if (Str::endsWith($lc, ['_token'])) return false;
            return true;
        }));
        // prioritiza campos comunes en primeros lugares
        $priority = ['id','nombre','razon_social','email','rfc','status','estado','monto','fecha','created_at'];
        usort($cols, function($a,$b) use($priority){
            $pa = array_search($a, $priority, true); $pa = $pa===false ? 999 : $pa;
            $pb = array_search($b, $priority, true); $pb = $pb===false ? 999 : $pb;
            if ($pa === $pb) return strcmp($a,$b);
            return $pa <=> $pb;
        });
        // limita columnas para listado
        return array_slice($cols, 0, 12);
    }

    protected function editableColumns(): array
    {
        $cols = array_values(array_filter($this->columns(), function ($c) {
            $lc = strtolower($c);
            if (in_array($lc, $this->hiddenCols, true)) return false;
            if (in_array($lc, $this->readonlyCols, true)) return false;
            if ($lc === $this->pk()) return false;
            return true;
        }));
        return $cols;
    }

    protected function guessInputType(string $col): string
    {
        $lc = strtolower($col);
        if (Str::contains($lc, ['password'])) return 'password';
        if (Str::contains($lc, ['email']))    return 'email';
        if (Str::contains($lc, ['fecha','_at','date'])) return 'datetime-local';
        if (Str::contains($lc, ['monto','amount','precio','price','total','cantidad'])) return 'number';
        if (Str::contains($lc, ['rfc'])) return 'text';
        if (Str::contains($lc, ['activo','status','estado'])) return 'text'; // podrías convertir a select si quieres
        if (Str::contains($lc, ['descripcion','notes','nota','detalle'])) return 'textarea';
        return 'text';
    }

    protected function sanitizePayload(Request $req, array $cols, bool $isUpdate=false): array
    {
        $payload = [];
        foreach ($cols as $c) {
            $type = $this->guessInputType($c);
            if ($type === 'password') {
                $v = $req->string($c)->toString();
                if ($v === '' && $isUpdate) continue; // no cambiar
                $payload[$c] = bcrypt($v);
            } else {
                $v = $req->input($c);
                // convierte datetime-local a timestamp si aplica
                if ($v !== null && $this->guessInputType($c) === 'datetime-local') {
                    try { $v = \Carbon\Carbon::parse($v)->toDateTimeString(); } catch (\Throwable $e) {}
                }
                $payload[$c] = $v;
            }
        }
        return $payload;
    }

    // ===== Vistas base =============================================================

    public function index(Request $request)
    {
        if (!$this->exists()) {
            return view('admin.crud.blank', [
                'title' => $this->title,
                'message' => "No existe una tabla compatible para «{$this->title}».",
            ]);
        }

        $q = trim((string)$request->query('q',''));
        $pk = $this->pk();
        $cols = $this->listableColumns();
        $query = DB::table($this->table)->select($cols);

        if ($q !== '') {
            $query->where(function($w) use($cols,$q){
                foreach ($cols as $i=>$c) {
                    if ($i===0) $w->where($c, 'like', '%'.$q.'%');
                    else $w->orWhere($c, 'like', '%'.$q.'%');
                }
            });
        }

        if (in_array('created_at',$this->columns(),true)) $query->orderByDesc('created_at');
        else $query->orderByDesc($pk);

        $rows = $query->paginate(20)->withQueryString();

        return view('admin.crud.index', [
            'title'     => $this->title,
            'routeBase' => $this->routeBase,
            'pk'        => $pk,
            'cols'      => $cols,
            'rows'      => $rows,
            'createAllowed' => count($this->editableColumns())>0,
        ]);
    }

    public function create()
    {
        if (!$this->exists()) return $this->noTable();
        return view('admin.crud.form', [
            'title'     => $this->title,
            'routeBase' => $this->routeBase,
            'pk'        => $this->pk(),
            'cols'      => $this->editableColumns(),
            'row'       => [],
            'mode'      => 'create',
        ]);
    }

    public function store(Request $request)
    {
        if (!$this->exists()) return $this->noTable();
        $payload = $this->sanitizePayload($request, $this->editableColumns(), false);
        if (in_array('created_at',$this->columns(),true)) $payload['created_at'] = now();
        if (in_array('updated_at',$this->columns(),true)) $payload['updated_at'] = now();
        $id = DB::table($this->table)->insertGetId($payload);
        return redirect()->route($this->routeBase.'.show', [$this->resourceParam=>$id])
            ->with('status', "{$this->title}: creado correctamente.");
    }

    public function show($id)
    {
        if (!$this->exists()) return $this->noTable();
        $pk = $this->pk();
        $row = DB::table($this->table)->where($pk,$id)->first();
        if (!$row) abort(404);
        return view('admin.crud.show', [
            'title'     => $this->title,
            'routeBase' => $this->routeBase,
            'pk'        => $pk,
            'row'       => (array)$row,
        ]);
    }

    public function edit($id)
    {
        if (!$this->exists()) return $this->noTable();
        $pk = $this->pk();
        $row = DB::table($this->table)->where($pk,$id)->first();
        if (!$row) abort(404);
        return view('admin.crud.form', [
            'title'     => $this->title,
            'routeBase' => $this->routeBase,
            'pk'        => $pk,
            'cols'      => $this->editableColumns(),
            'row'       => (array)$row,
            'mode'      => 'edit',
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$this->exists()) return $this->noTable();
        $pk = $this->pk();
        $payload = $this->sanitizePayload($request, $this->editableColumns(), true);
        if (in_array('updated_at',$this->columns(),true)) $payload['updated_at'] = now();
        DB::table($this->table)->where($pk,$id)->update($payload);
        return redirect()->route($this->routeBase.'.show', [$this->resourceParam=>$id])
            ->with('status', "{$this->title}: actualizado.");
    }

    public function destroy($id)
    {
        if (!$this->exists()) return $this->noTable();
        $pk = $this->pk();
        DB::table($this->table)->where($pk,$id)->delete();
        return redirect()->route($this->routeBase.'.index')
            ->with('status', "{$this->title}: eliminado.");
    }

    protected function noTable()
    {
        return view('admin.crud.blank', [
            'title' => $this->title,
            'message' => 'Pendiente de implementar.',
        ]);
    }
}
