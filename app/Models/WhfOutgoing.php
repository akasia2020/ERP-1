<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhfOutgoing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'outgoing_number',
        'delivery_number',
        'product_id',
        'customer_id',
        'quantity',
        'outgoing_date',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'outgoing_date' => 'date'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateOutgoingNumber(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->outgoing_number, -3)) + 1 : 1;
        return 'OUT-' . date('Ymd') . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('outgoing_date', [$startDate, $endDate]);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('outgoing_number', 'LIKE', "%{$keyword}%")
              ->orWhere('delivery_number', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        })->orWhereHas('product', function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%");
        })->orWhereHas('customer', function ($q) use ($keyword) {
            $q->where('name', 'LIKE', "%{$keyword}%");
        });
    }
}