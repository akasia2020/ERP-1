<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit',
        'stock_current',
        'packaging',
        'packaging_qty',
        'created_by'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function formula(): HasOne
    {
        return $this->hasOne(ProductFormula::class);
    }

    public function finishGoods(): HasMany
    {
        return $this->hasMany(FinishGood::class);
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class);
    }

    public function whfStock(): HasOne
    {
        return $this->hasOne(WhfStock::class);
    }

    public function stockCards(): HasMany
    {
        return $this->hasMany(StockCard::class);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('sku', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%")
              ->orWhere('category', 'LIKE', "%{$keyword}%");
        });
    }

    public static function generateSku(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->sku, -2)) + 1 : 1;
        return 'PRD-LA' . str_pad($number, 2, '0', STR_PAD_LEFT);
    }

    public function getStockStatus(): array
    {
        $min = Setting::getValue('global_stock_min', 100);
        if ($this->stock_current <= 0) {
            return ['status' => 'Habis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($this->stock_current < ($min * 0.3)) {
            return ['status' => 'Kritis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($this->stock_current < $min) {
            return ['status' => 'Menipis', 'class' => 'row-stock-warning', 'badge' => 'badge-warning'];
        }
        return ['status' => 'Aman', 'class' => '', 'badge' => 'badge-success'];
    }
}