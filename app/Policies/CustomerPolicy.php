<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Customer;
use App\Support\CommercialScope;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Customer');
    }

    public function view(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('View:Customer')
            && CommercialScope::owns($customer->user_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Customer');
    }

    public function import(AuthUser $authUser): bool
    {
        return $authUser->can('Import:Customer');
    }

    public function update(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('Update:Customer')
            && CommercialScope::owns($customer->user_id);
    }

    public function delete(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('Delete:Customer')
            && CommercialScope::owns($customer->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Customer');
    }

    public function restore(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('Restore:Customer')
            && CommercialScope::owns($customer->user_id);
    }

    public function forceDelete(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('ForceDelete:Customer')
            && CommercialScope::owns($customer->user_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Customer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Customer');
    }

    public function replicate(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('Replicate:Customer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Customer');
    }

}