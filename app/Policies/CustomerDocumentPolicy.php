<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerDocument;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CustomerDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Customer') || $authUser->can('Update:Customer');
    }

    public function view(AuthUser $authUser, CustomerDocument $customerDocument): bool
    {
        return $authUser->can('View:Customer') || $authUser->can('Update:Customer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Customer') || $authUser->can('Create:Customer');
    }

    public function update(AuthUser $authUser, CustomerDocument $customerDocument): bool
    {
        return $authUser->can('Update:Customer');
    }

    public function delete(AuthUser $authUser, CustomerDocument $customerDocument): bool
    {
        return $authUser->can('Update:Customer') || $authUser->can('Delete:Customer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Customer') || $authUser->can('DeleteAny:Customer');
    }
}
