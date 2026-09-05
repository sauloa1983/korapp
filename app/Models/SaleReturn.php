<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Concerns\HasDocumentTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class SaleReturn extends Model
{
    use HasDocumentTotals;

    protected $fillable = [
        'code',
        'sale_id',
        'warehouse_id',
        'user_id',
        'subtotal',
        'iva_rate',
        'iva_amount',
        'total',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleReturn $return): void {
            if (blank($return->code)) {
                $return->code = static::generateCode();
            }

            if (blank($return->warehouse_id)) {
                $return->warehouse_id = $return->sale?->warehouse_id ?? Warehouse::defaultId();
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public static function generateCode(): string
    {
        $year = now()->year;
        $sequence = static::whereYear('created_at', $year)->count() + 1;

        do {
            $code = sprintf('NC-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Aplica la nota crédito: reingresa al inventario los items devueltos
     * (entrada) y actualiza el estado de la venta si quedó totalmente devuelta.
     */
    public function apply(?int $userId = null): void
    {
        if ($this->stockMovements()->exists()) {
            return;
        }

        DB::transaction(function () use ($userId): void {
            foreach ($this->items()->with('item')->get() as $line) {
                $line->item->registerMovement(
                    type: StockMovementType::Entrada,
                    quantity: $line->quantity,
                    userId: $userId ?? $this->user_id,
                    source: $this,
                    reference: "Devolución {$this->code}",
                    unitCost: $line->item->cost,
                    warehouseId: $this->warehouse_id,
                );
            }

            $this->loadMissing(['items', 'sale']);
            $subtotal = $this->items->sum(fn (SaleReturnItem $i): float => (float) $i->quantity * (float) $i->unit_price);
            $rate = $this->sale?->iva_rate !== null ? (float) $this->sale->iva_rate : null;
            $this->applyTaxFromSubtotal($subtotal, $rate);

            $this->syncSaleStatus();
        });
    }

    /** Marca la venta como Devuelta cuando se devolvió todo lo vendido. */
    protected function syncSaleStatus(): void
    {
        $sale = $this->sale;

        if (! $sale) {
            return;
        }

        $sold = $sale->items()->sum('quantity');
        $returned = SaleReturnItem::query()
            ->whereIn('sale_return_id', $sale->returns()->pluck('id'))
            ->sum('quantity');

        if ((float) $returned >= (float) $sold && $sold > 0) {
            $sale->forceFill(['status' => SaleStatus::Devuelta])->save();
        }
    }
}
