<?php

namespace App\Filament\Support;

use App\Enums\AcrylicLightingPricingMode;
use App\Enums\SernaItemType;
use App\Models\AcrylicLightingOption;
use App\Models\QuotePiece;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;
use App\Services\Serna\SernaQuotationEngine;
use App\Support\Money;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Campos de ítem del cotizador Serna, reutilizados en Cotizador y Editar cotización.
 */
class SernaItemFormFields
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        return [
            Select::make('item_type')
                ->label('Tipo de ítem')
                ->options(SernaItemType::class)
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    $set('process_rate_id', null);
                    $set('sheet_price_id', null);
                    $set('catalog_product_id', null);
                    $set('lighting_option_id', null);
                    $set('unit_price', null);
                })
                ->columnSpan(2),
            TextInput::make('material')
                ->label('Material')
                ->maxLength(255)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->columnSpan(2),
            Textarea::make('acabados')
                ->label('Acabados / especificaciones')
                ->rows(3)
                ->columnSpan(2)
                ->helperText('Texto de la columna ACABADOS en la cotización.'),
            Select::make('lighting_option_id')
                ->label('Opción de iluminación / fuente')
                ->options(fn () => AcrylicLightingOption::query()
                    ->active()
                    ->where('pricing_mode', '!=', AcrylicLightingPricingMode::None->value)
                    ->ordered()
                    ->get()
                    ->mapWithKeys(fn (AcrylicLightingOption $light) => [
                        $light->id => $light->name
                            .' · luz '.Money::format($light->unit_price)
                            .' · fuente '.Money::format($light->power_supply_cost),
                    ]))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => self::isItemType($get('item_type'), SernaItemType::Iluminacion)
                    || self::isItemType($get('item_type'), SernaItemType::Fuente))
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set, Get $get): void {
                    if (! $state) {
                        return;
                    }
                    $light = AcrylicLightingOption::query()->find($state);
                    if (! $light) {
                        return;
                    }
                    if (self::isItemType($get('item_type'), SernaItemType::Fuente)) {
                        $set('material', 'FUENTE DE ALIMENTACION');
                        $set('acabados', 'PSU PARA '.$light->name);
                    } else {
                        $set('material', mb_strtoupper($light->name, 'UTF-8'));
                        $set('acabados', $light->pricing_mode->getLabel());
                    }
                })
                ->helperText('La fuente usa el power_supply_cost de la misma opción. El asistente IA suele agregar la fuente sola.')
                ->columnSpan(2),
            Select::make('process_rate_id')
                ->label('Tarifa / proceso')
                ->options(fn (Get $get) => self::processRateOptions($get('item_type')))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => self::usesProcessRate($get('item_type')))
                ->required(fn (Get $get): bool => self::usesProcessRate($get('item_type'))
                    && ! self::isItemType($get('item_type'), SernaItemType::CorteLaser)
                    && ! self::isItemType($get('item_type'), SernaItemType::ManoObra))
                ->columnSpan(2),
            Select::make('sheet_price_id')
                ->label('Lámina (formato · calibre · acabado)')
                ->options(fn () => SernaSheetPrice::query()->active()->ordered()->get()
                    ->mapWithKeys(fn (SernaSheetPrice $sheet) => [
                        $sheet->id => $sheet->label().' · '.Money::format($sheet->price),
                    ]))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => self::isItemType($get('item_type'), SernaItemType::LaminaEntera))
                ->required(fn (Get $get): bool => self::isItemType($get('item_type'), SernaItemType::LaminaEntera))
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set): void {
                    if (! $state) {
                        return;
                    }
                    $sheet = SernaSheetPrice::query()->find($state);
                    if (! $sheet) {
                        return;
                    }
                    $set('width_cm', (float) $sheet->width_cm);
                    $set('height_cm', (float) $sheet->height_cm);
                    $set('thickness_mm', (float) $sheet->thickness_mm);
                    $set('material', 'ACRILICO '.rtrim(rtrim((string) $sheet->thickness_mm, '0'), '.').'MM');
                    $set('acabados', str_replace('_', ' ', $sheet->finish));
                })
                ->columnSpan(2),
            Select::make('catalog_product_id')
                ->label('Producto de catálogo')
                ->options(fn () => SernaCatalogProduct::query()->active()->ordered()->get()
                    ->mapWithKeys(fn (SernaCatalogProduct $p) => [
                        $p->id => $p->label().' · '.Money::format($p->unit_price),
                    ]))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => self::isItemType($get('item_type'), SernaItemType::ProductoCatalogo))
                ->required(fn (Get $get): bool => self::isItemType($get('item_type'), SernaItemType::ProductoCatalogo))
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set): void {
                    if (! $state) {
                        return;
                    }
                    $product = SernaCatalogProduct::query()->find($state);
                    if (! $product) {
                        return;
                    }
                    $set('material', isset($product->specs['thickness_mm'])
                        ? 'ACRILICO '.$product->specs['thickness_mm'].'MM'
                        : mb_strtoupper((string) $product->category, 'UTF-8'));
                    $set('acabados', $product->name);
                    $set('unit_price', Money::formatInputState($product->unit_price));
                    if (isset($product->specs['width_cm'])) {
                        $set('width_cm', $product->specs['width_cm']);
                    }
                    if (isset($product->specs['height_cm'])) {
                        $set('height_cm', $product->specs['height_cm']);
                    }
                    if (isset($product->specs['thickness_mm'])) {
                        $set('thickness_mm', $product->specs['thickness_mm']);
                    }
                })
                ->columnSpan(2),
            TextInput::make('thickness_mm')
                ->label('Calibre (mm)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => self::resolveItemType($get('item_type')) === null
                    || self::isItemType($get('item_type'), SernaItemType::CorteLaser)
                    || self::isItemType($get('item_type'), SernaItemType::ManoObra)
                    || self::isItemType($get('item_type'), SernaItemType::LaminaEntera))
                ->live(debounce: 400),
            TextInput::make('width_cm')
                ->label('Medida X (cm)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => self::needsDimensions($get('item_type')))
                ->live(debounce: 400),
            TextInput::make('height_cm')
                ->label('Medida Y (cm)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => self::needsDimensions($get('item_type')))
                ->live(debounce: 400),
            Placeholder::make('area_cm2_display')
                ->label('Área (cm²)')
                ->content(function (Get $get): string {
                    $x = (float) ($get('width_cm') ?? 0);
                    $y = (float) ($get('height_cm') ?? 0);
                    if ($x <= 0 || $y <= 0) {
                        return '—';
                    }

                    return number_format($x * $y, 0, ',', '.');
                })
                ->visible(fn (Get $get): bool => self::needsDimensions($get('item_type'))),
            MoneyFormat::copInput('unit_price', 'V. unitario (solo precio fijo)')
                ->visible(fn (Get $get): bool => self::isItemType($get('item_type'), SernaItemType::PrecioFijo))
                ->helperText('Transporte e instalación: escríbelo a mano.'),
            TextInput::make('quantity')
                ->label('Cant.')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required()
                ->live(debounce: 400),
            Placeholder::make('line_preview')
                ->label('V. total línea')
                ->content(fn (Get $get): string => self::linePreview($get))
                ->columnSpan(2),
        ];
    }

    public static function resolveItemType(mixed $type): ?SernaItemType
    {
        if ($type instanceof SernaItemType) {
            return $type;
        }

        if (is_string($type) && $type !== '') {
            return SernaItemType::tryFrom($type);
        }

        return null;
    }

    public static function itemTypeValue(mixed $type): ?string
    {
        return self::resolveItemType($type)?->value;
    }

    public static function usesProcessRate(mixed $type): bool
    {
        return self::resolveItemType($type)?->pricingMode() === 'per_cm2';
    }

    public static function needsDimensions(mixed $type): bool
    {
        $enum = self::resolveItemType($type);

        if ($enum === null) {
            return true;
        }

        return $enum->usesArea() || $enum === SernaItemType::LaminaEntera;
    }

    public static function isItemType(mixed $type, SernaItemType $expected): bool
    {
        return self::resolveItemType($type) === $expected;
    }

    /**
     * @return array<int, string>
     */
    public static function processRateOptions(mixed $type): array
    {
        $enum = self::resolveItemType($type);
        if (! $enum || $enum->pricingMode() !== 'per_cm2') {
            return [];
        }

        $category = match ($enum) {
            SernaItemType::CorteLaser => 'corte_laser',
            SernaItemType::ManoObra => 'mano_obra',
            SernaItemType::Terminado => 'terminado',
            SernaItemType::Vinilo => 'vinilo',
            SernaItemType::Plotter => 'plotter',
            SernaItemType::Espejo => 'espejo',
            SernaItemType::Enchapado => 'enchapado',
            default => null,
        };

        if (! $category) {
            return [];
        }

        return SernaProcessRate::query()
            ->active()
            ->category($category)
            ->ordered()
            ->get()
            ->mapWithKeys(fn (SernaProcessRate $rate) => [
                $rate->id => $rate->name.' · $'.number_format((float) $rate->price_per_cm2, 1, ',', '.').'/cm²',
            ])
            ->all();
    }

    public static function linePreview(Get $get): string
    {
        try {
            $itemType = self::itemTypeValue($get('item_type'));
            $item = [
                'item_type' => $itemType,
                'pieza' => $get('pieza'),
                'material' => $get('material'),
                'acabados' => $get('acabados'),
                'width_cm' => $get('width_cm'),
                'height_cm' => $get('height_cm'),
                'thickness_mm' => $get('thickness_mm'),
                'quantity' => $get('quantity') ?: 1,
                'process_rate_id' => $get('process_rate_id'),
                'sheet_price_id' => $get('sheet_price_id'),
                'catalog_product_id' => $get('catalog_product_id'),
                'lighting_option_id' => $get('lighting_option_id'),
                'unit_price' => $get('unit_price'),
            ];

            if (blank($itemType)) {
                return '—';
            }

            $line = app(SernaQuotationEngine::class)->calculateItem($item);

            return Money::format($line['line_total']).' · unit. '.Money::format($line['unit_price']);
        } catch (Throwable) {
            return 'Completa los datos';
        }
    }

    /**
     * Etiqueta del acordeón de pieza: nombre + total (sin esperar el desglose).
     *
     * En editar cotización el repeater de ítems es relationship (dehydrated=false),
     * así que getStateSnapshot() no trae items: hay que leer el estado crudo o el modelo.
     *
     * @param  array<string, mixed>  $state
     */
    public static function pieceLabel(array $state, mixed $schema = null): string|HtmlString
    {
        $state = self::resolvePieceStateForLabel($state, $schema);

        $name = filled($state['name'] ?? null)
            ? (string) $state['name']
            : 'Pieza';

        $total = self::pieceTotal($state);

        if ($total === null) {
            return $name;
        }

        // Total fijo a la derecha: si va al final del texto, al truncar/colapsar “se mueve”.
        return new HtmlString(
            '<span class="fi-piece-label">'
            .'<span class="fi-piece-label-name">'.e($name).'</span>'
            .'<span class="fi-piece-label-total">'.e(Money::format($total)).'</span>'
            .'</span>'
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function resolvePieceStateForLabel(array $state, mixed $schema = null): array
    {
        $items = $state['items'] ?? null;
        if (is_array($items) && $items !== []) {
            return $state;
        }

        if ($schema !== null && method_exists($schema, 'getRawState')) {
            $raw = $schema->getRawState();
            if (is_array($raw) && is_array($raw['items'] ?? null) && $raw['items'] !== []) {
                $state['items'] = $raw['items'];
                if (! filled($state['name'] ?? null) && filled($raw['name'] ?? null)) {
                    $state['name'] = $raw['name'];
                }

                return $state;
            }
        }

        $pieceId = $state['id'] ?? null;
        if (filled($pieceId)) {
            $piece = QuotePiece::query()->with('items')->find($pieceId);
            if ($piece) {
                $state['name'] = $state['name'] ?? $piece->name;
                $state['items'] = $piece->items->map(fn ($item): array => [
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'meta' => $item->meta,
                ])->all();
            }
        }

        return $state;
    }

    /**
     * Suma de líneas calculables de la pieza.
     * En editar cotización los ítems llegan con datos en meta.calculation
     * o solo quantity/unit_price; no depender solo de item_type del formulario.
     *
     * @param  array<string, mixed>  $state
     */
    public static function pieceTotal(array $state): ?float
    {
        $items = array_values(array_filter(
            $state['items'] ?? [],
            fn ($row): bool => is_array($row),
        ));

        if ($items === []) {
            return null;
        }

        $engine = app(SernaQuotationEngine::class);
        $total = 0.0;
        $priced = 0;

        foreach ($items as $row) {
            $normalized = self::normalizeItemRowForPricing($row);
            $lineTotal = null;

            $itemType = self::itemTypeValue($normalized['item_type'] ?? null);
            if (filled($itemType)) {
                try {
                    $line = $engine->calculateItem([
                        'item_type' => $itemType,
                        'pieza' => $state['name'] ?? ($normalized['pieza'] ?? null),
                        'material' => $normalized['material'] ?? null,
                        'acabados' => $normalized['acabados'] ?? null,
                        'width_cm' => $normalized['width_cm'] ?? null,
                        'height_cm' => $normalized['height_cm'] ?? null,
                        'thickness_mm' => $normalized['thickness_mm'] ?? null,
                        'quantity' => $normalized['quantity'] ?? 1,
                        'process_rate_id' => $normalized['process_rate_id'] ?? null,
                        'sheet_price_id' => $normalized['sheet_price_id'] ?? null,
                        'catalog_product_id' => $normalized['catalog_product_id'] ?? null,
                        'lighting_option_id' => $normalized['lighting_option_id'] ?? null,
                        'unit_price' => $normalized['unit_price'] ?? null,
                    ]);
                    $lineTotal = (float) $line['line_total'];
                } catch (Throwable) {
                    $lineTotal = null;
                }
            }

            if ($lineTotal === null && filled($normalized['line_total'] ?? null)) {
                $lineTotal = (float) $normalized['line_total'];
            }

            if ($lineTotal === null) {
                $qty = (float) ($normalized['quantity'] ?? 0);
                $unit = Money::parseInput($normalized['unit_price'] ?? null);
                if ($qty > 0 && $unit !== null) {
                    $lineTotal = $qty * $unit;
                }
            }

            if ($lineTotal === null) {
                continue;
            }

            $total += $lineTotal;
            $priced++;
        }

        return $priced > 0 ? Money::round($total) : null;
    }

    /**
     * Unifica estado de formulario (cotizador) y filas de relación (editar).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeItemRowForPricing(array $row): array
    {
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $calc = is_array($meta['calculation'] ?? null) ? $meta['calculation'] : [];

        return [
            'item_type' => $row['item_type'] ?? $calc['item_type'] ?? null,
            'material' => $row['material'] ?? $calc['material'] ?? null,
            'acabados' => $row['acabados'] ?? $calc['acabados'] ?? null,
            'width_cm' => $row['width_cm'] ?? $calc['width_cm'] ?? null,
            'height_cm' => $row['height_cm'] ?? $calc['height_cm'] ?? null,
            'thickness_mm' => $row['thickness_mm'] ?? $calc['thickness_mm'] ?? null,
            'quantity' => $row['quantity'] ?? $calc['quantity'] ?? 1,
            'process_rate_id' => $row['process_rate_id'] ?? $calc['process_rate_id'] ?? null,
            'sheet_price_id' => $row['sheet_price_id'] ?? $calc['sheet_price_id'] ?? null,
            'catalog_product_id' => $row['catalog_product_id'] ?? $calc['catalog_product_id'] ?? null,
            'lighting_option_id' => $row['lighting_option_id'] ?? $calc['lighting_option_id'] ?? null,
            'unit_price' => $row['unit_price'] ?? $calc['unit_price'] ?? null,
            'line_total' => $row['line_total'] ?? $calc['line_total'] ?? null,
            'pieza' => $row['pieza'] ?? $calc['pieza'] ?? $calc['piece_name'] ?? $meta['piece_name'] ?? null,
        ];
    }
}
