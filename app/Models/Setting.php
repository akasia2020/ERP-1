<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value'
    ];

    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, $value): self
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getAlertColors(): array
    {
        return [
            'danger' => self::getValue('alert_danger_color', '#EF4444'),
            'warning' => self::getValue('alert_warning_color', '#F59E0B'),
            'info' => self::getValue('alert_info_color', '#3B82F6'),
        ];
    }

    public static function getGlobalStockMin(): int
    {
        return (int) self::getValue('global_stock_min', 100);
    }

    public static function getFGPrefix(): string
    {
        return self::getValue('fg_prefix', 'FG-2026-');
    }

    public static function getFGLastNo(): string
    {
        return self::getValue('fg_last_no', '001');
    }

    public static function setFGLastNo(string $number): void
    {
        self::setValue('fg_last_no', $number);
    }

    public static function getIpWhitelist(): array
    {
        $ips = self::getValue('ip_whitelist', '');
        return $ips ? explode(',', $ips) : [];
    }

    public static function setIpWhitelist(array $ips): void
    {
        self::setValue('ip_whitelist', implode(',', $ips));
    }

    public static function getUnits(): array
    {
        return Unit::pluck('name')->toArray();
    }

    public static function getCategories(): array
    {
        return Category::pluck('name')->toArray();
    }
}