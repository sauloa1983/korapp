<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'quote_piece_id',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'meta',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'meta' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuoteItem $item): void {
            if (blank($item->quote_id) && filled($item->quote_piece_id)) {
                $item->quote_id = QuotePiece::query()
                    ->whereKey($item->quote_piece_id)
                    ->value('quote_id');
            }
        });

        static::saving(function (QuoteItem $item): void {
            if (blank($item->quote_id) && filled($item->quote_piece_id)) {
                $item->quote_id = QuotePiece::query()
                    ->whereKey($item->quote_piece_id)
                    ->value('quote_id');
            }

            $item->line_total = round((float) $item->quantity * (float) $item->unit_price, 2);
        });

        static::saved(function (QuoteItem $item): void {
            $item->quote?->recalculateTotal();
        });

        static::deleted(function (QuoteItem $item): void {
            $item->quote?->recalculateTotal();
        });
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(QuotePiece::class, 'quote_piece_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
