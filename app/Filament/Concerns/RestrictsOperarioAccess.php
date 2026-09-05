<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait RestrictsOperarioAccess
{
    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if ($user === null || $user->isOperario()) {
            return false;
        }

        return parent::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (Auth::user()?->isOperario()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }
}
