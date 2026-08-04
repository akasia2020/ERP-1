<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinishGood extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'plan_id',
        'product_id',
        'line_id',
        'delivery_number',
        'finish_number',
        'pic',
        'quantity',
        'qc_status',
        'finish_date',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'finish_date' => 'date'
    ];

    // QC Status Constants
    public const QC_PASSED = 'Passed';
    public const QC_FAILED = 'Failed';

    // Status Constants
    public const STATUS_SELESAI = 'Selesai';

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function whfIncoming(): HasOne
    {
        return $this->hasOne(WhfIncoming::class);
    }

    public static function generateFinishNumber(string $prefix = null): string
    {
        $prefix = $prefix ?? Setting::getFGPrefix();
        $lastNo = Setting::getFGLastNo();
        
        $exists = true;
        $newNo = intval($lastNo);
        while ($exists) {
            $number = $prefix . str_pad($newNo, 3, '0', STR_PAD_LEFT);
            $exists = self::where('finish_number', $number)->exists();
            if ($exists) {
                $newNo++;
            }
        }

        Setting::setFGLastNo(str_pad($newNo, 3, '0', STR_PAD_LEFT));
        return $prefix . str_pad($newNo, 3, '0', STR_PAD_LEFT);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('finish_number', 'LIKE', "%{$keyword}%")
              ->orWhere('delivery_number', 'LIKE', "%{$keyword}%")
              ->orWhere('pic', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        })->orWhereHas('product', function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%");
        });
    }

    public function scopeByPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByLine($query, $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('finish_date', [$startDate, $endDate]);
    }

    public function scopeByQCStatus($query, $status)
    {
        return $query->where('qc_status', $status);
    }
}