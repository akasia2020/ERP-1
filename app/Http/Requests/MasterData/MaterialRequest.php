<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('material');

        return [
            'code' => ['required', 'string', 'max:20', "unique:materials,code,{$id},id,deleted_at,NULL"],
            'name' => ['required', 'string', 'max:100'],
            'specification' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:20'],
            'stock_initial' => ['nullable', 'integer', 'min:0'],
            'stock_minimum' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode material wajib diisi',
            'code.unique' => 'Kode material sudah digunakan',
            'name.required' => 'Nama material wajib diisi',
            'unit.required' => 'Satuan material wajib diisi',
            'supplier_id.exists' => 'Supplier tidak valid',
        ];
    }
}