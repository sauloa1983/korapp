<?php

namespace App\Services\Acrylic;

use App\Enums\AcrylicFinishPricingMode;
use App\Enums\AcrylicLetteringPricingMode;
use App\Enums\AcrylicLetteringType;
use App\Enums\AcrylicLightingPricingMode;
use App\Enums\AcrylicSignType;
use App\Models\AcrylicFinishOption;
use App\Models\AcrylicLetteringOption;
use App\Models\AcrylicLightingOption;
use App\Models\AcrylicMaterial;
use App\Models\AcrylicPricingSetting;
use App\Support\Money;
use App\Support\Tax;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Cotiza un aviso unificado (un precio final) con desglose interno en capas:
 * 1) Base/fondo acrílico (opcional: puede ser solo letras)
 * 2) Letras / logo
 * 3) Luces + accesorios + ensamble / mano de obra
 */
class AcrylicQuotationService
{
    /**
     * @param  list<int>  $finishOptionIds
     * @return array<string, mixed>
     */
    public function calculate(
        float $widthCm,
        float $heightCm,
        int $quantity,
        ?int $materialId,
        int $lightingOptionId,
        int $letteringOptionId,
        array $finishOptionIds = [],
        int $spacerCount = 0,
        float $letteringCoveragePercent = 0,
        float $cutComplexity = 1,
        ?float $marginPercentOverride = null,
        AcrylicSignType|string $signType = AcrylicSignType::WithBase,
        int $letterCount = 0,
        bool $hasLogo = false,
        float $logoCoveragePercent = 0,
        ?float $lettersWidthCm = null,
        ?float $lettersHeightCm = null,
        ?float $logoWidthCm = null,
        ?float $logoHeightCm = null,
    ): array {
        $signType = $signType instanceof AcrylicSignType
            ? $signType
            : AcrylicSignType::from($signType);

        $this->assertLetteringInputs($letteringCoveragePercent, $cutComplexity, $letterCount, $hasLogo, $logoCoveragePercent);

        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
        }

        /** @var AcrylicLightingOption $lighting */
        $lighting = AcrylicLightingOption::query()->active()->findOrFail($lightingOptionId);
        /** @var AcrylicLetteringOption $lettering */
        $lettering = AcrylicLetteringOption::query()->active()->findOrFail($letteringOptionId);

        $hasLetters = $lettering->type !== AcrylicLetteringType::None;
        $separateSizes = $signType === AcrylicSignType::LettersOnly;
        $needsMaterial = $signType->requiresBase() || $separateSizes;

        if ($needsMaterial && blank($materialId)) {
            throw new InvalidArgumentException(
                $separateSizes
                    ? 'Selecciona el material acrílico de las letras / logo.'
                    : 'Selecciona el material / espesor de la base.'
            );
        }

        $material = null;
        if ($needsMaterial) {
            /** @var AcrylicMaterial $material */
            $material = AcrylicMaterial::query()->active()->findOrFail($materialId);
        }

        if ($signType === AcrylicSignType::LettersOnly && ! $hasLetters && ! $hasLogo) {
            throw new InvalidArgumentException('En avisos sin base elige letras o activa “solo logo”.');
        }

        if (! $separateSizes && $hasLetters && $hasLogo && $logoCoveragePercent >= 100) {
            throw new InvalidArgumentException('Con letras, el logo no puede ocupar el 100% del ancho. Deja espacio para las letras o usa solo logo.');
        }

        $settings = AcrylicPricingSetting::current();
        $marginPercent = $marginPercentOverride ?? (float) $settings->margin_percent;

        if ($marginPercent < 0) {
            throw new InvalidArgumentException('El margen comercial no puede ser negativo.');
        }

        // Sin base: ancho/alto de letras son POR LETRA; el logo tiene medidas propias.
        $perLetterWidthCm = 0.0;
        $perLetterHeightCm = 0.0;

        if ($separateSizes) {
            $resolvedLogoWidth = $hasLogo
                ? (float) (($logoWidthCm !== null && $logoWidthCm > 0)
                    ? $logoWidthCm
                    : ($hasLetters ? 0.0 : $widthCm))
                : 0.0;
            $resolvedLogoHeight = $hasLogo
                ? (float) (($logoHeightCm !== null && $logoHeightCm > 0)
                    ? $logoHeightCm
                    : ($hasLetters ? 0.0 : $heightCm))
                : 0.0;

            if ($hasLetters) {
                if ($letterCount < 1) {
                    throw new InvalidArgumentException('Indica cuántas letras tiene el aviso (mínimo 1).');
                }

                $perLetterWidthCm = (float) (($lettersWidthCm !== null && $lettersWidthCm > 0)
                    ? $lettersWidthCm
                    : ($widthCm > 0 ? $widthCm / $letterCount : 0));
                $perLetterHeightCm = (float) (($lettersHeightCm !== null && $lettersHeightCm > 0)
                    ? $lettersHeightCm
                    : $heightCm);

                if ($perLetterWidthCm <= 0 || $perLetterHeightCm <= 0) {
                    throw new InvalidArgumentException('Indica el ancho y alto por letra.');
                }

                // Ancho total del texto = ancho por letra × cantidad
                $resolvedLettersWidth = Money::round($perLetterWidthCm * $letterCount, 2);
                $resolvedLettersHeight = Money::round($perLetterHeightCm, 2);
            } else {
                $resolvedLettersWidth = 0.0;
                $resolvedLettersHeight = 0.0;
            }

            if ($hasLogo && ($resolvedLogoWidth <= 0 || $resolvedLogoHeight <= 0)) {
                throw new InvalidArgumentException('Indica el ancho y alto del logo.');
            }

            if ($hasLetters && $hasLogo) {
                $widthCm = $resolvedLettersWidth + $resolvedLogoWidth;
                $heightCm = max($resolvedLettersHeight, $resolvedLogoHeight);
            } elseif ($hasLetters) {
                $widthCm = $resolvedLettersWidth;
                $heightCm = $resolvedLettersHeight;
            } else {
                $widthCm = $resolvedLogoWidth;
                $heightCm = $resolvedLogoHeight;
            }
        } else {
            $this->assertPositiveDimensions($widthCm, $heightCm, $quantity);
            $resolvedLettersWidth = $hasLetters ? $widthCm : 0.0;
            $resolvedLettersHeight = $hasLetters ? $heightCm : 0.0;
            $resolvedLogoWidth = null;
            $resolvedLogoHeight = null;
        }

        $widthM = Money::round($widthCm / 100, 4);
        $heightM = Money::round($heightCm / 100, 4);
        $areaM2 = Money::round($widthM * $heightM, 4);
        $perimeterM = Money::round(2 * ($widthM + $heightM), 4);

        if ($hasLetters && $separateSizes) {
            // Área y corte = suma de cada letra (ancho × alto × cantidad)
            $letterWm = Money::round($perLetterWidthCm / 100, 4);
            $letterHm = Money::round($perLetterHeightCm / 100, 4);
            $lettersAreaM2 = Money::round($letterWm * $letterHm * $letterCount, 4);
            $lettersPerimeterM = Money::round(2 * ($letterWm + $letterHm) * $letterCount, 4);
        } elseif ($hasLetters) {
            $lettersWidthM = Money::round($resolvedLettersWidth / 100, 4);
            $lettersHeightM = Money::round($resolvedLettersHeight / 100, 4);
            $lettersAreaM2 = Money::round($lettersWidthM * $lettersHeightM, 4);
            $lettersPerimeterM = Money::round(2 * ($lettersWidthM + $lettersHeightM), 4);
        } else {
            $lettersAreaM2 = 0.0;
            $lettersPerimeterM = 0.0;
        }

        // Solo letras sin base: cobertura 100% sobre su propia área.
        $effectiveCoverage = ($hasLetters && ($separateSizes || $signType->forcesFullLetterCoverage()))
            ? 100.0
            : $letteringCoveragePercent;

        // --- Capa 1: Base de placa (con caja) o material acrílico de letras/logo (sin placa) ---
        $billableArea = 0.0;
        $baseCost = 0.0;

        if ($material !== null && $signType->requiresBase()) {
            $billableArea = Money::round($areaM2 * (1 + ((float) $material->waste_percent / 100)), 4);
            $baseCost = Money::round($billableArea * (float) $material->price_per_m2);
        }

        // --- Capa 2: Letras (área/corte + cantidad) + logo opcional / solo logo ---
        if ($hasLetters) {
            $letteringBreakdown = $this->letteringCost(
                $lettering,
                $lettersAreaM2,
                $lettersPerimeterM,
                $effectiveCoverage,
                $cutComplexity,
                $signType,
                $letterCount,
            );
        } else {
            $letteringBreakdown = [
                'surface_amount' => 0.0,
                'per_letter_amount' => 0.0,
                'amount' => 0.0,
                'letter_area_m2' => 0.0,
                'cut_meters' => 0.0,
                'letter_count' => 0,
                'price_per_letter' => 0.0,
            ];
        }

        $letterHeightCm = $hasLetters ? $resolvedLettersHeight : 0.0;

        $logoBreakdown = $this->logoCost(
            $settings,
            $widthCm,
            $heightCm,
            $areaM2,
            $hasLogo,
            $logoCoveragePercent,
            $hasLetters,
            $letterHeightCm,
            $separateSizes ? $resolvedLogoWidth : null,
            $separateSizes ? $resolvedLogoHeight : null,
        );
        $lettersOnlyCost = $letteringBreakdown['amount'];
        $logoCost = $logoBreakdown['amount'];
        $letteringCost = Money::round($lettersOnlyCost + $logoCost);

        // Sin placa: el acrílico se cobra sobre el área de letras + logo (con merma).
        if ($material !== null && $separateSizes) {
            $acrylicArea = Money::round(
                (float) ($letteringBreakdown['letter_area_m2'] ?? 0) + (float) ($logoBreakdown['area_m2'] ?? 0),
                4,
            );
            $billableArea = Money::round($acrylicArea * (1 + ((float) $material->waste_percent / 100)), 4);
            $baseCost = Money::round($billableArea * (float) $material->price_per_m2);
        }

        // --- Capa 3: Luces + accesorios + ensamble ---
        $lightingCost = $this->lightingCost($lighting, $areaM2, $perimeterM);

        /** @var Collection<int, AcrylicFinishOption> $finishes */
        $finishes = AcrylicFinishOption::query()
            ->active()
            ->whereIn('id', $finishOptionIds)
            ->ordered()
            ->get();

        $finishLines = [];
        $accessoriesCost = 0.0;

        foreach ($finishes as $finish) {
            $amount = $this->finishCost($finish, $areaM2, $perimeterM, $spacerCount);
            $accessoriesCost += $amount;
            $finishLines[] = [
                'id' => $finish->id,
                'name' => $finish->name,
                'type' => $finish->type->value,
                'pricing_mode' => $finish->pricing_mode->value,
                'unit_price' => (float) $finish->unit_price,
                'amount' => $amount,
            ];
        }

        $accessoriesCost = Money::round($accessoriesCost);

        $baseLabor = Money::round(
            (float) $settings->labor_fixed_cost + ($areaM2 * (float) $settings->labor_per_m2)
        );
        $letteringAssembly = Money::round(
            $letteringCost * ((float) ($settings->assembly_percent_of_lettering ?? 0) / 100)
        );
        $laborCost = Money::round($baseLabor + $letteringAssembly);

        $assemblyCost = Money::round($lightingCost + $accessoriesCost + $laborCost);

        $costSubtotal = Money::round($baseCost + $letteringCost + $assemblyCost);
        $marginAmount = Money::round($costSubtotal * ($marginPercent / 100));
        $unitPriceExIva = Money::round($costSubtotal + $marginAmount);
        $lineSubtotalExIva = Money::round($unitPriceExIva * $quantity);
        $tax = Tax::breakdown($lineSubtotalExIva);

        $description = $this->buildDescription(
            $signType,
            $widthCm,
            $heightCm,
            $material,
            $lettering,
            $lighting,
            $letterCount,
            $hasLogo,
        );

        return [
            'sign_type' => $signType->value,
            'sign_type_label' => $signType->getLabel(),
            'has_base' => $signType->requiresBase(),
            'width_cm' => $widthCm,
            'height_cm' => $heightCm,
            'quantity' => $quantity,
            'width_m' => $widthM,
            'height_m' => $heightM,
            'area_m2' => $areaM2,
            'perimeter_m' => $perimeterM,
            'spacer_count' => $spacerCount,
            'lettering_coverage_percent' => $effectiveCoverage,
            'cut_complexity' => $cutComplexity,
            'material' => $material ? [
                'id' => $material->id,
                'name' => $material->name,
                'thickness_mm' => (float) $material->thickness_mm,
                'price_per_m2' => (float) $material->price_per_m2,
                'waste_percent' => (float) $material->waste_percent,
                'billable_area_m2' => $billableArea,
            ] : [
                'id' => null,
                'name' => 'Sin base / fondo',
                'thickness_mm' => 0.0,
                'price_per_m2' => 0.0,
                'waste_percent' => 0.0,
                'billable_area_m2' => 0.0,
            ],
            'lettering' => [
                'id' => $lettering->id,
                'name' => $lettering->name,
                'type' => $lettering->type->value,
                'pricing_mode' => $lettering->pricing_mode->value,
                'coverage_percent' => $effectiveCoverage,
                'cut_complexity' => $cutComplexity,
                'letter_count' => $letteringBreakdown['letter_count'],
                'price_per_letter' => $letteringBreakdown['price_per_letter'],
                'surface_amount' => $letteringBreakdown['surface_amount'],
                'per_letter_amount' => $letteringBreakdown['per_letter_amount'],
                'letter_area_m2' => $letteringBreakdown['letter_area_m2'],
                'cut_meters' => $letteringBreakdown['cut_meters'],
                'width_cm' => Money::round($resolvedLettersWidth, 2),
                'height_cm' => Money::round($resolvedLettersHeight, 2),
                'per_letter_width_cm' => $separateSizes
                    ? Money::round($perLetterWidthCm, 2)
                    : null,
                'per_letter_height_cm' => $separateSizes
                    ? Money::round($perLetterHeightCm, 2)
                    : null,
                'separate_sizes' => $separateSizes,
                'amount' => $lettersOnlyCost,
                'per_letter_check' => $this->letterPerUnitCheck(
                    $separateSizes ? $resolvedLettersWidth : $widthCm,
                    $separateSizes ? $resolvedLettersHeight : $heightCm,
                    $letteringBreakdown['letter_count'],
                    $letteringBreakdown['surface_amount'],
                    $letteringBreakdown['price_per_letter'],
                    $lettersOnlyCost,
                    $hasLogo && ! $separateSizes,
                    $separateSizes ? 0.0 : (float) $logoBreakdown['width_cm'],
                ),
            ],
            'logo' => [
                'has_logo' => $hasLogo,
                'coverage_percent' => $logoBreakdown['coverage_percent'],
                'area_m2' => $logoBreakdown['area_m2'],
                'price_per_m2' => $logoBreakdown['price_per_m2'],
                'fixed_cost' => $logoBreakdown['fixed_cost'],
                'amount' => $logoCost,
                'width_cm' => $logoBreakdown['width_cm'],
                'height_cm' => $logoBreakdown['height_cm'],
                'linked_to_letter_height' => $logoBreakdown['linked_to_letter_height'],
                'logo_only' => $logoBreakdown['logo_only'],
                'separate_sizes' => $separateSizes,
                'size_check' => $this->logoSizeCheck($logoBreakdown, $logoCost),
            ],
            'lighting' => [
                'id' => $lighting->id,
                'name' => $lighting->name,
                'pricing_mode' => $lighting->pricing_mode->value,
                'unit_price' => (float) $lighting->unit_price,
                'power_supply_cost' => (float) $lighting->power_supply_cost,
            ],
            'finishes' => $finishLines,
            'layers' => [
                'base' => [
                    'label' => $signType === AcrylicSignType::LettersOnly
                        ? 'Material acrílico (letras / logo)'
                        : 'Base / fondo acrílico',
                    'amount' => $baseCost,
                ],
                'lettering' => [
                    'label' => 'Letras + logo',
                    'amount' => $letteringCost,
                    'letters' => $lettersOnlyCost,
                    'logo' => $logoCost,
                ],
                'assembly' => [
                    'label' => 'Luces, accesorios y ensamble',
                    'amount' => $assemblyCost,
                    'lighting' => $lightingCost,
                    'accessories' => $accessoriesCost,
                    'labor' => $laborCost,
                    'base_labor' => $baseLabor,
                    'lettering_assembly' => $letteringAssembly,
                ],
            ],
            'costs' => [
                'material' => $baseCost,
                'base' => $baseCost,
                'lettering' => $letteringCost,
                'letters' => $lettersOnlyCost,
                'logo' => $logoCost,
                'lighting' => $lightingCost,
                'finishes' => $accessoriesCost,
                'accessories' => $accessoriesCost,
                'labor' => $laborCost,
                'assembly' => $assemblyCost,
                'subtotal' => $costSubtotal,
                'margin_percent' => Money::round($marginPercent),
                'margin_amount' => $marginAmount,
                'unit_price_ex_iva' => $unitPriceExIva,
                'line_subtotal_ex_iva' => $lineSubtotalExIva,
            ],
            'tax' => $tax,
            'description' => $description,
            'calc_trace' => $this->buildCalcTrace(
                $signType,
                $widthCm,
                $heightCm,
                $areaM2,
                $perimeterM,
                $material,
                $billableArea,
                $baseCost,
                $lettering,
                $letteringBreakdown,
                $separateSizes,
                $perLetterWidthCm,
                $perLetterHeightCm,
                $effectiveCoverage,
                $cutComplexity,
                $lettersOnlyCost,
                $logoBreakdown,
                $logoCost,
                $lighting,
                $lightingCost,
                $finishLines,
                $accessoriesCost,
                $settings,
                $baseLabor,
                $letteringAssembly,
                $laborCost,
                $assemblyCost,
                $letteringCost,
                $costSubtotal,
                $marginPercent,
                $marginAmount,
                $unitPriceExIva,
                $quantity,
                $lineSubtotalExIva,
                $tax,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $letteringBreakdown
     * @param  array<string, mixed>  $logoBreakdown
     * @param  list<array<string, mixed>>  $finishLines
     * @param  array<string, mixed>  $tax
     * @return array{items: list<array{group: string, label: string, formula: string, amount: float}>}
     */
    private function buildCalcTrace(
        AcrylicSignType $signType,
        float $widthCm,
        float $heightCm,
        float $areaM2,
        float $perimeterM,
        ?AcrylicMaterial $material,
        float $billableArea,
        float $baseCost,
        AcrylicLetteringOption $lettering,
        array $letteringBreakdown,
        bool $separateSizes,
        float $perLetterWidthCm,
        float $perLetterHeightCm,
        float $effectiveCoverage,
        float $cutComplexity,
        float $lettersOnlyCost,
        array $logoBreakdown,
        float $logoCost,
        AcrylicLightingOption $lighting,
        float $lightingCost,
        array $finishLines,
        float $accessoriesCost,
        AcrylicPricingSetting $settings,
        float $baseLabor,
        float $letteringAssembly,
        float $laborCost,
        float $assemblyCost,
        float $letteringCost,
        float $costSubtotal,
        float $marginPercent,
        float $marginAmount,
        float $unitPriceExIva,
        int $quantity,
        float $lineSubtotalExIva,
        array $tax,
    ): array {
        $n = static fn (float $v, int $d = 4): string => rtrim(rtrim(number_format($v, $d, '.', ''), '0'), '.');
        $items = [];

        $items[] = [
            'group' => 'Medidas',
            'label' => 'Área / perímetro del aviso',
            'formula' => sprintf(
                '(%s÷100)×(%s÷100) = %s m² · perímetro 2×(%s+%s) = %s m',
                $n($widthCm, 2),
                $n($heightCm, 2),
                $n($areaM2),
                $n($widthCm / 100),
                $n($heightCm / 100),
                $n($perimeterM),
            ),
            'amount' => 0.0,
        ];

        if ($material !== null && $signType === AcrylicSignType::LettersOnly) {
            $acrylicRaw = Money::round(
                (float) ($letteringBreakdown['letter_area_m2'] ?? 0) + (float) ($logoBreakdown['area_m2'] ?? 0),
                4,
            );
            $items[] = [
                'group' => '1 · Material',
                'label' => 'Acrílico letras/logo · '.$material->label(),
                'formula' => sprintf(
                    '(área letras %s + logo %s) = %s m² × (1 + %s%% merma) = %s m² × %s $/m²',
                    $n((float) ($letteringBreakdown['letter_area_m2'] ?? 0)),
                    $n((float) ($logoBreakdown['area_m2'] ?? 0)),
                    $n($acrylicRaw),
                    $n((float) $material->waste_percent, 2),
                    $n($billableArea),
                    $n((float) $material->price_per_m2, 2),
                ),
                'amount' => $baseCost,
            ];
        } elseif ($material !== null) {
            $items[] = [
                'group' => '1 · Base',
                'label' => $material->name,
                'formula' => sprintf(
                    '%s m² × (1 + %s%% merma) = %s m² cobrables × %s $/m²',
                    $n($areaM2),
                    $n((float) $material->waste_percent, 2),
                    $n($billableArea),
                    $n((float) $material->price_per_m2, 2),
                ),
                'amount' => $baseCost,
            ];
        } else {
            $items[] = [
                'group' => '1 · Base',
                'label' => 'Sin base / fondo',
                'formula' => 'No aplica (aviso sin placa)',
                'amount' => 0.0,
            ];
        }

        $letterCount = (int) ($letteringBreakdown['letter_count'] ?? 0);
        if ($letterCount > 0) {
            $surfaceFormula = match ($lettering->pricing_mode) {
                AcrylicLetteringPricingMode::None => 'Sin costo de superficie',
                AcrylicLetteringPricingMode::Fixed => sprintf('Precio fijo %s', $n((float) $lettering->fixed_price, 2)),
                AcrylicLetteringPricingMode::CoverageArea => sprintf(
                    'Área letras %s m² × %s $/m²',
                    $n((float) $letteringBreakdown['letter_area_m2']),
                    $n((float) $lettering->price_per_m2, 2),
                ),
                AcrylicLetteringPricingMode::CoverageAreaPlusCut => sprintf(
                    '(%s m² × %s $/m²) + (%s m corte × %s $/ml)',
                    $n((float) $letteringBreakdown['letter_area_m2']),
                    $n((float) $lettering->price_per_m2, 2),
                    $n((float) $letteringBreakdown['cut_meters']),
                    $n((float) $lettering->cut_price_per_meter, 2),
                ),
            };

            $sizeBit = $separateSizes
                ? sprintf(
                    'Por letra %s×%s cm · %d letras · área total = %s×%s×%d = %s m²',
                    $n($perLetterWidthCm, 2),
                    $n($perLetterHeightCm, 2),
                    $letterCount,
                    $n($perLetterWidthCm / 100),
                    $n($perLetterHeightCm / 100),
                    $letterCount,
                    $n((float) $letteringBreakdown['letter_area_m2']),
                )
                : sprintf(
                    'Cobertura %s%% sobre área · complejidad corte %s',
                    $n($effectiveCoverage, 2),
                    $n($cutComplexity, 2),
                );

            $items[] = [
                'group' => '2 · Letras',
                'label' => 'Superficie / corte · '.$lettering->name,
                'formula' => $sizeBit.' · '.$surfaceFormula,
                'amount' => (float) $letteringBreakdown['surface_amount'],
            ];
            $items[] = [
                'group' => '2 · Letras',
                'label' => 'Cobro por letra',
                'formula' => sprintf(
                    '%d letras × %s $/letra',
                    $letterCount,
                    $n((float) $letteringBreakdown['price_per_letter'], 2),
                ),
                'amount' => (float) $letteringBreakdown['per_letter_amount'],
            ];
            $items[] = [
                'group' => '2 · Letras',
                'label' => 'Total letras',
                'formula' => 'Superficie/corte + cobro por letra',
                'amount' => $lettersOnlyCost,
            ];
        } else {
            $items[] = [
                'group' => '2 · Letras',
                'label' => 'Sin letras',
                'formula' => 'Tipo “Sin letras” o cantidad 0',
                'amount' => 0.0,
            ];
        }

        if ($logoBreakdown['width_cm'] > 0) {
            $logoFormula = ($logoBreakdown['logo_only'] ?? false) && ! ($logoBreakdown['linked_to_letter_height'] ?? false)
                && $signType === AcrylicSignType::LettersOnly
                ? sprintf(
                    '%s×%s cm = %s m² × %s $/m² + fijo %s',
                    $n((float) $logoBreakdown['width_cm'], 2),
                    $n((float) $logoBreakdown['height_cm'], 2),
                    $n((float) $logoBreakdown['area_m2']),
                    $n((float) $logoBreakdown['price_per_m2'], 2),
                    $n((float) $logoBreakdown['fixed_cost'], 2),
                )
                : sprintf(
                    'Área logo %s m² × %s $/m² + fijo %s (cobertura/ancho %s%%)',
                    $n((float) $logoBreakdown['area_m2']),
                    $n((float) $logoBreakdown['price_per_m2'], 2),
                    $n((float) $logoBreakdown['fixed_cost'], 2),
                    $n((float) $logoBreakdown['coverage_percent'], 2),
                );

            $items[] = [
                'group' => '2 · Logo',
                'label' => ($logoBreakdown['logo_only'] ?? false) ? 'Solo logo' : 'Logo',
                'formula' => $logoFormula,
                'amount' => $logoCost,
            ];
        } else {
            $items[] = [
                'group' => '2 · Logo',
                'label' => 'Sin logo',
                'formula' => 'Logo no activado',
                'amount' => 0.0,
            ];
        }

        $lightingFormula = match ($lighting->pricing_mode) {
            AcrylicLightingPricingMode::None => 'Sin iluminación',
            AcrylicLightingPricingMode::PerMeter => sprintf(
                '%s m perímetro × %s $/ml + fuente %s',
                $n($perimeterM),
                $n((float) $lighting->unit_price, 2),
                $n((float) $lighting->power_supply_cost, 2),
            ),
            AcrylicLightingPricingMode::PerSquareMeter => sprintf(
                '%s m² × %s $/m² + fuente %s',
                $n($areaM2),
                $n((float) $lighting->unit_price, 2),
                $n((float) $lighting->power_supply_cost, 2),
            ),
            AcrylicLightingPricingMode::Fixed => sprintf(
                'Fijo %s + fuente %s',
                $n((float) $lighting->unit_price, 2),
                $n((float) $lighting->power_supply_cost, 2),
            ),
        };

        $items[] = [
            'group' => '3 · Luces / extras',
            'label' => 'Iluminación · '.$lighting->name,
            'formula' => $lightingFormula,
            'amount' => $lightingCost,
        ];

        if ($finishLines === []) {
            $items[] = [
                'group' => '3 · Luces / extras',
                'label' => 'Accesorios',
                'formula' => 'Ninguno seleccionado',
                'amount' => 0.0,
            ];
        } else {
            foreach ($finishLines as $finish) {
                $finishFormula = match ($finish['pricing_mode']) {
                    AcrylicFinishPricingMode::Fixed->value => sprintf('Fijo %s', $n((float) $finish['unit_price'], 2)),
                    AcrylicFinishPricingMode::PerSquareMeter->value => sprintf(
                        '%s m² × %s',
                        $n($areaM2),
                        $n((float) $finish['unit_price'], 2),
                    ),
                    AcrylicFinishPricingMode::PerMeter->value => sprintf(
                        '%s m × %s',
                        $n($perimeterM),
                        $n((float) $finish['unit_price'], 2),
                    ),
                    AcrylicFinishPricingMode::PerUnit->value => sprintf(
                        'Cantidad × %s (ver distanciadores)',
                        $n((float) $finish['unit_price'], 2),
                    ),
                    default => 'Según modo de cobro',
                };

                $items[] = [
                    'group' => '3 · Luces / extras',
                    'label' => 'Accesorio · '.$finish['name'],
                    'formula' => $finishFormula,
                    'amount' => (float) $finish['amount'],
                ];
            }
        }

        $items[] = [
            'group' => '3 · Luces / extras',
            'label' => 'Mano de obra base',
            'formula' => sprintf(
                'Fijo %s + (%s m² × %s $/m²)',
                $n((float) $settings->labor_fixed_cost, 2),
                $n($areaM2),
                $n((float) $settings->labor_per_m2, 2),
            ),
            'amount' => $baseLabor,
        ];
        $items[] = [
            'group' => '3 · Luces / extras',
            'label' => 'Ensamble sobre letras/logo',
            'formula' => sprintf(
                '(%s letras + %s logo) × %s%%',
                $n($lettersOnlyCost, 2),
                $n($logoCost, 2),
                $n((float) ($settings->assembly_percent_of_lettering ?? 0), 2),
            ),
            'amount' => $letteringAssembly,
        ];
        $items[] = [
            'group' => '3 · Luces / extras',
            'label' => 'Total ensamble / MO',
            'formula' => 'MO base + % ensamble',
            'amount' => $laborCost,
        ];
        $items[] = [
            'group' => '3 · Luces / extras',
            'label' => 'Total capa 3',
            'formula' => sprintf(
                'Luces %s + accesorios %s + ensamble %s',
                $n($lightingCost, 2),
                $n($accessoriesCost, 2),
                $n($laborCost, 2),
            ),
            'amount' => $assemblyCost,
        ];

        $items[] = [
            'group' => 'Totales',
            'label' => 'Subtotal costos',
            'formula' => sprintf(
                'Base %s + letras/logo %s + capa 3 %s',
                $n($baseCost, 2),
                $n($letteringCost, 2),
                $n($assemblyCost, 2),
            ),
            'amount' => $costSubtotal,
        ];
        $items[] = [
            'group' => 'Totales',
            'label' => 'Margen comercial',
            'formula' => sprintf('Subtotal × %s%%', $n($marginPercent, 2)),
            'amount' => $marginAmount,
        ];
        $items[] = [
            'group' => 'Totales',
            'label' => 'Precio unitario sin IVA',
            'formula' => 'Subtotal costos + margen',
            'amount' => $unitPriceExIva,
        ];
        $items[] = [
            'group' => 'Totales',
            'label' => 'Subtotal línea',
            'formula' => sprintf('Unitario × %d', $quantity),
            'amount' => $lineSubtotalExIva,
        ];
        $items[] = [
            'group' => 'Totales',
            'label' => 'IVA',
            'formula' => sprintf('%s%% sobre subtotal línea', $n((float) $tax['iva_rate'], 2)),
            'amount' => (float) $tax['iva_amount'],
        ];
        $items[] = [
            'group' => 'Totales',
            'label' => 'Total con IVA',
            'formula' => 'Subtotal línea + IVA',
            'amount' => (float) $tax['total'],
        ];

        return [
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     amount: float,
     *     surface_amount: float,
     *     per_letter_amount: float,
     *     letter_area_m2: float,
     *     cut_meters: float,
     *     letter_count: int,
     *     price_per_letter: float
     * }
     */
    private function letteringCost(
        AcrylicLetteringOption $lettering,
        float $areaM2,
        float $perimeterM,
        float $coveragePercent,
        float $cutComplexity,
        AcrylicSignType $signType,
        int $letterCount,
    ): array {
        $letterArea = Money::round($areaM2 * (max(0, $coveragePercent) / 100), 4);
        $cutMeters = Money::round($perimeterM * (max(0, $coveragePercent) / 100) * max(0, $cutComplexity), 4);

        $surfaceAmount = match ($lettering->pricing_mode) {
            AcrylicLetteringPricingMode::None => 0.0,
            AcrylicLetteringPricingMode::CoverageArea => $letterArea * (float) $lettering->price_per_m2,
            AcrylicLetteringPricingMode::CoverageAreaPlusCut => ($letterArea * (float) $lettering->price_per_m2)
                + ($cutMeters * (float) $lettering->cut_price_per_meter),
            AcrylicLetteringPricingMode::Fixed => (float) $lettering->fixed_price,
        };

        if ($lettering->pricing_mode !== AcrylicLetteringPricingMode::None
            && $lettering->pricing_mode !== AcrylicLetteringPricingMode::Fixed
            && $coveragePercent <= 0) {
            $message = $signType === AcrylicSignType::LettersOnly
                ? 'Indica las medidas envolventes de las letras.'
                : 'Indica el % de cobertura de letras sobre la base.';

            throw new InvalidArgumentException($message);
        }

        $pricePerLetter = $lettering->type === AcrylicLetteringType::None
            ? 0.0
            : (float) ($lettering->price_per_letter ?? 0);

        if ($lettering->type !== AcrylicLetteringType::None && $letterCount < 1) {
            throw new InvalidArgumentException('Indica cuántas letras tiene el aviso (mínimo 1).');
        }

        $effectiveLetterCount = $lettering->type === AcrylicLetteringType::None ? 0 : max(0, $letterCount);
        $perLetterAmount = Money::round($effectiveLetterCount * $pricePerLetter);

        return [
            'surface_amount' => Money::round($surfaceAmount),
            'per_letter_amount' => $perLetterAmount,
            'amount' => Money::round($surfaceAmount + $perLetterAmount),
            'letter_area_m2' => $letterArea,
            'cut_meters' => $cutMeters,
            'letter_count' => $effectiveLetterCount,
            'price_per_letter' => $pricePerLetter,
        ];
    }

    /**
     * Medidas y costo promedio por letra. Con logo, el alto es el del aviso y el ancho útil baja.
     *
     * @return array{
     *     letter_count: int,
     *     sign_width_cm: float,
     *     letter_zone_width_cm: float,
     *     logo_reserved_width_cm: float,
     *     estimated_width_cm: float,
     *     estimated_height_cm: float,
     *     catalog_price_per_letter: float,
     *     surface_share_per_letter: float,
     *     avg_cost_per_letter: float,
     *     logo_reduces_letters: bool
     * }|null
     */
    private function letterPerUnitCheck(
        float $widthCm,
        float $heightCm,
        int $letterCount,
        float $surfaceAmount,
        float $pricePerLetter,
        float $lettersTotal,
        bool $hasLogo,
        float $logoWidthCm,
    ): ?array {
        if ($letterCount < 1) {
            return null;
        }

        $logoReservedWidth = $hasLogo ? max(0.0, $logoWidthCm) : 0.0;
        $letterZoneWidth = Money::round(max(0, $widthCm - $logoReservedWidth), 2);
        // Alto de letra = alto del aviso (= alto del logo cuando hay logo).
        $letterHeight = Money::round($heightCm, 2);

        return [
            'letter_count' => $letterCount,
            'sign_width_cm' => Money::round($widthCm, 2),
            'letter_zone_width_cm' => $letterZoneWidth,
            'logo_reserved_width_cm' => Money::round($logoReservedWidth, 2),
            'estimated_width_cm' => Money::round($letterZoneWidth / $letterCount, 2),
            'estimated_height_cm' => $letterHeight,
            'catalog_price_per_letter' => Money::round($pricePerLetter),
            'surface_share_per_letter' => Money::round($surfaceAmount / $letterCount),
            'avg_cost_per_letter' => Money::round($lettersTotal / $letterCount),
            'logo_reduces_letters' => $hasLogo && $logoReservedWidth > 0,
        ];
    }

    /**
     * @param  array{
     *     coverage_percent: float,
     *     area_m2: float,
     *     width_cm: float,
     *     height_cm: float,
     *     linked_to_letter_height: bool,
     *     logo_only: bool
     * }  $logoBreakdown
     * @return array{
     *     width_cm: float,
     *     height_cm: float,
     *     area_m2: float,
     *     coverage_percent: float,
     *     amount: float,
     *     linked_to_letter_height: bool,
     *     logo_only: bool
     * }|null
     */
    private function logoSizeCheck(array $logoBreakdown, float $logoAmount): ?array
    {
        if (($logoBreakdown['width_cm'] ?? 0) <= 0) {
            return null;
        }

        return [
            'width_cm' => (float) $logoBreakdown['width_cm'],
            'height_cm' => (float) $logoBreakdown['height_cm'],
            'area_m2' => (float) $logoBreakdown['area_m2'],
            'coverage_percent' => (float) $logoBreakdown['coverage_percent'],
            'amount' => Money::round($logoAmount),
            'linked_to_letter_height' => (bool) $logoBreakdown['linked_to_letter_height'],
            'logo_only' => (bool) $logoBreakdown['logo_only'],
        ];
    }

    /**
     * Sin base: ancho/alto explícitos del logo.
     * Con base + letras: alto = letras; cobertura = % del ancho.
     * Con base + solo logo: medidas del aviso; cobertura = % del área.
     *
     * @return array{
     *     amount: float,
     *     coverage_percent: float,
     *     area_m2: float,
     *     price_per_m2: float,
     *     fixed_cost: float,
     *     width_cm: float,
     *     height_cm: float,
     *     linked_to_letter_height: bool,
     *     logo_only: bool
     * }
     */
    private function logoCost(
        AcrylicPricingSetting $settings,
        float $widthCm,
        float $heightCm,
        float $areaM2,
        bool $hasLogo,
        float $logoCoveragePercent,
        bool $hasLetters,
        float $letterHeightCm,
        ?float $explicitLogoWidthCm = null,
        ?float $explicitLogoHeightCm = null,
    ): array {
        $pricePerM2 = (float) ($settings->logo_price_per_m2 ?? 0);
        $fixed = (float) ($settings->logo_fixed_cost ?? 0);

        if (! $hasLogo) {
            return [
                'amount' => 0.0,
                'coverage_percent' => 0.0,
                'area_m2' => 0.0,
                'price_per_m2' => $pricePerM2,
                'fixed_cost' => $fixed,
                'width_cm' => 0.0,
                'height_cm' => 0.0,
                'linked_to_letter_height' => false,
                'logo_only' => false,
            ];
        }

        if ($explicitLogoWidthCm !== null && $explicitLogoHeightCm !== null) {
            $logoWidth = Money::round($explicitLogoWidthCm, 2);
            $logoHeight = Money::round($explicitLogoHeightCm, 2);
            $logoArea = Money::round(($logoWidth / 100) * ($logoHeight / 100), 4);
            $coverage = 100.0;
            $linked = false;
            $logoOnly = ! $hasLetters;
        } else {
            if ($logoCoveragePercent <= 0) {
                throw new InvalidArgumentException(
                    $hasLetters
                        ? 'Indica qué % del ancho del aviso ocupa el logo.'
                        : 'Indica el % de cobertura del logo.'
                );
            }

            if ($hasLetters) {
                $logoHeight = Money::round($letterHeightCm > 0 ? $letterHeightCm : $heightCm, 2);
                $logoWidth = Money::round(min($widthCm, $widthCm * ($logoCoveragePercent / 100)), 2);
                $logoArea = Money::round(($logoWidth / 100) * ($logoHeight / 100), 4);
                $coverage = $logoCoveragePercent;
                $linked = true;
                $logoOnly = false;
            } else {
                $logoWidth = Money::round($widthCm, 2);
                $logoHeight = Money::round($heightCm, 2);
                $logoArea = Money::round($areaM2 * ($logoCoveragePercent / 100), 4);
                $coverage = $logoCoveragePercent;
                $linked = false;
                $logoOnly = true;
            }
        }

        $amount = Money::round($fixed + ($logoArea * $pricePerM2));

        return [
            'amount' => $amount,
            'coverage_percent' => Money::round($coverage, 2),
            'area_m2' => $logoArea,
            'price_per_m2' => $pricePerM2,
            'fixed_cost' => $fixed,
            'width_cm' => $logoWidth,
            'height_cm' => $logoHeight,
            'linked_to_letter_height' => $linked,
            'logo_only' => $logoOnly,
        ];
    }

    private function lightingCost(AcrylicLightingOption $lighting, float $areaM2, float $perimeterM): float
    {
        $base = match ($lighting->pricing_mode) {
            AcrylicLightingPricingMode::None => 0.0,
            AcrylicLightingPricingMode::PerMeter => $perimeterM * (float) $lighting->unit_price,
            AcrylicLightingPricingMode::PerSquareMeter => $areaM2 * (float) $lighting->unit_price,
            AcrylicLightingPricingMode::Fixed => (float) $lighting->unit_price,
        };

        $psu = $lighting->pricing_mode === AcrylicLightingPricingMode::None
            ? 0.0
            : (float) $lighting->power_supply_cost;

        return Money::round($base + $psu);
    }

    private function finishCost(
        AcrylicFinishOption $finish,
        float $areaM2,
        float $perimeterM,
        int $spacerCount,
    ): float {
        return Money::round(match ($finish->pricing_mode) {
            AcrylicFinishPricingMode::Fixed => (float) $finish->unit_price,
            AcrylicFinishPricingMode::PerSquareMeter => $areaM2 * (float) $finish->unit_price,
            AcrylicFinishPricingMode::PerMeter => $perimeterM * (float) $finish->unit_price,
            AcrylicFinishPricingMode::PerUnit => max(0, $spacerCount) * (float) $finish->unit_price,
        });
    }

    private function buildDescription(
        AcrylicSignType $signType,
        float $widthCm,
        float $heightCm,
        ?AcrylicMaterial $material,
        AcrylicLetteringOption $lettering,
        AcrylicLightingOption $lighting,
        int $letterCount,
        bool $hasLogo,
    ): string {
        $size = sprintf('%s × %s cm', $this->formatCm($widthCm), $this->formatCm($heightCm));
        $lettersBit = $letterCount > 0
            ? sprintf('%d letras', $letterCount)
            : ($lettering->type === AcrylicLetteringType::None ? null : $lettering->name);
        $logoBit = $hasLogo
            ? ($lettersBit ? ' + logo' : 'solo logo')
            : '';
        $contentBit = $lettersBit
            ? $lettersBit.$logoBit
            : ($hasLogo ? 'solo logo' : 'sin letras');

        return match ($signType) {
            AcrylicSignType::LettersOnly => sprintf(
                '%s %s · %s · %s',
                $hasLogo && $letterCount < 1 ? 'Logo individual' : 'Letras individuales',
                $size,
                $contentBit,
                $lighting->name,
            ),
            AcrylicSignType::FlatLaser => sprintf(
                'Aviso corte láser plano %s · %s · %s%s',
                $size,
                $material?->label() ?? 'acrílico',
                $lighting->name,
                $hasLogo ? ' · logo' : '',
            ),
            AcrylicSignType::WithBase => sprintf(
                'Aviso acrílico %s · %s · %s · %s',
                $size,
                $material?->label() ?? 'base',
                $contentBit,
                $lighting->name,
            ),
        };
    }

    private function assertPositiveDimensions(float $widthCm, float $heightCm, int $quantity): void
    {
        if ($widthCm <= 0 || $heightCm <= 0) {
            throw new InvalidArgumentException('El ancho y el alto deben ser mayores a cero.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
        }
    }

    private function assertLetteringInputs(
        float $coveragePercent,
        float $cutComplexity,
        int $letterCount,
        bool $hasLogo,
        float $logoCoveragePercent,
    ): void {
        if ($coveragePercent < 0 || $coveragePercent > 100) {
            throw new InvalidArgumentException('La cobertura de letras debe estar entre 0% y 100%.');
        }

        if ($cutComplexity < 0 || $cutComplexity > 5) {
            throw new InvalidArgumentException('La complejidad de corte debe estar entre 0 y 5.');
        }

        if ($letterCount < 0 || $letterCount > 999) {
            throw new InvalidArgumentException('La cantidad de letras debe estar entre 0 y 999.');
        }

        if ($hasLogo && ($logoCoveragePercent < 0 || $logoCoveragePercent > 100)) {
            throw new InvalidArgumentException('La cobertura del logo debe estar entre 0% y 100%.');
        }
    }

    private function formatCm(float $cm): string
    {
        return rtrim(rtrim(number_format($cm, 2, ',', '.'), '0'), ',');
    }
}
