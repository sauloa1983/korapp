<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'item_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Mantiene el total de la compra sincronizado con sus líneas.
        static::saved(fn (PurchaseItem $item) => $item->purchase?->recalculateTotal());
        static::deleted(fn (PurchaseItem $item) => $item->purchase?->recalculateTotal());
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function subtotal(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->quantity * (float) $this->unit_cost);
    }
}
