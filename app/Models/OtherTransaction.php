<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'transaction_date',
        'material_id',
        'material_name',
        'quantity_in',
        'quantity_out',
        'need_type',
        'note',
        'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'date'
    ];

    // Need Type Constants
    public const NEED_BBK = 'BBK';
    public const NEED_BBM = 'BBM';
    public const NEED_BBR = 'BBR';

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateTransactionNumber(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->transaction_number, -3)) + 1 : 1;
        return 'OTH-' . date('Ymd') . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('transaction_number', 'LIKE', "%{$keyword}%")
              ->orWhere('material_name', 'LIKE', "%{$keyword}%")
              ->orWhere('note', 'LIKE', "%{$keyword}%")
              ->orWhere('need_type', 'LIKE', "%{$keyword}%");
        });
    }

    public function scopeByMaterial($query, $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    public function scopeByNeedType($query, $needType)
    {
        return $query->where('need_type', $needType);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}