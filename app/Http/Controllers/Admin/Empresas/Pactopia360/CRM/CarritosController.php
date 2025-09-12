<?php

namespace App\Http\Controllers\Admin\Empresas\Pactopia360\CRM;

use App\Http\Controllers\Controller;
use App\Models\Empresas\Pactopia360\CRM\Carrito; // <-- único import correcto
use Illuminate\Http\Request;

class CarritosController extends Controller
{
    /**
     * Listado y búsqueda simple.
     */
    public function index(Request $request)
    {
        $q      = trim((string) $request->input('q'));
        $estado = $request->input('estado');

        $carritos = Carrito::query()
            ->when($q !== '', function ($qrb) use ($q) {
                $qrb->where(function ($w) use ($q) {
                    $w->where('titulo',   'like', "%{$q}%")
                      ->orWhere('cliente',  'like', "%{$q}%")
                      ->orWhere('email',    'like', "%{$q}%")
                      ->orWhere('telefono', 'like', "%{$q}%")
                      ->orWhere('origen',   'like', "%{$q}%");
                });
            })
            ->when($estado, fn ($w) => $w->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.empresas.pactopia360.crm.carritos.index', [
            'carritos' => $carritos,
            'q'        => $q,
            'estado'   => $estado,
            'estados'  => Carrito::ESTADOS,
        ]);
    }

    /**
     * Formulario de creación.
     */
    public function create()
    {
        return view('admin.empresas.pactopia360.crm.carritos.create', [
            'estados' => Carrito::ESTADOS,
        ]);
    }

    /**
     * Guardar nuevo carrito.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo'      => ['required','string','max:200'],
            'estado'      => ['required','in:abierto,convertido,cancelado'],
            'total'       => ['required','numeric','min:0'],
            'moneda'      => ['required','string','size:3'],

            'cliente'     => ['nullable','string','max:160'],
            'email'       => ['nullable','email','max:160'],
            'telefono'    => ['nullable','string','max:60'],
            'origen'      => ['nullable','string','max:60'],

            'etiquetas'   => ['nullable','array'],
            'etiquetas.*' => ['string','max:40'],
            'meta'        => ['nullable','array'],
            'notas'       => ['nullable','string'],

            // si en tu tabla empresa_slug es NOT NULL, cámbialo a required
            'empresa_slug'=> ['nullable','string','max:50'],
        ]);

        if (isset($data['etiquetas'])) {
            $data['etiquetas'] = array_values(array_filter($data['etiquetas']));
        }

        Carrito::create($data);

        return redirect()
            ->route('admin.empresas.pactopia360.crm.carritos.index')
            ->with('ok', 'Carrito creado correctamente.');
    }

    /**
     * Detalle.
     */
    public function show($id)
    {
        $carrito = Carrito::findOrFail($id);
        return view('admin.empresas.pactopia360.crm.carritos.show', compact('carrito'));
    }

    /**
     * Formulario de edición.
     */
    public function edit($id)
    {
        $carrito = Carrito::findOrFail($id);

        return view('admin.empresas.pactopia360.crm.carritos.edit', [
            'carrito' => $carrito,
            'estados' => Carrito::ESTADOS,
        ]);
    }

    /**
     * Actualizar.
     */
    public function update(Request $request, $id)
    {
        $carrito = Carrito::findOrFail($id);

        $data = $request->validate([
            'titulo'      => ['required','string','max:200'],
            'estado'      => ['required','in:abierto,convertido,cancelado'],
            'total'       => ['required','numeric','min:0'],
            'moneda'      => ['required','string','size:3'],

            'cliente'     => ['nullable','string','max:160'],
            'email'       => ['nullable','email','max:160'],
            'telefono'    => ['nullable','string','max:60'],
            'origen'      => ['nullable','string','max:60'],

            'etiquetas'   => ['nullable','array'],
            'etiquetas.*' => ['string','max:40'],
            'meta'        => ['nullable','array'],
            'notas'       => ['nullable','string'],

            'empresa_slug'=> ['nullable','string','max:50'],
        ]);

        if (isset($data['etiquetas'])) {
            $data['etiquetas'] = array_values(array_filter($data['etiquetas']));
        }

        $carrito->update($data);

        return redirect()
            ->route('admin.empresas.pactopia360.crm.carritos.index')
            ->with('ok', 'Carrito actualizado.');
    }

    /**
     * Eliminar.
     */
    public function destroy($id)
    {
        $carrito = Carrito::findOrFail($id);
        $carrito->delete();

        return redirect()
            ->route('admin.empresas.pactopia360.crm.carritos.index')
            ->with('ok', 'Carrito eliminado.');
    }
}
