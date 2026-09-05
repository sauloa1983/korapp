<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SernaSheetPrice extends Model
{
    protected $fillable = [
        'format',
        'width_cm',
        'height_cm',
        'thickness_mm',
        'finish',
        'price',
        'price_source',
        'year',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'thickness_mm' => 'decimal:2',
            'price' => 'decimal:2',
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
        return $query->orderBy('sort_order')->orderBy('format')->orderBy('thickness_mm');
    }

    public function label(): string
    {
        return sprintf(
            '%s · %s mm · %s',
            $this->format,
            rtrim(rtrim(number_format((float) $this->thickness_mm, 2, '.', ''), '0'), '.'),
            str_replace('_', ' ', $this->finish),
        );
    }
}
