<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Visit;
use App\Support\CommercialScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VisitPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Visit');
    }

    public function view(AuthUser $authUser, Visit $visit): bool
    {
        return $authUser->can('View:Visit')
            && CommercialScope::owns($visit->user_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Visit');
    }

    public function update(AuthUser $authUser, Visit $visit): bool
    {
        return $authUser->can('Update:Visit')
            && CommercialScope::owns($visit->user_id);
    }

    public function delete(AuthUser $authUser, Visit $visit): bool
    {
        return $authUser->can('Delete:Visit')
            && CommercialScope::owns($visit->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Visit');
    }

    public function restore(AuthUser $authUser, Visit $visit): bool
    {
        return $authUser->can('Restore:Visit')
            && CommercialScope::owns($visit->user_id);
    }

    public function forceDelete(AuthUser $authUser, Visit $visit): bool
    {
        return $authUser->can('ForceDelete:Visit')
            && CommercialScope::owns($visit->user_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Visit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Visit');
    }

    public function replicate(AuthUser $authUser, Visit $visit): bool
    {
        return $authUser->can('Replicate:Visit')
            && CommercialScope::owns($visit->user_id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Visit');
    }
}
