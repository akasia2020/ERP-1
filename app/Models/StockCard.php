<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCard extends Model
{
    protected $fillable = [
        'transaction_date',
        'transaction_number',
        'reference_number',
        'product_id',
        'product_code',
        'product_name',
        'stock_before',
        'quantity_in',
        'quantity_out',
        'total_in',
        'total_out',
        'stock_after',
        'total_materials',
        'tolerance',
        'transaction_type',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'date'
    ];

    // Transaction Types
    public const TYPE_MATERIAL_INCOMING = 'material_incoming';
    public const TYPE_PRODUCTION_PLAN = 'production_plan';
    public const TYPE_FINISH_GOOD = 'finish_good';
    public const TYPE_WHF_OUTGOING = 'whf_outgoing';
    public const TYPE_RETURN = 'return';
    public const TYPE_OTHER = 'other';

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
            $q->where('product_code', 'LIKE', "%{$keyword}%")
              ->orWhere('product_name', 'LIKE', "%{$keyword}%")
              ->orWhere('reference_number', 'LIKE', "%{$keyword}%")
              ->orWhere('transaction_number', 'LIKE', "%{$keyword}%");
        });
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByProductCode($query, $productCode)
    {
        return $query->where('product_code', $productCode);
    }

    public function scopeByTransactionType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}