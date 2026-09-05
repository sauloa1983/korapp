<?php

namespace App\Models;

use App\Enums\AcrylicLetteringPricingMode;
use App\Enums\AcrylicLetteringType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcrylicLetteringOption extends Model
{
    protected $fillable = [
        'name',
        'type',
        'pricing_mode',
        'price_per_m2',
        'cut_price_per_meter',
        'fixed_price',
        'price_per_letter',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AcrylicLetteringType::class,
            'pricing_mode' => AcrylicLetteringPricingMode::class,
            'price_per_m2' => 'decimal:2',
            'cut_price_per_meter' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'price_per_letter' => 'decimal:2',
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

    public function requiresCoverageInputs(): bool
    {
        return in_array($this->pricing_mode, [
            AcrylicLetteringPricingMode::CoverageArea,
            AcrylicLetteringPricingMode::CoverageAreaPlusCut,
        ], true);
    }

    public function requiresCutInputs(): bool
    {
        return $this->pricing_mode === AcrylicLetteringPricingMode::CoverageAreaPlusCut;
    }
}
