<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AcrylicPricingSetting extends Model
{
    protected $fillable = [
        'labor_fixed_cost',
        'labor_per_m2',
        'assembly_percent_of_lettering',
        'logo_price_per_m2',
        'logo_fixed_cost',
        'margin_percent',
    ];

    protected function casts(): array
    {
        return [
            'labor_fixed_cost' => 'decimal:2',
            'labor_per_m2' => 'decimal:2',
            'assembly_percent_of_lettering' => 'decimal:2',
            'logo_price_per_m2' => 'decimal:2',
            'logo_fixed_cost' => 'decimal:2',
            'margin_percent' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return Cache::rememberForever('acrylic_pricing_settings.current', function (): self {
            $settings = static::query()->first();

            if ($settings) {
                return $settings;
            }

            return static::query()->create([
                'labor_fixed_cost' => 25000,
                'labor_per_m2' => 15000,
                'assembly_percent_of_lettering' => 15,
                'logo_price_per_m2' => 180000,
                'logo_fixed_cost' => 0,
                'margin_percent' => 35,
            ]);
        });
    }

    public static function flushCache(): void
    {
        Cache::forget('acrylic_pricing_settings.current');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
