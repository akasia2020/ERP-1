<?php

namespace App\Http\Requests\Gudang;

use Illuminate\Foundation\Http\FormRequest;

class ProductionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'string', 'in:High,Medium,Low'],
            'plan_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih',
            'product_id.exists' => 'Produk tidak valid',
            'quantity.required' => 'Quantity wajib diisi',
            'quantity.min' => 'Quantity minimal 1',
            'plan_date.required' => 'Tanggal planning wajib diisi',
        ];
    }
}