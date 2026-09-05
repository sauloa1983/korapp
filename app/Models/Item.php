<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Notifications\LowStockAlert;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sku', 'name', 'type', 'stock', 'min_stock', 'cost', 'price', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'item_category_id',
        'sku',
        'name',
        'type',
        'description',
        'unit_of_measure',
        'stock',
        'min_stock',
        'cost',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'stock' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // -----------------------------------------------------------------
    // Relaciones
    // -----------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['supplier_sku', 'last_purchase_cost', 'lead_time_days'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Existencias por bodega (multi-bodega). */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class)
            ->withPivot('stock')
            ->withTimestamps();
    }

    /** Existencia del item en una bodega específica. */
    public function stockInWarehouse(int $warehouseId): float
    {
        $pivot = $this->warehouses()->where('warehouse_id', $warehouseId)->first();

        return $pivot ? (float) $pivot->pivot->stock : 0.0;
    }

    /** Órdenes en las que este item es el producto a fabricar (Línea 2). */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /** Requerimientos en los que este item es consumido como insumo/materia prima. */
    public function requirements(): HasMany
    {
        return $this->hasMany(ProductionOrderRequirement::class);
    }

    /** Líneas de compra donde este item ha sido adquirido (Línea 1). */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** Líneas de venta donde este item ha sido vendido (Línea 1). */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeOfType(Builder $query, ItemType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeRawMaterials(Builder $query): Builder
    {
        return $query->whereIn('type', [ItemType::MateriaPrima->value, ItemType::Insumo->value]);
    }

    public function scopeFinishedProducts(Builder $query): Builder
    {
        return $query->where('type', ItemType::ProductoTerminado->value);
    }

    /** Productos terminados e insumos que se pueden fabricar en planta. */
    public function scopeProducible(Builder $query): Builder
    {
        return $query->whereIn('type', ItemType::producibleValues());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -----------------------------------------------------------------
    // Atributos derivados
    // -----------------------------------------------------------------

    protected function isLowStock(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->stock <= (float) $this->min_stock);
    }

    // -----------------------------------------------------------------
    // Lógica de inventario (une la Línea 1 y la Línea 2)
    // -----------------------------------------------------------------

    /**
     * Registra un movimiento de stock y actualiza el saldo del item.
     * Todo cambio de inventario debe pasar por aquí para mantener trazabilidad.
     */
    public function registerMovement(
        StockMovementType $type,
        float $quantity,
        ?int $userId = null,
        ?Model $source = null,
        ?string $reference = null,
        ?float $unitCost = null,
        ?string $notes = null,
        ?int $warehouseId = null,
    ): StockMovement {
        $signedDelta = match ($type) {
            StockMovementType::Entrada => abs($quantity),
            StockMovementType::Salida => -abs($quantity),
            // El ajuste respeta el signo recibido (puede sumar o restar).
            StockMovementType::Ajuste => $quantity,
        };

        $warehouseId = $warehouseId ?? Warehouse::defaultId();

        $minStock = (float) $this->min_stock;
        $wasLow = (float) $this->stock <= $minStock;

        $this->stock = (float) $this->stock + $signedDelta;
        $this->save();

        // Actualiza el saldo de la bodega afectada (si el módulo está activo).
        $this->applyWarehouseDelta($warehouseId, $signedDelta);

        // Alerta sólo al cruzar el umbral (evita notificar en cada salida).
        if ($minStock > 0 && ! $wasLow && (float) $this->stock <= $minStock) {
            $this->notifyLowStock();
        }

        $movement = new StockMovement([
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $this->stock,
            'unit_cost' => $unitCost,
            'reference' => $reference,
            'notes' => $notes,
        ]);

        if ($source !== null) {
            $movement->source()->associate($source);
        }

        return $this->stockMovements()->save($movement);
    }

    /**
     * Aplica el delta de existencias a la bodega indicada. La primera vez que
     * un item se mueve, migra su saldo de apertura a la bodega predeterminada
     * para que la suma por bodega coincida siempre con el stock total.
     */
    protected function applyWarehouseDelta(?int $warehouseId, float $delta): void
    {
        if ($warehouseId === null) {
            return;
        }

        if (! $this->warehouses()->exists()) {
            $defaultId = Warehouse::defaultId();

            if ($defaultId !== null) {
                // Saldo previo al movimiento actual = stock ya actualizado menos el delta.
                $opening = (float) $this->stock - $delta;
                $this->warehouses()->attach($defaultId, ['stock' => $opening]);
            }
        }

        $existing = $this->warehouses()->where('warehouse_id', $warehouseId)->first();
        $current = $existing ? (float) $existing->pivot->stock : 0.0;

        if ($existing) {
            $this->warehouses()->updateExistingPivot($warehouseId, ['stock' => $current + $delta]);
        } else {
            $this->warehouses()->attach($warehouseId, ['stock' => $current + $delta]);
        }
    }

    /**
     * Avisa a los administradores del stock bajo por correo y por la campana
     * del panel. Los errores se registran sin interrumpir el movimiento.
     */
    protected function notifyLowStock(): void
    {
        try {
            $admins = User::role('super_admin')->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::send($admins, new LowStockAlert($this));

            foreach ($admins as $admin) {
                FilamentNotification::make()
                    ->title('Inventario bajo')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->warning()
                    ->body("{$this->sku} — {$this->name}: ".(float) $this->stock." (mín ".(float) $this->min_stock.')')
                    ->sendToDatabase($admin);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar la alerta de inventario bajo: '.$e->getMessage());
        }
    }
}
