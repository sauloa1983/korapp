<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PurchasesTable
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
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
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
                TextColumn::make('ordered_at')
                    ->label('Orden')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('received_at')
                    ->label('Recibida')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PurchaseStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('Recibir')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Ingresa las cantidades al inventario como entradas y cierra la compra.')
                    ->visible(fn (Purchase $record): bool => $record->status === PurchaseStatus::Borrador && $record->items()->exists())
                    ->action(function (Purchase $record): void {
                        $record->receive(Auth::id());
                        Notification::make()
                            ->title('Compra recibida')
                            ->body('El inventario se incrementó y quedó registrado en el kardex.')
                            ->success()
                            ->send();
                    }),
                Action::make('return')
                    ->label('Devolver')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Purchase $record): bool => $record->status === PurchaseStatus::Recibida)
                    ->fillForm(fn (Purchase $record): array => [
                        'warehouse_id' => $record->warehouse_id,
                        'items' => $record->items->map(fn ($line): array => [
                            'item_id' => $line->item_id,
                            'quantity' => (float) $line->quantity,
                            'unit_cost' => (float) $line->unit_cost,
                        ])->all(),
                    ])
                    ->schema([
                        Select::make('warehouse_id')
                            ->label('Bodega de salida')
                            ->options(fn () => \App\Models\Warehouse::query()->pluck('name', 'id'))
                            ->default(fn () => \App\Models\Warehouse::defaultId()),
                        TextInput::make('reason')
                            ->label('Motivo')
                            ->maxLength(255),
                        Repeater::make('items')
                            ->label('Artículos a devolver')
                            ->schema([
                                Select::make('item_id')
                                    ->label('Artículo')
                                    ->options(fn () => \App\Models\Item::query()->pluck('name', 'id'))
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->step(0.0001),
                                TextInput::make('unit_cost')
                                    ->label('Costo unit.')
                                    ->numeric()
                                    ->prefix('$')
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->minItems(1),
                    ])
                    ->action(function (Purchase $record, array $data): void {
                        $return = $record->returns()->create([
                            'warehouse_id' => $data['warehouse_id'] ?? $record->warehouse_id,
                            'user_id' => Auth::id(),
                            'reason' => $data['reason'] ?? null,
                        ]);

                        foreach ($data['items'] as $line) {
                            if ((float) ($line['quantity'] ?? 0) <= 0) {
                                continue;
                            }

                            $return->items()->create([
                                'item_id' => $line['item_id'],
                                'quantity' => $line['quantity'],
                                'unit_cost' => $line['unit_cost'] ?? 0,
                            ]);
                        }

                        $return->load('items');
                        $return->apply(Auth::id());

                        Notification::make()
                            ->title('Devolución registrada')
                            ->body("Devolución {$return->code}: inventario descontado.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
