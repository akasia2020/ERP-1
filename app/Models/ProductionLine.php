<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionLine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'pic',
        'status',
        'created_by'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function planDistributions(): HasMany
    {
        return $this->hasMany(PlanDistribution::class);
    }

    public function finishGoods(): HasMany
    {
        return $this->hasMany(FinishGood::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'Aktif';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Aktif');
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('code', 'LIKE', "%{$keyword}%")
              ->orWhere('name', 'LIKE', "%{$keyword}%")
              ->orWhere('pic', 'LIKE', "%{$keyword}%");
        });
    }

    public static function generateCode(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->code, -1)) + 1 : 1;
        return 'LN-' . chr(64 + $number);
    }
}