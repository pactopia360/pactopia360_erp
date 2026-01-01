<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StripePriceStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'price_key'        => ['required','string','max:60','regex:/^[a-z0-9_]+$/i','unique:mysql_admin.stripe_price_list,price_key'],
            'name'             => ['nullable','string','max:120'],
            'plan'             => ['required','string','max:30'],
            'billing_cycle'    => ['required','in:mensual,anual'],
            'stripe_price_id'  => ['required','string','max:120','regex:/^price_/','unique:mysql_admin.stripe_price_list,stripe_price_id'],
            'currency'         => ['required','string','max:10'],
            'display_amount'   => ['nullable','numeric','min:0'],
            'is_active'        => ['nullable','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_key.regex' => 'La clave debe usar solo letras, números y guion bajo (ej: pro_mensual).',
            'stripe_price_id.regex' => 'El Stripe Price ID debe iniciar con price_.',
        ];
    }
}
