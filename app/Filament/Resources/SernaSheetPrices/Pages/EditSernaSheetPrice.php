<?php

namespace App\Filament\Resources\SernaSheetPrices\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SernaSheetPrices\SernaSheetPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSernaSheetPrice extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SernaSheetPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
