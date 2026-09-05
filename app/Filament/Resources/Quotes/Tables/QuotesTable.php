<?php

namespace App\Filament\Resources\Quotes\Tables;

use App\Enums\QuoteStatus;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Quotes\Actions\CreateProductionOrderFromQuoteAction;
use App\Filament\Resources\Quotes\Actions\CreateSaleFromQuoteAction;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotesTable
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
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder(fn ($record): string => filled($record->lead?->company)
                        ? "{$record->lead->name} ({$record->lead->company})"
                        : ($record->lead?->name ?? '—'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
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
                TextColumn::make('valid_until')
                    ->label('Válida hasta')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color('gray'),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->toggleable()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(QuoteStatus::class),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Quote $record): string => route('quotes.pdf', $record))
                    ->openUrlInNewTab(),
                CreateProductionOrderFromQuoteAction::make()
                    ->visible(fn (Quote $record): bool => $record->canCreateProductionOrders()),
                Action::make('viewOrders')
                    ->label('Ver OP')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->visible(fn (Quote $record): bool => $record->productionOrders()->exists())
                    ->url(fn (Quote $record): string => ProductionOrderResource::getUrl('index', [
                        'tableFilters' => [
                            'quote_id' => ['value' => $record->id],
                        ],
                    ]))
                    ->openUrlInNewTab(),
                CreateSaleFromQuoteAction::make()
                    ->visible(fn (Quote $record): bool => $record->canCreateSale()),
                Action::make('viewSale')
                    ->label('Ver venta')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (Quote $record): bool => $record->sale()->exists())
                    ->url(fn (Quote $record): string => SaleResource::getUrl('edit', ['record' => $record->sale]))
                    ->openUrlInNewTab(),
                Action::make('markSent')
                    ->label('Marcar enviada')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn ($record): bool => $record->status === QuoteStatus::Draft)
                    ->action(function ($record): void {
                        $record->update([
                            'status' => QuoteStatus::Sent,
                            'sent_at' => now(),
                        ]);
                        Notification::make()->title('Cotización marcada como enviada')->success()->send();
                    }),
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn (Quote $record): bool => $record->isEditable()),
                Action::make('viewQuote')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Quote $record): string => \App\Filament\Resources\Quotes\QuoteResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Quote $record): bool => ! $record->isEditable()),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (Quote $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin cotizaciones')
            ->emptyStateDescription('Crea una cotización para enviarla a un cliente o prospecto.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
