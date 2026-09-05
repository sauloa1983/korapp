<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SernaProcessRate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'thickness_mm_min',
        'thickness_mm_max',
        'price_per_cm2',
        'min_charge',
        'year',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'thickness_mm_min' => 'decimal:2',
            'thickness_mm_max' => 'decimal:2',
            'price_per_cm2' => 'decimal:4',
            'min_charge' => 'decimal:2',
            'year' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** @param  Builder<self>  $query */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function appliesToThickness(?float $thicknessMm): bool
    {
        if ($this->thickness_mm_min === null && $this->thickness_mm_max === null) {
            return true;
        }

        if ($thicknessMm === null) {
            return false;
        }

        $min = $this->thickness_mm_min !== null ? (float) $this->thickness_mm_min : null;
        $max = $this->thickness_mm_max !== null ? (float) $this->thickness_mm_max : null;

        if ($min !== null && $thicknessMm + 0.0001 < $min) {
            return false;
        }

        if ($max !== null && $thicknessMm - 0.0001 > $max) {
            return false;
        }

        return true;
    }
}
