<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Enums\PaymentMethod;
use App\Enums\ProductionOrderStatus;
use App\Enums\SaleStatus;
use App\Models\Concerns\HasDocumentTotals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sale extends Model
{
    use HasDocumentTotals, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'invoice_number', 'status', 'subtotal', 'iva_amount', 'total', 'customer_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'code',
        'invoice_sequence',
        'invoice_number',
        'customer_id',
        'quote_id',
        'user_id',
        'warehouse_id',
        'status',
        'subtotal',
        'iva_rate',
        'iva_amount',
        'total',
        'notes',
        'payment_method',
        'advance_amount',
        'sold_at',
        'delivered_at',
        'einvoice_status',
        'einvoice_uuid',
        'einvoice_response',
        'einvoiced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_method' => PaymentMethod::class,
            'advance_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sold_at' => 'date',
            'delivered_at' => 'datetime',
            'einvoice_status' => \App\Enums\EInvoiceStatus::class,
            'einvoice_response' => 'array',
            'einvoiced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            if (blank($sale->code)) {
                $sale->code = static::generateCode();
            }

            if (blank($sale->status)) {
                $sale->status = SaleStatus::Borrador;
            }

            if (blank($sale->sold_at)) {
                $sale->sold_at = now();
            }
        });
    }

    // -----------------------------------------------------------------
    // Relaciones
    // -----------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
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
        return $this->hasMany(SaleItem::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /**
     * OPs de esta factura (por sale_id o cotización vinculada).
     *
     * @return Builder<ProductionOrder>
     */
    public function deliveryOrdersQuery(): Builder
    {
        return ProductionOrder::query()
            ->where(function (Builder $query): void {
                $query->where('sale_id', $this->id);

                if (filled($this->quote_id)) {
                    $query->orWhere(function (Builder $inner): void {
                        $inner->where('quote_id', $this->quote_id)
                            ->where(function (Builder $saleLink): void {
                                $saleLink->whereNull('sale_id')->orWhere('sale_id', $this->id);
                            });
                    });
                }
            });
    }

    /** Tiene OPs vinculadas a esta factura (por sale_id o cotización). */
    public function needsProductionDelivery(): bool
    {
        return $this->deliveryOrdersQuery()->exists();
    }

    /** Lista para entregar: OPs completadas, o venta de stock confirmada sin OP. */
    public function isReadyForDelivery(): bool
    {
        if ($this->status === SaleStatus::Borrador || $this->status === SaleStatus::Anulada) {
            return false;
        }

        if ($this->needsProductionDelivery()) {
            return $this->deliveryOrdersQuery()->get()->contains(
                fn (ProductionOrder $order): bool => $order->isReadyForDelivery()
            );
        }

        return $this->status === SaleStatus::Confirmada && $this->delivered_at === null;
    }

    /** Entrega completa: todas las OPs entregadas, o stock con delivered_at. */
    public function isFullyDelivered(): bool
    {
        if ($this->needsProductionDelivery()) {
            $orders = $this->deliveryOrdersQuery()
                ->where('status', '!=', ProductionOrderStatus::Cancelado->value)
                ->get();

            if ($orders->isEmpty()) {
                return false;
            }

            return $orders->every(fn (ProductionOrder $order): bool => $order->status === ProductionOrderStatus::Entregado);
        }

        return $this->delivered_at !== null;
    }

    /** Tiene OPs pendientes con fecha pactada vencida. */
    public function isDeliveryOverdue(): bool
    {
        if ($this->isFullyDelivered() || ! $this->needsProductionDelivery()) {
            return false;
        }

        return $this->deliveryOrdersQuery()->deliveryOverdue()->exists();
    }

    /** Ej: "1/2 entregadas", "Stock / sin OP" o "Entregada". */
    public function deliveryProgressLabel(): string
    {
        $orders = $this->deliveryOrdersQuery()
            ->where('status', '!=', ProductionOrderStatus::Cancelado->value)
            ->get();

        $total = $orders->count();

        if ($total === 0) {
            return $this->delivered_at !== null ? 'Entregada' : 'Stock / sin OP';
        }

        $delivered = $orders->where('status', ProductionOrderStatus::Entregado)->count();
        $ready = $orders->filter(fn (ProductionOrder $order): bool => $order->isReadyForDelivery())->count();

        if ($delivered === $total) {
            return "{$delivered}/{$total} entregadas";
        }

        if ($ready > 0) {
            return "{$ready}/{$total} listas · {$delivered} entregadas";
        }

        return "{$delivered}/{$total} entregadas";
    }

    public function deliveryDueAt(): ?\Carbon\CarbonInterface
    {
        return $this->deliveryOrdersQuery()
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->value('due_at');
    }

    public function deliveredAt(): ?\Carbon\CarbonInterface
    {
        if ($this->delivered_at !== null) {
            return $this->delivered_at;
        }

        return $this->deliveryOrdersQuery()
            ->where('status', ProductionOrderStatus::Entregado->value)
            ->orderByDesc('delivered_at')
            ->value('delivered_at');
    }

    /** Texto para tooltip: líneas “Entrega recibida por…” de la venta y sus OPs. */
    public function deliveryReceivedByTooltip(): ?string
    {
        $chunks = [];

        $fromNotes = static function (?string $notes): array {
            if (blank($notes)) {
                return [];
            }

            return collect(preg_split('/\r\n|\r|\n/', (string) $notes) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter(fn (string $line): bool => str_contains($line, 'Entrega recibida por'))
                ->values()
                ->all();
        };

        foreach ($fromNotes($this->notes) as $line) {
            $chunks[] = $line;
        }

        $orders = $this->relationLoaded('productionOrders')
            ? $this->productionOrders
            : $this->deliveryOrdersQuery()->get(['id', 'code', 'notes']);

        foreach ($orders as $order) {
            foreach ($fromNotes($order->notes) as $line) {
                $chunks[] = filled($order->code) ? "{$order->code}: {$line}" : $line;
            }
        }

        $chunks = array_values(array_unique($chunks));

        return $chunks === [] ? null : implode("\n", $chunks);
    }

    /**
     * Entrega stock (sin OP) o todas las OPs listas de esta factura.
     *
     * @return int Cantidad de OPs marcadas (0 si fue entrega de stock)
     */
    public function markReadyOrdersDelivered(?string $receivedBy = null): int
    {
        if ($this->status === SaleStatus::Borrador) {
            throw new \InvalidArgumentException('Confirma la venta antes de registrar la entrega.');
        }

        if ($this->status === SaleStatus::Anulada) {
            throw new \InvalidArgumentException('No se puede entregar una venta anulada.');
        }

        $orders = $this->deliveryOrdersQuery()->with(['sale', 'quote.sale'])->get();

        if ($orders->isEmpty()) {
            $this->markStockDelivered($receivedBy);

            return 0;
        }

        $ready = $orders->filter(fn (ProductionOrder $order): bool => $order->isReadyForDelivery());

        if ($ready->isEmpty()) {
            throw new \InvalidArgumentException('No hay órdenes de producción listas para entregar en esta factura.');
        }

        foreach ($ready as $order) {
            $order->markDelivered($receivedBy, notify: false);
        }

        app(\App\Services\NotifyDeliveryRegistered::class)->handleSale(
            $this->fresh(['customer', 'user', 'quote', 'productionOrders']) ?? $this,
            $ready->count()
        );

        return $ready->count();
    }

    /** Entrega de factura sin órdenes de producción (stock / lámina lista). */
    public function markStockDelivered(?string $receivedBy = null): void
    {
        if ($this->status === SaleStatus::Borrador) {
            throw new \InvalidArgumentException('Confirma la venta antes de registrar la entrega.');
        }

        if ($this->status === SaleStatus::Anulada) {
            throw new \InvalidArgumentException('No se puede entregar una venta anulada.');
        }

        if ($this->needsProductionDelivery()) {
            throw new \InvalidArgumentException('Esta factura tiene órdenes de producción; entrega desde las OPs listas.');
        }

        if ($this->delivered_at !== null) {
            throw new \InvalidArgumentException('Esta factura ya está entregada.');
        }

        $notes = $this->notes;

        if (filled($receivedBy)) {
            $line = 'Entrega recibida por: '.mb_strtoupper(trim($receivedBy), 'UTF-8')
                .' ('.now()->format('d/m/Y H:i').')';
            $notes = filled($notes) ? rtrim((string) $notes)."\n{$line}" : $line;
        }

        $this->forceFill([
            'delivered_at' => now(),
            'notes' => $notes,
        ])->save();

        app(\App\Services\NotifyDeliveryRegistered::class)->handleSale(
            $this->fresh(['customer', 'user', 'quote', 'productionOrders']) ?? $this,
            0
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeReadyForDelivery(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', SaleStatus::Borrador->value)
            ->where('status', '!=', SaleStatus::Anulada->value)
            ->where(function (Builder $outer): void {
                $outer
                    ->whereHas('productionOrders', fn (Builder $orders): Builder => $orders->readyForDelivery())
                    ->orWhere(function (Builder $stock): void {
                        $stock
                            ->where('status', SaleStatus::Confirmada->value)
                            ->whereNull('delivered_at')
                            ->whereDoesntHave('productionOrders');
                    });
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDeliveryOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', SaleStatus::Borrador->value)
            ->where('status', '!=', SaleStatus::Anulada->value)
            ->whereHas('productionOrders', fn (Builder $orders): Builder => $orders->deliveryOverdue())
            ->whereHas('productionOrders', fn (Builder $orders): Builder => $orders->whereNotIn('status', [
                ProductionOrderStatus::Entregado->value,
                ProductionOrderStatus::Cancelado->value,
            ]));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFullyDelivered(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', SaleStatus::Anulada->value)
            ->where(function (Builder $outer): void {
                $outer
                    ->where(function (Builder $withOps): void {
                        $withOps
                            ->whereHas('productionOrders')
                            ->whereDoesntHave('productionOrders', function (Builder $orders): void {
                                $orders->whereNotIn('status', [
                                    ProductionOrderStatus::Entregado->value,
                                    ProductionOrderStatus::Cancelado->value,
                                ]);
                            });
                    })
                    ->orWhere(function (Builder $stock): void {
                        $stock
                            ->whereNotNull('delivered_at')
                            ->whereDoesntHave('productionOrders');
                    });
            });
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    /** Monto de anticipo recibido (0 si vacío). */
    public function advancePaid(): float
    {
        return max(0, (float) ($this->advance_amount ?? 0));
    }

    /** Saldo pendiente = total − anticipo (nunca negativo). */
    public function balanceDue(): float
    {
        return max(0, (float) ($this->total ?? 0) - $this->advancePaid());
    }

    // -----------------------------------------------------------------
    // Código único VT-AAAA-#####
    // -----------------------------------------------------------------

    public static function generateCode(): string
    {
        $year = now()->year;
        $sequence = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        do {
            $code = sprintf('VT-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /** Siguiente consecutivo respetando el número inicial del rango autorizado. */
    public static function nextInvoiceSequence(): int
    {
        $start = (int) config('invoicing.start', 1);
        $max = (int) static::withTrashed()->max('invoice_sequence');

        return max($max, $start - 1) + 1;
    }

    public static function formatInvoiceNumber(int $sequence): string
    {
        $prefix = (string) config('invoicing.prefix', 'FV');
        $padding = (int) config('invoicing.padding', 6);

        return sprintf('%s-%s', $prefix, str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT));
    }

    // -----------------------------------------------------------------
    // Lógica de negocio (Línea 1: salida de inventario por venta)
    // -----------------------------------------------------------------

    public function recalculateTotal(): void
    {
        $this->loadMissing('items');

        $subtotal = $this->items->sum(
            fn (SaleItem $item): float => (float) $item->quantity * (float) $item->unit_price
        );

        $this->applyTaxFromSubtotal($subtotal);
    }

    /**
     * Confirma la venta y cierra el documento.
     * Temporalmente no descuenta inventario ni valida stock al confirmar.
     */
    public function confirm(?int $userId = null): void
    {
        $status = $this->status ?? SaleStatus::Borrador;

        if ($status === SaleStatus::Confirmada) {
            return;
        }

        if ($status !== SaleStatus::Borrador) {
            throw new \InvalidArgumentException('Solo se puede confirmar una venta en borrador.');
        }

        $this->assertCustomerReadyForOrders();

        DB::transaction(function (): void {
            $attributes = [
                'status' => SaleStatus::Confirmada,
                'sold_at' => $this->sold_at ?? now(),
            ];

            // Asigna consecutivo fiscal formal la primera vez que se confirma.
            if (blank($this->invoice_sequence)) {
                $next = static::nextInvoiceSequence();
                $attributes['invoice_sequence'] = $next;
                $attributes['invoice_number'] = static::formatInvoiceNumber($next);
            }

            $this->forceFill($attributes)->save();
        });

        $this->load('items');
        $this->recalculateTotal();

        // Emite la factura electrónica de forma asíncrona si está habilitada.
        if (config('einvoice.enabled')) {
            \App\Jobs\SubmitElectronicInvoice::dispatch($this->refresh());
        }
    }

    /** Una venta confirmada (o anulada/devuelta) no se edita; hay que reversarla primero. */
    public function isEditable(): bool
    {
        return $this->status === SaleStatus::Borrador;
    }

    /**
     * Revierte una venta confirmada a borrador para poder corregirla.
     * Conserva el número de factura ya asignado. No toca inventario.
     */
    public function reverse(): void
    {
        if ($this->status !== SaleStatus::Confirmada) {
            throw new \InvalidArgumentException('Solo se puede reversar una venta confirmada.');
        }

        $this->forceFill([
            'status' => SaleStatus::Borrador,
            'einvoice_status' => \App\Enums\EInvoiceStatus::Pendiente,
            'einvoice_uuid' => null,
            'einvoice_response' => null,
            'einvoiced_at' => null,
        ])->save();
    }

    public function cancel(): void
    {
        $this->forceFill(['status' => SaleStatus::Anulada])->save();
    }

    /**
     * Un cliente vinculado debe tener NIT/documento antes de confirmar el pedido.
     * Las ventas al público (sin cliente) siguen permitidas.
     */
    public function assertCustomerReadyForOrders(): void
    {
        if (blank($this->customer_id)) {
            return;
        }

        $this->loadMissing('customer');

        $this->customer?->assertReadyForOrders();
    }

    /** Valida que haya inventario suficiente para todas las líneas de esta venta. */
    public function assertEnoughStock(): void
    {
        $this->loadMissing('items.item');

        $quantities = [];

        foreach ($this->items as $line) {
            $itemId = (int) $line->item_id;
            $quantities[$itemId] = ($quantities[$itemId] ?? 0) + (float) $line->quantity;
        }

        static::assertStockForQuantities($quantities, $this->warehouse_id);
    }

    /**
     * @param  array<int, float>  $quantitiesByItemId
     */
    public static function assertStockForQuantities(array $quantitiesByItemId, ?int $warehouseId = null): void
    {
        if ($quantitiesByItemId === []) {
            return;
        }

        $items = Item::query()
            ->whereIn('id', array_keys($quantitiesByItemId))
            ->get()
            ->keyBy('id');

        $shortages = [];

        foreach ($quantitiesByItemId as $itemId => $needed) {
            $item = $items->get($itemId);

            if (! $item) {
                $shortages[] = "Producto #{$itemId}: no encontrado";

                continue;
            }

            $available = static::availableStockFor($item, $warehouseId);
            $needed = (float) $needed;

            if ($needed > $available) {
                $shortages[] = "{$item->sku} — {$item->name}: disponible ".static::formatQty($available).', solicitado '.static::formatQty($needed);
            }
        }

        if ($shortages === []) {
            return;
        }

        throw ValidationException::withMessages([
            'stock' => 'No hay inventario suficiente para generar la venta. '.implode('; ', $shortages),
        ]);
    }

    public static function availableStockFor(Item $item, ?int $warehouseId = null): float
    {
        if ($warehouseId && $item->warehouses()->where('warehouse_id', $warehouseId)->exists()) {
            return $item->stockInWarehouse($warehouseId);
        }

        return (float) $item->stock;
    }

    protected static function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }

    /**
     * Crea órdenes de producción para cada línea de producto terminado
     * e inicia el pipeline con los procesos indicados (o todos los activos).
     *
     * @param  iterable<int>|null  $processIds
     * @return Collection<int, ProductionOrder>
     */
    public function createProductionOrders(?int $userId = null, ?iterable $processIds = null): Collection
    {
        $this->loadMissing('items.item');

        $processIdList = $processIds === null
            ? null
            : collect($processIds)->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($processIdList?->isEmpty()) {
            throw ValidationException::withMessages([
                'process_ids' => 'Selecciona al menos un proceso de producción.',
            ]);
        }

        return DB::transaction(function () use ($userId, $processIdList): Collection {
            $this->loadMissing(['quote.lead', 'customer']);

            $orders = collect();
            $lead = $this->quote?->lead;
            $contactName = $this->quote?->contactDisplay();
            if ($contactName === '—' || blank($contactName)) {
                $contactName = $lead?->name ?: $this->customer?->personContactName();
            }
            $quotedByUserId = $this->quote?->user_id ?: $this->user_id;

            foreach ($this->items as $line) {
                if ($line->item?->type !== ItemType::ProductoTerminado) {
                    continue;
                }

                if ($this->productionOrders()->where('sale_item_id', $line->id)->exists()) {
                    continue;
                }

                $order = ProductionOrder::query()->create([
                    'item_id' => $line->item_id,
                    'sale_id' => $this->id,
                    'sale_item_id' => $line->id,
                    'user_id' => $userId ?? $this->user_id ?? auth()->id(),
                    'warehouse_id' => $this->warehouse_id ?? Warehouse::defaultId(),
                    'quantity' => $line->quantity,
                    'status' => \App\Enums\ProductionOrderStatus::Pendiente,
                    'notes' => "Origen: venta {$this->code}",
                    'contact_name' => $contactName,
                    'quoted_by_user_id' => $quotedByUserId,
                    'payment_method' => $this->payment_method,
                    'advance_amount' => $this->advance_amount,
                    'requested_at' => now(),
                    'due_at' => $this->quote?->valid_until,
                ]);

                $order->generatePipeline($processIdList?->all());
                $orders->push($order->fresh('logs'));
            }

            return $orders;
        });
    }
}
