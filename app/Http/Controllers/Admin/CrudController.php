<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** Base de rutas nombradas (ej: admin.clientes) */
    protected string $routeBase = 'admin.module';

    /** Títulos para vistas */
    protected array $titles = [
        'index'  => 'Listado',
        'create' => 'Crear',
        'edit'   => 'Editar',
    ];

    /**
     * Definición de campos:
     * - name, label, type, options, step, required, only(create|edit), full(bool)
     */
    protected array $fields = [];

    /** Reglas base (store/update usarán esto salvo overrides en hijos) */
    protected array $rules = [];

    /** (Opcional) CSS/JS propio del módulo (se inyecta con @push en las vistas CRUD) */
    protected ?string $moduleCss = null;
    protected ?string $moduleJs  = null;

    // ===================== Helpers =====================

    protected function newModel(): Model
    {
        /** @var Model $m */
        $m = new $this->model();
        return $m;
    }

    protected function baseQuery(): Builder
    {
        return $this->newModel()->newQuery();
    }

    /** Columnas visibles en listados (omite passwords, etc.) */
    protected function indexColumns(): array
    {
        $cols = [];
        foreach ($this->fields as $f) {
            $type = $f['type'] ?? 'text';
            if (in_array($type, ['password'], true)) continue;
            $cols[] = $f['name'];
        }
        if (!$cols) {
            $cols = $this->newModel()->getFillable();
            if (!$cols) $cols = ['id'];
        }
        return array_values(array_unique($cols));
    }

    /** Campos ‘buscables’ en query string q */
    protected function searchableColumns(): array
    {
        $out = [];
        foreach ($this->fields as $f) {
            $name = $f['name'] ?? null;
            if (!$name) continue;
            $type = $f['type'] ?? 'text';
            if (in_array($type, ['password','switch'], true)) continue;
            $out[] = $name;
        }
        return $out ?: $this->indexColumns();
    }

    /** Normaliza payload según tipos (switch, datetime, etc.) */
    protected function normalizeByFieldTypes(array $data): array
    {
        foreach ($this->fields as $f) {
            $name = $f['name'] ?? null; if (!$name) continue;
            $type = $f['type'] ?? 'text';

            if ($type === 'switch') {
                $data[$name] = (int) !empty($data[$name]);
            } elseif ($type === 'datetime' && array_key_exists($name, $data)) {
                if ($data[$name]) {
                    try { $data[$name] = \Illuminate\Support\Carbon::parse($data[$name])->toDateTimeString(); } catch (\Throwable $e) {}
                }
            } elseif ($type === 'multiselect') {
                if (!isset($data[$name])) $data[$name] = [];
                if (!is_array($data[$name])) $data[$name] = (array) $data[$name];
            }
        }
        return $data;
    }

    // ===================== Acciones =====================

    /** Listado */
    public function index(Request $request): ViewContract
    {
        $query = $this->baseQuery();
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $cols = $this->searchableColumns();
            $query->where(function (Builder $w) use ($cols, $q) {
                foreach ($cols as $i => $c) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $w->{$method}($c, 'like', '%'.$q.'%');
                }
            });
        }

        $model = $this->newModel();
        $table = $model->getTable();
        $hasCreatedAt = in_array('created_at', $model->getConnection()->getSchemaBuilder()->getColumnListing($table), true);

        if ($hasCreatedAt) $query->orderByDesc('created_at');
        else $query->orderByDesc($model->getKeyName());

        $items = $query->paginate(20)->withQueryString();

        return view('admin.crud.index', [
            'items'     => $items,
            'fields'    => $this->fields,
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ]);
    }

    /** Formulario de creación */
    public function create(): ViewContract
    {
        return view('admin.crud.form', [
            'item'      => null,
            'fields'    => $this->fields,
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ]);
    }

    /** Guardar nuevo */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules);
        $data = $this->normalizeByFieldTypes($data);

        $model = $this->newModel();
        $model->forceFill($data)->save();

        return redirect()
            ->route($this->routeBase.'.index')
            ->with('status', 'Creado correctamente.');
    }

    /** Formulario de edición */
    public function edit($id): ViewContract
    {
        $item = $this->newModel()->newQuery()->findOrFail($id);

        return view('admin.crud.form', [
            'item'      => $item,
            'fields'    => $this->fields,
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ]);
    }

    /** Actualizar */
    public function update(Request $request, $id): RedirectResponse
    {
        $data = $request->validate($this->rules);
        $data = $this->normalizeByFieldTypes($data);

        $item = $this->newModel()->newQuery()->findOrFail($id);
        foreach ($this->fields as $f) {
            if (($f['type'] ?? 'text') === 'password') {
                $n = $f['name'] ?? null;
                if ($n && Arr::get($data, $n) === '') unset($data[$n]);
            }
        }
        $item->forceFill($data)->save();

        return redirect()
            ->route($this->routeBase.'.index')
            ->with('status', 'Actualizado correctamente.');
    }

    /** Eliminar */
    public function destroy($id): RedirectResponse
    {
        $item = $this->newModel()->newQuery()->findOrFail($id);
        $item->delete();

        return redirect()
            ->route($this->routeBase.'.index')
            ->with('status', 'Eliminado correctamente.');
    }

    /** Mostrar */
    public function show($id): ViewContract
    {
        $item = $this->newModel()->newQuery()->findOrFail($id);
        return view('admin.crud.show', [
            'item'      => $item,
            'fields'    => $this->fields,
            'titles'    => $this->titles + ['show' => ($this->titles['index'] ?? 'Detalle')],
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ]);
    }
}
