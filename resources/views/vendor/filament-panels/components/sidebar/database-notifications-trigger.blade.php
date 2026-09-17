@props([
    'unreadNotificationsCount' => 0,
])

<button
    type="button"
    class="fi-sidebar-database-notifications-btn"
    title="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
    aria-label="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
>
    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedBell, alias: \Filament\View\PanelsIconAlias::SIDEBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON, size: \Filament\Support\Enums\IconSize::Large) }}

    @if ($unreadNotificationsCount)
        <span class="fi-sidebar-database-notifications-btn-badge-ctn">
            <x-filament::badge size="xs">
                {{ $unreadNotificationsCount }}
            </x-filament::badge>
        </span>
    @endif
</button>
