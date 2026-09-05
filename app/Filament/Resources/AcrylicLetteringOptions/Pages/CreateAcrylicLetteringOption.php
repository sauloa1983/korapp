<?php

namespace App\Filament\Resources\AcrylicLetteringOptions\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicLetteringOptions\AcrylicLetteringOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcrylicLetteringOption extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicLetteringOptionResource::class;
}
