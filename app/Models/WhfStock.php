<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhfStock extends Model
{
    protected $fillable = [
        'product_id',
        'stock_initial',
        'stock_current',
        'total_in',
        'total_out',
        'box_count'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateBox(int $packagingQty): int
    {
        if ($packagingQty <= 0) return 0;
        return floor($this->stock_current / $packagingQty);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->whereHas('product', function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%");
        });
    }
}