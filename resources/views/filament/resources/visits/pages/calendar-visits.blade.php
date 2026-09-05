<x-filament-panels::page>
    <div class="saas-calendar">
        <div class="saas-calendar-toolbar">
            <div class="saas-calendar-toolbar-left">
                <x-filament::button color="gray" wire:click="previousPeriod" icon="heroicon-m-chevron-left" size="sm">
                    Anterior
                </x-filament::button>
                <x-filament::button color="gray" wire:click="goToday" size="sm">
                    Hoy
                </x-filament::button>
                <x-filament::button color="gray" wire:click="nextPeriod" icon="heroicon-m-chevron-right" icon-position="after" size="sm">
                    Siguiente
                </x-filament::button>
            </div>

            <h2 class="saas-calendar-month">{{ ucfirst($this->periodLabel) }}</h2>

            <div class="saas-calendar-toolbar-right">
                <div class="saas-calendar-view-switch">
                    <button
                        type="button"
                        wire:click="setViewMode('month')"
                        @class(['saas-calendar-view-btn', 'is-active' => $viewMode === 'month'])
                    >Mes</button>
                    <button
                        type="button"
                        wire:click="setViewMode('week')"
                        @class(['saas-calendar-view-btn', 'is-active' => $viewMode === 'week'])
                    >Semana</button>
                    <button
                        type="button"
                        wire:click="setViewMode('day')"
                        @class(['saas-calendar-view-btn', 'is-active' => $viewMode === 'day'])
                    >Día</button>
                </div>
                <div class="saas-calendar-legend">
                    <span class="saas-calendar-legend-item saas-calendar-legend-item--warning">Programada</span>
                    <span class="saas-calendar-legend-item saas-calendar-legend-item--success">Realizada</span>
                    <span class="saas-calendar-legend-item saas-calendar-legend-item--danger">No asistió</span>
                    <span class="saas-calendar-legend-item saas-calendar-legend-item--gray">Cancelada</span>
                </div>
            </div>
        </div>

        @if ($viewMode === 'day')
            <div class="saas-calendar-dayview">
                @forelse ($this->dayVisits as $visit)
                    @php($contact = $visit->customer?->name ?? $visit->lead?->name ?? $visit->contactName())
                    <a
                        href="{{ \App\Filament\Resources\Visits\VisitResource::getUrl('edit', ['record' => $visit]) }}"
                        class="saas-calendar-dayview-item saas-calendar-event--{{ $this->eventColor($visit) }}"
                    >
                        <div class="saas-calendar-dayview-time">
                            {{ $visit->scheduled_at?->format('H:i') ?? '—' }}
                        </div>
                        <div class="saas-calendar-dayview-body">
                            <div class="saas-calendar-dayview-title">{{ $visit->subject }}</div>
                            <div class="saas-calendar-dayview-meta">
                                <span>{{ $contact }}</span>
                                <span>·</span>
                                <span>{{ $visit->type->getLabel() }}</span>
                                <span>·</span>
                                <span>{{ $visit->status->getLabel() }}</span>
                                @if ($visit->user)
                                    <span>·</span>
                                    <span>{{ $visit->user->name }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="saas-calendar-dayview-empty">
                        No hay contactos agendados para este día.
                    </div>
                @endforelse
            </div>
        @else
            <div class="saas-calendar-weekdays">
                @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)
                    <div class="saas-calendar-weekday">{{ $weekday }}</div>
                @endforeach
            </div>

            <div @class([
                'saas-calendar-grid',
                'saas-calendar-grid--week' => $viewMode === 'week',
            ])>
                @foreach ($this->days as $day)
                    <div @class([
                        'saas-calendar-day',
                        'saas-calendar-day--muted' => ! $day['inMonth'],
                        'saas-calendar-day--today' => $day['isToday'],
                        'saas-calendar-day--week' => $viewMode === 'week',
                    ])>
                        <div class="saas-calendar-day-number">
                            @if ($viewMode === 'week')
                                <span class="saas-calendar-day-weekday">{{ $day['date']->locale('es')->translatedFormat('D') }}</span>
                            @endif
                            {{ $day['date']->day }}
                        </div>

                        <div class="saas-calendar-events">
                            @foreach ($day['visits']->take($this->eventLimit()) as $visit)
                                @php($contact = $visit->customer?->name ?? $visit->lead?->name ?? $visit->contactName())
                                <a
                                    href="{{ \App\Filament\Resources\Visits\VisitResource::getUrl('edit', ['record' => $visit]) }}"
                                    class="saas-calendar-event saas-calendar-event--{{ $this->eventColor($visit) }}"
                                    title="{{ $visit->scheduled_at?->format('H:i') }} · {{ $visit->subject }} · {{ $contact }}"
                                >
                                    <span class="saas-calendar-event-time">{{ $visit->scheduled_at?->format('H:i') }}</span>
                                    <span class="saas-calendar-event-title">{{ $visit->subject }}</span>
                                    <span class="saas-calendar-event-contact">{{ $contact }}</span>
                                </a>
                            @endforeach

                            @if ($day['visits']->count() > $this->eventLimit())
                                <div class="saas-calendar-more">+{{ $day['visits']->count() - $this->eventLimit() }} más</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
