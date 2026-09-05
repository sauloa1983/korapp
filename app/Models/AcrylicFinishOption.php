<?php

namespace App\Models;

use App\Enums\AcrylicFinishPricingMode;
use App\Enums\AcrylicFinishType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcrylicFinishOption extends Model
{
    protected $fillable = [
        'name',
        'type',
        'pricing_mode',
        'unit_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AcrylicFinishType::class,
            'pricing_mode' => AcrylicFinishPricingMode::class,
            'unit_price' => 'decimal:2',
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
