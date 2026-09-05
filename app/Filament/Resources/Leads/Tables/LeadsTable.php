<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStage;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Contacto')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->company)
                    ->weight('medium'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—')
                    ->color('gray'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—')
                    ->color('gray'),
                TextColumn::make('stage')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->badge()
                    ->placeholder('Sin asignar')
                    ->formatStateUsing(fn (?string $state, Lead $record): string => $record->isUnassigned()
                        ? 'Sin asignar'
                        : (string) $state)
                    ->color(fn (Lead $record): string => $record->isUnassigned() ? 'warning' : 'primary')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('—')
                    ->toggleable()
                    ->url(fn ($record): ?string => $record->customer_id
                        ? \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->customer_id])
                        : null)
                    ->color('primary'),
                TextColumn::make('converted_at')
                    ->label('Convertido')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('value')
                    ->label('Valor')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->label('Estado')
                    ->options(LeadStage::class),
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
            ])
            ->recordActions([
                Action::make('viewCustomer')
                    ->label('Ver cliente')
                    ->icon('heroicon-o-building-office-2')
                    ->color('success')
                    ->url(fn (Lead $record): string => \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->customer_id]))
                    ->visible(fn (Lead $record): bool => $record->isConverted()),
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Eliminar'),
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
                                    ->orderSellersFirst()
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
                        ->modalDescription('Los prospectos seleccionados quedarán sin vendedor asignado.')
                        ->action(function (Collection $records): void {
                            $records->each->update(['user_id' => null]);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Prospectos sin asignar'),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin prospectos en esta vista')
            ->emptyStateDescription('Los activos son oportunidades en curso. Los convertidos viven en Clientes.')
            ->emptyStateIcon('heroicon-o-user-plus');
    }
}
