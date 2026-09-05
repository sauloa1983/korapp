<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Concerns\HasDocumentTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class PurchaseReturn extends Model
{
    use HasDocumentTotals;

    protected $fillable = [
        'code',
        'purchase_id',
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
        static::creating(function (PurchaseReturn $return): void {
            if (blank($return->code)) {
                $return->code = static::generateCode();
            }

            if (blank($return->warehouse_id)) {
                $return->warehouse_id = $return->purchase?->warehouse_id ?? Warehouse::defaultId();
            }
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
        return $this->hasMany(PurchaseReturnItem::class);
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
            $code = sprintf('DC-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Aplica la devolución al proveedor: da salida del inventario los items
     * devueltos y marca la compra como Devuelta si se devolvió todo.
     */
    public function apply(?int $userId = null): void
    {
        if ($this->stockMovements()->exists()) {
            return;
        }

        DB::transaction(function () use ($userId): void {
            foreach ($this->items()->with('item')->get() as $line) {
                $line->item->registerMovement(
                    type: StockMovementType::Salida,
                    quantity: $line->quantity,
                    userId: $userId ?? $this->user_id,
                    source: $this,
                    reference: "Devolución compra {$this->code}",
                    unitCost: $line->unit_cost,
                    warehouseId: $this->warehouse_id,
                );
            }

            $this->loadMissing(['items', 'purchase']);
            $subtotal = $this->items->sum(fn (PurchaseReturnItem $i): float => (float) $i->quantity * (float) $i->unit_cost);
            $rate = $this->purchase?->iva_rate !== null ? (float) $this->purchase->iva_rate : null;
            $this->applyTaxFromSubtotal($subtotal, $rate);

            $this->syncPurchaseStatus();
        });
    }

    protected function syncPurchaseStatus(): void
    {
        $purchase = $this->purchase;

        if (! $purchase) {
            return;
        }

        $received = $purchase->items()->sum('quantity');
        $returned = PurchaseReturnItem::query()
            ->whereIn('purchase_return_id', $purchase->returns()->pluck('id'))
            ->sum('quantity');

        if ((float) $returned >= (float) $received && $received > 0) {
            $purchase->forceFill(['status' => PurchaseStatus::Devuelta])->save();
        }
    }
}
