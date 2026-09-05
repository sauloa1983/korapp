<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionOrder extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ProductionOrderResource::class;
}
