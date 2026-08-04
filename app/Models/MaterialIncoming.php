<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIncoming extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'material_id',
        'quantity',
        'stock_before',
        'po_number',
        'supplier_id',
        'incoming_date',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'incoming_date' => 'date'
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateTransactionNumber(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->transaction_number, -3)) + 1 : 1;
        return 'BM-' . date('Ymd') . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('transaction_number', 'LIKE', "%{$keyword}%")
              ->orWhere('po_number', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        });
    }

    public function scopeByMaterial($query, $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('incoming_date', [$startDate, $endDate]);
    }
}