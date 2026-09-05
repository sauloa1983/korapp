<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SernaCatalogProduct extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category',
        'specs',
        'unit_price',
        'pricing_mode',
        'year',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'unit_price' => 'decimal:2',
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

    public function label(): string
    {
        return "{$this->name} · {$this->sku}";
    }
}
