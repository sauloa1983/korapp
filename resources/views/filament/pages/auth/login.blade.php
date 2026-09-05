@php
    use App\Models\CompanySetting;

    try {
        $company = CompanySetting::current();
        $brandName = $company->name ?: config('app.name', 'Korapp');
        $logoUrl = $company->logoUrl();
        $tagline = $company->tagline ?: 'Inventario, ventas y producción en un solo lugar.';
    } catch (Throwable) {
        $brandName = config('app.name', 'Korapp');
        $logoUrl = null;
        $tagline = 'Inventario, ventas y producción en un solo lugar.';
    }
@endphp

<div class="saas-login">
    {{-- LEFT: brand panel --}}
    <aside class="saas-login-brand" aria-hidden="false">
        <div class="saas-login-brand-glow saas-login-brand-glow--1"></div>
        <div class="saas-login-brand-glow saas-login-brand-glow--2"></div>
        <div class="saas-login-brand-shapes" aria-hidden="true">
            <span class="saas-login-shape saas-login-shape--a"></span>
            <span class="saas-login-shape saas-login-shape--b"></span>
            <span class="saas-login-shape saas-login-shape--c"></span>
        </div>

        <div class="saas-login-brand-inner">
            <div class="saas-login-brand-logo">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" />
                @else
                    <span class="saas-login-brand-mark">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 7.5L12 4L18 7.5V16.5L12 20L6 16.5V7.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                            <path d="M12 12L18 8.5M12 12L6 8.5M12 12V20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                @endif
                @unless ($logoUrl)
                    <span>{{ $brandName }}</span>
                @endunless
            </div>

            <div class="saas-login-brand-copy">
                <h1>Gestiona tu negocio de forma inteligente</h1>
                <p>{{ $tagline }}</p>
            </div>

            <ul class="saas-login-brand-points">
                <li>Ventas y CRM en un solo flujo</li>
                <li>Inventario y producción al día</li>
                <li>Reportes claros para decidir mejor</li>
            </ul>
        </div>
    </aside>

    {{-- RIGHT: form card --}}
    <main class="saas-login-main">
        <div class="saas-login-card">
            <header class="saas-login-card-header">
                <h2>{{ $this->getHeading() }}</h2>
                @if (filled($sub = $this->getSubheading()))
                    <p>{{ $sub }}</p>
                @else
                    <p>Ingresa a tu cuenta</p>
                @endif
            </header>

            <div class="saas-login-card-body">
                {{ $this->content }}
            </div>
        </div>
    </main>
</div>
