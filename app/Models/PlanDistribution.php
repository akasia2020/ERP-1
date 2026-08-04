<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanDistribution extends Model
{
    protected $fillable = [
        'plan_id',
        'line_id',
        'quantity',
        'distribution_date'
    ];

    protected $casts = [
        'distribution_date' => 'date'
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function scopeByPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeByLine($query, $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('distribution_date', [$startDate, $endDate]);
    }
}