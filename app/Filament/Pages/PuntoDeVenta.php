<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Support\Tax;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PuntoDeVenta extends Page
{
    protected string $view = 'filament.pages.punto-de-venta';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $title = 'Punto de venta';

    protected static ?string $navigationLabel = 'Punto de venta';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 7;

    public string $search = '';

    public ?int $customerId = null;

    public ?int $warehouseId = null;

    /** @var array<int, array{item_id:int, sku:string, name:string, price:float, qty:float}> */
    public array $cart = [];

    public ?int $lastSaleId = null;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('Create:Sale')
            || $user->can('View:PuntoDeVenta');
    }

    public function mount(): void
    {
        $this->warehouseId = Warehouse::defaultId();
    }

    public function getProductsProperty(): Collection
    {
        return Item::query()
            ->where('is_active', true)
            ->when($this->search !== '', function ($q): void {
                $q->where(function ($sub): void {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function getCustomersProperty(): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getWarehousesProperty(): Collection
    {
        return Warehouse::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->cart)->sum(fn (array $line): float => (float) $line['price'] * (float) $line['qty']);
    }

    /**
     * @return array{subtotal: float, iva_rate: float, iva_amount: float, total: float}
     */
    public function getTaxBreakdownProperty(): array
    {
        return Tax::breakdown($this->subtotal);
    }

    public function getTotalProperty(): float
    {
        return (float) $this->taxBreakdown['total'];
    }

    public function addToCart(int $itemId): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['item_id'] === $itemId) {
                $this->cart[$i]['qty']++;

                return;
            }
        }

        $item = Item::find($itemId);

        if (! $item) {
            return;
        }

        $this->cart[] = [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'price' => (float) $item->price,
            'qty' => 1,
        ];
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function checkout(): void
    {
        $this->authorize('create', Sale::class);

        $lines = collect($this->cart)->filter(fn (array $l): bool => (float) $l['qty'] > 0);

        if ($lines->isEmpty()) {
            Notification::make()->title('El carrito está vacío.')->warning()->send();

            return;
        }

        if (filled($this->customerId)) {
            $customer = Customer::query()->find($this->customerId);

            if (! $customer?->hasBillingDocument()) {
                Notification::make()
                    ->title('Cliente incompleto')
                    ->body('Este cliente no tiene NIT/documento. Completa su información antes de registrar el pedido.')
                    ->danger()
                    ->send();

                return;
            }
        }

        try {
            $sale = Sale::create([
                'customer_id' => $this->customerId,
                'warehouse_id' => $this->warehouseId,
                'user_id' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $sale->items()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['qty'],
                    'unit_price' => $line['price'],
                ]);
            }

            $sale->confirm(Auth::id());
            $sale->refresh();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('No se pudo cobrar')
                ->body(collect($e->errors())->flatten()->first() ?: 'No se pudo completar la venta.')
                ->danger()
                ->send();

            return;
        }

        $this->cart = [];
        $this->customerId = null;
        $this->lastSaleId = $sale->id;

        Notification::make()
            ->title('Venta cobrada')
            ->body(($sale->invoice_number ?? $sale->code).' · Total '.money($sale->total))
            ->success()
            ->send();
    }
}
