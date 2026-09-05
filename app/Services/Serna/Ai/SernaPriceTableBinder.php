<?php

namespace App\Services\Serna\Ai;

use App\Enums\AcrylicLightingPricingMode;
use App\Enums\SernaItemType;
use App\Enums\SernaSheetFinish;
use App\Models\AcrylicLightingOption;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;
use App\Services\Serna\SernaQuotationEngine;
use App\Support\Money;
use Throwable;

/**
 * Asegura que cada ítem del borrador use IDs/precios de las tablas Serna activas.
 */
class SernaPriceTableBinder
{
    public function __construct(
        private readonly SernaQuotationEngine $engine,
    ) {}

    /**
     * @param  array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string, explanation?: ?string, unrecognized?: list<string>}  $draft
     * @return array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string, explanation?: ?string, pricing: array<string, mixed>, unresolved: list<string>}
     */
    public function bind(array $draft): array
    {
        $pieces = [];
        $unresolved = [];

        foreach ($draft['unrecognized'] ?? [] as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                $unresolved[] = 'No reconocido: '.$message;
            }
        }

        foreach ($draft['pieces'] ?? [] as $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $pieceName = mb_strtoupper(trim((string) ($piece['name'] ?? 'PIEZA')), 'UTF-8');
            $items = [];

            foreach ($piece['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $bound = $this->bindItem($item);
                if ($bound !== null) {
                    $items[] = $bound;

                    continue;
                }

                $material = trim((string) ($item['material'] ?? $item['acabados'] ?? $item['item_type'] ?? 'ítem'));
                $unresolved[] = "No se pudo crear/enlazar «{$material}» en la pieza «{$pieceName}» (sin tarifa/catálogo coincidente).";
            }

            $items = $this->inheritDimensionsWithinPiece($items);
            $items = $this->ensurePowerSupplyItems($items);

            if ($items === []) {
                if (($piece['items'] ?? []) !== []) {
                    $unresolved[] = "La pieza «{$pieceName}» no quedó con ítems válidos.";
                }

                continue;
            }

            $pieces[] = [
                'name' => $pieceName,
                'items' => $items,
            ];
        }

        $draft['pieces'] = $pieces;
        $draft['unresolved'] = array_values(array_unique($unresolved));
        $draft['pricing'] = $this->priceSnapshot($pieces);

        $tableNote = $this->pricingFootnote($draft['pricing']);
        $existing = trim((string) ($draft['explanation'] ?? ''));
        $draft['explanation'] = trim(implode("\n\n", array_filter([$existing, $tableNote])));

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function bindItem(array $item): ?array
    {
        $type = SernaItemType::tryFrom((string) ($item['item_type'] ?? ''));
        if (! $type) {
            return null;
        }

        $item['item_type'] = $type->value;
        $item['quantity'] = max(1, (int) ($item['quantity'] ?? 1));
        $thickness = $this->floatOrNull($item['thickness_mm'] ?? null);
        $item['thickness_mm'] = $thickness;

        return match ($type->pricingMode()) {
            'full_sheet' => $this->bindSheet($item, $thickness),
            'fixed' => $this->bindFixed($item, $type),
            'lighting', 'power_supply' => $this->bindLighting($item, $type),
            default => $this->bindProcessRate($item, $type, $thickness),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function bindLighting(array $item, SernaItemType $type): ?array
    {
        $lighting = $this->findLightingOption($item);
        if (! $lighting || $lighting->pricing_mode === AcrylicLightingPricingMode::None) {
            return null;
        }

        if ($type === SernaItemType::Fuente && (float) $lighting->power_supply_cost <= 0) {
            return null;
        }

        $item['lighting_option_id'] = $lighting->id;
        $item['process_rate_id'] = null;
        $item['sheet_price_id'] = null;
        $item['catalog_product_id'] = null;
        $item['unit_price'] = null;
        $item['material'] = filled($item['material'] ?? null)
            ? $item['material']
            : ($type === SernaItemType::Fuente
                ? 'FUENTE DE ALIMENTACION'
                : mb_strtoupper($lighting->name, 'UTF-8'));
        $item['acabados'] = filled($item['acabados'] ?? null)
            ? $item['acabados']
            : ($type === SernaItemType::Fuente
                ? 'PSU PARA '.$lighting->name
                : $lighting->pricing_mode->getLabel());
        $item['table_unit_price'] = $type === SernaItemType::Fuente
            ? (float) $lighting->power_supply_cost
            : (float) $lighting->unit_price;
        $item['table_source'] = 'acrylic_lighting_options';

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findLightingOption(array $item): ?AcrylicLightingOption
    {
        if (filled($item['lighting_option_id'] ?? null)) {
            $option = AcrylicLightingOption::query()->active()->find((int) $item['lighting_option_id']);
            if ($option) {
                return $option;
            }
        }

        $haystack = mb_strtolower(trim(
            ($item['material'] ?? '').' '.($item['acabados'] ?? '').' '.($item['lighting_name'] ?? ''),
        ), 'UTF-8');

        $options = AcrylicLightingOption::query()
            ->active()
            ->where('pricing_mode', '!=', AcrylicLightingPricingMode::None->value)
            ->ordered()
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($options as $option) {
            $name = mb_strtolower($option->name, 'UTF-8');
            $score = 0;

            if ($haystack !== '' && str_contains($haystack, $name)) {
                $score += 80;
            }
            if (str_contains($haystack, 'backlight') && str_contains($name, 'backlight')) {
                $score += 60;
            }
            if ((str_contains($haystack, 'perimetral') || str_contains($haystack, 'modulo') || str_contains($haystack, 'módulo'))
                && str_contains($name, 'perimetral')) {
                $score += 60;
            }
            if ((str_contains($haystack, 'neon') || str_contains($haystack, 'neón') || str_contains($haystack, 'flex'))
                && str_contains($name, 'neón')) {
                $score += 60;
            }
            if (str_contains($haystack, 'led') && str_contains($name, 'led')) {
                $score += 30;
            }
            if (str_contains($haystack, 'luz') || str_contains($haystack, 'ilumin')) {
                $score += 10;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $option;
            }
        }

        // Default razonable si solo dijeron "luces/LED".
        if ($bestScore < 20 && (str_contains($haystack, 'led') || str_contains($haystack, 'luz') || str_contains($haystack, 'ilumin') || str_contains($haystack, 'fuente'))) {
            return $options->firstWhere('name', 'LED perimetral / módulos') ?? $options->first();
        }

        return $bestScore >= 20 ? $best : null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function inheritDimensionsWithinPiece(array $items): array
    {
        $refX = null;
        $refY = null;

        foreach ($items as $item) {
            $x = $this->floatOrNull($item['width_cm'] ?? null);
            $y = $this->floatOrNull($item['height_cm'] ?? null);
            if ($x && $y && $x > 1 && $y > 1) {
                $refX = $x;
                $refY = $y;
                break;
            }
        }

        if ($refX === null || $refY === null) {
            return $items;
        }

        foreach ($items as $index => $item) {
            $type = SernaItemType::tryFrom((string) ($item['item_type'] ?? ''));
            if (! in_array($type, [SernaItemType::Iluminacion, SernaItemType::Fuente], true)) {
                continue;
            }

            if ($this->floatOrNull($item['width_cm'] ?? null) === null || (float) ($item['width_cm'] ?? 0) <= 0) {
                $items[$index]['width_cm'] = $refX;
            }
            if ($this->floatOrNull($item['height_cm'] ?? null) === null || (float) ($item['height_cm'] ?? 0) <= 0) {
                $items[$index]['height_cm'] = $refY;
            }
        }

        return $items;
    }

    /**
     * Si hay luces y no hay fuente, agrega la fuente desde la misma opción de iluminación.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function ensurePowerSupplyItems(array $items): array
    {
        $hasFuente = collect($items)->contains(
            fn (array $item): bool => ($item['item_type'] ?? null) === SernaItemType::Fuente->value
        );

        if ($hasFuente) {
            return $items;
        }

        foreach ($items as $item) {
            if (($item['item_type'] ?? null) !== SernaItemType::Iluminacion->value) {
                continue;
            }

            $lightingId = (int) ($item['lighting_option_id'] ?? 0);
            if ($lightingId < 1) {
                continue;
            }

            $lighting = AcrylicLightingOption::query()->active()->find($lightingId);
            if (! $lighting || (float) $lighting->power_supply_cost <= 0) {
                continue;
            }

            $items[] = [
                'item_type' => SernaItemType::Fuente->value,
                'material' => 'FUENTE DE ALIMENTACION',
                'acabados' => 'PSU PARA '.$lighting->name,
                'width_cm' => $item['width_cm'] ?? 1,
                'height_cm' => $item['height_cm'] ?? 1,
                'thickness_mm' => null,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'lighting_option_id' => $lighting->id,
                'process_rate_id' => null,
                'sheet_price_id' => null,
                'catalog_product_id' => null,
                'unit_price' => null,
                'table_unit_price' => (float) $lighting->power_supply_cost,
                'table_source' => 'acrylic_lighting_options',
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function bindProcessRate(array $item, SernaItemType $type, ?float $thickness): ?array
    {
        $rate = $this->findProcessRate($type, $item, $thickness);
        if (! $rate) {
            return null;
        }

        // Los precios por cm² siempre salen de la tabla; nunca de la IA.
        $item['process_rate_id'] = $rate->id;
        $item['process_rate_code'] = $rate->code;
        $item['sheet_price_id'] = null;
        $item['catalog_product_id'] = null;
        $item['unit_price'] = null;
        $item['table_price_per_cm2'] = (float) $rate->price_per_cm2;
        $item['table_rate_name'] = $rate->name;

        if ($thickness === null && $rate->thickness_mm_min !== null && $rate->thickness_mm_max !== null
            && (float) $rate->thickness_mm_min === (float) $rate->thickness_mm_max) {
            $item['thickness_mm'] = (float) $rate->thickness_mm_min;
        }

        if (blank($item['material'] ?? null)) {
            $mm = $item['thickness_mm'] ?? $thickness;
            $item['material'] = $mm
                ? 'ACRILICO '.$this->n((float) $mm).'MM'
                : mb_strtoupper($rate->name, 'UTF-8');
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function bindSheet(array $item, ?float $thickness): ?array
    {
        $sheet = $this->findSheet($item, $thickness);
        if (! $sheet) {
            return null;
        }

        $item['sheet_price_id'] = $sheet->id;
        $item['process_rate_id'] = null;
        $item['catalog_product_id'] = null;
        $item['unit_price'] = null;
        $item['width_cm'] = (float) $sheet->width_cm;
        $item['height_cm'] = (float) $sheet->height_cm;
        $item['thickness_mm'] = (float) $sheet->thickness_mm;
        $item['material'] = 'ACRILICO '.$this->n((float) $sheet->thickness_mm).'MM';
        $item['acabados'] = filled($item['acabados'] ?? null)
            ? $item['acabados']
            : (SernaSheetFinish::tryFrom($sheet->finish)?->getLabel() ?? $sheet->finish);
        $item['table_unit_price'] = (float) $sheet->price;
        $item['table_source'] = $sheet->price_source;

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function bindFixed(array $item, SernaItemType $type): ?array
    {
        $product = $this->findCatalogProduct($item);

        if ($product) {
            $item['item_type'] = SernaItemType::ProductoCatalogo->value;
            $item['catalog_product_id'] = $product->id;
            $item['process_rate_id'] = null;
            $item['sheet_price_id'] = null;
            $item['unit_price'] = null; // precio desde tabla
            $item['material'] = filled($item['material'] ?? null)
                ? $item['material']
                : mb_strtoupper((string) ($product->specs['thickness_mm'] ?? $product->category), 'UTF-8');
            $item['acabados'] = filled($item['acabados'] ?? null) ? $item['acabados'] : $product->name;
            $item['table_unit_price'] = (float) $product->unit_price;
            $item['table_sku'] = $product->sku;

            if (isset($product->specs['width_cm'])) {
                $item['width_cm'] = (float) $product->specs['width_cm'];
            }
            if (isset($product->specs['height_cm'])) {
                $item['height_cm'] = (float) $product->specs['height_cm'];
            }
            if (isset($product->specs['thickness_mm'])) {
                $item['thickness_mm'] = (float) $product->specs['thickness_mm'];
            }

            return $item;
        }

        // Precio fijo (transporte/instalación): se escribe a mano, no se calcula.
        // Conservamos el ítem aunque falte el valor para que el vendedor lo complete.
        if ($type === SernaItemType::PrecioFijo) {
            $unitPrice = Money::parseInput($item['unit_price'] ?? null);
            $item['catalog_product_id'] = null;
            $item['process_rate_id'] = null;
            $item['sheet_price_id'] = null;
            $item['lighting_option_id'] = null;
            $item['material'] = filled($item['material'] ?? null)
                ? $item['material']
                : 'TRANSPORTE E INSTALACION';
            $item['acabados'] = filled($item['acabados'] ?? null)
                ? $item['acabados']
                : 'VALOR MANUAL — completar en cotizador';
            $item['width_cm'] = $this->floatOrNull($item['width_cm'] ?? null) ?? 1;
            $item['height_cm'] = $this->floatOrNull($item['height_cm'] ?? null) ?? 1;

            if ($unitPrice !== null && $unitPrice > 0) {
                $item['unit_price'] = Money::formatInputState((int) round($unitPrice));
                $item['table_unit_price'] = $unitPrice;
                $item['table_source'] = 'manual_explicit';
            } else {
                $item['unit_price'] = null;
                $item['table_unit_price'] = null;
                $item['table_source'] = 'manual_pending';
            }

            return $item;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findProcessRate(SernaItemType $type, array $item, ?float $thickness): ?SernaProcessRate
    {
        if (filled($item['process_rate_id'] ?? null)) {
            $rate = SernaProcessRate::query()->active()->find((int) $item['process_rate_id']);
            if ($rate) {
                return $rate;
            }
        }

        if (filled($item['process_rate_code'] ?? null)) {
            $rate = SernaProcessRate::query()->active()->where('code', $item['process_rate_code'])->first();
            if ($rate) {
                return $rate;
            }
        }

        $category = match ($type) {
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
            return null;
        }

        $rows = SernaProcessRate::query()->active()->category($category)->ordered()->get();

        if (in_array($type, [SernaItemType::CorteLaser, SernaItemType::ManoObra], true)) {
            if ($thickness === null) {
                $thickness = 3.0;
            }

            return $rows->first(fn (SernaProcessRate $row): bool => $row->appliesToThickness($thickness));
        }

        $haystack = mb_strtolower(
            trim(($item['acabados'] ?? '').' '.($item['material'] ?? '').' '.($item['process_rate_code'] ?? '')),
            'UTF-8',
        );

        $preferredCode = match ($type) {
            SernaItemType::Terminado => match (true) {
                str_contains($haystack, 'pestaña') || str_contains($haystack, 'pestana') => 'letra_pestana',
                str_contains($haystack, 'curva') && str_contains($haystack, 'tapa') => 'letra_curva_con_tapa',
                str_contains($haystack, 'recta') && str_contains($haystack, 'tapa') => 'letra_recta_con_tapa',
                str_contains($haystack, 'curva') => 'letra_curva_sin_tapa',
                str_contains($haystack, 'caja') || str_contains($haystack, 'canton') => 'caja_cantonera',
                default => 'letra_recta_sin_tapa',
            },
            SernaItemType::Vinilo => str_contains($haystack, 'instal') ? 'vinilo_instalado' : 'vinilo_adhesivo',
            SernaItemType::Plotter => str_contains($haystack, 'instal') ? 'plotter_instalado' : 'plotter',
            SernaItemType::Espejo => (str_contains($haystack, 'rosa') || str_contains($haystack, 'rojo') || str_contains($haystack, 'bronce'))
                ? 'espejo_rosa_rojo_bronce'
                : 'espejo_plata_dorado',
            SernaItemType::Enchapado => 'enchapado',
            default => null,
        };

        if ($preferredCode) {
            $match = $rows->firstWhere('code', $preferredCode);
            if ($match) {
                return $match;
            }
        }

        return $rows->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findSheet(array $item, ?float $thickness): ?SernaSheetPrice
    {
        if (filled($item['sheet_price_id'] ?? null)) {
            $sheet = SernaSheetPrice::query()->active()->find((int) $item['sheet_price_id']);
            if ($sheet) {
                return $sheet;
            }
        }

        $format = $item['format'] ?? null;
        if (! is_string($format) || $format === '') {
            $w = $this->floatOrNull($item['width_cm'] ?? null);
            $h = $this->floatOrNull($item['height_cm'] ?? null);
            if ($w && $h) {
                $format = $this->n($w).'x'.$this->n($h);
            }
        }

        $finish = $this->resolveFinish($item);
        $thickness ??= $this->floatOrNull($item['thickness_mm'] ?? null) ?? 3.0;

        $query = SernaSheetPrice::query()->active()->where('thickness_mm', $thickness);

        if (is_string($format) && $format !== '') {
            $query->where('format', $format);
        }

        if ($finish) {
            $query->where('finish', $finish);
        }

        $sheet = $query->ordered()->first();
        if ($sheet) {
            return $sheet;
        }

        // Fallback: mismo calibre + acabado, formato 120x180 oficial.
        return SernaSheetPrice::query()
            ->active()
            ->where('thickness_mm', $thickness)
            ->where('finish', $finish ?? SernaSheetFinish::CristalOpal->value)
            ->where('format', '120x180')
            ->first()
            ?? SernaSheetPrice::query()->active()->where('thickness_mm', $thickness)->ordered()->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findCatalogProduct(array $item): ?SernaCatalogProduct
    {
        if (filled($item['catalog_product_id'] ?? null)) {
            $product = SernaCatalogProduct::query()->active()->find((int) $item['catalog_product_id']);
            if ($product) {
                return $product;
            }
        }

        if (filled($item['sku'] ?? null)) {
            $product = SernaCatalogProduct::query()->active()->where('sku', $item['sku'])->first();
            if ($product) {
                return $product;
            }
        }

        $haystack = mb_strtolower(trim(
            ($item['material'] ?? '').' '.($item['acabados'] ?? '').' '.($item['pieza'] ?? ''),
        ), 'UTF-8');

        if ($haystack === '') {
            return null;
        }

        // No mapear "instalación" genérica a un producto de catálogo salvo coincidencia clara.
        $products = SernaCatalogProduct::query()->active()->ordered()->get();
        $best = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $sku = mb_strtolower($product->sku, 'UTF-8');
            $name = mb_strtolower($product->name, 'UTF-8');
            $category = mb_strtolower($product->category, 'UTF-8');
            $score = 0;

            if (str_contains($haystack, $sku)) {
                $score += 100;
            }
            if (str_contains($haystack, $name)) {
                $score += 80;
            }
            if ($category === 'cubrealfombra' && str_contains($haystack, 'cubrealfombra')) {
                $score += 10;
                if (str_contains($haystack, 'presiden') && str_contains($name, 'presidente')) {
                    $score += 50;
                } elseif (str_contains($haystack, 'sub') && str_contains($name, 'sub')) {
                    $score += 50;
                } elseif (str_contains($haystack, 'gerente') && str_contains($name, 'gerente') && ! str_contains($name, 'sub')) {
                    $score += 50;
                } elseif (str_contains($haystack, 'secret') && str_contains($name, 'secret')) {
                    $score += 50;
                }
            }
            if ($category === 'cuna' && str_contains($haystack, 'cuna')) {
                $score += 40;
            }
            if ($category === 'cono' && str_contains($haystack, 'cono')) {
                $score += 40;
            }
            if (str_contains($haystack, 'plantilla') && str_contains($name, 'plantilla')) {
                $score += 60;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $product;
            }
        }

        return $bestScore >= 40 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveFinish(array $item): ?string
    {
        if (filled($item['finish'] ?? null) && SernaSheetFinish::tryFrom((string) $item['finish'])) {
            return (string) $item['finish'];
        }

        $haystack = mb_strtolower(($item['acabados'] ?? '').' '.($item['material'] ?? ''), 'UTF-8');

        return match (true) {
            str_contains($haystack, 'estamp') || str_contains($haystack, '2 color') || str_contains($haystack, 'dos color') => SernaSheetFinish::DosColoresEstampada->value,
            str_contains($haystack, 'color') && ! str_contains($haystack, 'cristal') => SernaSheetFinish::Color->value,
            default => SernaSheetFinish::CristalOpal->value,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return array<string, mixed>
     */
    private function priceSnapshot(array $pieces): array
    {
        if ($pieces === []) {
            return [
                'ok' => false,
                'subtotal' => 0,
                'lines' => [],
                'errors' => ['Sin piezas enlazadas a tarifas Serna.'],
            ];
        }

        try {
            $proposal = $this->engine->calculateProposalFromPieces($pieces);

            return [
                'ok' => true,
                'subtotal' => $proposal['subtotal'],
                'iva_amount' => $proposal['iva_amount'],
                'total_payable' => $proposal['total_payable'],
                'lines' => collect($proposal['items'])->map(fn (array $line): array => [
                    'pieza' => $line['pieza'] ?? '',
                    'material' => $line['material'] ?? '',
                    'pricing_mode' => $line['pricing_mode'] ?? '',
                    'process_rate_code' => $line['process_rate_code'] ?? null,
                    'price_per_cm2' => $line['price_per_cm2'] ?? null,
                    'area_cm2' => $line['area_cm2'] ?? null,
                    'unit_price' => $line['unit_price'] ?? 0,
                    'line_total' => $line['line_total'] ?? 0,
                    'rate_snapshot' => $line['rate_snapshot'] ?? null,
                ])->values()->all(),
                'errors' => [],
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'subtotal' => 0,
                'lines' => [],
                'errors' => [$exception->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private function pricingFootnote(array $pricing): string
    {
        if (! ($pricing['ok'] ?? false)) {
            $errors = implode(' ', $pricing['errors'] ?? ['No se pudo calcular con tablas Serna.']);

            return 'Precios: pendientes. '.$errors;
        }

        $lines = [
            'Precios tomados de tus tablas Serna 2026.',
            'Subtotal calculado: '.Money::format($pricing['subtotal']).'.',
        ];

        foreach ($pricing['lines'] as $line) {
            $bit = ($line['material'] ?: $line['pieza']).' → '.Money::format($line['line_total']);
            if (! empty($line['price_per_cm2'])) {
                $bit .= ' ('.$this->n((float) $line['area_cm2']).' cm² × $'
                    .number_format((float) $line['price_per_cm2'], 1, ',', '.').'/cm²'
                    .(! empty($line['process_rate_code']) ? ' · '.$line['process_rate_code'] : '')
                    .')';
            }
            $lines[] = '• '.$bit;
        }

        return implode("\n", $lines);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
