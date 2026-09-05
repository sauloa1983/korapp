<?php

namespace App\Filament\Resources\SernaProcessRates\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SernaProcessRates\SernaProcessRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSernaProcessRate extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SernaProcessRateResource::class;
}
