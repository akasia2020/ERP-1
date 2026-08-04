<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormulaDetail extends Model
{
    protected $fillable = [
        'formula_id',
        'material_id',
        'quantity'
    ];

    public function formula(): BelongsTo
    {
        return $this->belongsTo(ProductFormula::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getRequiredQuantity(int $productionQty): float
    {
        return $this->quantity * $productionQty;
    }
}