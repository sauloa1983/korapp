<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Support\CommercialScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo prospecto')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getSubheading(): ?string
    {
        return CommercialScope::seesOnlyOwnData()
            ? 'Trabaja tus prospectos activos. Al ganar, pasan a tus Clientes.'
            : 'Trabaja los activos aquí. Al ganar, pasan a Clientes y salen del seguimiento diario.';
    }

    protected function leadsQuery(): Builder
    {
        return CommercialScope::constrain(Lead::query());
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Activos')
                ->icon('heroicon-o-bolt')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(fn (): int => $this->leadsQuery()->open()->count())
                ->badgeColor('primary'),
            'converted' => Tab::make('Convertidos')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->converted())
                ->badge(fn (): int => $this->leadsQuery()->converted()->count())
                ->badgeColor('success'),
            'lost' => Tab::make('Perdidos')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->lost())
                ->badge(fn (): int => $this->leadsQuery()->lost()->count())
                ->badgeColor('danger'),
            'all' => Tab::make('Todos')
                ->badge(fn (): int => $this->leadsQuery()->count()),
        ];
    }
}
