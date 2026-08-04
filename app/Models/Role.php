<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description'
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Role constants
    public const MASTER_DATA = 'masterdata';
    public const GUDANG = 'gudang';
    public const PRODUKSI = 'produksi';
    public const WHF = 'whf';

    public static function getRoleNames(): array
    {
        return [
            self::MASTER_DATA => 'Master Data',
            self::GUDANG => 'Divisi Gudang',
            self::PRODUKSI => 'Plan Produksi',
            self::WHF => 'Divisi WHF',
        ];
    }
}