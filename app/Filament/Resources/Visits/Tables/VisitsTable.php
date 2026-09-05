<?php

namespace App\Filament\Resources\Visits\Tables;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Visit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VisitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lead.name')
                    ->label('Prospecto')
                    ->placeholder('—')
                    ->url(fn (Visit $record): ?string => $record->lead_id
                        ? LeadResource::getUrl('edit', ['record' => $record->lead_id])
                        : null)
                    ->color('primary')
                    ->toggleable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('—')
                    ->url(fn (Visit $record): ?string => $record->customer_id
                        ? CustomerResource::getUrl('edit', ['record' => $record->customer_id])
                        : null)
                    ->color('primary')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('next_follow_up_at')
                    ->label('Próx. seguimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->color(fn (Visit $record): ?string => $record->next_follow_up_at?->isPast() ? 'danger' : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(VisitStatus::class),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(VisitType::class),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Marcar realizada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Visit $record): bool => $record->status === VisitStatus::Programada)
                    ->requiresConfirmation()
                    ->action(function (Visit $record): void {
                        $record->markCompleted();
                        Notification::make()
                            ->title('Visita marcada como realizada')
                            ->success()
                            ->send();
                    }),
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin visitas ni seguimientos')
            ->emptyStateDescription('Agenda la primera visita o llamada para dar seguimiento comercial.')
            ->emptyStateIcon('heroicon-o-map-pin');
    }
}
