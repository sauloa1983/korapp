<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Resources\Visits\VisitResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Agenda comercial';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Agenda comercial';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Historial de visitas, llamadas y seguimientos. También puedes verla en calendario.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label('Ver calendario')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(VisitResource::getUrl('calendar')),
            CreateAction::make()
                ->label('Agendar contacto'),
        ];
    }
}
