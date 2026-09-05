<?php

namespace App\Filament\Resources\SernaCatalogProducts\Pages;

use App\Filament\Resources\SernaCatalogProducts\SernaCatalogProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSernaCatalogProducts extends ListRecords
{
    protected static string $resource = SernaCatalogProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
