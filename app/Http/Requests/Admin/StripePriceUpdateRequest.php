<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StripePriceUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = (int) ($this->route('id') ?? 0);

        return [
            'price_key'        => ['required','string','max:60','regex:/^[a-z0-9_]+$/i', Rule::unique('mysql_admin.stripe_price_list','price_key')->ignore($id)],
            'name'             => ['nullable','string','max:120'],
            'plan'             => ['required','string','max:30'],
            'billing_cycle'    => ['required','in:mensual,anual'],
            'stripe_price_id'  => ['required','string','max:120','regex:/^price_/', Rule::unique('mysql_admin.stripe_price_list','stripe_price_id')->ignore($id)],
            'currency'         => ['required','string','max:10'],
            'display_amount'   => ['nullable','numeric','min:0'],
            'is_active'        => ['nullable','boolean'],
        ];
    }
}
