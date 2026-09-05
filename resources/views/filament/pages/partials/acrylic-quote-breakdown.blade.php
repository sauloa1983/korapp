@php
    /** @var array<string, mixed>|null $result */
    /** @var string|null $error */

    $helpIcon = static function (string $text): string {
        return '<button type="button" class="acrylic-help" title="'.e($text).'" aria-label="'.e($text).'">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
            .'<path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0ZM8.94 6.94a.75.75 0 1 1-1.061-1.061 3 3 0 1 1 2.871 5.026v.345a.75.75 0 0 1-1.5 0v-.5c0-.72.57-1.172 1.081-1.387A1.5 1.5 0 1 0 8.94 6.94ZM10 15a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />'
            .'</svg></button>';
    };
@endphp

@if ($error ?? null)
    <div class="acrylic-quote-empty acrylic-quote-empty--error">
        <p>{{ $error }}</p>
    </div>
@elseif (! $result)
    <div class="acrylic-quote-empty">
        <div class="acrylic-quote-empty__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V15Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V19.5Zm2.25-6.75h.008v.008H10.5v-.008Zm0 2.25h.008v.008H10.5V15Zm0 2.25h.008v.008H10.5v-.008Zm0 2.25h.008v.008H10.5V19.5Zm2.25-6.75h.008v.008H12.75v-.008Zm0 2.25h.008v.008H12.75V15Zm0 2.25h.008v.008H12.75v-.008Zm0 2.25h.008v.008H12.75V19.5Zm2.25-6.75h.008v.008H15v-.008Zm0 2.25h.008v.008H15V15Zm0 2.25h.008v.008H15v-.008Zm0 2.25h.008v.008H15V19.5Zm2.25-6.75h.008v.008H17.25v-.008Zm0 2.25h.008v.008H17.25V15Zm0 2.25h.008v.008H17.25v-.008Zm0 2.25h.008v.008H17.25V19.5ZM6 21.75h12A2.25 2.25 0 0 0 20.25 19.5V8.456c0-.384-.148-.753-.413-1.028L15.322 2.413A1.875 1.875 0 0 0 13.964 1.875H6A2.25 2.25 0 0 0 3.75 4.125v15.375A2.25 2.25 0 0 0 6 21.75Z" />
            </svg>
        </div>
        <p class="acrylic-quote-empty__title">Aún no hay cotización</p>
        <p class="acrylic-quote-empty__text">Completa medidas, base, letras e iluminación para ver el desglose en vivo.</p>
    </div>
@else
    @php
        $costs = $result['costs'];
        $layers = $result['layers'];
        $tax = $result['tax'];
        $qty = (int) $result['quantity'];
    @endphp

    <div class="acrylic-quote-summary">
        <div class="acrylic-quote-summary__hero">
            <p class="acrylic-quote-summary__hero-label">
                Total con IVA
                {!! $helpIcon('Precio final al cliente: subtotal de la línea (sin IVA) + IVA de la empresa.') !!}
            </p>
            <p class="acrylic-quote-summary__hero-value">{{ \App\Support\Money::format($tax['total']) }}</p>
            <p class="acrylic-quote-summary__hero-sub">
                {{ $qty }} {{ $qty === 1 ? 'aviso' : 'avisos' }}
                · unitario {{ \App\Support\Money::format($costs['unit_price_ex_iva']) }} sin IVA
            </p>
        </div>

        <div class="acrylic-quote-summary__metrics">
            <div class="acrylic-quote-metric">
                <span class="acrylic-quote-metric__label">
                    Área
                    {!! $helpIcon('Área del aviso completo: (ancho ÷ 100) × (alto ÷ 100), en m².') !!}
                </span>
                <span class="acrylic-quote-metric__value">{{ number_format($result['area_m2'], 3, ',', '.') }} <small>m²</small></span>
            </div>
            <div class="acrylic-quote-metric">
                <span class="acrylic-quote-metric__label">
                    Perímetro
                    {!! $helpIcon('Contorno del aviso: 2 × (ancho_m + alto_m). Se usa para LED perimetral y metros de corte estimados.') !!}
                </span>
                <span class="acrylic-quote-metric__value">{{ number_format($result['perimeter_m'], 3, ',', '.') }} <small>m</small></span>
            </div>
            <div class="acrylic-quote-metric">
                <span class="acrylic-quote-metric__label">
                    Letras
                    {!! $helpIcon(($result['has_base'] ?? true)
                        ? 'Área estimada de letras/logo: área del aviso × % de cobertura. No es el área de la placa completa.'
                        : 'Área envolvente de las letras individuales (sin placa base). Aquí equivale al 100% de las medidas.') !!}
                </span>
                <span class="acrylic-quote-metric__value">{{ number_format($result['lettering']['letter_area_m2'] ?? 0, 3, ',', '.') }} <small>m²</small></span>
            </div>
        </div>

        @if (! empty($result['sign_type_label']))
            <p class="acrylic-quote-summary__desc" style="margin-top:0">
                Tipo: <strong>{{ $result['sign_type_label'] }}</strong>
                @if (! ($result['has_base'] ?? true))
                    · sin base/fondo
                @endif
            </p>
        @endif

        @php
            $letterCheck = $result['lettering']['per_letter_check'] ?? null;
            $logoCheck = $result['logo']['size_check'] ?? null;
        @endphp
        @if ($letterCheck || $logoCheck)
            <div class="acrylic-quote-summary__block">
                <p class="acrylic-quote-summary__block-title">Comprobación de medidas</p>

                @if ($letterCheck)
                    <div class="acrylic-quote-check">
                        <p class="acrylic-quote-check__title">Letras</p>
                        <p class="acrylic-quote-check__hint">
                            @if ($result['lettering']['separate_sizes'] ?? false)
                                {{ number_format($result['lettering']['per_letter_width_cm'] ?? $letterCheck['estimated_width_cm'], 1, ',', '.') }}
                                × {{ number_format($result['lettering']['per_letter_height_cm'] ?? $letterCheck['estimated_height_cm'], 1, ',', '.') }} cm por letra
                                · total {{ number_format($result['lettering']['width_cm'] ?? 0, 1, ',', '.') }} cm
                                ({{ $letterCheck['letter_count'] }} letras)
                            @elseif ($letterCheck['logo_reduces_letters'] ?? false)
                                Zona letras {{ number_format($letterCheck['letter_zone_width_cm'], 1, ',', '.') }} cm
                                (aviso {{ number_format($letterCheck['sign_width_cm'], 0, ',', '.') }} − logo {{ number_format($letterCheck['logo_reserved_width_cm'], 1, ',', '.') }})
                                ÷ {{ $letterCheck['letter_count'] }}
                            @else
                                Ancho aviso {{ number_format($letterCheck['sign_width_cm'], 0, ',', '.') }} cm
                                ÷ {{ $letterCheck['letter_count'] }} letras
                            @endif
                        </p>
                        <div class="acrylic-quote-check__sizes">
                            <div class="acrylic-quote-check__size">
                                <span class="acrylic-quote-check__size-label">Ancho</span>
                                <span class="acrylic-quote-check__size-value">{{ number_format($letterCheck['estimated_width_cm'], 1, ',', '.') }} <small>cm</small></span>
                            </div>
                            <div class="acrylic-quote-check__size">
                                <span class="acrylic-quote-check__size-label">Alto</span>
                                <span class="acrylic-quote-check__size-value">{{ number_format($letterCheck['estimated_height_cm'], 1, ',', '.') }} <small>cm</small></span>
                            </div>
                        </div>
                        <dl class="acrylic-quote-rows">
                            <div class="acrylic-quote-row">
                                <dt>Tarifa catálogo ($/letra)</dt>
                                <dd>{{ \App\Support\Money::format($letterCheck['catalog_price_per_letter']) }}</dd>
                            </div>
                            <div class="acrylic-quote-row">
                                <dt>Parte área/corte / letra</dt>
                                <dd>{{ \App\Support\Money::format($letterCheck['surface_share_per_letter']) }}</dd>
                            </div>
                            <div class="acrylic-quote-row acrylic-quote-row--sub">
                                <dt>Costo promedio / letra</dt>
                                <dd>{{ \App\Support\Money::format($letterCheck['avg_cost_per_letter']) }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                @if ($logoCheck)
                    <div class="acrylic-quote-check acrylic-quote-check--logo">
                        <p class="acrylic-quote-check__title">
                            {{ ($logoCheck['logo_only'] ?? false) ? 'Solo logo' : 'Logo' }}
                        </p>
                        <p class="acrylic-quote-check__hint">
                            @if ($result['logo']['separate_sizes'] ?? false)
                                Medidas propias del logo (sin placa) ·
                                {{ number_format($logoCheck['area_m2'], 3, ',', '.') }} m²
                            @elseif ($logoCheck['linked_to_letter_height'] ?? false)
                                Alto = letras ({{ number_format($logoCheck['height_cm'], 1, ',', '.') }} cm) ·
                                {{ number_format($logoCheck['coverage_percent'], 1, ',', '.') }}% del ancho del aviso
                                ({{ number_format($logoCheck['area_m2'], 3, ',', '.') }} m²)
                            @elseif ($logoCheck['logo_only'] ?? false)
                                Medidas del aviso = tamaño del logo ·
                                {{ number_format($logoCheck['coverage_percent'], 1, ',', '.') }}% del área cobrada
                                ({{ number_format($logoCheck['area_m2'], 3, ',', '.') }} m²)
                            @else
                                {{ number_format($logoCheck['coverage_percent'], 1, ',', '.') }}% ·
                                {{ number_format($logoCheck['area_m2'], 3, ',', '.') }} m²
                            @endif
                        </p>
                        <div class="acrylic-quote-check__sizes">
                            <div class="acrylic-quote-check__size">
                                <span class="acrylic-quote-check__size-label">Ancho</span>
                                <span class="acrylic-quote-check__size-value">{{ number_format($logoCheck['width_cm'], 1, ',', '.') }} <small>cm</small></span>
                            </div>
                            <div class="acrylic-quote-check__size">
                                <span class="acrylic-quote-check__size-label">Alto</span>
                                <span class="acrylic-quote-check__size-value">{{ number_format($logoCheck['height_cm'], 1, ',', '.') }} <small>cm</small></span>
                            </div>
                        </div>
                        <dl class="acrylic-quote-rows">
                            <div class="acrylic-quote-row acrylic-quote-row--sub">
                                <dt>Costo del logo</dt>
                                <dd>{{ \App\Support\Money::format($logoCheck['amount']) }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif
            </div>
        @endif

        <div class="acrylic-quote-summary__block">
            <p class="acrylic-quote-summary__block-title">Capas de costo</p>
            <dl class="acrylic-quote-rows">
                <div class="acrylic-quote-row">
                    <dt>1 · {{ $layers['base']['label'] }}</dt>
                    <dd>{{ \App\Support\Money::format($layers['base']['amount']) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>2 · Letras</dt>
                    <dd>{{ \App\Support\Money::format($layers['lettering']['letters'] ?? $result['costs']['letters'] ?? $layers['lettering']['amount']) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>2 · Logo</dt>
                    <dd>{{ \App\Support\Money::format($layers['lettering']['logo'] ?? $result['costs']['logo'] ?? 0) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>3 · {{ $layers['assembly']['label'] }}</dt>
                    <dd>{{ \App\Support\Money::format($layers['assembly']['amount']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="acrylic-quote-summary__block">
            <p class="acrylic-quote-summary__block-title">Detalle capa 3</p>
            <dl class="acrylic-quote-rows">
                <div class="acrylic-quote-row">
                    <dt>Iluminación</dt>
                    <dd>{{ \App\Support\Money::format($layers['assembly']['lighting']) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>Accesorios</dt>
                    <dd>{{ \App\Support\Money::format($layers['assembly']['accessories']) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>Ensamble / mano de obra</dt>
                    <dd>{{ \App\Support\Money::format($layers['assembly']['labor']) }}</dd>
                </div>
                <div class="acrylic-quote-row acrylic-quote-row--sub">
                    <dt>Subtotal costos</dt>
                    <dd>{{ \App\Support\Money::format($costs['subtotal']) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>Margen ({{ number_format($costs['margin_percent'], 1, ',', '.') }}%)</dt>
                    <dd>{{ \App\Support\Money::format($costs['margin_amount']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="acrylic-quote-summary__unit">
            <span>Precio unitario sin IVA</span>
            <strong>{{ \App\Support\Money::format($costs['unit_price_ex_iva']) }}</strong>
        </div>

        <div class="acrylic-quote-summary__block">
            <p class="acrylic-quote-summary__block-title">Impuestos</p>
            <dl class="acrylic-quote-rows">
                <div class="acrylic-quote-row">
                    <dt>Subtotal línea</dt>
                    <dd>{{ \App\Support\Money::format($costs['line_subtotal_ex_iva']) }}</dd>
                </div>
                <div class="acrylic-quote-row">
                    <dt>IVA ({{ number_format($tax['iva_rate'], 0, ',', '.') }}%)</dt>
                    <dd>{{ \App\Support\Money::format($tax['iva_amount']) }}</dd>
                </div>
            </dl>
        </div>

        <p class="acrylic-quote-summary__desc">{{ $result['description'] }}</p>

        @if (($showCalcTrace ?? false) && ! empty($result['calc_trace']['items']))
            @php
                $traceItems = $result['calc_trace']['items'];
                $traceGroups = collect($traceItems)->groupBy('group');
            @endphp
            <div class="acrylic-quote-summary__block acrylic-quote-trace">
                <p class="acrylic-quote-summary__block-title">Desglose de cálculo</p>
                <p class="acrylic-quote-trace__note">
                    Fórmula de cada ítem para revisar cómo se arma el precio.
                </p>
                @foreach ($traceGroups as $group => $groupItems)
                    <div class="acrylic-quote-trace__group">
                        <p class="acrylic-quote-trace__group-title">{{ $group }}</p>
                        <ul class="acrylic-quote-trace__list">
                            @foreach ($groupItems as $item)
                                <li class="acrylic-quote-trace__item">
                                    <div class="acrylic-quote-trace__item-head">
                                        <span class="acrylic-quote-trace__label">{{ $item['label'] }}</span>
                                        <strong class="acrylic-quote-trace__amount">{{ \App\Support\Money::format($item['amount'] ?? 0) }}</strong>
                                    </div>
                                    <code class="acrylic-quote-trace__formula">{{ $item['formula'] }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
