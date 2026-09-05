<?php

namespace App\Models;

use App\Enums\AcrylicLightingPricingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcrylicLightingOption extends Model
{
    protected $fillable = [
        'name',
        'pricing_mode',
        'unit_price',
        'power_supply_cost',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => AcrylicLightingPricingMode::class,
            'unit_price' => 'decimal:2',
            'power_supply_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
