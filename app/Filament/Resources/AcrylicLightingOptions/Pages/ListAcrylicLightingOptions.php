<?php

namespace App\Filament\Resources\AcrylicLightingOptions\Pages;

use App\Filament\Resources\AcrylicLightingOptions\AcrylicLightingOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcrylicLightingOptions extends ListRecords
{
    protected static string $resource = AcrylicLightingOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
