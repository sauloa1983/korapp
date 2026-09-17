<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ProductionLogStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductionOrder extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'status', 'quantity', 'item_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'code',
        'item_id',
        'user_id',
        'warehouse_id',
        'quote_id',
        'quote_item_id',
        'sale_id',
        'sale_item_id',
        'quantity',
        'status',
        'notes',
        'contact_name',
        'quoted_by_user_id',
        'payment_method',
        'advance_amount',
        'file_path',
        'has_plans',
        'plans_attachment',
        'requested_at',
        'due_at',
        'started_at',
        'completed_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'status' => ProductionOrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'advance_amount' => 'decimal:2',
            'has_plans' => 'boolean',
            'requested_at' => 'date',
            'due_at' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionOrder $order): void {
            if (blank($order->code)) {
                $order->code = static::generateCode();
            }

            if (blank($order->requested_at)) {
                $order->requested_at = now();
            }
        });
    }

    // -----------------------------------------------------------------
    // Relaciones
    // -----------------------------------------------------------------

    /** Producto terminado a fabricar. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by_user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProductionOrderRequirement::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionLog::class)->orderBy('sequence');
    }

    /** Procesos por los que transita la orden (a través de los logs de trazabilidad). */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'production_logs')
            ->withPivot(['status', 'started_at', 'ended_at', 'duration_seconds'])
            ->withTimestamps();
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeStatus(Builder $query, ProductionOrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProductionOrderStatus::Pendiente->value,
            ProductionOrderStatus::EnProgreso->value,
        ]);
    }

    // -----------------------------------------------------------------
    // Fábrica de código único (OP-AAAA-#####)
    // -----------------------------------------------------------------

    public static function generateCode(): string
    {
        $year = now()->year;
        $sequence = static::withTrashed()
            ->whereYear('created_at', $year)
            ->count() + 1;

        do {
            $code = sprintf('OP-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    // -----------------------------------------------------------------
    // Flujo dinámico de producción
    // -----------------------------------------------------------------

    /**
     * Genera el pipeline de trazabilidad clonando procesos activos
     * (o un subconjunto indicado) en logs con su QR único.
     *
     * @param  iterable<int>|null  $processIds
     */
    public function generatePipeline(?iterable $processIds = null): void
    {
        // Orden del menú Procesos (sort_order), no el orden de los checkboxes.
        $query = Process::query()->active()->ordered();

        if ($processIds !== null) {
            $ids = collect($processIds)->filter()->map(fn ($id): int => (int) $id)->unique()->values();

            if ($ids->isEmpty()) {
                return;
            }

            $query->whereIn('id', $ids->all());
        }

        foreach ($query->get()->values() as $index => $process) {
            $this->logs()->firstOrCreate(
                ['process_id' => $process->id],
                [
                    'sequence' => $index + 1,
                    'status' => ProductionLogStatus::EnEspera,
                ],
            );
        }
    }

    /**
     * Descuenta del inventario la materia prima e insumos de la orden,
     * dejando su rastro en stock_movements (unión Línea 1 <-> Línea 2).
     */
    public function consumeStock(?int $userId = null): void
    {
        DB::transaction(function () use ($userId): void {
            foreach ($this->requirements()->with('item')->get() as $requirement) {
                $pending = (float) $requirement->quantity_required - (float) $requirement->quantity_consumed;

                if ($pending <= 0) {
                    continue;
                }

                $requirement->item->registerMovement(
                    type: StockMovementType::Salida,
                    quantity: $pending,
                    userId: $userId,
                    source: $this,
                    reference: "Consumo orden {$this->code}",
                    warehouseId: $this->warehouse_id,
                );

                $requirement->update([
                    'quantity_consumed' => $requirement->quantity_required,
                ]);
            }
        });
    }

    /**
     * Ingresa al inventario el artículo fabricado (producto terminado o insumo propio).
     * Idempotente: no duplica la entrada si ya existe.
     */
    public function produceStock(?int $userId = null): void
    {
        if ($this->hasProducedStock()) {
            return;
        }

        $this->loadMissing('item');

        if (! $this->item) {
            return;
        }

        $this->item->registerMovement(
            type: StockMovementType::Entrada,
            quantity: (float) $this->quantity,
            userId: $userId,
            source: $this,
            reference: "Producción {$this->code}",
            unitCost: $this->item->cost,
            warehouseId: $this->warehouse_id,
        );
    }

    public function hasProducedStock(): bool
    {
        return $this->stockMovements()
            ->where('type', StockMovementType::Entrada->value)
            ->where('item_id', $this->item_id)
            ->exists();
    }

    public function markInProgress(): void
    {
        if ($this->status === ProductionOrderStatus::Pendiente) {
            $this->forceFill([
                'status' => ProductionOrderStatus::EnProgreso,
                'started_at' => $this->started_at ?? now(),
            ])->save();
        }
    }

    /**
     * Marca la orden como completada automáticamente cuando todas las
     * etapas de su pipeline han terminado, consume materiales pendientes
     * e ingresa el artículo fabricado al inventario.
     */
    public function syncStatusFromLogs(?int $userId = null): void
    {
        if (in_array($this->status, [
            ProductionOrderStatus::Cancelado,
            ProductionOrderStatus::Completado,
            ProductionOrderStatus::Entregado,
        ], true)) {
            return;
        }

        $total = $this->logs()->count();

        if ($total === 0) {
            return;
        }

        $finished = $this->logs()
            ->where('status', ProductionLogStatus::Terminado->value)
            ->count();

        if ($finished !== $total) {
            return;
        }

        DB::transaction(function () use ($userId): void {
            $this->consumeStock($userId);
            $this->produceStock($userId);

            $this->forceFill([
                'status' => ProductionOrderStatus::Completado,
                'completed_at' => now(),
            ])->save();
        });

        app(\App\Services\NotifyProductionCompleted::class)->handle($this->fresh([
            'item',
            'quote',
            'quotedBy',
            'user',
        ]) ?? $this);
    }

    public function cancel(): void
    {
        $this->forceFill(['status' => ProductionOrderStatus::Cancelado])->save();
    }

    /** Completada en planta y con venta lista (o sin cotización). */
    public function isReadyForDelivery(): bool
    {
        if ($this->status !== ProductionOrderStatus::Completado) {
            return false;
        }

        $this->loadMissing(['sale', 'quote.sale']);

        $sale = $this->sale ?? $this->quote?->sale;

        if ($this->quote_id && $sale === null) {
            return false;
        }

        if ($sale !== null && $sale->status === SaleStatus::Borrador) {
            return false;
        }

        return true;
    }

    /** Tiene fecha pactada vencida y aún no se entregó. */
    public function isDeliveryOverdue(): bool
    {
        if ($this->status === ProductionOrderStatus::Entregado || $this->status === ProductionOrderStatus::Cancelado) {
            return false;
        }

        if ($this->due_at === null) {
            return false;
        }

        return $this->due_at->endOfDay()->isPast();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeReadyForDelivery(Builder $query): Builder
    {
        return $query
            ->where('status', ProductionOrderStatus::Completado)
            ->where(function (Builder $q): void {
                $q->whereNull('quote_id')
                    ->orWhereHas('sale', fn (Builder $sale): Builder => $sale->where('status', '!=', SaleStatus::Borrador->value))
                    ->orWhereHas('quote.sale', fn (Builder $sale): Builder => $sale->where('status', '!=', SaleStatus::Borrador->value));
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDeliveryOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [
                ProductionOrderStatus::Entregado->value,
                ProductionOrderStatus::Cancelado->value,
            ])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', now()->toDateString());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecentlyDelivered(Builder $query): Builder
    {
        return $query
            ->where('status', ProductionOrderStatus::Entregado)
            ->whereNotNull('delivered_at')
            ->orderByDesc('delivered_at');
    }

    /**
     * Limita OPs a la cartera del vendedor (cotización, venta o quien cotizó).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCommercialUser(Builder $query, ?int $userId = null): Builder
    {
        $ownerId = $userId ?? \App\Support\CommercialScope::ownerId();

        if ($ownerId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($ownerId): void {
            $q->where('quoted_by_user_id', $ownerId)
                ->orWhere('user_id', $ownerId)
                ->orWhereHas('quote', fn (Builder $quote): Builder => $quote->where('user_id', $ownerId))
                ->orWhereHas('sale', fn (Builder $sale): Builder => $sale->where('user_id', $ownerId));
        });
    }

    /** Entrega final al cliente (después de fabricar y facturar). */
    public function markDelivered(?string $receivedBy = null, bool $notify = true): void
    {
        if ($this->status !== ProductionOrderStatus::Completado) {
            throw new \InvalidArgumentException('Solo se puede entregar una orden completada en planta.');
        }

        $this->loadMissing(['sale', 'quote.sale']);

        $sale = $this->sale ?? $this->quote?->sale;

        if ($this->quote_id && $sale === null) {
            throw new \InvalidArgumentException(
                'Flujo: Cotización → OP → Venta → Entrega. Primero crea la venta/factura.'
            );
        }

        if ($sale !== null && $sale->status === SaleStatus::Borrador) {
            throw new \InvalidArgumentException('Confirma la venta antes de registrar la entrega.');
        }

        $notes = $this->notes;

        if (filled($receivedBy)) {
            $line = 'Entrega recibida por: '.mb_strtoupper(trim($receivedBy), 'UTF-8')
                .' ('.now()->format('d/m/Y H:i').')';
            $notes = filled($notes) ? rtrim((string) $notes)."\n{$line}" : $line;
        }

        $this->forceFill([
            'status' => ProductionOrderStatus::Entregado,
            'delivered_at' => now(),
            'notes' => $notes,
        ])->save();

        if ($notify) {
            app(\App\Services\NotifyDeliveryRegistered::class)->handle(
                $this->fresh(['item', 'quote', 'quotedBy', 'user', 'sale.customer', 'sale.user']) ?? $this
            );
        }
    }

    // -----------------------------------------------------------------
    // Reportería de tiempos
    // -----------------------------------------------------------------

    /** Tiempo total de producción (suma de todas las etapas) en minutos. */
    public function totalDurationMinutes(): float
    {
        return round((int) $this->logs()->sum('duration_seconds') / 60, 2);
    }
}
