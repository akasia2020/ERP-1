<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class FormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.material_id' => ['required', 'exists:materials,id'],
            'materials.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih',
            'product_id.exists' => 'Produk tidak valid',
            'materials.required' => 'Minimal 1 bahan baku wajib ditambahkan',
            'materials.*.material_id.required' => 'Material wajib dipilih',
            'materials.*.quantity.min' => 'Quantity minimal 0.01',
        ];
    }
}