<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Imports\CustomerImporter;
use App\Filament\Pages\ReasignarClientes;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Widgets\CustomerStatsOverview;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected ?string $subheading = 'Administra clientes, estados y actividad comercial desde un solo lugar.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadImportTemplate')
                ->label('Descargar formato')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('customers.import-template'))
                ->openUrlInNewTab(false)
                ->visible(fn (): bool => auth()->user()?->can('create', Customer::class) ?? false),
            ImportAction::make()
                ->label('Importar Excel/CSV')
                ->importer(CustomerImporter::class)
                ->color('gray')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Importar clientes')
                ->modalDescription('Usa el formato descargable. Si trabajas en Excel, guarda el archivo como CSV UTF-8 antes de subirlo. Si el NIT o correo ya existe, se actualiza el cliente.')
                ->chunkSize(100),
            Action::make('reassign')
                ->label('Reasignar cartera')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->url(ReasignarClientes::getUrl())
                ->visible(fn (): bool => ReasignarClientes::canAccess()),
            CreateAction::make()
                ->label('Crear Cliente')
                ->icon('heroicon-m-plus')
                ->size('lg'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            CustomerStatsOverview::class,
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(fn (): int => Customer::query()->count()),
            'active' => Tab::make('Activos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true))
                ->badge(fn (): int => Customer::query()->where('is_active', true)->count())
                ->badgeColor('success'),
            'unassigned' => Tab::make('Sin asignar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->unassigned())
                ->badge(fn (): int => Customer::query()->unassigned()->count())
                ->badgeColor('warning'),
            'inactive' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', false))
                ->badge(fn (): int => Customer::query()->where('is_active', false)->count())
                ->badgeColor('gray'),
        ];
    }
}
