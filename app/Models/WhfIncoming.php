<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhfIncoming extends Model
{
    protected $table = 'whf_incoming';

    protected $fillable = [
        'finish_good_id',
        'product_id',
        'quantity',
        'incoming_date',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'incoming_date' => 'date'
    ];

    public function finishGood(): BelongsTo
    {
        return $this->belongsTo(FinishGood::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByFinishGood($query, $finishGoodId)
    {
        return $query->where('finish_good_id', $finishGoodId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('incoming_date', [$startDate, $endDate]);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->whereHas('product', function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%");
        })->orWhere('notes', 'LIKE', "%{$keyword}%");
    }
}