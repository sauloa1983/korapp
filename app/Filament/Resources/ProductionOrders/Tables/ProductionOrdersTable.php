<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Support\OrderCodeModal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductionOrdersTable
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
                TextColumn::make('quote.code')
                    ->label('Cotización')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('item.name')
                    ->label('Producto')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Cant.')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('sale.code')
                    ->label('Venta')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('progress')
                    ->label('Avance')
                    ->badge()
                    ->color('info')
                    ->state(function ($record): string {
                        $total = $record->logs->count();
                        if ($total === 0) {
                            return 'Sin flujo';
                        }
                        $done = $record->logs->where('status', \App\Enums\ProductionLogStatus::Terminado)->count();

                        return "{$done}/{$total} etapas";
                    }),
                TextColumn::make('total_time')
                    ->label('Tiempo')
                    ->state(fn ($record): string => $record->totalDurationMinutes().' min'),
                TextColumn::make('due_at')
                    ->label('Entrega pactada')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->label('Entregado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ProductionOrderStatus::class),
                SelectFilter::make('quote_id')
                    ->label('Cotización')
                    ->relationship('quote', 'code')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('generatePipeline')
                    ->label('Generar flujo')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Incluye todas las etapas activas del catálogo (por departamento) con su QR. Preferible elegir procesos al crear la OP desde la cotización.')
                    ->visible(fn ($record): bool => $record->logs()->count() === 0)
                    ->action(function ($record): void {
                        $record->generatePipeline();
                        Notification::make()
                            ->title('Flujo de producción generado')
                            ->success()
                            ->send();
                    }),
                Action::make('markDelivered')
                    ->label('Entrega final')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Marca la orden como entregada al cliente. Debe estar completada en planta.')
                    ->visible(fn ($record): bool => $record->status === ProductionOrderStatus::Completado)
                    ->action(function ($record): void {
                        $record->markDelivered();
                        Notification::make()
                            ->title('Entrega registrada')
                            ->body("La OP {$record->code} quedó como Entregada.")
                            ->success()
                            ->send();
                    }),
                Action::make('consumeStock')
                    ->label('Consumir inventario')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Descuenta del inventario la materia prima e insumos definidos como requerimientos.')
                    ->action(function ($record): void {
                        $record->consumeStock(auth()->id());
                        Notification::make()
                            ->title('Inventario descontado')
                            ->body('Los requerimientos se registraron como salidas en el kardex.')
                            ->success()
                            ->send();
                    }),
                Action::make('codes')
                    ->label('QR / Barras')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalHeading(fn ($record): string => 'Códigos · '.$record->code)
                    ->modalContent(fn ($record) => OrderCodeModal::content($record)),
                Action::make('pdf')
                    ->label('Imprimir OP')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->url(fn ($record): string => route('orders.pdf', $record))
                    ->openUrlInNewTab(),
                Action::make('labels')
                    ->label('Etiquetas')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record): string => route('orders.labels', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
