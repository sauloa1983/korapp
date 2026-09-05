<?php

namespace App\Filament\Resources\ItemCategories\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ItemCategories\ItemCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItemCategory extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ItemCategoryResource::class;
}
