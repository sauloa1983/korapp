<?php

namespace App\Filament\Resources\AcrylicFinishOptions\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicFinishOptions\AcrylicFinishOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcrylicFinishOption extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicFinishOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
