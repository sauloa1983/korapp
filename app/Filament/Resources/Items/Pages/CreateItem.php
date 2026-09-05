<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Items\ItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItem extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ItemResource::class;
}
