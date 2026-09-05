<?php

namespace App\Http\Responses;

use App\Filament\Pages\Auth\ForceChangePassword;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $user = Filament::auth()->user();

        if ($user !== null) {
            $user->refresh();

            if ($user->mustChangePasswordBeforeAccess()) {
                return redirect()->to(ForceChangePassword::getUrl());
            }
        }

        return redirect()->intended(Filament::getUrl());
    }
}
