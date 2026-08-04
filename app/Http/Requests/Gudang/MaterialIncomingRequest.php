<?php

namespace App\Http\Requests\Gudang;

use Illuminate\Foundation\Http\FormRequest;

class MaterialIncomingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('material_incoming');

        return [
            'material_id' => ['required', 'exists:materials,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'po_number' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'incoming_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'material_id.required' => 'Material wajib dipilih',
            'material_id.exists' => 'Material tidak valid',
            'quantity.required' => 'Quantity wajib diisi',
            'quantity.min' => 'Quantity minimal 1',
            'supplier_id.exists' => 'Supplier tidak valid',
        ];
    }
}