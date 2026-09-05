<?php

namespace App\Filament\Resources\SernaSheetPrices\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SernaSheetPrices\SernaSheetPriceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSernaSheetPrice extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SernaSheetPriceResource::class;
}
