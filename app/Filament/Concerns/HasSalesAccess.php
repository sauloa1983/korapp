<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait HasSalesAccess
{
    public static function canAccessSalesModule(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('ViewAny:Lead')
            || $user->can('ViewAny:Sale')
            || $user->can('ViewAny:Visit')
            || $user->can('View:SalesDashboard')
            || $user->can('View:SalesPipeline');
    }
}
