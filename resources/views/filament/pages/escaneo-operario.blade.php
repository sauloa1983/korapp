<x-filament-panels::page>
    <style>
        .korapp-escaneo {
            width: 100%;
            max-width: 48rem;
            margin-inline: auto;
        }

        .korapp-escaneo__form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            padding-top: 0.25rem;
        }

        .korapp-escaneo__input {
            min-height: 3.25rem;
            padding: 0.85rem 1rem !important;
            font-size: 1.05rem !important;
            border-radius: 0.75rem !important;
        }

        .korapp-escaneo__submit {
            min-height: 3.25rem;
            font-size: 1rem !important;
            font-weight: 600 !important;
        }

        .korapp-escaneo__logs > li {
            padding-block: 0.9rem;
        }

        .korapp-escaneo__logs > li:first-child {
            padding-top: 0;
        }

        .korapp-escaneo__logs > li:last-child {
            padding-bottom: 0;
        }
    </style>

    <div class="korapp-escaneo space-y-6">
        <x-filament::section>
            <x-slot name="heading">Escanea o ingresa el código</x-slot>
            <x-slot name="description">
                El primer escaneo inicia la etapa; el segundo la finaliza y calcula el tiempo.
            </x-slot>

            <form wire:submit="procesar" class="korapp-escaneo__form">
                <div>
                    <label for="escaneo-token" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Código / URL del QR
                    </label>
                    <input
                        id="escaneo-token"
                        type="text"
                        wire:model="token"
                        autofocus
                        autocomplete="off"
                        inputmode="text"
                        placeholder="Pega la URL del QR o el token…"
                        class="fi-input korapp-escaneo__input block w-full border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    />
                </div>

                <x-filament::button type="submit" size="lg" class="korapp-escaneo__submit w-full justify-center">
                    Procesar etapa
                </x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Mis últimos registros</x-slot>
            <x-slot name="description">
                Retroalimentación inmediata de las etapas que has iniciado o terminado.
            </x-slot>

            @php
                try {
                    $logs = $this->getRecentLogs();
                } catch (\Throwable $e) {
                    report($e);
                    $logs = collect();
                }
            @endphp

            @if ($logs->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Aún no has registrado etapas. Escanea un QR para comenzar.
                </p>
            @else
                <ul class="korapp-escaneo__logs divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($logs as $log)
                        <li class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $log->process?->name ?? 'Etapa' }}
                                </div>
                                <div class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Orden {{ $log->productionOrder?->code ?? '—' }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($log->status)
                                    <x-filament::badge :color="$log->status->getColor()">
                                        {{ $log->status->getLabel() }}
                                    </x-filament::badge>
                                @endif
                                @if ($log->status === \App\Enums\ProductionLogStatus::Procesando && $log->started_at)
                                    <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        Desde {{ $log->started_at->format('d/m H:i') }}
                                    </div>
                                @elseif ($log->duration_for_humans)
                                    <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $log->duration_for_humans }}
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
