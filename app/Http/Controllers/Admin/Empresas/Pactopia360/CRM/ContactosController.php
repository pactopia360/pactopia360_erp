<?php

namespace App\Http\Controllers\Admin\Empresas\Pactopia360\CRM;

use App\Http\Controllers\Controller;
use App\Models\CrmContacto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactosController extends Controller
{
    /**
     * Slug fijo de la empresa para este módulo.
     */
    private const COMPANY = 'pactopia360';

    /**
     * Listado + búsqueda simple.
     */
    public function index(Request $request)
    {
        $q   = trim((string) $request->input('q', ''));
        $act = $request->boolean('act', true);

        $rows = CrmContacto::query()
            // Si tu modelo tiene scopeForEmpresa, úsalo; si no, filtra por columna.
            ->when(
                method_exists(CrmContacto::query()->getModel(), 'scopeForEmpresa'),
                fn($qb) => $qb->forEmpresa(self::COMPANY),
                fn($qb) => $qb->where('empresa_slug', self::COMPANY)
            )
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('nombre',   'like', "%{$q}%")
                      ->orWhere('email',   'like', "%{$q}%")
                      ->orWhere('telefono','like', "%{$q}%")
                      ->orWhere('puesto',  'like', "%{$q}%");
                });
            })
            ->when(!$act, fn($qb) => $qb->where('activo', 0))
            ->latest('id')
            ->paginate(15)
            ->appends($request->only('q','act'));

        return view('admin.empresas.pactopia360.crm.contactos.index', compact('rows','q','act'));
    }

    /**
     * Formulario de creación.
     */
    public function create()
    {
        $contacto = new CrmContacto(['empresa_slug' => self::COMPANY, 'activo' => 1]);

        return view('admin.empresas.pactopia360.crm.contactos.create', compact('contacto'));
    }

    /**
     * Guardar nuevo contacto.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['empresa_slug'] = self::COMPANY;

        CrmContacto::create($data);

        return redirect()
            ->route('admin.empresas.pactopia360.crm.contactos.index')
            ->with('ok', 'Contacto creado correctamente.');
    }

    /**
     * Formulario de edición.
     * (Usa Route Model Binding: {contacto})
     */
    public function edit(CrmContacto $contacto)
    {
        if ($contacto->empresa_slug !== self::COMPANY) {
            abort(404);
        }

        return view('admin.empresas.pactopia360.crm.contactos.edit', compact('contacto'));
    }

    /**
     * Actualizar contacto.
     */
    public function update(Request $request, CrmContacto $contacto)
    {
        if ($contacto->empresa_slug !== self::COMPANY) {
            abort(404);
        }

        $data = $this->validateData($request, $contacto->id);

        $contacto->update($data);

        return redirect()
            ->route('admin.empresas.pactopia360.crm.contactos.index')
            ->with('ok', 'Contacto actualizado.');
    }

    /**
     * Eliminar contacto.
     */
    public function destroy(CrmContacto $contacto)
    {
        if ($contacto->empresa_slug !== self::COMPANY) {
            abort(404);
        }

        $contacto->delete();

        return redirect()
            ->route('admin.empresas.pactopia360.crm.contactos.index')
            ->with('ok', 'Contacto eliminado.');
    }

    /**
     * Validación compartida para create/update.
     */
    private function validateData(Request $request, ?int $id = null): array
    {
        $id = $id ?: 0;

        return $request->validate([
            'nombre'     => ['required','string','max:120'],
            'email'      => ['nullable','email','max:160', Rule::unique('crm_contactos','email')->ignore($id)],
            'telefono'   => ['nullable','string','max:40'],
            'puesto'     => ['nullable','string','max:120'],
            'notas'      => ['nullable','string','max:2000'],
            'activo'     => ['required','boolean'],
        ]);
    }
}
