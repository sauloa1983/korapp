<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Support\MoneyFormat;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\CommercialScope;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                                modifyQueryUsing: fn (Builder $query): Builder => CommercialScope::constrain(
                                    $query->orderBy('name')
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Customer $record): string => $record->hasBillingDocument()
                                ? $record->displayName().' · '.$record->tax_id
                                : "{$record->displayName()} (sin documento)")
                            ->getOptionLabelUsing(function ($value): ?string {
                                $customer = CommercialScope::constrain(Customer::query())->find($value);

                                if (! $customer) {
                                    return null;
                                }

                                return $customer->hasBillingDocument()
                                    ? $customer->displayName().' · '.$customer->tax_id
                                    : "{$customer->displayName()} (sin documento)";
                            })
                            ->disableOptionWhen(fn ($value): bool => filled($value)
                                && ! CommercialScope::constrain(Customer::query())->find($value)?->hasBillingDocument())
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
                            ->preload()
                            ->visible(fn (): bool => ! CommercialScope::seesOnlyOwnData())
                            ->dehydrated()
                            ->dehydrateStateUsing(fn ($state): ?int => CommercialScope::seesOnlyOwnData()
                                ? auth()->id()
                                : ($state !== null && $state !== '' ? (int) $state : auth()->id())),
                        Select::make('payment_method')
                            ->label('Forma de pago')
                            ->options(PaymentMethod::class)
                            ->native(false)
                            ->nullable(),
                        MoneyFormat::copInput('advance_amount', 'Anticipo')
                            ->helperText('Monto ya recibido. El saldo pendiente se calcula automáticamente.')
                            ->rule(fn (Get $get, ?Sale $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record): void {
                                $advance = Money::parseInput($value) ?? 0.0;
                                $total = (float) ($record?->total ?? $get('total') ?? 0);

                                if ($advance < 0) {
                                    $fail('El anticipo no puede ser negativo.');

                                    return;
                                }

                                if ($total > 0 && $advance > $total + 0.009) {
                                    $fail('El anticipo no puede superar el total de la venta.');
                                }
                            }),
                        DatePicker::make('sold_at')
                            ->label('Fecha de venta')
                            ->default(now()),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
                Section::make('Totales')
                    ->description('Los precios de línea son sin IVA. El IVA se calcula según la configuración de la empresa.')
                    ->columns(3)
                    ->visibleOn('edit')
                    ->schema([
                        MoneyFormat::display('subtotal', 'Subtotal'),
                        TextInput::make('iva_rate')
                            ->label('IVA %')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('%')
                            ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.')),
                        MoneyFormat::display('iva_amount', 'Valor IVA'),
                        MoneyFormat::display('total', 'Total'),
                        TextInput::make('advance_display')
                            ->label('Anticipo')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('$')
                            ->formatStateUsing(function ($state, Get $get, ?Sale $record): string {
                                $advance = Money::parseInput($get('advance_amount'))
                                    ?? (float) ($record?->advance_amount ?? 0);

                                return number_format($advance, 0, ',', '.');
                            }),
                        TextInput::make('balance_due')
                            ->label('Saldo pendiente')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('$')
                            ->formatStateUsing(function ($state, Get $get, ?Sale $record): string {
                                $total = (float) ($record?->total ?? $get('total') ?? 0);
                                $advance = Money::parseInput($get('advance_amount'))
                                    ?? (float) ($record?->advance_amount ?? 0);

                                return number_format(max(0, $total - $advance), 0, ',', '.');
                            }),
                    ]),
            ]);
    }
}
