<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Concerns\HasDocumentTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Purchase extends Model
{
    use HasDocumentTotals, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'status', 'subtotal', 'iva_amount', 'total', 'supplier_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'code',
        'supplier_id',
        'user_id',
        'warehouse_id',
        'status',
        'subtotal',
        'iva_rate',
        'iva_amount',
        'total',
        'notes',
        'ordered_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'subtotal' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'ordered_at' => 'date',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase): void {
            if (blank($purchase->code)) {
                $purchase->code = static::generateCode();
            }

            if (blank($purchase->ordered_at)) {
                $purchase->ordered_at = now();
            }
        });
    }

    // -----------------------------------------------------------------
    // Relaciones
    // -----------------------------------------------------------------

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    // -----------------------------------------------------------------
    // Código único OC-AAAA-#####
    // -----------------------------------------------------------------

    public static function generateCode(): string
    {
        $year = now()->year;
        $sequence = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        do {
            $code = sprintf('OC-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    // -----------------------------------------------------------------
    // Lógica de negocio (Línea 1: entrada de inventario)
    // -----------------------------------------------------------------

    public function recalculateTotal(): void
    {
        $this->loadMissing('items');

        $subtotal = $this->items->sum(
            fn (PurchaseItem $item): float => (float) $item->quantity * (float) $item->unit_cost
        );

        $this->applyTaxFromSubtotal($subtotal);
    }

    /**
     * Recibe la compra: ingresa cada item al inventario como entrada,
     * actualiza el costo del item y el costo del proveedor, y cierra la orden.
     */
    public function receive(?int $userId = null): void
    {
        if ($this->status === PurchaseStatus::Recibida) {
            return;
        }

        DB::transaction(function () use ($userId): void {
            foreach ($this->items()->with('item')->get() as $line) {
                $line->item->registerMovement(
                    type: StockMovementType::Entrada,
                    quantity: $line->quantity,
                    userId: $userId,
                    source: $this,
                    reference: "Compra {$this->code}",
                    unitCost: $line->unit_cost,
                    warehouseId: $this->warehouse_id,
                );

                // Actualiza el último costo del item y del proveedor.
                $line->item->forceFill(['cost' => $line->unit_cost])->save();

                $this->supplier->items()->syncWithoutDetaching([
                    $line->item_id => ['last_purchase_cost' => $line->unit_cost],
                ]);
            }

            $this->forceFill([
                'status' => PurchaseStatus::Recibida,
                'received_at' => now(),
            ])->save();
        });

        $this->load('items');
        $this->recalculateTotal();
    }

    public function cancel(): void
    {
        $this->forceFill(['status' => PurchaseStatus::Cancelada])->save();
    }
}
