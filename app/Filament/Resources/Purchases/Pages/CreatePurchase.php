<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = PurchaseResource::class;
}
