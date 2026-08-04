<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'product_id' => ['nullable', 'exists:products,id'],
            'material_id' => ['nullable', 'exists:materials,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'line_id' => ['nullable', 'exists:production_lines,id'],
            'status' => ['nullable', 'string'],
            'transaction_type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:excel,pdf'],
            'type' => ['nullable', 'in:stock,production,warehouse,whf'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal',
        ];
    }
}