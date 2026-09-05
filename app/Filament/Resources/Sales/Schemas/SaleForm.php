<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Venta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->helperText('Se genera automáticamente (VT-AAAA-#####).'),
                        Select::make('status')
                            ->label('Estado')
                            ->options(SaleStatus::class)
                            ->default(SaleStatus::Borrador->value)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Cambia a "Confirmada" desde el botón Confirmar.'),
                        Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship(
                                name: 'customer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Customer $record): string => $record->hasBillingDocument()
                                ? $record->displayName().' · '.$record->tax_id
                                : "{$record->displayName()} (sin documento)")
                            ->getOptionLabelUsing(function ($value): ?string {
                                $customer = Customer::query()->find($value);

                                if (! $customer) {
                                    return null;
                                }

                                return $customer->hasBillingDocument()
                                    ? $customer->displayName().' · '.$customer->tax_id
                                    : "{$customer->displayName()} (sin documento)";
                            })
                            ->disableOptionWhen(fn ($value): bool => filled($value)
                                && ! Customer::query()->find($value)?->hasBillingDocument())
                            ->searchable(['name', 'company_name', 'tax_id', 'email', 'city'])
                            ->preload()
                            ->createOptionForm(CustomerForm::identityFields(requireDocument: true))
                            ->createOptionUsing(fn (array $data): int => CustomerForm::createFromQuickForm($data)->getKey())
                            ->createOptionModalHeading('Nuevo cliente')
                            ->helperText('Solo clientes con documento. Déjalo vacío para venta al público.'),
                        Select::make('warehouse_id')
                            ->label('Bodega origen')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => \App\Models\Warehouse::defaultId())
                            ->hidden()
                            ->dehydrated(),
                        Select::make('user_id')
                            ->label('Vendedor')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload(),
                        Select::make('payment_method')
                            ->label('Forma de pago')
                            ->options(PaymentMethod::class)
                            ->native(false)
                            ->nullable(),
                        TextInput::make('advance_amount')
                            ->label('Anticipo')
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0),
                        DatePicker::make('sold_at')
                            ->label('Fecha de venta')
                            ->default(now()),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
                Section::make('Totales')
                    ->description('Los precios de línea son sin IVA. El IVA se calcula según la configuración de la empresa.')
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
