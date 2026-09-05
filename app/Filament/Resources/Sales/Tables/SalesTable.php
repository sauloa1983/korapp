<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('invoice_number')
                    ->label('Factura')
                    ->placeholder('—')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Público')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Líneas')
                    ->counts('items')
                    ->badge(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('iva_amount')
                    ->label('IVA')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state))
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state))
                    ->sortable(),
                TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(SaleStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirma la venta y asigna el número de factura.')
                    ->visible(fn (Sale $record): bool => $record->status === SaleStatus::Borrador && $record->items()->exists())
                    ->action(function (Sale $record): void {
                        try {
                            $record->confirm(Auth::id());
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            $message = collect($e->errors())->flatten()->first() ?: 'No se pudo confirmar la venta.';

                            Notification::make()
                                ->title('No se pudo confirmar')
                                ->body($message)
                                ->danger()
                                ->send();

                            return;
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('No se pudo confirmar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Venta confirmada')
                            ->body('La factura quedó confirmada.')
                            ->success()
                            ->send();
                    }),
                Action::make('reverse')
                    ->label('Reversar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reversar venta')
                    ->modalDescription('La venta vuelve a borrador para poder editarla. Se conserva el número de factura.')
                    ->visible(fn (Sale $record): bool => $record->status === SaleStatus::Confirmada)
                    ->action(function (Sale $record): void {
                        try {
                            $record->reverse();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('No se pudo reversar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Venta reversada')
                            ->body('Quedó en borrador. Ya puedes editarla y volver a confirmar.')
                            ->success()
                            ->send();
                    }),
                Action::make('receipt')
                    ->label('Comprobante')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Sale $record): string => route('sales.receipt', $record))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn (Sale $record): bool => $record->isEditable()),
                Action::make('viewSale')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Sale $record): string => SaleResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Sale $record): bool => ! $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
