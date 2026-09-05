@php
    $clients = $this->getTopClients();
    $sellers = $this->getTopSellers();
@endphp

<x-filament-widgets::widget>
    <div class="saas-rankings">
        <section class="saas-rankings-panel">
            <header class="saas-rankings-header">
                <h3>Mejores clientes</h3>
                <p>Últimos 90 días</p>
            </header>
            <ul class="saas-rankings-list">
                @forelse ($clients as $i => $client)
                    <li>
                        <span class="saas-rankings-index">{{ $i + 1 }}</span>
                        <div class="saas-rankings-main">
                            <strong>{{ $client->name }}</strong>
                            <span>{{ $client->deals }} ventas</span>
                        </div>
                        <span class="saas-rankings-value">{{ money($client->revenue) }}</span>
                    </li>
                @empty
                    <li class="saas-rankings-empty">Sin datos aún</li>
                @endforelse
            </ul>
        </section>

        <section class="saas-rankings-panel">
            <header class="saas-rankings-header">
                <h3>Mejores vendedores</h3>
                <p>Últimos 90 días</p>
            </header>
            <ul class="saas-rankings-list">
                @forelse ($sellers as $i => $seller)
                    <li>
                        <span class="saas-rankings-index">{{ $i + 1 }}</span>
                        <div class="saas-rankings-main">
                            <strong>{{ $seller->name }}</strong>
                            <span>{{ $seller->deals }} venta{{ $seller->deals === 1 ? '' : 's' }}</span>
                        </div>
                        <span class="saas-rankings-value">{{ money($seller->revenue) }}</span>
                    </li>
                @empty
                    <li class="saas-rankings-empty">Sin datos aún</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-filament-widgets::widget>
