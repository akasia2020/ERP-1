<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('supplier');

        return [
            'code' => ['required', 'string', 'max:20', "unique:suppliers,code,{$id},id,deleted_at,NULL"],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:Aktif,Tidak Aktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode supplier wajib diisi',
            'code.unique' => 'Kode supplier sudah digunakan',
            'name.required' => 'Nama supplier wajib diisi',
        ];
    }
}