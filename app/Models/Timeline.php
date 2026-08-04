<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timeline extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'time'
    ];

    protected $casts = [
        'time' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Types
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_DANGER = 'danger';
}