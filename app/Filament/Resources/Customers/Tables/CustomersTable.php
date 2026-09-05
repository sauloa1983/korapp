<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Support\Tables\IdentityColumns;
use App\Models\Customer;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ...IdentityColumns::make(
                    nameAttribute: 'name',
                    secondaryAttribute: 'email',
                    label: 'Cliente',
                ),
                TextColumn::make('tax_id')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sin documento')
                    ->description(fn (Customer $record): ?string => $record->document_type?->getLabel())
                    ->color(fn ($state): string => filled($state) ? 'gray' : 'danger')
                    ->toggleable(),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('company_name')
                    ->label('Nombre comercial')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->badge()
                    ->placeholder('Sin asignar')
                    ->formatStateUsing(fn (?string $state, Customer $record): string => $record->isUnassigned()
                        ? 'Sin asignar'
                        : (string) $state)
                    ->color(fn (Customer $record): string => $record->isUnassigned() ? 'warning' : 'primary')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
                TextColumn::make('is_active')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($record): string => ! $record->hasBillingDocument()
                        ? 'Incompleto'
                        : ($record->is_active ? 'Activo' : 'Inactivo'))
                    ->color(fn ($record): string => ! $record->hasBillingDocument()
                        ? 'warning'
                        : ($record->is_active ? 'success' : 'gray'))
                    ->sortable(),
                TextColumn::make('documents_count')
                    ->label('Docs.')
                    ->counts('documents')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('sales_count')
                    ->label('Ventas')
                    ->counts('sales')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->defaultSort('name')
            ->striped(false)
            ->filters([
                SelectFilter::make('assignment')
                    ->label('Asignación')
                    ->options([
                        'unassigned' => 'Sin asignar',
                        'assigned' => 'Con vendedor',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'unassigned' => $query->unassigned(),
                            'assigned' => $query->assigned(),
                            default => $query,
                        };
                    }),
                SelectFilter::make('user_id')
                    ->label('Vendedor')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-m-pencil-square'),
                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-m-trash'),
                    RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-m-arrow-uturn-left'),
                    ForceDeleteAction::make()
                        ->label('Eliminar permanentemente')
                        ->icon('heroicon-m-x-circle'),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->tooltip('Acciones')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignSeller')
                        ->label('Asignar vendedor')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('user_id')
                                ->label('Vendedor')
                                ->options(fn (): array => \App\Models\User::query()
                                    ->visibleInDirectory()
                                    ->role(['Vendedor', 'super_admin'])
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['user_id' => $data['user_id']]);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Vendedor asignado'),
                    BulkAction::make('unassignSeller')
                        ->label('Dejar sin asignar')
                        ->icon('heroicon-o-user-minus')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Los clientes seleccionados quedarán sin vendedor asignado.')
                        ->action(function (Collection $records): void {
                            $records->each->update(['user_id' => null]);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Clientes sin asignar'),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin clientes')
            ->emptyStateDescription('Crea tu primer cliente para empezar a gestionar tu cartera.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
