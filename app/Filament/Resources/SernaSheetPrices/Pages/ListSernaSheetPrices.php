<?php

namespace App\Filament\Resources\SernaSheetPrices\Pages;

use App\Filament\Resources\SernaSheetPrices\SernaSheetPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSernaSheetPrices extends ListRecords
{
    protected static string $resource = SernaSheetPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
