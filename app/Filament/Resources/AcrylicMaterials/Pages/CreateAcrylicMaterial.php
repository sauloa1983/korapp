<?php

namespace App\Filament\Resources\AcrylicMaterials\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicMaterials\AcrylicMaterialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcrylicMaterial extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicMaterialResource::class;
}
