<x-filament-panels::page>
    <div
        class="saas-pipeline"
        x-data="{
            draggingId: null,
            onDragStart(event, id) {
                this.draggingId = id
                event.dataTransfer.effectAllowed = 'move'
                event.dataTransfer.setData('text/plain', String(id))
            },
            onDrop(event, stage) {
                event.preventDefault()
                const id = Number(event.dataTransfer.getData('text/plain') || this.draggingId)
                if (! id) return
                $wire.moveLead(id, stage)
                this.draggingId = null
            }
        }"
    >
        <div class="saas-pipeline-legend">
            <span>Arrastra entre columnas para avanzar</span>
            <span><strong>Ganar</strong> solo en Propuesta o Negociación · <strong>Perder</strong> en cualquier etapa</span>
        </div>

        <div class="saas-pipeline-board">
            @foreach (\App\Enums\LeadStage::openCases() as $index => $stage)
                @php
                    $cards = $this->columns[$stage->value] ?? collect();
                    $columnTotal = $cards->sum(fn ($lead) => (float) $lead->value);
                @endphp
                <section
                    class="saas-pipeline-column saas-pipeline-column--{{ $stage->value }}"
                    data-stage="{{ $stage->value }}"
                    x-on:dragover.prevent
                    x-on:drop="onDrop($event, @js($stage->value))"
                >
                    <header class="saas-pipeline-column-header">
                        <div class="saas-pipeline-column-title">
                            <span class="saas-pipeline-step">{{ $index + 1 }}</span>
                            <div>
                                <span class="saas-pipeline-column-name">{{ $stage->getLabel() }}</span>
                                <span class="saas-pipeline-column-sum">{{ money($columnTotal) }}</span>
                            </div>
                        </div>
                        <span class="saas-pipeline-count">{{ $cards->count() }}</span>
                    </header>

                    <div class="saas-pipeline-cards">
                        @forelse ($cards as $lead)
                            <article
                                class="saas-pipeline-card"
                                draggable="true"
                                x-on:dragstart="onDragStart($event, {{ $lead->id }})"
                                wire:key="lead-{{ $lead->id }}"
                            >
                                <h3 class="saas-pipeline-card-name">{{ $lead->name }}</h3>
                                @if ($lead->company)
                                    <p class="saas-pipeline-card-company">{{ $lead->company }}</p>
                                @endif

                                <div class="saas-pipeline-card-meta">
                                    <span class="saas-pipeline-card-value">
                                        {{ money($lead->value) }}
                                    </span>
                                    <span class="saas-pipeline-card-owner {{ $lead->user ? '' : 'saas-pipeline-card-owner--unassigned' }}">
                                        {{ $lead->user?->name ?? 'Sin asignar' }}
                                    </span>
                                </div>

                                @if ($lead->matchingCustomer())
                                    <p class="saas-pipeline-card-match">
                                        Ya existe en Clientes · al ganar se vincula
                                    </p>
                                @endif

                                <div class="saas-pipeline-card-actions" x-on:mousedown.stop x-on:dragstart.stop.prevent>
                                    @if ($stage->canMarkWon())
                                        <button
                                            type="button"
                                            class="saas-pipeline-btn saas-pipeline-btn--won"
                                            wire:click="moveLead({{ $lead->id }}, 'won')"
                                            wire:confirm="¿Convertir a cliente y sacar del embudo?"
                                        >
                                            Ganar
                                        </button>
                                    @endif
                                    @if ($stage->canMarkLost())
                                        <button
                                            type="button"
                                            class="saas-pipeline-btn saas-pipeline-btn--lost {{ $stage->canMarkWon() ? '' : 'saas-pipeline-btn--full' }}"
                                            wire:click="moveLead({{ $lead->id }}, 'lost')"
                                            wire:confirm="¿Marcar como perdido?"
                                        >
                                            Perder
                                        </button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="saas-pipeline-empty">Arrastra aquí</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        @if ($this->recentConversions->isNotEmpty())
            <section class="saas-pipeline-recent">
                <div class="saas-pipeline-recent-header">
                    <div>
                        <h3>Últimos 5 clientes ganados</h3>
                        <p class="saas-pipeline-recent-note">Resumen del embudo · historial completo en Prospectos → Convertidos</p>
                    </div>
                    <a class="saas-pipeline-recent-link" href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('index', ['activeTab' => 'converted']) }}">
                        Ver historial
                    </a>
                </div>

                <div class="saas-pipeline-recent-table" role="table">
                    <div class="saas-pipeline-recent-row saas-pipeline-recent-row--head" role="row">
                        <span>Cliente</span>
                        <span>Vendedor</span>
                        <span>Valor</span>
                        <span>Cuándo</span>
                        <span></span>
                    </div>

                    @foreach ($this->recentConversions as $lead)
                        <div class="saas-pipeline-recent-row" role="row">
                            <div class="saas-pipeline-recent-client">
                                <span class="saas-pipeline-recent-avatar" aria-hidden="true">{{ $lead->initials ?: '?' }}</span>
                                <div>
                                    <strong>{{ $lead->customer?->name ?? $lead->name }}</strong>
                                    @if ($lead->company && $lead->company !== ($lead->customer?->name ?? $lead->name))
                                        <small>{{ $lead->company }}</small>
                                    @endif
                                </div>
                            </div>
                            <span class="saas-pipeline-recent-seller">{{ $lead->user?->name ?? 'Sin vendedor' }}</span>
                            <span class="saas-pipeline-recent-value">{{ money($lead->value) }}</span>
                            <span class="saas-pipeline-recent-when">
                                {{ $lead->converted_at?->format('d/m/Y') ?? '—' }}
                                @if ($lead->converted_at)
                                    <small>{{ $lead->converted_at->diffForHumans() }}</small>
                                @endif
                            </span>
                            <div class="saas-pipeline-recent-action">
                                @if ($lead->customer_id)
                                    <a href="{{ \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $lead->customer_id]) }}">
                                        Abrir
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
