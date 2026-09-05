<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Enums\ItemType;
use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\ProductionOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProductionOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Orden')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->helperText('Se genera automáticamente (OP-AAAA-#####).'),
                        Select::make('status')
                            ->label('Estado')
                            ->options(ProductionOrderStatus::class)
                            ->default(ProductionOrderStatus::Pendiente->value)
                            ->required(),
                        Select::make('item_id')
                            ->label('Artículo a fabricar')
                            ->relationship(
                                name: 'item',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('type', ItemType::producibleValues()),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Puedes fabricar productos terminados o insumos propios (ej. piezas intermedias).'),
                        TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->step(0.0001),
                        Select::make('user_id')
                            ->label('Solicitante')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Datos comerciales')
                    ->description('Flujo: Cotización → OP → Venta → Entrega. Datos para la OP impresa.')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('linked_quote')
                            ->label('Cotización')
                            ->content(function (?ProductionOrder $record): HtmlString|string {
                                if (! $record?->quote_id) {
                                    return 'Sin cotización vinculada';
                                }

                                $quote = $record->quote;
                                $url = QuoteResource::getUrl('edit', ['record' => $quote]);

                                return new HtmlString('<a href="'.e($url).'" class="text-primary-600 underline">'.e($quote->code).'</a>');
                            })
                            ->visibleOn('edit'),
                        Placeholder::make('linked_sale')
                            ->label('Venta / factura')
                            ->content(function (?ProductionOrder $record): HtmlString|string {
                                if (! $record?->sale_id) {
                                    return 'Pendiente (se vincula al crear la venta)';
                                }

                                $sale = $record->sale;
                                $url = SaleResource::getUrl('edit', ['record' => $sale]);

                                return new HtmlString('<a href="'.e($url).'" class="text-primary-600 underline">'.e($sale->code).'</a>');
                            })
                            ->visibleOn('edit'),
                        Placeholder::make('linked_customer')
                            ->label('Cliente')
                            ->content(function (?ProductionOrder $record): HtmlString|string {
                                $customer = $record?->quote?->customer ?? $record?->sale?->customer;
                                if (! $customer) {
                                    $lead = $record?->quote?->lead;
                                    if ($lead) {
                                        return filled($lead->company)
                                            ? "{$lead->name} ({$lead->company})"
                                            : (string) $lead->name;
                                    }

                                    return '—';
                                }

                                $url = CustomerResource::getUrl('edit', ['record' => $customer]);

                                return new HtmlString('<a href="'.e($url).'" class="text-primary-600 underline">'.e($customer->displayName()).'</a>');
                            })
                            ->visibleOn('edit'),
                        TextInput::make('contact_name')
                            ->label('Contacto')
                            ->maxLength(255)
                            ->placeholder('Nombre de la persona de contacto'),
                        Select::make('quoted_by_user_id')
                            ->label('Cotizó')
                            ->relationship('quotedBy', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        DatePicker::make('due_at')
                            ->label('Fecha de entrega'),
                    ]),
                Section::make('Fechas')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('requested_at')
                            ->label('Fecha de solicitud')
                            ->default(now()),
                        DateTimePicker::make('started_at')
                            ->label('Inicio real')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        DateTimePicker::make('completed_at')
                            ->label('Finalización real')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ]),
                Section::make('Observaciones')
                    ->columns(2)
                    ->schema([
                        TextInput::make('file_path')
                            ->label('Ruta del archivo')
                            ->maxLength(500)
                            ->placeholder('Ej: \\\\servidor\\trabajos\\cliente\\archivo.ai')
                            ->columnSpanFull(),
                        Toggle::make('has_plans')
                            ->label('Planos adjuntos')
                            ->inline(false),
                        FileUpload::make('plans_attachment')
                            ->label('Archivo de planos')
                            ->disk('public')
                            ->directory('production-orders/plans')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/zip',
                            ])
                            ->maxSize(20480)
                            ->downloadable()
                            ->openable()
                            ->helperText('Opcional. PDF o imagen de planos.'),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
