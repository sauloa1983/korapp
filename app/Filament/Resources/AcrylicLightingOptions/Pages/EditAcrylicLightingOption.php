<?php

namespace App\Filament\Resources\AcrylicLightingOptions\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicLightingOptions\AcrylicLightingOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcrylicLightingOption extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicLightingOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
