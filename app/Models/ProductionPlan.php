<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'plan_number',
        'product_id',
        'quantity',
        'remaining_qty',
        'total_materials',
        'status',
        'priority',
        'plan_date',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'plan_date' => 'date'
    ];

    // Status Constants
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PROSES = 'Proses';
    public const STATUS_SELESAI = 'Selesai';
    public const STATUS_BATAL = 'Batal';

    // Priority Constants
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_LOW = 'Low';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(PlanDistribution::class);
    }

    public function finishGoods(): HasMany
    {
        return $this->hasMany(FinishGood::class);
    }

    public function stockCards(): HasMany
    {
        return $this->hasMany(StockCard::class);
    }

    public static function generatePlanNumber(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->plan_number, -3)) + 1 : 1;
        return 'PLN-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isProses(): bool
    {
        return $this->status === self::STATUS_PROSES;
    }

    public function isSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    public function isBatal(): bool
    {
        return $this->status === self::STATUS_BATAL;
    }

    public function getProgressPercentage(): float
    {
        if ($this->quantity <= 0) return 0;
        return round((($this->quantity - $this->remaining_qty) / $this->quantity) * 100, 2);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('plan_number', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        })->orWhereHas('product', function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%");
        });
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('plan_date', [$startDate, $endDate]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_PROSES]);
    }
}