<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Leads\LeadResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class CreateLead extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LeadResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    public function getTitle(): string | Htmlable
    {
        return 'Crear Prospecto';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Registra un nuevo prospecto para dar seguimiento a posibles oportunidades de negocio.';
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Crear prospecto');
    }
}
