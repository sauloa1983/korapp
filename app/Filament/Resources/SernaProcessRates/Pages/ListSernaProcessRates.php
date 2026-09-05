<?php

namespace App\Filament\Resources\SernaProcessRates\Pages;

use App\Filament\Resources\SernaProcessRates\SernaProcessRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSernaProcessRates extends ListRecords
{
    protected static string $resource = SernaProcessRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
