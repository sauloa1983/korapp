<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Visits\VisitResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class CreateVisit extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = VisitResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FourExtraLarge;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Agendar contacto comercial';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Agendar contacto comercial';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Anota visitas, llamadas o reuniones con prospectos y clientes para no perder el seguimiento.';
    }
}
