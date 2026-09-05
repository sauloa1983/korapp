<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Visits\VisitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class EditVisit extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = VisitResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FourExtraLarge;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Editar contacto comercial';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Editar contacto comercial';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Actualiza el estado, el resultado o la próxima fecha de seguimiento.';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Eliminar'),
        ];
    }
}
