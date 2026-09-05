<?php

namespace App\Filament\Resources\SernaProcessRates\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SernaProcessRates\SernaProcessRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSernaProcessRate extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SernaProcessRateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
