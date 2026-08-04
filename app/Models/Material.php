<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'specification',
        'unit',
        'stock_initial',
        'stock_current',
        'stock_minimum',
        'supplier_id',
        'created_by'
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function formulaDetails(): HasMany
    {
        return $this->hasMany(FormulaDetail::class);
    }

    public function materialIncoming(): HasMany
    {
        return $this->hasMany(MaterialIncoming::class);
    }

    public function otherTransactions(): HasMany
    {
        return $this->hasMany(OtherTransaction::class);
    }

    public function getStockStatus(): array
    {
        if ($this->stock_current <= 0) {
            return ['status' => 'Habis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($this->stock_current < ($this->stock_minimum * 0.3)) {
            return ['status' => 'Kritis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($this->stock_current < $this->stock_minimum) {
            return ['status' => 'Menipis', 'class' => 'row-stock-warning', 'badge' => 'badge-warning'];
        }
        return ['status' => 'Aman', 'class' => '', 'badge' => 'badge-success'];
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('code', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%")
              ->orWhere('specification', 'LIKE', "%{$keyword}%")
              ->orWhere('unit', 'LIKE', "%{$keyword}%");
        });
    }

    public static function generateCode(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->code, -3)) + 1 : 1;
        return 'MT-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}