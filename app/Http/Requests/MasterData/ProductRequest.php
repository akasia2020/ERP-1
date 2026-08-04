<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product');

        return [
            'sku' => ['required', 'string', 'max:20', "unique:products,sku,{$id},id,deleted_at,NULL"],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:20'],
            'packaging' => ['nullable', 'string', 'max:20'],
            'packaging_qty' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'Kode produk wajib diisi',
            'sku.unique' => 'Kode produk sudah digunakan',
            'name.required' => 'Nama produk wajib diisi',
        ];
    }
}