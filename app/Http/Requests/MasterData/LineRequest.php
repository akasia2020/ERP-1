<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class LineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('line');

        return [
            'code' => ['required', 'string', 'max:20', "unique:production_lines,code,{$id},id,deleted_at,NULL"],
            'name' => ['required', 'string', 'max:100'],
            'pic' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:Aktif,Tidak Aktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode line wajib diisi',
            'code.unique' => 'Kode line sudah digunakan',
            'name.required' => 'Nama line wajib diisi',
        ];
    }
}