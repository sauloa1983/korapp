<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Support\CommercialScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SalePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sale');
    }

    public function view(AuthUser $authUser, Sale $sale): bool
    {
        return $authUser->can('View:Sale')
            && CommercialScope::owns($sale->user_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sale');
    }

    public function update(AuthUser $authUser, Sale $sale): bool
    {
        return $authUser->can('Update:Sale')
            && $sale->isEditable()
            && CommercialScope::owns($sale->user_id);
    }

    public function delete(AuthUser $authUser, Sale $sale): bool
    {
        return $authUser->can('Delete:Sale')
            && $sale->isEditable()
            && CommercialScope::owns($sale->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Sale');
    }

    public function restore(AuthUser $authUser, Sale $sale): bool
    {
        return $authUser->can('Restore:Sale')
            && CommercialScope::owns($sale->user_id);
    }

    public function forceDelete(AuthUser $authUser, Sale $sale): bool
    {
        return $authUser->can('ForceDelete:Sale')
            && CommercialScope::owns($sale->user_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Sale');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Sale');
    }

    public function replicate(AuthUser $authUser, Sale $sale): bool
    {
        return $authUser->can('Replicate:Sale')
            && CommercialScope::owns($sale->user_id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Sale');
    }
}
