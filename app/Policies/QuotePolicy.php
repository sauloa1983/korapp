<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Quote;
use App\Support\CommercialScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class QuotePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Quote');
    }

    public function view(AuthUser $authUser, Quote $quote): bool
    {
        return $authUser->can('View:Quote')
            && CommercialScope::owns($quote->user_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Quote');
    }

    public function update(AuthUser $authUser, Quote $quote): bool
    {
        return $authUser->can('Update:Quote')
            && $quote->isEditable()
            && CommercialScope::owns($quote->user_id);
    }

    public function delete(AuthUser $authUser, Quote $quote): bool
    {
        return $authUser->can('Delete:Quote')
            && $quote->isEditable()
            && CommercialScope::owns($quote->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Quote');
    }

    public function restore(AuthUser $authUser, Quote $quote): bool
    {
        return $authUser->can('Restore:Quote')
            && CommercialScope::owns($quote->user_id);
    }

    public function forceDelete(AuthUser $authUser, Quote $quote): bool
    {
        return $authUser->can('ForceDelete:Quote')
            && CommercialScope::owns($quote->user_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Quote');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Quote');
    }

    public function replicate(AuthUser $authUser, Quote $quote): bool
    {
        return $authUser->can('Replicate:Quote')
            && CommercialScope::owns($quote->user_id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Quote');
    }
}
