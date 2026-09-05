<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Auth\ForceChangePassword;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user() ?? $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Relee el usuario por si la sesión trae datos incompletos.
        $user->refresh();

        if (! $user->mustChangePasswordBeforeAccess()) {
            return $next($request);
        }

        if ($this->shouldAllow($request)) {
            return $next($request);
        }

        return redirect()->to(ForceChangePassword::getUrl());
    }

    protected function shouldAllow(Request $request): bool
    {
        if ($request->routeIs([
            'filament.admin.auth.logout',
            'filament.admin.pages.cambiar-contrasena',
        ])) {
            return true;
        }

        if ($request->is('admin/logout')) {
            return true;
        }

        // Solo permite Livewire de la pantalla de cambio de contraseña.
        if ($request->is('livewire/*')) {
            $referer = (string) $request->headers->get('referer', '');

            return str_contains($referer, '/admin/cambiar-contrasena');
        }

        $changePath = parse_url(ForceChangePassword::getUrl(), PHP_URL_PATH);

        return is_string($changePath) && $request->is(ltrim($changePath, '/'));
    }
}
