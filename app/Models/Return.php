<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Return extends Model
{
    use SoftDeletes;

    protected $table = 'returns';

    protected $fillable = [
        'delivery_number',
        'product_id',
        'store_name',
        'quantity',
        'return_date',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'return_date' => 'date'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('delivery_number', 'LIKE', "%{$keyword}%")
              ->orWhere('store_name', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        })->orWhereHas('product', function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%");
        });
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('return_date', [$startDate, $endDate]);
    }

    public function scopeByStore($query, $storeName)
    {
        return $query->where('store_name', 'LIKE', "%{$storeName}%");
    }
}