<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Enums\PurchaseStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Compra')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->helperText('Se genera automáticamente (OC-AAAA-#####).'),
                        Select::make('status')
                            ->label('Estado')
                            ->options(PurchaseStatus::class)
                            ->default(PurchaseStatus::Borrador->value)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Cambia a "Recibida" desde el botón Recibir.'),
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('warehouse_id')
                            ->label('Bodega destino')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => \App\Models\Warehouse::defaultId())
                            ->helperText('Bodega donde ingresará el inventario al recibir.'),
                        Select::make('user_id')
                            ->label('Registrada por')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload(),
                        DatePicker::make('ordered_at')
                            ->label('Fecha de orden')
                            ->default(now()),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
                Section::make('Totales')
                    ->description('Costos sin IVA. El IVA se aplica según la configuración de la empresa.')
                    ->columns(4)
                    ->visibleOn('edit')
                    ->schema([
                        \App\Filament\Support\MoneyFormat::display('subtotal', 'Subtotal'),
                        TextInput::make('iva_rate')
                            ->label('IVA %')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('%')
                            ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.')),
                        \App\Filament\Support\MoneyFormat::display('iva_amount', 'Valor IVA'),
                        \App\Filament\Support\MoneyFormat::display('total', 'Total'),
                    ]),
            ]);
    }
}
