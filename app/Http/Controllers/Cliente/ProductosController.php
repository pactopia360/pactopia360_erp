<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ProductosController extends Controller
{
    public function index(Request $request)
    {
        $cuentaId = $this->cuentaId();

        $productos = Producto::query()
            ->where('cuenta_id', $cuentaId)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->q);

                $q->where(function ($sub) use ($term) {
                    $sub->where('sku', 'like', "%{$term}%")
                        ->orWhere('descripcion', 'like', "%{$term}%")
                        ->orWhere('clave_prodserv', 'like', "%{$term}%")
                        ->orWhere('clave_unidad', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('activo')
            ->orderBy('descripcion')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'productos' => $productos->map(fn ($p) => $this->payload($p))->values(),
            ]);
        }

        return view('cliente.productos.index', compact('productos'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $cuentaId = $this->cuentaId();

        $producto = Producto::create([
            'cuenta_id' => $cuentaId,
            'sku' => $data['sku'] ?? null,
            'descripcion' => $data['descripcion'],
            'precio_unitario' => $data['precio_unitario'] ?? 0,
            'iva_tasa' => $data['iva_tasa'] ?? 0.16,
            'clave_prodserv' => $data['clave_prodserv'] ?? '01010101',
            'clave_unidad' => $data['clave_unidad'] ?? 'E48',
            'activo' => $request->boolean('activo', true),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Producto registrado correctamente.',
                'producto' => $this->payload($producto),
            ]);
        }

        return back()->with('ok', 'Producto registrado correctamente.');
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizeProducto($producto);

        $data = $this->validated($request);

        $producto->update([
            'sku' => $data['sku'] ?? null,
            'descripcion' => $data['descripcion'],
            'precio_unitario' => $data['precio_unitario'] ?? 0,
            'iva_tasa' => $data['iva_tasa'] ?? 0.16,
            'clave_prodserv' => $data['clave_prodserv'] ?? '01010101',
            'clave_unidad' => $data['clave_unidad'] ?? 'E48',
            'activo' => $request->boolean('activo', true),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Producto actualizado correctamente.',
                'producto' => $this->payload($producto->fresh()),
            ]);
        }

        return back()->with('ok', 'Producto actualizado correctamente.');
    }

    public function destroy(Request $request, Producto $producto)
    {
        $this->authorizeProducto($producto);

        $producto->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Producto eliminado correctamente.',
            ]);
        }

        return back()->with('ok', 'Producto eliminado correctamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'sku' => ['nullable', 'string', 'max:80'],
            'descripcion' => ['required', 'string', 'max:500'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'iva_tasa' => ['nullable', 'numeric', 'min:0'],
            'clave_prodserv' => ['nullable', 'string', 'max:20'],
            'clave_unidad' => ['nullable', 'string', 'max:20'],
            'activo' => ['nullable'],
        ]);
    }

    private function payload(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'sku' => $producto->sku,
            'descripcion' => $producto->descripcion,
            'label' => trim(($producto->sku ? "{$producto->sku} · " : '') . $producto->descripcion),
            'precio_unitario' => (float) $producto->precio_unitario,
            'iva_tasa' => (float) $producto->iva_tasa,
            'clave_sat' => $producto->clave_prodserv,
            'clave_prodserv' => $producto->clave_prodserv,
            'unidad' => $producto->clave_unidad,
            'clave_unidad' => $producto->clave_unidad,
            'objeto_impuesto' => '02',
            'activo' => (bool) $producto->activo,
        ];
    }

    private function authorizeProducto(Producto $producto): void
    {
        abort_if((string) $producto->cuenta_id !== (string) $this->cuentaId(), 404);
    }

    private function cuentaId(): string
    {
        $user = Auth::guard('web')->user();

        $candidates = [
            $user->cuenta_id ?? null,
            $user->cuenta_cliente_id ?? null,
            $user->account_id ?? null,
            $user->cliente_id ?? null,

            session('cliente.cuenta_id'),
            session('cliente.account_id'),
            session('client.cuenta_id'),
            session('client.account_id'),
            session('cuenta_id'),
            session('account_id'),
            session('client_cuenta_id'),
            session('client_account_id'),
            session('p360.account_id'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '' && strtolower($value) !== 'null') {
                return $value;
            }
        }

        if ($user && Schema::connection('mysql_clientes')->hasTable('cuentas_clientes')) {
            $cuenta = $this->buscarCuentaCliente($user);

            if ($cuenta && isset($cuenta->id)) {
                $value = trim((string) $cuenta->id);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        abort(422, 'No se pudo resolver la cuenta cliente para guardar productos.');
    }

    private function buscarCuentaCliente($user): ?CuentaCliente
    {
        $query = CuentaCliente::query();

        $query->where(function ($q) use ($user) {
            $added = false;

            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_clientes', 'usuario_id')) {
                $q->orWhere('usuario_id', $user->id);
                $added = true;
            }

            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_clientes', 'user_id')) {
                $q->orWhere('user_id', $user->id);
                $added = true;
            }

            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_clientes', 'owner_user_id')) {
                $q->orWhere('owner_user_id', $user->id);
                $added = true;
            }

            if (! $added) {
                $q->whereRaw('1 = 0');
            }
        });

        return $query->first();
    }
}