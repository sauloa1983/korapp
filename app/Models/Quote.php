<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Models\Concerns\HasDocumentTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Quote extends Model
{
    use HasDocumentTotals, SoftDeletes;

    protected $fillable = [
        'code',
        'project_name',
        'status',
        'customer_id',
        'lead_id',
        'contact_name',
        'user_id',
        'subtotal',
        'iva_rate',
        'iva_amount',
        'withholding_rate',
        'withholding_amount',
        'advance_percent',
        'payment_form',
        'total',
        'valid_until',
        'validity_days',
        'delivery_date',
        'delivery_note',
        'notes',
        'terms',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'withholding_rate' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'advance_percent' => 'decimal:2',
            'total' => 'decimal:2',
            'valid_until' => 'date',
            'validity_days' => 'integer',
            'delivery_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function deliveryDisplay(): string
    {
        if ($this->delivery_date) {
            return $this->delivery_date->format('d/m/Y');
        }

        return filled($this->delivery_note)
            ? (string) $this->delivery_note
            : 'A CONVENIR';
    }

    /** Una cotización aceptada queda cerrada: no se edita el contenido comercial. */
    public function isEditable(): bool
    {
        return $this->status !== QuoteStatus::Accepted;
    }

    public function validityDisplay(): string
    {
        $days = max(1, (int) ($this->validity_days ?: 10));

        return $days === 1 ? '1 DIA' : $days.' DIAS';
    }

    public function paymentFormDisplay(): string
    {
        if (filled($this->payment_form)) {
            return mb_strtoupper((string) $this->payment_form, 'UTF-8');
        }

        $advance = (float) ($this->advance_percent ?? 0);

        return $advance > 0 && $advance < 100
            ? number_format($advance, 0, ',', '.').'% ANTICIPO'
            : 'CONTADO';
    }

    public function clientCodeDisplay(): string
    {
        if (! $this->customer_id) {
            return '';
        }

        return (string) $this->customer_id;
    }

    /**
     * Contacto para PDF/UI: ignora si el valor guardado es en realidad la empresa.
     */
    public function contactDisplay(): string
    {
        $this->loadMissing(['customer', 'lead']);

        $stored = filled($this->contact_name) ? trim((string) $this->contact_name) : null;
        $companyLabels = array_values(array_filter([
            $this->customer?->name,
            $this->customer?->company_name,
            $this->lead?->company,
        ], static fn ($value): bool => filled($value)));

        $storedIsCompany = $stored !== null && collect($companyLabels)->contains(
            static fn ($label): bool => mb_strtoupper(trim((string) $label), 'UTF-8') === mb_strtoupper($stored, 'UTF-8')
        );

        if ($stored !== null && ! $storedIsCompany) {
            return $stored;
        }

        $fromCustomer = $this->customer?->personContactName();
        if (filled($fromCustomer)) {
            return (string) $fromCustomer;
        }

        if (filled($this->lead?->name)) {
            return (string) $this->lead->name;
        }

        return '—';
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote): void {
            if (blank($quote->code)) {
                $quote->code = static::generateCode();
            }

            if (blank($quote->user_id) && auth()->id()) {
                $quote->user_id = auth()->id();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(QuotePiece::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public static function generateCode(): string
    {
        $year = now()->year;
        $sequence = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        do {
            $code = sprintf('CT-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function recalculateTotal(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');
        $this->applyTaxFromSubtotal($subtotal, withholdingRate: (float) ($this->withholding_rate ?? 0));
    }

    public function totalPayable(): float
    {
        return round((float) $this->total - (float) ($this->withholding_amount ?? 0), 2);
    }

    public function finishedGoodLines(): Collection
    {
        $this->loadMissing('items.item');

        return $this->items->filter(
            fn (QuoteItem $line): bool => $line->item?->type === ItemType::ProductoTerminado
                && $this->isManufacturingQuoteLine($line)
        )->values();
    }

    /**
     * Transporte / instalación y otros precios fijos: se facturan, no se fabrican en planta.
     */
    public function isManufacturingQuoteLine(QuoteItem $line): bool
    {
        $meta = is_array($line->meta) ? $line->meta : [];
        $calc = is_array($meta['calculation'] ?? null) ? $meta['calculation'] : [];
        $sernaType = (string) ($calc['item_type'] ?? $meta['item_type'] ?? '');

        if ($sernaType === 'precio_fijo') {
            return false;
        }

        if (in_array($line->item?->sku, ['SERNA-MANUAL', 'SERNA-TRANSPORTE'], true)) {
            return false;
        }

        $haystack = mb_strtolower(
            trim(($line->description ?? '').' '.($calc['material'] ?? '').' '.($meta['material'] ?? '')),
            'UTF-8',
        );

        if ($haystack !== '' && preg_match('/\b(transporte|instalaci[oó]n)\b/u', $haystack)) {
            return false;
        }

        return true;
    }

    /**
     * Cotizaciones del cotizador Serna: varias líneas de costo = un solo trabajo.
     */
    public function isSernaQuote(): bool
    {
        $this->loadMissing(['items.item']);

        return $this->items->contains(function (QuoteItem $line): bool {
            if (($line->meta['source'] ?? null) === 'serna_cotizador') {
                return true;
            }

            return in_array($line->item?->sku, ['SERNA-SERVICIO', 'SERNA-LAMINA', 'SERNA-MANUAL'], true);
        });
    }

    /**
     * Trabajos de planta a fabricar.
     * - Catálogo: 1 OP por línea de producto terminado.
     * - Serna: 1 OP por pieza (o 1 por cotización si no hay piezas), con secuencia de etapas dentro.
     *
     * @return Collection<int, array{anchor: QuoteItem, label: string, quantity: float, item_id: int, line_ids: list<int>}>
     */
    public function productionJobs(): Collection
    {
        $finished = $this->finishedGoodLines();

        if ($finished->isEmpty()) {
            return collect();
        }

        if (! $this->isSernaQuote()) {
            return $finished->map(fn (QuoteItem $line): array => [
                'anchor' => $line,
                'label' => trim((string) ($line->description ?: $line->item?->name ?: 'Producto')),
                'quantity' => (float) $line->quantity,
                'item_id' => (int) $line->item_id,
                'line_ids' => [$line->id],
            ])->values();
        }

        $this->loadMissing(['pieces.items.item']);

        $jobs = collect();

        foreach ($this->pieces as $piece) {
            $lines = $piece->items
                ->filter(fn (QuoteItem $line): bool => $line->item?->type === ItemType::ProductoTerminado
                    && $this->isManufacturingQuoteLine($line))
                ->values();

            if ($lines->isEmpty()) {
                continue;
            }

            $anchor = $lines->first(
                fn (QuoteItem $line): bool => ($line->item?->sku ?? '') === 'SERNA-SERVICIO'
            ) ?? $lines->first();

            $jobs->push([
                'anchor' => $anchor,
                'label' => trim((string) ($piece->name ?: 'Pieza')),
                'quantity' => 1.0,
                'item_id' => (int) $anchor->item_id,
                'line_ids' => $lines->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ]);
        }

        if ($jobs->isNotEmpty()) {
            return $jobs->values();
        }

        $anchor = $finished->first();

        return collect([[
            'anchor' => $anchor,
            'label' => trim((string) ($this->project_name ?: $this->code)),
            'quantity' => 1.0,
            'item_id' => (int) $anchor->item_id,
            'line_ids' => $finished->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ]]);
    }

    public function hasProductionOrderForJob(array $job): bool
    {
        $lineIds = $job['line_ids'] ?? [];

        if ($lineIds === []) {
            return false;
        }

        return $this->productionOrders()
            ->whereIn('quote_item_id', $lineIds)
            ->exists();
    }

    public function canCreateProductionOrders(): bool
    {
        if ($this->status !== QuoteStatus::Accepted) {
            return false;
        }

        $jobs = $this->productionJobs();

        if ($jobs->isEmpty()) {
            return false;
        }

        return $jobs->contains(
            fn (array $job): bool => ! $this->hasProductionOrderForJob($job)
        );
    }

    public function canCreateSale(): bool
    {
        if ($this->status !== QuoteStatus::Accepted || $this->sale()->exists()) {
            return false;
        }

        $jobs = $this->productionJobs();

        if ($jobs->isEmpty()) {
            return true;
        }

        // Flujo: Cotización → OP → Venta.
        // Solo trabajos de planta (por pieza en Serna). Transporte/instalación se factura sin OP.
        foreach ($jobs as $job) {
            if (! $this->hasProductionOrderForJob($job)) {
                return false;
            }
        }

        // Todas las OP de planta deben estar finalizadas; líneas sin OP (p. ej. transporte) no bloquean.
        return $this->allProductionOrdersFinishedForSale();
    }

    /**
     * OP activas de la cotización (solo planta) completadas o entregadas.
     * Transporte / instalación no crean OP y no intervienen aquí.
     */
    public function allProductionOrdersFinishedForSale(): bool
    {
        $orders = $this->productionOrders()->get();

        $active = $orders->filter(
            fn (ProductionOrder $order): bool => $order->status !== ProductionOrderStatus::Cancelado
        );

        if ($active->isEmpty()) {
            return false;
        }

        return $active->every(
            fn (ProductionOrder $order): bool => in_array(
                $order->status,
                [ProductionOrderStatus::Completado, ProductionOrderStatus::Entregado],
                true
            )
        );
    }

    /**
     * Crea órdenes de producción desde la cotización aceptada (antes de la venta).
     *
     * @param  iterable<int>|null  $processIds
     * @return Collection<int, ProductionOrder>
     */
    public function createProductionOrders(?int $userId = null, ?iterable $processIds = null): Collection
    {
        if ($this->status !== QuoteStatus::Accepted) {
            throw new InvalidArgumentException('Solo se puede crear OP desde una cotización aceptada.');
        }

        $this->loadMissing(['items.item', 'pieces.items.item', 'lead', 'customer']);

        $jobs = $this->productionJobs();

        if ($jobs->isEmpty()) {
            throw new InvalidArgumentException('La cotización no tiene productos terminados para fabricar.');
        }

        $processIdList = $processIds === null
            ? null
            : collect($processIds)->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($processIdList?->isEmpty()) {
            throw ValidationException::withMessages([
                'process_ids' => 'Selecciona al menos un proceso de producción.',
            ]);
        }

        $contactDisplay = $this->contactDisplay();
        $contactName = $contactDisplay !== '—' ? $contactDisplay : null;

        return DB::transaction(function () use ($userId, $processIdList, $jobs, $contactName): Collection {
            $orders = collect();

            foreach ($jobs as $job) {
                if ($this->hasProductionOrderForJob($job)) {
                    continue;
                }

                /** @var QuoteItem $anchor */
                $anchor = $job['anchor'];

                if (blank($job['item_id'] ?? null)) {
                    throw new InvalidArgumentException('Todas las líneas de producto terminado deben tener un artículo vinculado.');
                }

                $notes = "Origen: cotización {$this->code}";
                if (filled($job['label'] ?? null)) {
                    $notes .= "\nTrabajo: {$job['label']}";
                }
                if ($this->isSernaQuote()) {
                    $notes .= "\nSecuencia de etapas en esta OP (líneas Serna agrupadas).";
                } elseif (filled($anchor->description)) {
                    $notes .= "\n{$anchor->description}";
                }

                $order = ProductionOrder::query()->create([
                    'item_id' => $job['item_id'],
                    'quote_id' => $this->id,
                    'quote_item_id' => $anchor->id,
                    'user_id' => $userId ?? $this->user_id ?? auth()->id(),
                    'warehouse_id' => Warehouse::defaultId(),
                    'quantity' => $job['quantity'],
                    'status' => ProductionOrderStatus::Pendiente,
                    'notes' => $notes,
                    'contact_name' => $contactName,
                    'quoted_by_user_id' => $this->user_id,
                    'requested_at' => now(),
                    'due_at' => $this->delivery_date ?? $this->valid_until,
                ]);

                $order->generatePipeline($processIdList?->all());
                $orders->push($order->fresh('logs'));
            }

            if ($orders->isEmpty()) {
                throw new InvalidArgumentException('Ya existen órdenes de producción para todos los trabajos de esta cotización.');
            }

            return $orders;
        });
    }

    /**
     * Genera una venta en borrador a partir de esta cotización aceptada.
     * Flujo: Cotización → OP → Venta. No crea OPs nuevas; vincula las existentes.
     */
    public function createSale(?int $userId = null): Sale
    {
        if ($this->status !== QuoteStatus::Accepted) {
            throw new InvalidArgumentException('Solo se puede crear una venta desde una cotización aceptada.');
        }

        if ($this->sale()->exists()) {
            throw new InvalidArgumentException('Esta cotización ya tiene una venta asociada.');
        }

        // Una venta soft-deleted sigue ocupando el unique de quote_id.
        $this->purgeTrashedSaleForQuote();

        $this->loadMissing(['items.item', 'lead', 'customer', 'productionOrders']);

        if ($this->items->isEmpty()) {
            throw new InvalidArgumentException('La cotización no tiene líneas para convertir en venta.');
        }

        $missingProduct = $this->items->first(fn (QuoteItem $line): bool => blank($line->item_id));

        if ($missingProduct) {
            throw new InvalidArgumentException('Todas las líneas deben tener un producto vinculado para crear la venta.');
        }

        foreach ($this->productionJobs() as $job) {
            if (! $this->hasProductionOrderForJob($job)) {
                throw new InvalidArgumentException('Primero crea la orden de producción (Cotización → OP → Venta).');
            }
        }

        if ($this->productionJobs()->isNotEmpty() && ! $this->allProductionOrdersFinishedForSale()) {
            throw new InvalidArgumentException(
                'Todas las órdenes de producción deben estar completadas o entregadas antes de crear la venta.'
            );
        }

        $customerId = $this->resolveCustomerIdForSale();
        $warehouseId = Warehouse::defaultId();

        // Serna: 1 línea de venta por ítem de cotización (mismo SKU, precios distintos).
        // Catálogo: se agrupa por producto.
        $saleLines = $this->isSernaQuote()
            ? $this->saleLinesFromSernaQuoteItems()
            : $this->saleLinesGroupedByCatalogItem();

        $stockCheck = [];
        foreach ($saleLines as $data) {
            if (! $data['is_finished']) {
                $itemId = (int) $data['item_id'];
                $stockCheck[$itemId] = ($stockCheck[$itemId] ?? 0) + (float) $data['quantity'];
            }
        }

        Sale::assertStockForQuantities($stockCheck, $warehouseId);

        return DB::transaction(function () use ($userId, $customerId, $warehouseId, $saleLines): Sale {
            $notes = filled($this->notes)
                ? "Origen: cotización {$this->code}\n{$this->notes}"
                : "Origen: cotización {$this->code}";

            $sale = Sale::query()->create([
                'customer_id' => $customerId,
                'quote_id' => $this->id,
                'user_id' => $userId ?? $this->user_id ?? auth()->id(),
                'warehouse_id' => $warehouseId,
                'status' => SaleStatus::Borrador,
                'notes' => $notes,
                'sold_at' => now(),
            ]);

            foreach ($saleLines as $data) {
                $saleItem = $sale->items()->create([
                    'item_id' => $data['item_id'],
                    'description' => $data['description'] ?? null,
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                ]);

                // Vincula OPs de esta cotización a la venta / línea.
                ProductionOrder::query()
                    ->where('quote_id', $this->id)
                    ->whereIn('quote_item_id', $data['quote_item_ids'])
                    ->whereNull('sale_id')
                    ->update([
                        'sale_id' => $sale->id,
                        'sale_item_id' => $saleItem->id,
                    ]);
            }

            // OP de la cotización aún sin línea (por si el ancla no coincide 1:1).
            ProductionOrder::query()
                ->where('quote_id', $this->id)
                ->whereNull('sale_id')
                ->update(['sale_id' => $sale->id]);

            $sale->refresh()->recalculateTotal();

            return $sale->fresh(['items', 'customer', 'productionOrders.logs']);
        });
    }

    /**
     * Elimina ventas soft-deleted de esta cotización para liberar el unique quote_id.
     */
    protected function purgeTrashedSaleForQuote(): void
    {
        $trashed = Sale::onlyTrashed()->where('quote_id', $this->id)->get();

        foreach ($trashed as $sale) {
            ProductionOrder::query()
                ->where('sale_id', $sale->id)
                ->update([
                    'sale_id' => null,
                    'sale_item_id' => null,
                ]);

            $sale->forceDelete();
        }
    }

    /**
     * Serna: 1 línea de venta por pieza (total unitario, sin desglose de materiales).
     * Transporte / instalación y otros no manufactura quedan en líneas aparte.
     *
     * @return list<array{item_id: int, description: ?string, quantity: float, unit_price: float, quote_item_ids: list<int>, is_finished: bool}>
     */
    protected function saleLinesFromSernaQuoteItems(): array
    {
        $this->loadMissing(['items.item', 'pieces.items.item']);

        $lines = [];
        $consumedIds = [];

        foreach ($this->pieces as $piece) {
            $manufacturing = $piece->items
                ->filter(fn (QuoteItem $line): bool => filled($line->item_id) && $this->isManufacturingQuoteLine($line))
                ->values();

            if ($manufacturing->isEmpty()) {
                continue;
            }

            $anchor = $manufacturing->first(
                fn (QuoteItem $line): bool => ($line->item?->sku ?? '') === 'SERNA-SERVICIO'
            ) ?? $manufacturing->first();

            $total = round($manufacturing->sum(function (QuoteItem $line): float {
                $lineTotal = (float) ($line->line_total ?? 0);

                return $lineTotal > 0
                    ? $lineTotal
                    : (float) $line->quantity * (float) $line->unit_price;
            }), 2);

            $lines[] = [
                'item_id' => (int) $anchor->item_id,
                'description' => filled($piece->name)
                    ? (string) $piece->name
                    : (filled($anchor->description) ? (string) $anchor->description : 'Pieza'),
                'quantity' => 1.0,
                'unit_price' => $total,
                'quote_item_ids' => $manufacturing->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                'is_finished' => true,
            ];

            foreach ($manufacturing as $line) {
                $consumedIds[(int) $line->id] = true;
            }
        }

        // Transporte / instalación y líneas huérfanas (sin pieza).
        foreach ($this->items as $line) {
            if (isset($consumedIds[(int) $line->id]) || blank($line->item_id)) {
                continue;
            }

            // Manufactura sin pieza: una sola línea agregada del proyecto.
            if ($this->isManufacturingQuoteLine($line)) {
                continue;
            }

            $lines[] = [
                'item_id' => (int) $line->item_id,
                'description' => filled($line->description) ? (string) $line->description : null,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'quote_item_ids' => [(int) $line->id],
                'is_finished' => $line->item?->type === ItemType::ProductoTerminado,
            ];
        }

        // Si hubo manufactura sin piezas, agrupar en una línea de proyecto.
        $orphanMfg = $this->items->filter(
            fn (QuoteItem $line): bool => ! isset($consumedIds[(int) $line->id])
                && filled($line->item_id)
                && $this->isManufacturingQuoteLine($line)
        )->values();

        if ($orphanMfg->isNotEmpty()) {
            $anchor = $orphanMfg->first(
                fn (QuoteItem $line): bool => ($line->item?->sku ?? '') === 'SERNA-SERVICIO'
            ) ?? $orphanMfg->first();

            $total = round($orphanMfg->sum(function (QuoteItem $line): float {
                $lineTotal = (float) ($line->line_total ?? 0);

                return $lineTotal > 0
                    ? $lineTotal
                    : (float) $line->quantity * (float) $line->unit_price;
            }), 2);

            $lines[] = [
                'item_id' => (int) $anchor->item_id,
                'description' => filled($this->project_name)
                    ? (string) $this->project_name
                    : (filled($anchor->description) ? (string) $anchor->description : 'Manufactura Serna'),
                'quantity' => 1.0,
                'unit_price' => $total,
                'quote_item_ids' => $orphanMfg->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                'is_finished' => true,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{item_id: int, quantity: float, unit_price: float, quote_item_ids: list<int>, is_finished: bool}>
     */
    protected function saleLinesGroupedByCatalogItem(): array
    {
        $mergedLines = [];

        foreach ($this->items as $line) {
            $itemId = (int) $line->item_id;

            if (! isset($mergedLines[$itemId])) {
                $mergedLines[$itemId] = [
                    'item_id' => $itemId,
                    'description' => filled($line->description) ? (string) $line->description : null,
                    'quantity' => 0.0,
                    'unit_price' => (float) $line->unit_price,
                    'quote_item_ids' => [],
                    'is_finished' => $line->item?->type === ItemType::ProductoTerminado,
                ];
            }

            $mergedLines[$itemId]['quantity'] += (float) $line->quantity;
            $mergedLines[$itemId]['unit_price'] = (float) $line->unit_price;
            $mergedLines[$itemId]['quote_item_ids'][] = $line->id;
            $mergedLines[$itemId]['is_finished'] = $mergedLines[$itemId]['is_finished']
                || $line->item?->type === ItemType::ProductoTerminado;
        }

        return array_values($mergedLines);
    }

    protected function resolveCustomerIdForSale(): ?int
    {
        if (filled($this->customer_id)) {
            return (int) $this->customer_id;
        }

        if (filled($this->lead_id)) {
            $conversion = $this->lead->convertToCustomer();
            $customer = $conversion['customer'];

            $this->forceFill(['customer_id' => $customer->id])->saveQuietly();

            return $customer->id;
        }

        return null;
    }
}
