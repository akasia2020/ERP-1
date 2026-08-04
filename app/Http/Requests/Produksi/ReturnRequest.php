<?php

namespace App\Http\Requests\Produksi;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('return');

        return [
            'delivery_number' => ['required', 'string', 'max:50'],
            'product_id' => ['required', 'exists:products,id'],
            'store_name' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_number.required' => 'No Surat Jalan wajib diisi',
            'product_id.required' => 'Produk wajib dipilih',
            'product_id.exists' => 'Produk tidak valid',
            'store_name.required' => 'Nama toko wajib diisi',
            'quantity.required' => 'Qty wajib diisi',
            'quantity.min' => 'Qty minimal 1',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = $this->input('product_id');
            $quantity = $this->input('quantity');

            if ($productId && $quantity) {
                $product = \App\Models\Product::find($productId);
                if ($product && $product->stock_current < $quantity) {
                    $validator->errors()->add('quantity', 'Stock tidak mencukupi (tersedia: ' . $product->stock_current . ' pcs)');
                }
            }
        });
    }
}