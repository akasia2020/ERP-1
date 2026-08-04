<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'LIKE', "%{$keyword}%")
              ->orWhere('description', 'LIKE', "%{$keyword}%");
        });
    }
}