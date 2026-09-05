<?php

namespace App\Filament\Resources\AcrylicFinishOptions\Pages;

use App\Filament\Resources\AcrylicFinishOptions\AcrylicFinishOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcrylicFinishOptions extends ListRecords
{
    protected static string $resource = AcrylicFinishOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
