<?php

namespace App\Http\Requests\Whf;

use Illuminate\Foundation\Http\FormRequest;

class ProductOutgoingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product_outgoing');

        return [
            'delivery_number' => ['required', 'string', 'max:50'],
            'product_id' => ['required', 'exists:products,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'outgoing_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_number.required' => 'No Surat Jalan wajib diisi',
            'product_id.required' => 'Produk wajib dipilih',
            'product_id.exists' => 'Produk tidak valid',
            'customer_id.exists' => 'Customer tidak valid',
            'quantity.required' => 'Qty wajib diisi',
            'quantity.min' => 'Qty minimal 1',
            'outgoing_date.required' => 'Tanggal keluar wajib diisi',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = $this->input('product_id');
            $quantity = $this->input('quantity');

            if ($productId && $quantity) {
                $whfStock = \App\Models\WhfStock::where('product_id', $productId)->first();
                if ($whfStock && $whfStock->stock_current < $quantity) {
                    $validator->errors()->add('quantity', 
                        'Stock WHF tidak mencukupi (tersedia: ' . $whfStock->stock_current . ' pcs)'
                    );
                }
            }
        });
    }
}