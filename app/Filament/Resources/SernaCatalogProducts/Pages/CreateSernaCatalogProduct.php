<?php

namespace App\Filament\Resources\SernaCatalogProducts\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SernaCatalogProducts\SernaCatalogProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSernaCatalogProduct extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SernaCatalogProductResource::class;
}
