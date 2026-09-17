<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Solo administración (super_admin). Bloquea Operario, Vendedor y Gerencia
 * en menús de configuración de precios / parámetros sensibles.
 */
trait RestrictsToSuperAdmin
{
    public static function canViewAny(): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }
}
