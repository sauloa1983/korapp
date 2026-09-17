<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateCustomer extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = CustomerResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }
}
