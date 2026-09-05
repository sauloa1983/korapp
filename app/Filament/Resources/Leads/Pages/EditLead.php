<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class EditLead extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LeadResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    public function getSubheading(): string | Htmlable | null
    {
        return 'Actualiza los datos del prospecto y el seguimiento de la oportunidad.';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
