<?php

namespace App\Filament\Resources\SernaCatalogProducts\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SernaCatalogProducts\SernaCatalogProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSernaCatalogProduct extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SernaCatalogProductResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
