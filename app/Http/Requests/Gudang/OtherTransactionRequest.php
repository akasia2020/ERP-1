<?php

namespace App\Http\Requests\Gudang;

use Illuminate\Foundation\Http\FormRequest;

class OtherTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('other_transaction');

        return [
            'material_id' => ['required', 'exists:materials,id'],
            'transaction_date' => ['nullable', 'date'],
            'quantity_in' => ['nullable', 'integer', 'min:0'],
            'quantity_out' => ['nullable', 'integer', 'min:0'],
            'need_type' => ['nullable', 'string', 'in:BBK,BBM,BBR'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'material_id.required' => 'Material wajib dipilih',
            'material_id.exists' => 'Material tidak valid',
            'need_type.in' => 'Jenis kebutuhan tidak valid (BBK, BBM, BBR)',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $quantityIn = $this->input('quantity_in', 0);
            $quantityOut = $this->input('quantity_out', 0);

            if ($quantityIn == 0 && $quantityOut == 0) {
                $validator->errors()->add('quantity_in', 'Quantity masuk atau keluar harus diisi');
            }

            if ($quantityIn > 0 && $quantityOut > 0) {
                $validator->errors()->add('quantity_in', 'Hanya boleh mengisi salah satu: masuk atau keluar');
            }
        });
    }
}