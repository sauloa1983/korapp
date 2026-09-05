<x-filament-panels::page>
    <form wire:submit.prevent class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
            <input type="date" wire:model.live="from"
                class="fi-input mt-1 rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
            <input type="date" wire:model.live="to"
                class="fi-input mt-1 rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
        </div>
    </form>

    @php($summary = $this->getSummary())

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Ventas confirmadas</div>
            <div class="mt-1 text-3xl font-bold">{{ $summary['count'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Ingresos</div>
            <div class="mt-1 text-3xl font-bold">{{ money($summary['total']) }}</div>
            @if ($summary['growth_percent'] !== null)
                @php($growth = $summary['growth_percent'])
                <div @class([
                    'mt-1 text-sm font-medium',
                    'text-success-600' => $growth >= 0,
                    'text-danger-600' => $growth < 0,
                ])>
                    {{ $growth >= 0 ? '+' : '' }}{{ number_format($growth, 1, ',', '.') }}% vs periodo anterior
                    <span class="font-normal text-gray-500 dark:text-gray-400">({{ money($summary['previous_total']) }})</span>
                </div>
            @else
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sin periodo anterior comparable</div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Ticket promedio</div>
            <div class="mt-1 text-3xl font-bold">{{ money($summary['avg_ticket']) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">IVA cobrado</div>
            <div class="mt-1 text-3xl font-bold">{{ money($summary['iva']) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Clientes distintos</div>
            <div class="mt-1 text-3xl font-bold">{{ $summary['customers'] }}</div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Con venta confirmada en el rango</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Cotizaciones → ventas</div>
            <div class="mt-1 text-3xl font-bold">
                {{ $summary['quotes_converted'] }}
                <span class="text-lg font-semibold text-gray-500 dark:text-gray-400">/ {{ $summary['quotes_accepted'] }}</span>
            </div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Cotizaciones aceptadas con venta
                @if ($summary['quote_to_sale_percent'] !== null)
                    · {{ number_format($summary['quote_to_sale_percent'], 1, ',', '.') }}%
                @endif
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Ventas por día</x-slot>
            @php($rows = $this->getSalesByDay())
            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Sin ventas en el rango.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-1">Fecha</th>
                            <th class="py-1 text-right">Ventas</th>
                            <th class="py-1 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="py-1">{{ $row['date'] }}</td>
                                <td class="py-1 text-right">{{ $row['count'] }}</td>
                                <td class="py-1 text-right">{{ money($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Resumen por vendedor</x-slot>
            <x-slot name="description">Ventas confirmadas del rango, con ticket promedio.</x-slot>
            @php($sellers = $this->getSalesBySeller())
            @if ($sellers->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Sin ventas en el rango.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-1">Vendedor</th>
                            <th class="py-1 text-right">Ventas</th>
                            <th class="py-1 text-right">Ticket prom.</th>
                            <th class="py-1 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sellers as $row)
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="py-1">{{ $row['seller'] }}</td>
                                <td class="py-1 text-right">{{ $row['count'] }}</td>
                                <td class="py-1 text-right">{{ money($row['avg_ticket']) }}</td>
                                <td class="py-1 text-right">{{ money($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
