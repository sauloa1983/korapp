<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'item_id',
        'description',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (SaleItem $item) => $item->sale?->recalculateTotal());
        static::deleted(fn (SaleItem $item) => $item->sale?->recalculateTotal());
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Texto comercial de la línea (cotización) o nombre de catálogo. */
    public function displayDescription(): string
    {
        if (filled($this->description)) {
            return (string) $this->description;
        }

        return (string) ($this->item?->name ?? '—');
    }

    /**
     * Código visible: SKU de catálogo, o material/tipo Serna (más claro que SERNA-SERVICIO).
     */
    public function displaySku(): string
    {
        $sku = trim((string) ($this->item?->sku ?? ''));

        if ($sku !== '' && ! str_starts_with($sku, 'SERNA-')) {
            return $sku;
        }

        if (filled($this->description) && preg_match('/Material:\s*([^·\n]+)/u', (string) $this->description, $match)) {
            return mb_strtoupper(trim($match[1]), 'UTF-8');
        }

        if ($sku !== '' && str_starts_with($sku, 'SERNA-')) {
            if (str_contains(mb_strtolower((string) $this->description, 'UTF-8'), 'transporte')
                || str_contains(mb_strtolower((string) $this->description, 'UTF-8'), 'instalaci')) {
                return 'TRANSPORTE';
            }

            return 'PIEZA';
        }

        if (filled($this->description)) {
            $first = trim(explode('·', (string) $this->description, 2)[0]);

            if ($first !== '') {
                return mb_strtoupper($first, 'UTF-8');
            }
        }

        return $sku !== '' ? $sku : '—';
    }

    protected function subtotal(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->quantity * (float) $this->unit_price);
    }
}
