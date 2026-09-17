<div class="korapp-job-workflow mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">Proceso del trabajo</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">Cotización → Orden de producción → Venta → Entrega</div>
    </div>
    <ol class="grid grid-cols-1 gap-0 sm:grid-cols-4">
        @foreach ($steps as $index => $step)
            @php
                $isDone = $step['state'] === 'done';
                $isCurrent = $step['state'] === 'current';
            @endphp
            <li @class([
                'relative flex gap-3 px-4 py-4',
                'bg-primary-50/70 dark:bg-primary-500/10' => $isCurrent,
                'border-t border-gray-100 dark:border-white/10 sm:border-t-0 sm:border-l' => $index > 0,
            ])>
                <div @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                    'bg-success-600 text-white' => $isDone,
                    'bg-primary-600 text-white' => $isCurrent,
                    'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $isDone && ! $isCurrent,
                ])>
                    @if ($isDone)
                        ✓
                    @else
                        {{ $index + 1 }}
                    @endif
                </div>
                <div class="min-w-0">
                    <div @class([
                        'text-sm font-semibold',
                        'text-gray-950 dark:text-white' => $isDone || $isCurrent,
                        'text-gray-500 dark:text-gray-400' => ! $isDone && ! $isCurrent,
                    ])>
                        {{ $step['label'] }}
                    </div>
                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $step['hint'] }}</div>
                </div>
            </li>
        @endforeach
    </ol>
</div>
