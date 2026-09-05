<?php

namespace App\Filament\Resources\AcrylicMaterials\Pages;

use App\Filament\Resources\AcrylicMaterials\AcrylicMaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcrylicMaterials extends ListRecords
{
    protected static string $resource = AcrylicMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
