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
                    ->sortable()
                    ->color(fn ($record): ?string => $record->isDeliveryOverdue() ? 'danger' : null)
                    ->description(fn ($record): ?string => $record->isDeliveryOverdue() ? 'Atrasada' : null)
                    ->toggleable(),
                TextColumn::make('delivered_at')
                    ->label('Entregado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('delivery_state')
                    ->label('Entrega')
                    ->badge()
                    ->state(function ($record): string {
                        if ($record->status === ProductionOrderStatus::Entregado) {
                            return 'Entregada';
                        }
                        if ($record->isReadyForDelivery()) {
                            return 'Lista';
                        }
                        if ($record->isDeliveryOverdue()) {
                            return 'Atrasada';
                        }

                        return '—';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Entregada' => 'primary',
                        'Lista' => 'success',
                        'Atrasada' => 'danger',
                        default => 'gray',
                    })
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
                    ->form([
                        \Filament\Forms\Components\TextInput::make('received_by')
                            ->label('Recibido por')
                            ->placeholder('Nombre de quien recibe')
                            ->maxLength(120),
                    ])
                    ->modalHeading('Registrar entrega')
                    ->modalDescription('Marca la orden como entregada al cliente. Debe estar Completada y con venta confirmada.')
                    ->visible(fn ($record): bool => $record->isReadyForDelivery())
                    ->action(function ($record, array $data): void {
                        try {
                            $record->markDelivered($data['received_by'] ?? null);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('No se pudo entregar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }
                        Notification::make()
                            ->title('Entrega registrada')
                            ->body("La OP {$record->code} quedó como Entregada.")
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
