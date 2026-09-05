<x-filament-panels::page>
    @php($tax = $this->taxBreakdown)

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Catálogo --}}
        <x-filament::section>
            <x-slot name="heading">Productos</x-slot>

            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nombre o SKU…"
                class="fi-input mb-4 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            />

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @forelse ($this->products as $product)
                    <button
                        type="button"
                        wire:click="addToCart({{ $product->id }})"
                        class="flex flex-col rounded-lg border border-gray-200 p-3 text-left transition hover:border-primary-500 hover:bg-primary-50 dark:border-white/10 dark:hover:bg-white/5"
                    >
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }}</span>
                        <span class="font-semibold">{{ $product->name }}</span>
                        <span class="text-sm text-primary-600">{{ money($product->price) }}</span>
                    </button>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin resultados.</p>
                @endforelse
            </div>
        </x-filament::section>

        {{-- Carrito --}}
        <x-filament::section>
            <x-slot name="heading">Venta</x-slot>

            <div class="mb-4 grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                    <select wire:model="customerId" class="fi-input mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Público</option>
                        @foreach ($this->customers as $customer)
                            <option value="{{ $customer->id }}" @disabled(! $customer->hasBillingDocument())>
                                {{ $customer->name }}{{ $customer->hasBillingDocument() ? '' : ' (sin documento)' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Los clientes sin NIT/documento no pueden registrar pedidos.</p>
                </div>
            </div>

            @if (empty($cart))
                <p class="text-sm text-gray-500 dark:text-gray-400">Agrega productos desde el catálogo.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-1">Producto</th>
                            <th class="py-1 w-20 text-center">Cant.</th>
                            <th class="py-1 text-right">Subtotal</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cart as $index => $line)
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="py-1">
                                    <div class="font-medium">{{ $line['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ money($line['price']) }}</div>
                                </td>
                                <td class="py-1 text-center">
                                    <input type="number" min="0" step="1"
                                        wire:model.live="cart.{{ $index }}.qty"
                                        class="fi-input w-16 rounded-lg border-gray-300 text-center dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                </td>
                                <td class="py-1 text-right">{{ money((float) $line['price'] * (float) $line['qty']) }}</td>
                                <td class="py-1 text-right">
                                    <button type="button" wire:click="removeFromCart({{ $index }})" class="text-danger-600 hover:underline">Quitar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4 space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-white/10">
                    <div class="flex items-center justify-between">
                        <span>Subtotal</span>
                        <span>{{ money($tax['subtotal']) }}</span>
                    </div>
                    @if ($tax['iva_rate'] > 0)
                        <div class="flex items-center justify-between text-gray-600 dark:text-gray-300">
                            <span>IVA ({{ number_format($tax['iva_rate'], 2, ',', '.') }}%)</span>
                            <span>{{ money($tax['iva_amount']) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between pt-2 text-lg font-semibold">
                        <span>Total</span>
                        <span class="text-2xl font-bold">{{ money($tax['total']) }}</span>
                    </div>
                </div>

                <x-filament::button wire:click="checkout" size="lg" class="mt-4 w-full" icon="heroicon-o-banknotes">
                    Cobrar
                </x-filament::button>
            @endif

            @if ($lastSaleId)
                <div class="mt-4 rounded-lg bg-success-50 p-3 text-sm dark:bg-success-500/10">
                    Última venta registrada.
                    <a href="{{ route('sales.receipt', $lastSaleId) }}" target="_blank" class="font-semibold text-primary-600 hover:underline">
                        Ver comprobante
                    </a>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
