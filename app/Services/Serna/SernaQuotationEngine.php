<?php

namespace App\Services\Serna;

use App\Enums\AcrylicLightingPricingMode;
use App\Enums\SernaItemType;
use App\Enums\SernaSheetFinish;
use App\Models\AcrylicLightingOption;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;
use App\Support\Money;
use App\Support\Tax;
use InvalidArgumentException;
use Throwable;

class SernaQuotationEngine
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculateItem(array $input): array
    {
        $type = $this->resolveType($input['item_type'] ?? null);
        $quantity = max(1, (int) ($input['quantity'] ?? 1));
        $pieza = trim((string) ($input['pieza'] ?? $type->getLabel()));
        $acabados = trim((string) ($input['acabados'] ?? ''));
        $material = trim((string) ($input['material'] ?? ''));

        return match ($type->pricingMode()) {
            'full_sheet' => $this->calculateSheet($input, $type, $quantity, $pieza, $material, $acabados),
            'fixed' => $this->calculateFixed($input, $type, $quantity, $pieza, $material, $acabados),
            'lighting' => $this->calculateLighting($input, $type, $quantity, $pieza, $material, $acabados),
            'power_supply' => $this->calculatePowerSupply($input, $type, $quantity, $pieza, $material, $acabados),
            default => $this->calculatePerCm2($input, $type, $quantity, $pieza, $material, $acabados),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return array<string, mixed>
     */
    public function calculateProposalFromPieces(array $pieces, float $withholdingRate = 0, bool $allowIncomplete = false): array
    {
        $flatItems = [];
        $pieceSummaries = [];
        $incompleteCount = 0;

        foreach (array_values($pieces) as $pieceIndex => $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $pieceName = mb_strtoupper(trim((string) ($piece['name'] ?? '')), 'UTF-8');
            if ($pieceName === '') {
                if (! $allowIncomplete) {
                    throw new InvalidArgumentException('Cada pieza debe tener un nombre.');
                }
                $pieceName = 'PIEZA '.($pieceIndex + 1);
            }

            $pieceItems = array_values(array_filter(
                $piece['items'] ?? [],
                fn ($row): bool => is_array($row) && filled($row['item_type'] ?? null),
            ));

            if ($pieceItems === []) {
                if (! $allowIncomplete) {
                    throw new InvalidArgumentException("La pieza «{$pieceName}» no tiene ítems.");
                }

                continue;
            }

            $pieceLines = [];
            $pieceTotal = 0.0;

            foreach ($pieceItems as $item) {
                $item['pieza'] = $pieceName;

                try {
                    $line = $this->calculateItem($item);
                    $line['incomplete'] = false;
                } catch (Throwable $exception) {
                    if (! $allowIncomplete) {
                        throw $exception;
                    }

                    $line = $this->incompleteLinePayload($item, $pieceName, $exception->getMessage());
                    $incompleteCount++;
                }

                $line['piece_name'] = $pieceName;
                $line['piece_index'] = $pieceIndex + 1;
                $pieceLines[] = $line;
                $flatItems[] = $line;
                $pieceTotal += (float) $line['line_total'];
            }

            $pieceSummaries[] = [
                'name' => $pieceName,
                'index' => $pieceIndex + 1,
                'item_count' => count($pieceLines),
                'subtotal' => Money::round($pieceTotal),
                'items' => $pieceLines,
            ];
        }

        if ($flatItems === []) {
            throw new InvalidArgumentException(
                $allowIncomplete
                    ? 'Agrega al menos una pieza con un ítem para guardar el borrador.'
                    : 'Agrega al menos un ítem a la propuesta.'
            );
        }

        $proposal = $this->buildProposalTotals($flatItems, $withholdingRate);
        $proposal['pieces'] = $pieceSummaries;
        $proposal['piece_count'] = count($pieceSummaries);
        $proposal['incomplete_count'] = $incompleteCount;
        $proposal['has_incomplete'] = $incompleteCount > 0;

        return $proposal;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function calculateProposal(array $items, float $withholdingRate = 0): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('Agrega al menos un ítem a la propuesta.');
        }

        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $index => $item) {
            $line = $this->calculateItem($item);
            $line['index'] = $index + 1;
            $lines[] = $line;
            $subtotal += (float) $line['line_total'];
        }

        $indexed = [];
        foreach (array_values($lines) as $index => $line) {
            $line['index'] = $index + 1;
            $indexed[] = $line;
        }

        $proposal = $this->buildProposalTotals($indexed, $withholdingRate);
        $proposal['pieces'] = [];
        $proposal['piece_count'] = 0;
        $proposal['incomplete_count'] = 0;
        $proposal['has_incomplete'] = false;

        return $proposal;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function buildProposalTotals(array $lines, float $withholdingRate = 0): array
    {
        $indexed = [];
        foreach (array_values($lines) as $index => $line) {
            if (! isset($line['index'])) {
                $line['index'] = $index + 1;
            }
            $indexed[] = $line;
        }

        $subtotal = Money::round(array_sum(array_map(
            fn (array $line): float => (float) ($line['line_total'] ?? 0),
            $indexed,
        )));
        $tax = Tax::breakdown($subtotal);
        $withholdingRate = max(0, Money::round($withholdingRate));
        $withholdingAmount = $withholdingRate > 0
            ? Money::round($subtotal * ($withholdingRate / 100))
            : 0.0;
        $totalPayable = Money::round($tax['total'] - $withholdingAmount);

        return [
            'items' => $indexed,
            'subtotal' => $subtotal,
            'iva_rate' => $tax['iva_rate'],
            'iva_amount' => $tax['iva_amount'],
            'withholding_rate' => $withholdingRate,
            'withholding_amount' => $withholdingAmount,
            'total' => $tax['total'],
            'total_payable' => $totalPayable,
            'item_count' => count($indexed),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function incompleteLinePayload(array $input, string $pieza, string $reason): array
    {
        $type = $this->resolveType($input['item_type'] ?? null);
        $material = trim((string) ($input['material'] ?? ''));
        $acabados = trim((string) ($input['acabados'] ?? ''));
        $quantity = max(1, (int) ($input['quantity'] ?? 1));
        $widthCm = filled($input['width_cm'] ?? null) ? (float) $input['width_cm'] : null;
        $heightCm = filled($input['height_cm'] ?? null) ? (float) $input['height_cm'] : null;
        $thicknessMm = filled($input['thickness_mm'] ?? null) ? (float) $input['thickness_mm'] : null;

        $description = collect([
            '[PENDIENTE] '.$pieza,
            $material !== '' ? "Material: {$material}" : null,
            $acabados !== '' ? "Acabados: {$acabados}" : $type->getLabel(),
            $reason !== '' ? "Motivo: {$reason}" : null,
        ])->filter()->implode(' · ');

        return [
            'source' => 'serna_cotizador',
            'pricing_mode' => $type->pricingMode(),
            'item_type' => $type->value,
            'item_type_label' => $type->getLabel(),
            'pieza' => $pieza,
            'material' => $material,
            'acabados' => $acabados !== '' ? $acabados : 'PENDIENTE DE COMPLETAR',
            'width_cm' => $widthCm,
            'height_cm' => $heightCm,
            'area_cm2' => ($widthCm && $heightCm) ? Money::round($widthCm * $heightCm) : null,
            'thickness_mm' => $thicknessMm,
            'quantity' => $quantity,
            'unit_price' => 0.0,
            'line_total' => 0.0,
            'description' => $description,
            'incomplete' => true,
            'pending_reason' => $reason,
            'process_rate_id' => $input['process_rate_id'] ?? null,
            'sheet_price_id' => $input['sheet_price_id'] ?? null,
            'catalog_product_id' => $input['catalog_product_id'] ?? null,
            'lighting_option_id' => $input['lighting_option_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculatePerCm2(
        array $input,
        SernaItemType $type,
        int $quantity,
        string $pieza,
        string $material,
        string $acabados,
    ): array {
        $widthCm = (float) ($input['width_cm'] ?? 0);
        $heightCm = (float) ($input['height_cm'] ?? 0);
        $thicknessMm = filled($input['thickness_mm'] ?? null) ? (float) $input['thickness_mm'] : null;

        if ($widthCm <= 0 || $heightCm <= 0) {
            throw new InvalidArgumentException('Indica medidas X y Y mayores a cero (cm).');
        }

        $areaCm2 = Money::round($widthCm * $heightCm);
        $rate = $this->resolveProcessRate($type, $input, $thicknessMm);
        $pricePerCm2 = (float) $rate->price_per_cm2;
        $unitRaw = $areaCm2 * $pricePerCm2;
        $minCharge = $rate->min_charge !== null ? (float) $rate->min_charge : null;
        $unitPrice = Money::round($minCharge !== null ? max($unitRaw, $minCharge) : $unitRaw);
        $lineTotal = Money::round($unitPrice * $quantity);

        $materialLabel = $material !== ''
            ? $material
            : ($thicknessMm !== null ? "Calibre {$thicknessMm} mm" : $rate->name);

        return $this->linePayload(
            type: $type,
            pieza: $pieza !== '' ? $pieza : $rate->name,
            material: $materialLabel,
            acabados: $acabados !== '' ? $acabados : $rate->name,
            widthCm: $widthCm,
            heightCm: $heightCm,
            areaCm2: $areaCm2,
            thicknessMm: $thicknessMm,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            extra: [
                'process_rate_id' => $rate->id,
                'process_rate_code' => $rate->code,
                'price_per_cm2' => $pricePerCm2,
                'min_charge' => $minCharge,
                'raw_unit_price' => Money::round($unitRaw),
                'min_charge_applied' => $minCharge !== null && $unitRaw < $minCharge,
                'rate_snapshot' => $rate->only([
                    'code', 'name', 'category', 'thickness_mm_min', 'thickness_mm_max',
                    'price_per_cm2', 'min_charge', 'year',
                ]),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculateSheet(
        array $input,
        SernaItemType $type,
        int $quantity,
        string $pieza,
        string $material,
        string $acabados,
    ): array {
        $sheetId = (int) ($input['sheet_price_id'] ?? 0);

        if ($sheetId < 1) {
            throw new InvalidArgumentException('Selecciona una lámina del catálogo Serna.');
        }

        /** @var SernaSheetPrice $sheet */
        $sheet = SernaSheetPrice::query()->active()->findOrFail($sheetId);
        $finish = SernaSheetFinish::tryFrom($sheet->finish);
        $unitPrice = Money::round((float) $sheet->price);
        $lineTotal = Money::round($unitPrice * $quantity);

        return $this->linePayload(
            type: $type,
            pieza: $pieza !== '' ? $pieza : 'Lámina entera',
            material: $material !== '' ? $material : sprintf('%s mm', rtrim(rtrim((string) $sheet->thickness_mm, '0'), '.')),
            acabados: $acabados !== '' ? $acabados : ($finish?->getLabel() ?? $sheet->finish),
            widthCm: (float) $sheet->width_cm,
            heightCm: (float) $sheet->height_cm,
            areaCm2: Money::round((float) $sheet->width_cm * (float) $sheet->height_cm),
            thicknessMm: (float) $sheet->thickness_mm,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            extra: [
                'sheet_price_id' => $sheet->id,
                'format' => $sheet->format,
                'finish' => $sheet->finish,
                'price_source' => $sheet->price_source,
                'rate_snapshot' => $sheet->only([
                    'format', 'width_cm', 'height_cm', 'thickness_mm', 'finish', 'price', 'price_source', 'year',
                ]),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculateFixed(
        array $input,
        SernaItemType $type,
        int $quantity,
        string $pieza,
        string $material,
        string $acabados,
    ): array {
        $catalogId = filled($input['catalog_product_id'] ?? null) ? (int) $input['catalog_product_id'] : null;
        $unitPrice = null;
        $snapshot = null;
        $widthCm = filled($input['width_cm'] ?? null) ? (float) $input['width_cm'] : null;
        $heightCm = filled($input['height_cm'] ?? null) ? (float) $input['height_cm'] : null;
        $thicknessMm = filled($input['thickness_mm'] ?? null) ? (float) $input['thickness_mm'] : null;

        if ($catalogId) {
            /** @var SernaCatalogProduct $product */
            $product = SernaCatalogProduct::query()->active()->findOrFail($catalogId);
            $unitPrice = (float) $product->unit_price;
            $pieza = $pieza !== '' ? $pieza : $product->name;
            $material = $material !== '' ? $material : ($product->specs['thickness_mm'] ?? null
                ? $product->specs['thickness_mm'].' mm'
                : $product->category);
            $acabados = $acabados !== '' ? $acabados : ($product->specs['role'] ?? $product->category);
            $widthCm ??= isset($product->specs['width_cm']) ? (float) $product->specs['width_cm'] : null;
            $heightCm ??= isset($product->specs['height_cm']) ? (float) $product->specs['height_cm'] : null;
            $thicknessMm ??= isset($product->specs['thickness_mm']) ? (float) $product->specs['thickness_mm'] : null;
            $snapshot = $product->only(['sku', 'name', 'category', 'unit_price', 'specs', 'year']);
        } else {
            $parsed = Money::parseInput($input['unit_price'] ?? null);
            if ($parsed === null || $parsed < 0) {
                throw new InvalidArgumentException('Indica el valor unitario del ítem de precio fijo.');
            }
            $unitPrice = $parsed;
        }

        $unitPrice = Money::round($unitPrice);
        $lineTotal = Money::round($unitPrice * $quantity);
        $areaCm2 = ($widthCm && $heightCm) ? Money::round($widthCm * $heightCm) : null;

        return $this->linePayload(
            type: $type,
            pieza: $pieza !== '' ? $pieza : $type->getLabel(),
            material: $material,
            acabados: $acabados,
            widthCm: $widthCm,
            heightCm: $heightCm,
            areaCm2: $areaCm2,
            thicknessMm: $thicknessMm,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: $lineTotal,
            extra: [
                'catalog_product_id' => $catalogId,
                'rate_snapshot' => $snapshot,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculateLighting(
        array $input,
        SernaItemType $type,
        int $quantity,
        string $pieza,
        string $material,
        string $acabados,
    ): array {
        $lighting = $this->resolveLightingOption($input);
        if ($lighting->pricing_mode === AcrylicLightingPricingMode::None) {
            throw new InvalidArgumentException('Selecciona una opción de iluminación activa (LED, backlight, etc.).');
        }

        $widthCm = (float) ($input['width_cm'] ?? 0);
        $heightCm = (float) ($input['height_cm'] ?? 0);
        if ($widthCm <= 0 || $heightCm <= 0) {
            throw new InvalidArgumentException('La iluminación necesita medidas X y Y (cm) para calcular perímetro o área.');
        }

        $widthM = $widthCm / 100;
        $heightM = $heightCm / 100;
        $areaM2 = $widthM * $heightM;
        $perimeterM = 2 * ($widthM + $heightM);

        $unitPrice = Money::round(match ($lighting->pricing_mode) {
            AcrylicLightingPricingMode::PerMeter => $perimeterM * (float) $lighting->unit_price,
            AcrylicLightingPricingMode::PerSquareMeter => $areaM2 * (float) $lighting->unit_price,
            AcrylicLightingPricingMode::Fixed => (float) $lighting->unit_price,
            default => 0.0,
        });

        return $this->linePayload(
            type: $type,
            pieza: $pieza !== '' ? $pieza : 'Iluminación',
            material: $material !== '' ? $material : mb_strtoupper($lighting->name, 'UTF-8'),
            acabados: $acabados !== '' ? $acabados : $lighting->pricing_mode->getLabel(),
            widthCm: $widthCm,
            heightCm: $heightCm,
            areaCm2: Money::round($widthCm * $heightCm),
            thicknessMm: null,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: Money::round($unitPrice * $quantity),
            extra: [
                'lighting_option_id' => $lighting->id,
                'lighting_pricing_mode' => $lighting->pricing_mode->value,
                'perimeter_m' => Money::round($perimeterM, 4),
                'area_m2' => Money::round($areaM2, 4),
                'rate_snapshot' => $lighting->only(['id', 'name', 'pricing_mode', 'unit_price', 'power_supply_cost']),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculatePowerSupply(
        array $input,
        SernaItemType $type,
        int $quantity,
        string $pieza,
        string $material,
        string $acabados,
    ): array {
        $lighting = $this->resolveLightingOption($input);
        $unitPrice = Money::round((float) $lighting->power_supply_cost);

        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('La opción de iluminación seleccionada no tiene costo de fuente.');
        }

        return $this->linePayload(
            type: $type,
            pieza: $pieza !== '' ? $pieza : 'Fuente',
            material: $material !== '' ? $material : 'FUENTE DE ALIMENTACION',
            acabados: $acabados !== '' ? $acabados : ('PSU · '.$lighting->name),
            widthCm: filled($input['width_cm'] ?? null) ? (float) $input['width_cm'] : 1,
            heightCm: filled($input['height_cm'] ?? null) ? (float) $input['height_cm'] : 1,
            areaCm2: null,
            thicknessMm: null,
            quantity: $quantity,
            unitPrice: $unitPrice,
            lineTotal: Money::round($unitPrice * $quantity),
            extra: [
                'lighting_option_id' => $lighting->id,
                'rate_snapshot' => $lighting->only(['id', 'name', 'power_supply_cost']),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveLightingOption(array $input): AcrylicLightingOption
    {
        if (filled($input['lighting_option_id'] ?? null)) {
            return AcrylicLightingOption::query()->active()->findOrFail((int) $input['lighting_option_id']);
        }

        throw new InvalidArgumentException('Selecciona la opción de iluminación / fuente desde el catálogo.');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveProcessRate(SernaItemType $type, array $input, ?float $thicknessMm): SernaProcessRate
    {
        if (filled($input['process_rate_id'] ?? null)) {
            /** @var SernaProcessRate $rate */
            $rate = SernaProcessRate::query()->active()->findOrFail((int) $input['process_rate_id']);

            return $rate;
        }

        $category = match ($type) {
            SernaItemType::CorteLaser => 'corte_laser',
            SernaItemType::ManoObra => 'mano_obra',
            SernaItemType::Terminado => 'terminado',
            SernaItemType::Vinilo => 'vinilo',
            SernaItemType::Plotter => 'plotter',
            SernaItemType::Espejo => 'espejo',
            SernaItemType::Enchapado => 'enchapado',
            default => throw new InvalidArgumentException('Este tipo de ítem no usa tarifas por cm².'),
        };

        $query = SernaProcessRate::query()->active()->category($category)->ordered();

        if (in_array($type, [SernaItemType::CorteLaser, SernaItemType::ManoObra], true)) {
            if ($thicknessMm === null) {
                throw new InvalidArgumentException('Indica el calibre (mm) para resolver la tarifa.');
            }

            $rate = $query->get()->first(fn (SernaProcessRate $row): bool => $row->appliesToThickness($thicknessMm));

            if (! $rate) {
                throw new InvalidArgumentException("No hay tarifa Serna para calibre {$thicknessMm} mm en {$category}.");
            }

            return $rate;
        }

        $rate = $query->first();

        if (! $rate) {
            throw new InvalidArgumentException("No hay tarifas activas para {$category}.");
        }

        return $rate;
    }

    private function resolveType(mixed $value): SernaItemType
    {
        if ($value instanceof SernaItemType) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return SernaItemType::from($value);
        }

        throw new InvalidArgumentException('Selecciona el tipo de proyecto / pieza.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function linePayload(
        SernaItemType $type,
        string $pieza,
        string $material,
        string $acabados,
        ?float $widthCm,
        ?float $heightCm,
        ?float $areaCm2,
        ?float $thicknessMm,
        int $quantity,
        float $unitPrice,
        float $lineTotal,
        array $extra = [],
    ): array {
        $description = collect([
            $pieza,
            $material !== '' ? "Material: {$material}" : null,
            $acabados !== '' ? "Acabados: {$acabados}" : null,
            $widthCm && $heightCm ? sprintf('Medida: %sx%s cm', $this->n($widthCm), $this->n($heightCm)) : null,
            $areaCm2 ? sprintf('Área: %s cm²', $this->n($areaCm2)) : null,
        ])->filter()->implode(' · ');

        return [
            'source' => 'serna_cotizador',
            'pricing_mode' => $type->pricingMode(),
            'item_type' => $type->value,
            'item_type_label' => $type->getLabel(),
            'pieza' => $pieza,
            'material' => $material,
            'acabados' => $acabados,
            'width_cm' => $widthCm,
            'height_cm' => $heightCm,
            'area_cm2' => $areaCm2,
            'thickness_mm' => $thicknessMm,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'description' => $description,
            ...$extra,
        ];
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
