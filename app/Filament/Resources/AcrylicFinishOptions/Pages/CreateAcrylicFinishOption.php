<?php

namespace App\Filament\Resources\AcrylicFinishOptions\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\AcrylicFinishOptions\AcrylicFinishOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcrylicFinishOption extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AcrylicFinishOptionResource::class;
}
