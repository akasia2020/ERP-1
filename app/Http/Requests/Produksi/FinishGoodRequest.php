<?php

namespace App\Http\Requests\Produksi;

use Illuminate\Foundation\Http\FormRequest;

class FinishGoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('finish_good');

        return [
            'plan_id' => ['required', 'exists:production_plans,id'],
            'delivery_number' => ['required', 'string', 'max:50'],
            'line_id' => ['nullable', 'exists:production_lines,id'],
            'pic' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'qc_status' => ['nullable', 'string', 'in:Passed,Failed'],
            'finish_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Planning wajib dipilih',
            'plan_id.exists' => 'Planning tidak valid',
            'delivery_number.required' => 'No Surat Jalan wajib diisi',
            'quantity.required' => 'Qty wajib diisi',
            'quantity.min' => 'Qty minimal 1',
            'finish_date.required' => 'Tanggal finish wajib diisi',
            'qc_status.in' => 'QC Status harus Passed atau Failed',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $planId = $this->input('plan_id');
            $quantity = $this->input('quantity');

            if ($planId && $quantity) {
                $plan = \App\Models\ProductionPlan::find($planId);
                if ($plan && $quantity > $plan->remaining_qty) {
                    $validator->errors()->add('quantity', 'Qty melebihi sisa planning (' . $plan->remaining_qty . ' pcs)');
                }
            }
        });
    }
}