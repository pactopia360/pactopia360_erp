<?php

namespace App\Http\Requests\Admin\CRM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarritoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ajusta si necesitas checar un permiso específico con Gate.
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'cliente'      => ['required','string','max:160'],
            'email'        => ['nullable','email','max:160'],
            'telefono'     => ['nullable','string','max:60'],
            'moneda'       => ['required','string','size:3'],
            'total'        => ['required','numeric','min:0'],
            'estado'       => ['required', Rule::in(['abierto','convertido','cancelado'])],
            'origen'       => ['nullable','string','max:60'],
            'etiquetas'    => ['nullable','array'],
            'etiquetas.*'  => ['string','max:40'],
            'meta'         => ['nullable','array'],
            'notas'        => ['nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente.required' => 'El nombre del cliente es obligatorio.',
            'moneda.required'  => 'La moneda es obligatoria.',
            'moneda.size'      => 'La moneda debe tener 3 caracteres (p. ej., MXN).',
            'total.required'   => 'El total es obligatorio.',
            'total.numeric'    => 'El total debe ser numérico.',
            'estado.required'  => 'Debes indicar el estado.',
            'estado.in'        => 'Estado inválido. Usa: abierto, convertido o cancelado.',
            'email.email'      => 'El email no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cliente'  => is_string($this->cliente) ? trim($this->cliente) : $this->cliente,
            'email'    => is_string($this->email) ? trim($this->email) : $this->email,
            'telefono' => is_string($this->telefono) ? trim($this->telefono) : $this->telefono,
            'moneda'   => is_string($this->moneda) ? strtoupper(trim($this->moneda)) : $this->moneda,
            'origen'   => is_string($this->origen) ? trim($this->origen) : $this->origen,
        ]);
    }

    protected function passedValidation(): void
    {
        if ($this->has('etiquetas') && is_array($this->etiquetas)) {
            $this->merge([
                'etiquetas' => array_values(
                    array_filter($this->etiquetas, fn ($v) => (string) $v !== '')
                ),
            ]);
        }
    }
}
