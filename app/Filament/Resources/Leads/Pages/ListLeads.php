<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
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
        return 'Trabaja los activos aquí. Al ganar, pasan a Clientes y salen del seguimiento diario.';
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
                ->badge(fn (): int => Lead::query()->open()->count())
                ->badgeColor('primary'),
            'converted' => Tab::make('Convertidos')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->converted())
                ->badge(fn (): int => Lead::query()->converted()->count())
                ->badgeColor('success'),
            'lost' => Tab::make('Perdidos')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->lost())
                ->badge(fn (): int => Lead::query()->lost()->count())
                ->badgeColor('danger'),
            'all' => Tab::make('Todos')
                ->badge(fn (): int => Lead::query()->count()),
        ];
    }
}
