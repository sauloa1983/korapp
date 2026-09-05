<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Process;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Process');
    }

    public function view(AuthUser $authUser, Process $process): bool
    {
        return $authUser->can('View:Process');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Process');
    }

    public function update(AuthUser $authUser, Process $process): bool
    {
        return $authUser->can('Update:Process');
    }

    public function delete(AuthUser $authUser, Process $process): bool
    {
        return $authUser->can('Delete:Process');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Process');
    }

    public function restore(AuthUser $authUser, Process $process): bool
    {
        return $authUser->can('Restore:Process');
    }

    public function forceDelete(AuthUser $authUser, Process $process): bool
    {
        return $authUser->can('ForceDelete:Process');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Process');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Process');
    }

    public function replicate(AuthUser $authUser, Process $process): bool
    {
        return $authUser->can('Replicate:Process');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Process');
    }

}