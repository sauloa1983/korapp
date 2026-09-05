<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcrylicMaterial extends Model
{
    protected $fillable = [
        'name',
        'thickness_mm',
        'price_per_m2',
        'waste_percent',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'thickness_mm' => 'decimal:2',
            'price_per_m2' => 'decimal:2',
            'waste_percent' => 'decimal:2',
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
        return $query->orderBy('sort_order')->orderBy('thickness_mm')->orderBy('name');
    }

    public function label(): string
    {
        return sprintf('%s · %s mm', $this->name, rtrim(rtrim(number_format((float) $this->thickness_mm, 2, ',', '.'), '0'), ','));
    }
}
