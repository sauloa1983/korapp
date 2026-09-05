<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SaleResource::class;
}
