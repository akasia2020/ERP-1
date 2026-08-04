<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'contact',
        'location',
        'status',
        'created_by'
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
              ->orWhere('category', 'LIKE', "%{$keyword}%")
              ->orWhere('contact', 'LIKE', "%{$keyword}%")
              ->orWhere('location', 'LIKE', "%{$keyword}%");
        });
    }

    public static function generateCode(): string
    {
        $last = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $last ? intval(substr($last->code, -3)) + 1 : 1;
        return 'SUP-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}