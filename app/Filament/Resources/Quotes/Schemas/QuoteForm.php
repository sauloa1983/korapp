<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Enums\ItemType;
use App\Enums\QuoteStatus;
use App\Enums\SernaItemType;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Support\MoneyFormat;
use App\Filament\Support\SernaItemFormFields;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Lead;
use App\Models\SernaCatalogProduct;
use App\Services\Serna\SernaQuotationEngine;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        // Importante: EditRecord pone columns(2) por defecto y deja el layout al ~50%.
        return $schema
            ->columns(1)
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 12,
                ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'fi-quote-edit-layout'])
                    ->schema([
                        Section::make('Encabezado comercial')
                            ->description('Misma estructura del cotizador Serna.')
                            ->columns(2)
                            ->columnSpan(['default' => 'full', 'lg' => 8])
                            ->extraAttributes(['class' => 'fi-quote-edit-header'])
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Empresa / Cliente')
                                    ->relationship(
                                        name: 'customer',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Customer $record): string => $record->displayName()
                                        .(filled($record->tax_id) ? " · {$record->tax_id}" : ' · sin documento'))
                                    ->searchable(['name', 'company_name', 'contact_name', 'tax_id', 'email', 'city'])
                                    ->preload()
                                    ->nullable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $customer = Customer::query()->find($state);
                                        $set('contact_name', $customer?->contact_name ?: $customer?->name);
                                        if ($customer?->is_retenedor) {
                                            $set('withholding_rate', $customer->retenedor_percent);
                                        }
                                    })
                                    ->createOptionForm(CustomerForm::identityFields(requireDocument: true))
                                    ->createOptionUsing(fn (array $data): int => CustomerForm::createFromQuickForm($data)->getKey())
                                    ->createOptionModalHeading('Nuevo cliente'),
                                Select::make('lead_id')
                                    ->label('Prospecto')
                                    ->relationship(
                                        name: 'lead',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Lead $record): string => filled($record->company)
                                        ? "{$record->name} ({$record->company})"
                                        : (string) $record->name)
                                    ->searchable(['name', 'company', 'email', 'phone'])
                                    ->preload()
                                    ->nullable(),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(QuoteStatus::class)
                                    ->required()
                                    ->native(false)
                                    ->default(QuoteStatus::Draft),
                                TextInput::make('project_name')
                                    ->label('Proyecto')
                                    ->maxLength(255)
                                    ->columnSpanFull()
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('contact_name')
                                    ->label('Contacto')
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('payment_form')
                                    ->label('Forma de pago')
                                    ->datalist(['CONTADO', '50% ANTICIPO', 'TRANSFERENCIA', 'CREDITO'])
                                    ->maxLength(100)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('validity_days')
                                    ->label('Vigencia (días)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(10)
                                    ->required()
                                    ->live(debounce: 400)
                                    ->helperText('Al cambiar los días se recalcula la fecha «Válida hasta».')
                                    ->afterStateUpdated(function ($state, Set $set, callable $get): void {
                                        $days = max(1, (int) ($state ?: 10));
                                        $set('valid_until', now()->addDays($days)->toDateString());

                                        $terms = (string) ($get('terms') ?? '');
                                        $daysLabel = $days === 1 ? '1 día calendario' : "{$days} días calendario";
                                        $replacement = "Vigencia de la cotización: {$daysLabel}.";
                                        if (preg_match('/Vigencia de la cotización:[^\n]*/u', $terms)) {
                                            $set('terms', preg_replace('/Vigencia de la cotización:[^\n]*/u', $replacement, $terms, 1));
                                        }
                                    }),
                                DatePicker::make('valid_until')
                                    ->label('Válida hasta')
                                    ->native(false)
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Se calcula sola: hoy + vigencia en días.'),
                                DatePicker::make('delivery_date')
                                    ->label('Fecha de entrega')
                                    ->native(false),
                                TextInput::make('delivery_note')
                                    ->label('Entrega (texto)')
                                    ->placeholder('A CONVENIR')
                                    ->maxLength(100)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('advance_percent')
                                    ->label('Anticipo %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(50),
                                TextInput::make('withholding_rate')
                                    ->label('Retefuente %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),
                                Textarea::make('notes')
                                    ->label('Observaciones')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('terms')
                                    ->label('Forma de pago / condiciones')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Totales')
                            ->description('IVA según configuración de empresa. Retención según retenedor del cliente.')
                            ->columns(1)
                            ->columnSpan(['default' => 'full', 'lg' => 4])
                            ->visibleOn('edit')
                            ->extraAttributes(['class' => 'fi-quote-edit-totals'])
                            ->schema([
                                MoneyFormat::display('subtotal', 'Subtotal'),
                                TextInput::make('iva_rate')
                                    ->label('IVA %')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix('%')
                                    ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.')),
                                MoneyFormat::display('iva_amount', 'Valor IVA'),
                                MoneyFormat::display('withholding_amount', 'Retención'),
                                MoneyFormat::display('total', 'Total documento'),
                            ]),
                        Section::make('Piezas de la cotización')
                            ->description('Igual que en el cotizador: cada pieza agrupa sus materiales e ítems.')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'fi-quote-edit-pieces'])
                            ->schema([
                                Repeater::make('pieces')
                                    ->relationship()
                                    ->label('Piezas')
                                    ->live(debounce: 400)
                                    ->extraAttributes(['class' => 'fi-cotizador-pieces-zebra'])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Pieza')
                                            ->placeholder('PROYECTO MEDELLIN / AVISO FACHADA')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(debounce: 400)
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                            ->columnSpanFull(),
                                        TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(),
                                        Repeater::make('items')
                                            ->relationship()
                                            ->label('Ítems de esta pieza')
                                            ->live(debounce: 400)
                                            ->schema([
                                                ...SernaItemFormFields::make(),
                                                TextInput::make('sort_order')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->hidden(),
                                                Hidden::make('meta'),
                                                Hidden::make('item_id'),
                                                Hidden::make('description'),
                                            ])
                                            ->columns(6)
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar ítem a la pieza')
                                            ->cloneable()
                                            ->collapsible()
                                            ->extraAttributes(['class' => 'fi-cotizador-items-zebra'])
                                            ->orderColumn('sort_order')
                                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::hydrateItemFromMeta($data))
                                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::dehydrateItemToMeta($data))
                                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Model $record): array => self::dehydrateItemToMeta($data, $record))
                                            ->itemLabel(function (array $state): ?string {
                                                $material = $state['material'] ?? null;
                                                $type = SernaItemFormFields::resolveItemType($state['item_type'] ?? null);

                                                return $material ?: $type?->getLabel() ?: 'Ítem';
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Agregar pieza')
                                    ->cloneable()
                                    ->collapsible()
                                    ->orderColumn('sort_order')
                                    ->truncateItemLabel(false)
                                    ->itemLabel(fn (array $state, mixed $schema = null) => SernaItemFormFields::pieceLabel($state, $schema)),
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function hydrateItemFromMeta(array $data): array
    {
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $calc = is_array($meta['calculation'] ?? null) ? $meta['calculation'] : [];

        $data['item_type'] = $calc['item_type'] ?? $data['item_type'] ?? null;
        $data['material'] = $calc['material'] ?? $data['material'] ?? '';
        $data['acabados'] = self::sanitizeHydratedAcabados(
            (string) ($calc['acabados'] ?? $data['acabados'] ?? ''),
            SernaItemFormFields::itemTypeValue($data['item_type'] ?? null),
        );
        $data['width_cm'] = $calc['width_cm'] ?? $data['width_cm'] ?? null;
        $data['height_cm'] = $calc['height_cm'] ?? $data['height_cm'] ?? null;
        $data['thickness_mm'] = $calc['thickness_mm'] ?? $data['thickness_mm'] ?? null;
        $data['process_rate_id'] = $calc['process_rate_id'] ?? $data['process_rate_id'] ?? null;
        $data['sheet_price_id'] = $calc['sheet_price_id'] ?? $data['sheet_price_id'] ?? null;
        $data['catalog_product_id'] = $calc['catalog_product_id'] ?? $data['catalog_product_id'] ?? null;
        $data['lighting_option_id'] = $calc['lighting_option_id'] ?? $data['lighting_option_id'] ?? null;
        $data['quantity'] = $data['quantity'] ?? $calc['quantity'] ?? 1;
        $data['unit_price'] = Money::formatInputState($data['unit_price'] ?? $calc['unit_price'] ?? 0);

        return $data;
    }

    private static function sanitizeHydratedAcabados(string $acabados, ?string $itemType): string
    {
        $acabados = trim($acabados);
        if ($acabados === '') {
            return '';
        }

        $upper = mb_strtoupper($acabados, 'UTF-8');
        $hits = 0;
        foreach (['CONTACTO', 'CONTADO', 'ANTICIPO', 'VINILO', 'TRANSPORTE', 'INSTALACION', 'LED', 'BACKLIGHT', 'ACRILICO DE'] as $token) {
            if (str_contains($upper, $token)) {
                $hits++;
            }
        }

        // Pedido completo pegado, o texto que empieza como nombre de pieza + material.
        $looksLikeDump = $hits >= 2
            || mb_strlen($acabados) > 120
            || (bool) preg_match('/^AVISO\b.+,/u', $upper);

        if (! $looksLikeDump) {
            return mb_strtoupper(mb_substr($acabados, 0, 120, 'UTF-8'), 'UTF-8');
        }

        return match ($itemType) {
            'terminado' => 'TERMINADO SEGÚN DISEÑO APROBADO',
            'corte_laser' => 'CORTE LÁSER SEGÚN DISEÑO APROBADO',
            'vinilo' => 'VINILO SEGÚN DISEÑO APROBADO',
            'iluminacion' => 'ILUMINACION SEGÚN DISEÑO',
            'precio_fijo' => 'VALOR MANUAL',
            default => 'SEGÚN DISEÑO APROBADO',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function dehydrateItemToMeta(array $data, ?Model $record = null): array
    {
        $existingMeta = is_array($record?->getAttribute('meta')) ? $record->getAttribute('meta') : [];
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        if ($meta === [] && $existingMeta !== []) {
            $meta = $existingMeta;
        } else {
            $meta = array_replace_recursive($existingMeta, $meta);
        }

        $itemType = SernaItemFormFields::itemTypeValue($data['item_type'] ?? null);
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $input = [
            'item_type' => $itemType,
            'material' => trim((string) ($data['material'] ?? '')),
            'acabados' => trim((string) ($data['acabados'] ?? '')),
            'width_cm' => filled($data['width_cm'] ?? null) ? (float) $data['width_cm'] : null,
            'height_cm' => filled($data['height_cm'] ?? null) ? (float) $data['height_cm'] : null,
            'thickness_mm' => filled($data['thickness_mm'] ?? null) ? (float) $data['thickness_mm'] : null,
            'quantity' => $quantity,
            'process_rate_id' => filled($data['process_rate_id'] ?? null) ? (int) $data['process_rate_id'] : null,
            'sheet_price_id' => filled($data['sheet_price_id'] ?? null) ? (int) $data['sheet_price_id'] : null,
            'catalog_product_id' => filled($data['catalog_product_id'] ?? null) ? (int) $data['catalog_product_id'] : null,
            'lighting_option_id' => filled($data['lighting_option_id'] ?? null) ? (int) $data['lighting_option_id'] : null,
            'unit_price' => $data['unit_price'] ?? null,
            'pieza' => trim((string) ($meta['piece_name'] ?? $meta['calculation']['pieza'] ?? '')),
        ];

        $line = null;
        $pendingReason = null;

        if (filled($itemType)) {
            try {
                $line = app(SernaQuotationEngine::class)->calculateItem($input);
            } catch (Throwable $exception) {
                $pendingReason = $exception->getMessage();
            }
        } else {
            $pendingReason = 'Falta el tipo de ítem.';
        }

        if (is_array($line)) {
            $calc = $line;
            $data['unit_price'] = (float) $line['unit_price'];
            $data['quantity'] = (float) ($line['quantity'] ?? $quantity);
            $data['description'] = self::compactDescription($line);
            $data['item_id'] = self::resolveCatalogItemId($line) ?? ($data['item_id'] ?? $record?->getAttribute('item_id'));
            $meta['incomplete'] = false;
            $meta['pending_reason'] = null;
        } else {
            $calc = array_merge(
                is_array($meta['calculation'] ?? null) ? $meta['calculation'] : [],
                $input,
                [
                    'item_type_label' => SernaItemFormFields::resolveItemType($itemType)?->getLabel(),
                    'incomplete' => true,
                    'pending_reason' => $pendingReason,
                ],
            );
            $parsed = Money::parseInput($data['unit_price'] ?? null);
            $data['unit_price'] = $parsed ?? (float) ($record?->getAttribute('unit_price') ?? 0);
            $data['quantity'] = $quantity;
            $data['description'] = self::compactDescription($calc);
            $data['item_id'] = $data['item_id'] ?? $record?->getAttribute('item_id') ?? self::resolveCatalogItemId($calc);
            $meta['incomplete'] = true;
            $meta['pending_reason'] = $pendingReason;
        }

        $meta['source'] = $meta['source'] ?? 'serna_cotizador';
        $meta['calculation'] = $calc;
        $data['meta'] = $meta;

        unset(
            $data['item_type'],
            $data['material'],
            $data['acabados'],
            $data['width_cm'],
            $data['height_cm'],
            $data['thickness_mm'],
            $data['process_rate_id'],
            $data['sheet_price_id'],
            $data['catalog_product_id'],
            $data['lighting_option_id'],
            $data['area_cm2_display'],
            $data['line_preview'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function compactDescription(array $line): string
    {
        $pending = ! empty($line['incomplete']) ? '[PENDIENTE] ' : '';
        $pieza = trim((string) ($line['pieza'] ?? $line['piece_name'] ?? ''));
        $material = trim((string) ($line['material'] ?? ''));
        $acabados = trim((string) ($line['acabados'] ?? ''));
        $typeLabel = trim((string) ($line['item_type_label'] ?? ''));

        if (str_contains($acabados, ' · Material:') || str_contains($acabados, '"pricing_mode"')) {
            $acabados = $typeLabel !== '' ? $typeLabel : 'Ver detalle en meta';
        }

        $parts = array_values(array_filter([
            $pending.$pieza,
            $material !== '' ? 'Material: '.$material : null,
            $acabados !== '' ? 'Acabados: '.$acabados : null,
        ]));

        $description = implode(' · ', $parts);
        if ($description === '') {
            $description = $typeLabel !== '' ? $typeLabel : 'Ítem Serna';
        }

        return mb_substr($description, 0, 2000, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function resolveCatalogItemId(array $line): ?int
    {
        $type = SernaItemType::tryFrom((string) ($line['item_type'] ?? ''));

        if ($type === SernaItemType::ProductoCatalogo && filled($line['catalog_product_id'] ?? null)) {
            $product = SernaCatalogProduct::query()->find((int) $line['catalog_product_id']);
            if ($product) {
                $item = Item::query()->where('sku', $product->sku)->first();
                if ($item) {
                    return $item->id;
                }
            }
        }

        $sku = match ($type) {
            SernaItemType::LaminaEntera => 'SERNA-LAMINA',
            SernaItemType::PrecioFijo => 'SERNA-MANUAL',
            default => 'SERNA-SERVICIO',
        };

        $name = match ($sku) {
            'SERNA-LAMINA' => 'Lámina acrílica Serna',
            'SERNA-MANUAL' => 'Transporte / instalación / valor manual',
            default => 'Servicio / manufactura Serna',
        };

        $itemType = match ($sku) {
            'SERNA-LAMINA' => ItemType::MateriaPrima,
            default => ItemType::ProductoTerminado,
        };

        return Item::query()->where('sku', $sku)->value('id')
            ?? Item::query()->create([
                'sku' => $sku,
                'name' => $name,
                'type' => $itemType,
                'unit_of_measure' => 'und',
                'stock' => 0,
                'min_stock' => 0,
                'cost' => 0,
                'price' => 0,
                'is_active' => true,
            ])->id;
    }
}
