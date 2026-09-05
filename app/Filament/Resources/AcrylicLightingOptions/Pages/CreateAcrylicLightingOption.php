<?php

namespace App\Filament\Resources\AcrylicLightingOptions\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicLightingOptions\AcrylicLightingOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcrylicLightingOption extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicLightingOptionResource::class;
}
