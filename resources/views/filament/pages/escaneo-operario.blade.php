<x-filament-panels::page>
    <div class="mx-auto w-full max-w-xl space-y-6">
        <x-filament::section>
            <x-slot name="heading">Escanea o ingresa el código de la etapa</x-slot>
            <x-slot name="description">
                El primer escaneo inicia la etapa; el segundo la finaliza y calcula el tiempo.
            </x-slot>

            <form wire:submit="procesar" class="space-y-4">
                <input
                    type="text"
                    wire:model="token"
                    autofocus
                    placeholder="Pega la URL del QR o el token…"
                    class="fi-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />

                <x-filament::button type="submit" size="lg" class="w-full">
                    Procesar etapa
                </x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Mis últimos registros</x-slot>

            @php($logs = $this->getRecentLogs())

            @if ($logs->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Aún no has registrado etapas.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($logs as $log)
                        <div class="flex items-center justify-between gap-4 py-2 text-sm">
                            <div>
                                <div class="font-semibold">{{ $log->process->name }}</div>
                                <div class="text-gray-500 dark:text-gray-400">Orden {{ $log->productionOrder->code }}</div>
                            </div>
                            <div class="text-right">
                                <x-filament::badge :color="$log->status->getColor()">
                                    {{ $log->status->getLabel() }}
                                </x-filament::badge>
                                @if ($log->duration_for_humans)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $log->duration_for_humans }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
