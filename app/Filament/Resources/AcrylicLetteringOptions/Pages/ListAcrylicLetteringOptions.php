<?php

namespace App\Filament\Resources\AcrylicLetteringOptions\Pages;

use App\Filament\Resources\AcrylicLetteringOptions\AcrylicLetteringOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcrylicLetteringOptions extends ListRecords
{
    protected static string $resource = AcrylicLetteringOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
