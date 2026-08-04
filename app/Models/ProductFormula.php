<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductFormula extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'status',
        'created_by'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(FormulaDetail::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function getTotalMaterials(): int
    {
        return $this->details()->count();
    }
}