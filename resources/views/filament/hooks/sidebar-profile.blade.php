@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();
    $name = $user?->name ?? 'Usuario';
    $role = method_exists($user, 'getRoleNames') && $user?->getRoleNames()->isNotEmpty()
        ? str($user->getRoleNames()->first())->replace('_', ' ')->title()
        : 'Administrador';
    $initials = collect(explode(' ', $name))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

@if ($user)
    <div class="saas-sidebar-profile">
        <div class="saas-sidebar-profile-avatar" aria-hidden="true">{{ $initials }}</div>
        <div class="saas-sidebar-profile-meta">
            <p class="saas-sidebar-profile-name">{{ $name }}</p>
            <p class="saas-sidebar-profile-role">{{ $role }}</p>
        </div>
    </div>
@endif
