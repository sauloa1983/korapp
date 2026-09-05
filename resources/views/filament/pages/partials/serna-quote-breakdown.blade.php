@php
    /** @var array<string, mixed>|null $result */
    /** @var string|null $error */
@endphp

@if ($error ?? null)
    <div class="acrylic-quote-empty acrylic-quote-empty--error">
        <p>{{ $error }}</p>
    </div>
@elseif (! $result)
    <div class="acrylic-quote-empty">
        <p class="acrylic-quote-empty__title">Sin piezas</p>
        <p class="acrylic-quote-empty__text">Agrega piezas e ítems a la propuesta para ver el resumen financiero.</p>
    </div>
@else
    <div class="acrylic-quote-summary">
        <div class="acrylic-quote-summary__hero">
            <p class="acrylic-quote-summary__hero-label">Total a pagar</p>
            <p class="acrylic-quote-summary__hero-value">{{ \App\Support\Money::format($result['total_payable']) }}</p>
            <p class="acrylic-quote-summary__hero-sub">
                {{ (int) ($result['piece_count'] ?? 0) }} {{ (int) ($result['piece_count'] ?? 0) === 1 ? 'pieza' : 'piezas' }}
                · {{ (int) $result['item_count'] }} {{ (int) $result['item_count'] === 1 ? 'ítem' : 'ítems' }}
            </p>
        </div>

        <div class="space-y-2" style="margin-top:1rem;font-size:.9rem;">
            <div style="display:flex;justify-content:space-between;gap:.75rem;">
                <span>Subtotal</span>
                <strong>{{ \App\Support\Money::format($result['subtotal']) }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;gap:.75rem;">
                <span>IVA ({{ number_format((float) $result['iva_rate'], 2, ',', '.') }}%)</span>
                <strong>{{ \App\Support\Money::format($result['iva_amount']) }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;gap:.75rem;">
                <span>Retefuente ({{ number_format((float) $result['withholding_rate'], 2, ',', '.') }}%)</span>
                <strong>- {{ \App\Support\Money::format($result['withholding_amount']) }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;gap:.75rem;border-top:1px solid #e5e7eb;padding-top:.5rem;">
                <span>Total documento</span>
                <strong>{{ \App\Support\Money::format($result['total']) }}</strong>
            </div>
        </div>

        @if (! empty($result['pieces']))
            <div style="margin-top:1rem;">
                <p style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin:0 0 .5rem;">Piezas</p>
                <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.55rem;">
                    @foreach ($result['pieces'] as $piece)
                        <li style="font-size:.8rem;">
                            <div style="display:flex;justify-content:space-between;gap:.75rem;font-weight:700;">
                                <span>{{ $piece['name'] }}</span>
                                <span>{{ \App\Support\Money::format($piece['subtotal']) }}</span>
                            </div>
                            <ul style="list-style:none;padding:.25rem 0 0 .5rem;margin:0;color:#4b5563;">
                                @foreach ($piece['items'] as $line)
                                    <li style="display:flex;justify-content:space-between;gap:.75rem;">
                                        <span>{{ $line['material'] ?: ($line['item_type_label'] ?? 'Ítem') }}</span>
                                        <span>{{ \App\Support\Money::format($line['line_total']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
