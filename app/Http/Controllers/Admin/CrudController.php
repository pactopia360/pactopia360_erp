<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * CrudController base (backoffice)
 */
abstract class CrudController extends \App\Http\Controllers\Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $routeBase = 'admin.module';

    protected array $titles = [
        'index'  => 'Listado',
        'create' => 'Crear',
        'edit'   => 'Editar',
        'show'   => 'Detalle',
    ];

    protected array $fields = [];
    protected array $rules  = [];

    protected int $defaultPerPage = 25;
    protected int $maxPerPage     = 200;

    protected ?string $moduleCss = null;
    protected ?string $moduleJs  = null;

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

    protected function indexColumns(): array
    {
        $cols = [];
        foreach ($this->fields as $f) {
            $type = $f['type'] ?? 'text';
            if (in_array($type, ['password'], true)) continue;
            if (!empty($f['name'])) $cols[] = $f['name'];
        }
        if (!$cols) {
            $model = $this->newModel();
            $cols  = $model->getFillable();
            if (!$cols) {
                $table = $model->getTable();
                try {
                    $cols = Schema::connection($model->getConnectionName())
                        ->getColumnListing($table);
                } catch (\Throwable $e) {
                    $cols = ['id'];
                }
            }
        }
        $cols = array_values(array_unique($cols));
        return array_values(array_filter($cols, fn($c) => !in_array($c, ['password','remember_token'], true)));
    }

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

    protected function orderableColumns(): array
    {
        return $this->indexColumns();
    }

    protected function normalizeByFieldTypes(array $data): array
    {
        foreach ($this->fields as $f) {
            $name = $f['name'] ?? null; if (!$name) continue;
            $type = $f['type'] ?? 'text';

            if ($type === 'switch') {
                $data[$name] = (int) !empty($data[$name]);
            } elseif (($type === 'datetime' || $type === 'date') && array_key_exists($name, $data)) {
                if ($data[$name]) {
                    try {
                        $dt = \Illuminate\Support\Carbon::parse($data[$name]);
                        $data[$name] = $type === 'date' ? $dt->toDateString() : $dt->toDateTimeString();
                    } catch (\Throwable $e) {}
                }
            } elseif ($type === 'multiselect') {
                if (!isset($data[$name])) $data[$name] = [];
                if (!is_array($data[$name])) $data[$name] = (array) $data[$name];
            } elseif ($type === 'password') {
                if (array_key_exists($name, $data) && $data[$name] !== '') {
                    $data[$name] = Hash::make($data[$name]);
                }
            }
        }
        return $data;
    }

    protected function rulesFor(string $action): array
    {
        return $this->rules;
    }

    protected function prepareFormData(?Model $item = null): array
    {
        return [
            'item'      => $item,
            'fields'    => $this->fields,
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ];
    }

    protected function beforeSave(string $action, Model $model, array &$data): void {}
    protected function afterSave(string $action, Model $model): void {}

    public function index(Request $request): ViewContract
    {
        $query = $this->baseQuery();
        $q     = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $cols = $this->searchableColumns();
            $query->where(function (Builder $w) use ($cols, $q) {
                foreach ($cols as $i => $c) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $w->{$method}($c, 'like', '%'.$q.'%');
                }
            });
        }

        $sort  = (string) $request->query('sort', '');
        $dir   = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $model = $this->newModel();
        $table = $model->getTable();

        $orderables   = $this->orderableColumns();
        $hasCreatedAt = false;
        try {
            $hasCreatedAt = in_array('created_at',
                Schema::connection($model->getConnectionName())->getColumnListing($table),
                true
            );
        } catch (\Throwable $e) {}

        if ($sort !== '' && in_array($sort, $orderables, true)) {
            $query->orderBy($sort, $dir);
        } elseif ($hasCreatedAt) {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc($model->getKeyName());
        }

        $perPage = (int) $request->query('per_page', $this->defaultPerPage);
        if ($perPage < 1) $perPage = $this->defaultPerPage;
        if ($perPage > $this->maxPerPage) $perPage = $this->maxPerPage;

        $items = $query->paginate($perPage)->withQueryString();

        return view('admin.crud.index', [
            'items'     => $items,
            'fields'    => $this->fields,
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ]);
    }

    public function create(): ViewContract
    {
        return view('admin.crud.form', $this->prepareFormData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = $this->rulesFor('store');
        $data  = $request->validate($rules);
        $data  = $this->normalizeByFieldTypes($data);

        $model = $this->newModel();

        foreach ($this->fields as $f) {
            if (($f['type'] ?? 'text') === 'password') {
                $n = $f['name'] ?? null;
                if ($n && \Illuminate\Support\Arr::has($data, $n) && $data[$n] === '') {
                    unset($data[$n]);
                }
            }
        }

        DB::transaction(function () use (&$model, &$data) {
            $this->beforeSave('store', $model, $data);
            $model->forceFill($data)->save();
            $this->afterSave('store', $model);
        });

        return redirect()->route($this->routeBase.'.index')->with('ok', 'Creado correctamente.');
    }

    public function edit($id): ViewContract
    {
        $item = $this->newModel()->newQuery()->findOrFail($id);
        return view('admin.crud.form', $this->prepareFormData($item));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $rules = $this->rulesFor('update');
        $data  = $request->validate($rules);
        $data  = $this->normalizeByFieldTypes($data);

        $item = $this->newModel()->newQuery()->findOrFail($id);

        foreach ($this->fields as $f) {
            if (($f['type'] ?? 'text') === 'password') {
                $n = $f['name'] ?? null;
                if ($n && \Illuminate\Support\Arr::has($data, $n) && $data[$n] === '') {
                    unset($data[$n]);
                }
            }
        }

        DB::transaction(function () use ($item, &$data) {
            $this->beforeSave('update', $item, $data);
            $item->forceFill($data)->save();
            $this->afterSave('update', $item);
        });

        return redirect()->route($this->routeBase.'.index')->with('ok', 'Actualizado correctamente.');
    }

    /** Firma base */
    public function destroy(Request $request, $id): RedirectResponse
    {
        $item  = $this->newModel()->newQuery()->findOrFail($id);
        $force = (int) $request->query('force', 0) === 1;

        if ($force && in_array(SoftDeletes::class, class_uses_recursive($this->model), true)) {
            $item->forceDelete();
        } else {
            $item->delete();
        }

        return redirect()->route($this->routeBase.'.index')->with('ok', 'Eliminado correctamente.');
    }

    public function show($id): ViewContract
    {
        $item = $this->newModel()->newQuery()->findOrFail($id);
        return view('admin.crud.show', [
            'item'      => $item,
            'fields'    => $this->fields,
            'titles'    => $this->titles,
            'routeBase' => $this->routeBase,
            'moduleCss' => $this->moduleCss,
            'moduleJs'  => $this->moduleJs,
        ]);
    }
}
