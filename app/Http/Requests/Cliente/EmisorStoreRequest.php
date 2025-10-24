<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;

class EmisorStoreRequest extends FormRequest
{
    public function authorize(): bool { return auth('web')->check(); }

    public function rules(): array
    {
        $pro = strtoupper(auth('web')->user()?->cuenta?->plan_actual ?? 'FREE') === 'PRO';

        return [
            'rfc'               => 'required|string|max:13',
            'razon_social'      => 'required|string|max:190',
            'nombre_comercial'  => 'nullable|string|max:190',
            'email'             => 'required|email|max:190',
            'regimen_fiscal'    => 'required|string|max:10',      // WARETEK: "regimen"
            'grupo'             => 'nullable|string|max:50',

            // Dirección
            'dir_cp'            => 'required|string|max:10',
            'dir_direccion'     => 'nullable|string|max:240',
            'dir_ciudad'        => 'nullable|string|max:120',
            'dir_estado'        => 'nullable|string|max:120',

            // Series (como JSON opcional o campos simples)
            'series_json'       => 'nullable|string',             // si prefieres pegar JSON
            'serie_ingreso'     => 'nullable|string|max:10',
            'folio_ingreso'     => 'nullable|integer|min:1',
            'serie_egreso'      => 'nullable|string|max:10',
            'folio_egreso'      => 'nullable|integer|min:1',

            // Opcional PRO: sincronizar al PAC
            'sync_pac'          => 'sometimes|boolean',

            // PRO: archivos CSD/FIEL (si marca sync_pac, exigimos certificados)
            'csd_cer'           => $pro ? 'nullable|file|mimes:cer' : 'nullable|file|mimes:cer',
            'csd_key'           => $pro ? 'nullable|file|mimes:key' : 'nullable|file|mimes:key',
            'csd_password'      => 'nullable|string|max:100',

            'fiel_cer'          => $pro ? 'nullable|file|mimes:cer' : 'nullable|file|mimes:cer',
            'fiel_key'          => $pro ? 'nullable|file|mimes:key' : 'nullable|file|mimes:key',
            'fiel_password'     => 'nullable|string|max:100',
        ];
    }
}
