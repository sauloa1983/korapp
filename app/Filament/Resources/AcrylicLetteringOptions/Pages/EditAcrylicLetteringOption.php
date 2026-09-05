<?php

namespace App\Filament\Resources\AcrylicLetteringOptions\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicLetteringOptions\AcrylicLetteringOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcrylicLetteringOption extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicLetteringOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
