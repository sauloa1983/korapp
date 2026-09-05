<?php

namespace App\Filament\Resources\Processes\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Processes\ProcessResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProcess extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ProcessResource::class;
}
