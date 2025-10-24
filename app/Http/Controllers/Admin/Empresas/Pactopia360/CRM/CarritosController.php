<?php

namespace App\Http\Controllers\Admin\Empresas\Pactopia360\CRM;

use App\Http\Controllers\Controller;
use App\Models\Empresas\Pactopia360\CRM\Carrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CarritosController extends Controller
{
    /**
     * Campos permitidos para ordenamiento.
     * Evita SQL injection en ORDER BY.
     */
    private const SORTABLE = [
        'id', 'titulo', 'cliente', 'email', 'telefono',
        'estado', 'total', 'moneda', 'created_at', 'updated_at',
    ];

    /**
     * Estados válidos:
     * - Si el modelo define ESTADOS, úsalo.
     * - Si no, usa fallback que INCLUYE "nuevo" (coincide con seeder y vista).
     */
    private function estados(): array
    {
        if (defined(Carrito::class.'::ESTADOS')) {
            return Carrito::ESTADOS;
        }
        return ['nuevo', 'abierto', 'convertido', 'cancelado'];
    }

    /**
     * Construye query base con todos los filtros.
     */
    private function buildQuery(Request $request)
    {
        $q         = trim((string) $request->input('q', ''));
        $estado    = $request->input('estado');
        $moneda    = $request->input('moneda');
        $etiqueta  = $request->input('etiqueta');

        $desde     = $request->input('desde');     // YYYY-MM-DD
        $hasta     = $request->input('hasta');     // YYYY-MM-DD
        $minTotal  = $request->input('min_total'); // num
        $maxTotal  = $request->input('max_total'); // num

        $query = Carrito::query();

        // Búsqueda full-text simple (LIKE) sobre varias columnas
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                  ->orWhere('cliente', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('telefono', 'like', "%{$q}%")
                  ->orWhere('origen', 'like', "%{$q}%");
            });
        }

        // Filtro por estado (solo si es válido)
        if ($estado && in_array($estado, $this->estados(), true)) {
            $query->where('estado', $estado);
        }

        // Filtro por moneda (ej: MXN, USD)
        if ($moneda && is_string($moneda) && strlen($moneda) === 3) {
            $query->where('moneda', strtoupper($moneda));
        }

        // Filtro por etiqueta (si etiquetas es JSON/array)
        if ($etiqueta) {
            $query->where(function ($w) use ($etiqueta) {
                $w->whereJsonContains('etiquetas', $etiqueta)
                  ->orWhere('etiquetas', 'like', '%"'.$etiqueta.'"%'); // fallback
            });
        }

        // Rango de fechas (created_at)
        if ($desde) $query->whereDate('created_at', '>=', $desde);
        if ($hasta) $query->whereDate('created_at', '<=', $hasta);

        // Rango de totales
        if ($minTotal !== null && $minTotal !== '') $query->where('total', '>=', (float)$minTotal);
        if ($maxTotal !== null && $maxTotal !== '') $query->where('total', '<=', (float)$maxTotal);

        return $query;
    }

    /**
     * Listado con filtros, orden, paginación, métricas y export.
     */
    public function index(Request $request)
    {
        $estados = $this->estados();

        $query = $this->buildQuery($request);

        // Orden seguro
        $sort = $request->input('sort', 'id');
        $dir  = Str::lower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!in_array($sort, self::SORTABLE, true)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $dir);

        // Paginación configurable
        $perPage   = (int) ($request->input('per_page', 15));
        $perPage   = $perPage > 0 && $perPage <= 200 ? $perPage : 15;

        // Export CSV si se solicita
        if ($request->boolean('export') || $request->input('export') === 'csv') {
            return $this->exportCsv($query, $request);
        }

        $carritos = $query->paginate($perPage)->withQueryString();

        // Métricas rápidas por estado (para KPIs)
        $metricas = $this->metricsPorEstado($request);

        // Respuesta JSON (para AJAX/DataTables simples)
        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'data'     => $carritos->items(),
                'meta'     => [
                    'current_page' => $carritos->currentPage(),
                    'last_page'    => $carritos->lastPage(),
                    'per_page'     => $carritos->perPage(),
                    'total'        => $carritos->total(),
                    'sort'         => $sort,
                    'dir'          => $dir,
                ],
                'filters'  => $this->filtersFromRequest($request),
                'metricas' => $metricas,
                'estados'  => $estados,
            ]);
        }

        // Compat con vistas antiguas que esperan $rows
        return view('admin.empresas.pactopia360.crm.carritos.index', [
            'carritos' => $carritos,
            'rows'     => $carritos, // legacy
            'metricas' => $metricas,
            'estados'  => $estados,
        ] + $this->filtersFromRequest($request));
    }

    /**
     * Formulario de creación.
     */
    public function create()
    {
        return view('admin.empresas.pactopia360.crm.carritos.create', [
            'estados' => $this->estados(),
        ]);
    }

    /**
     * Guardar nuevo carrito.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // Normalización
        $data['etiquetas'] = $this->normalizeArray($data['etiquetas'] ?? null);
        $data['meta']      = is_array($data['meta'] ?? null) ? $data['meta'] : null;

        // Fallback de empresa_slug si lo usas (ajusta a tu sesión/tenant)
        if (empty($data['empresa_slug'])) {
            $data['empresa_slug'] = session('empresa_slug'); // opcional
        }

        DB::transaction(function () use ($data) {
            Carrito::create($data);
        });

        return redirect()
            ->route('admin.empresas.pactopia360.crm.carritos.index')
            ->with('ok', 'Carrito creado correctamente.');
    }

    /**
     * Detalle.
     */
    public function show(Carrito $carrito)
    {
        return view('admin.empresas.pactopia360.crm.carritos.show', compact('carrito'));
    }

    /**
     * Formulario de edición.
     */
    public function edit(Carrito $carrito)
    {
        return view('admin.empresas.pactopia360.crm.carritos.edit', [
            'carrito' => $carrito,
            'estados' => $this->estados(),
        ]);
    }

    /**
     * Actualizar.
     */
    public function update(Request $request, Carrito $carrito)
    {
        $data = $this->validateData($request, $carrito->id);

        $data['etiquetas'] = $this->normalizeArray($data['etiquetas'] ?? null);
        $data['meta']      = is_array($data['meta'] ?? null) ? $data['meta'] : null;

        DB::transaction(function () use ($carrito, $data) {
            $carrito->update($data);
        });

        return redirect()
            ->route('admin.empresas.pactopia360.crm.carritos.index')
            ->with('ok', 'Carrito actualizado.');
    }

    /**
     * Eliminar.
     */
    public function destroy(Carrito $carrito)
    {
        try {
            $carrito->delete();
            $msg = 'Carrito eliminado.';
            if (request()->wantsJson()) {
                return response()->json(['ok' => true, 'message' => $msg]);
            }
            return redirect()
                ->route('admin.empresas.pactopia360.crm.carritos.index')
                ->with('ok', $msg);
        } catch (\Throwable $e) {
            $msg = 'No se pudo eliminar el carrito. Verifica relaciones.';
            if (request()->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }
    }

    /**
     * Eliminación masiva (opcional): POST ids[].
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'Selecciona al menos un registro.');
        }

        try {
            DB::transaction(function () use ($ids) {
                Carrito::whereIn('id', $ids)->delete();
            });
            $msg = 'Registros eliminados.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => true, 'message' => $msg]);
            }
            return back()->with('ok', $msg);
        } catch (\Throwable $e) {
            $msg = 'Algunos registros no pudieron eliminarse.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }
    }

    /* =========================
     * Helpers
     * ========================= */

    /**
     * Reglas de validación (store/update).
     * Incluye "nuevo" para alinear con seeder y vista.
     */
    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'titulo'       => ['required', 'string', 'max:200'],
            'estado'       => ['required', 'in:'.implode(',', $this->estados())],
            'total'        => ['required', 'numeric', 'min:0'],
            'moneda'       => ['required', 'string', 'size:3'],

            'cliente'      => ['nullable', 'string', 'max:160'],
            'email'        => ['nullable', 'email', 'max:160'],
            'telefono'     => ['nullable', 'string', 'max:60'],
            'origen'       => ['nullable', 'string', 'max:60'],

            'etiquetas'    => ['nullable', 'array'],
            'etiquetas.*'  => ['nullable', 'string', 'max:40'],
            'meta'         => ['nullable', 'array'],
            'notas'        => ['nullable', 'string'],

            'empresa_slug' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * Normaliza arrays vacíos / strings en array limpio.
     */
    private function normalizeArray($value): ?array
    {
        if (is_null($value)) return null;
        if (is_string($value)) {
            // admite "tag1, tag2"
            $value = array_map('trim', explode(',', $value));
        }
        if (is_array($value)) {
            $value = array_values(array_filter(array_map('trim', $value), fn ($v) => $v !== ''));
            return $value ?: null;
        }
        return null;
    }

    /**
     * Extrae filtros “limpios” para reenviar a la vista/JSON.
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q'         => trim((string) $request->input('q', '')),
            'estado'    => $request->input('estado'),
            'moneda'    => $request->input('moneda'),
            'etiqueta'  => $request->input('etiqueta'),
            'desde'     => $request->input('desde'),
            'hasta'     => $request->input('hasta'),
            'min_total' => $request->input('min_total'),
            'max_total' => $request->input('max_total'),
            'sort'      => $request->input('sort', 'id'),
            'dir'       => $request->input('dir', 'desc'),
            'per_page'  => (int) $request->input('per_page', 15),
        ];
    }

    /**
     * Métricas por estado con filtros aplicados (para KPIs).
     */
    private function metricsPorEstado(Request $request): array
    {
        $base = $this->buildQuery($request);

        $estados = $this->estados();
        $conteos = [];
        $sumas   = [];

        foreach ($estados as $st) {
            $conteos[$st] = (clone $base)->where('estado', $st)->count();
            $sumas[$st]   = (float) (clone $base)->where('estado', $st)->sum('total');
        }

        $conteos['total'] = (clone $base)->count();
        $sumas['total']   = (float) (clone $base)->sum('total');

        return ['conteos' => $conteos, 'sumas' => $sumas];
    }

    /**
     * Exporta CSV con filtros aplicados.
     * Filtra columnas por existencia para no romper si el esquema varía.
     */
    private function exportCsv($query, Request $request): StreamedResponse
    {
        $filename = 'carritos_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $candidatas = [
            'id','titulo','estado','total','moneda',
            'cliente','email','telefono','origen',
            'etiquetas','meta','empresa_slug','created_at','updated_at',
        ];

        // Filtra por columnas que existan realmente (si hay conexión)
        $columns = [];
        try {
            foreach ($candidatas as $c) {
                if ($c === 'id' || Schema::hasColumn((new Carrito)->getTable(), $c)) {
                    $columns[] = $c;
                }
            }
        } catch (\Throwable $e) {
            // Si falla el esquema, usa el set por defecto (no rompe)
            $columns = $candidatas;
        }

        $callback = function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            // BOM para Excel en Windows
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $columns);

            $query->chunk(1000, function ($rows) use ($out, $columns) {
                foreach ($rows as $r) {
                    $row = [];
                    foreach ($columns as $col) {
                        $val = data_get($r, $col);
                        if (in_array($col, ['etiquetas', 'meta'], true)) {
                            $val = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string) $val;
                        }
                        $row[] = $val;
                    }
                    fputcsv($out, $row);
                }
            });

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
