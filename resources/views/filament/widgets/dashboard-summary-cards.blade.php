@php
    use function Filament\Support\generate_icon_html;

    $cards = $this->getCards();
@endphp

<x-filament-widgets::widget>
    <div class="saas-summary-grid">
        @foreach ($cards as $card)
            <div class="saas-summary-card saas-summary-card--{{ $card['tone'] }}">
                <div class="saas-summary-card-icon">
                    {{ generate_icon_html($card['icon']) }}
                </div>
                <div class="saas-summary-card-body">
                    <p class="saas-summary-card-label">{{ $card['label'] }}</p>
                    <p class="saas-summary-card-value">{{ $card['value'] }}</p>
                    <p class="saas-summary-card-hint">{{ $card['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
