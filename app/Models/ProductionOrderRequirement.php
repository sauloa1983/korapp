<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderRequirement extends Model
{
    protected $fillable = [
        'production_order_id',
        'item_id',
        'quantity_required',
        'quantity_consumed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
            'quantity_consumed' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function quantityPending(): Attribute
    {
        return Attribute::get(
            fn (): float => max(0, (float) $this->quantity_required - (float) $this->quantity_consumed),
        );
    }

    protected function isFullyConsumed(): Attribute
    {
        return Attribute::get(
            fn (): bool => (float) $this->quantity_consumed >= (float) $this->quantity_required,
        );
    }
}
