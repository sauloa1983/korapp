<?php

namespace App\Filament\Resources\AcrylicMaterials\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicMaterials\AcrylicMaterialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcrylicMaterial extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
