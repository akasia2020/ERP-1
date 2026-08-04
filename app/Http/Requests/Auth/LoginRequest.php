<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string', 'in:masterdata,gudang,produksi,whf']
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi',
            'password.required' => 'Password wajib diisi',
            'role.required' => 'Silakan pilih divisi',
            'role.in' => 'Divisi tidak valid'
        ];
    }
}