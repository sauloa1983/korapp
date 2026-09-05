@php
    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
    $title = trim(strip_tags($livewire?->getTitle() ?? ''));
    $brandName = trim(strip_tags(filament()->getBrandName()));
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ __('filament-panels::layout.direction') ?? 'ltr' }}"
    class="fi"
    data-theme="light"
    style="color-scheme: light;"
>
    <head>
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_START, scopes: $renderHookScopes) }}

        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="color-scheme" content="light only" />

        @if ($favicon = filament()->getFavicon())
            <link rel="icon" href="{{ $favicon }}" />
        @endif

        <title>
            {{ filled($title) ? $title : null }}
            {{ filled($brandName) && filled($title) ? ' - ' : null }}
            {{ filled($brandName) ? $brandName : null }}
        </title>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_BEFORE, scopes: $renderHookScopes) }}

        @filamentStyles
        {{ filament()->getTheme()->getHtml() }}
        {{ filament()->getFontPreloadHtml() }}
        {{ filament()->getFontHtml() }}

        <style>
            :root {
                --font-family: '{!! filament()->getFontFamily() !!}';
                --default-theme-mode: light;
                color-scheme: light;
            }

            /* Labels del login siempre legibles (sin JS) */
            .saas-login-card,
            .saas-login-card-body,
            html.dark .saas-login-card,
            html.dark .saas-login-card-body {
                color: #0f172a !important;
            }

            .saas-login-card-header h2,
            html.dark .saas-login-card-header h2 {
                color: #0f172a !important;
            }

            .saas-login-card-header p,
            html.dark .saas-login-card-header p,
            .saas-login-forgot,
            html.dark .saas-login-forgot {
                color: #64748b !important;
            }

            .saas-login-card-body .fi-fo-field-wrp-label,
            .saas-login-card-body .fi-fo-label,
            .saas-login-card-body label,
            .saas-login-card-body .fi-checkbox-label,
            .saas-login-card-body .fi-fo-field-wrp-hint,
            .saas-login-card-body .fi-fo-field-wrp-label *,
            .saas-login-card-body .fi-fo-field-wrp-label span,
            html.dark .saas-login-card-body .fi-fo-field-wrp-label,
            html.dark .saas-login-card-body .fi-fo-label,
            html.dark .saas-login-card-body label,
            html.dark .saas-login-card-body .fi-checkbox-label,
            html.dark .saas-login-card-body .fi-fo-field-wrp-hint,
            html.dark .saas-login-card-body .fi-fo-field-wrp-label *,
            html.dark .saas-login-card-body .fi-fo-field-wrp-label span {
                color: #0f172a !important;
                -webkit-text-fill-color: #0f172a !important;
            }

            @media (prefers-color-scheme: dark) {
                .saas-login-card-body .fi-fo-field-wrp-label,
                .saas-login-card-body .fi-fo-label,
                .saas-login-card-body label,
                .saas-login-card-body .fi-checkbox-label,
                .saas-login-card-body .fi-fo-field-wrp-label *,
                .saas-login-card-body .fi-fo-field-wrp-label span {
                    color: #0f172a !important;
                    -webkit-text-fill-color: #0f172a !important;
                }
            }
        </style>

        @stack('styles')
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_AFTER, scopes: $renderHookScopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_END, scopes: $renderHookScopes) }}
    </head>

    <body
        class="fi-body fi-panel-{{ filament()->getId() }} saas-login-body"
        data-theme="light"
        style="color-scheme: light;"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_START, scopes: $renderHookScopes) }}

        {{ $slot }}

        @livewire(Filament\Livewire\Notifications::class)

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_BEFORE, scopes: $renderHookScopes) }}
        @filamentScripts(withCore: true)
        @stack('scripts')
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER, scopes: $renderHookScopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_END, scopes: $renderHookScopes) }}

        <script>
            // Solo quita "dark" si aparece; no reescribe style (evita bucle infinito).
            (function () {
                var root = document.documentElement;
                if (root.classList.contains('dark')) {
                    root.classList.remove('dark');
                }
                document.addEventListener('livewire:navigated', function () {
                    document.documentElement.classList.remove('dark');
                });
            })();
        </script>
    </body>
</html>
