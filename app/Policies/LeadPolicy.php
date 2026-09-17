<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lead;
use App\Support\CommercialScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeadPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Lead');
    }

    public function view(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('View:Lead')
            && CommercialScope::owns($lead->user_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Lead');
    }

    public function update(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('Update:Lead')
            && CommercialScope::owns($lead->user_id);
    }

    public function delete(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('Delete:Lead')
            && CommercialScope::owns($lead->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Lead');
    }

    public function restore(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('Restore:Lead')
            && CommercialScope::owns($lead->user_id);
    }

    public function forceDelete(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('ForceDelete:Lead')
            && CommercialScope::owns($lead->user_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Lead');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Lead');
    }

    public function replicate(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('Replicate:Lead');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Lead');
    }
}
