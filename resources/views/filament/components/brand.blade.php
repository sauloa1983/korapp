@php
    use App\Models\CompanySetting;

    try {
        $company = CompanySetting::current();
        $name = $company->name ?: config('app.name', 'Korapp');
        $logoUrl = $company->logoUrl();
        $indigo = $company->usesIndigoSidebar();
    } catch (Throwable) {
        $name = config('app.name', 'Korapp');
        $logoUrl = null;
        $indigo = false;
    }
@endphp

<div class="saas-brand {{ $indigo ? 'saas-brand--indigo' : '' }}">
    @if ($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt="{{ $name }}"
            class="saas-brand-logo"
        />
    @else
        <span class="saas-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 7.5L12 4L18 7.5V16.5L12 20L6 16.5V7.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                <path d="M12 12L18 8.5M12 12L6 8.5M12 12V20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    @endif
    @unless ($logoUrl)
        <span class="saas-brand-copy">
            <span class="saas-brand-name">{{ $name }}</span>
        </span>
    @endunless
</div>
