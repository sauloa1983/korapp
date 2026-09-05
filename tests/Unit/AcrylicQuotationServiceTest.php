<?php

namespace Tests\Unit;

use App\Enums\AcrylicFinishPricingMode;
use App\Enums\AcrylicFinishType;
use App\Enums\AcrylicLetteringPricingMode;
use App\Enums\AcrylicLetteringType;
use App\Enums\AcrylicLightingPricingMode;
use App\Enums\AcrylicSignType;
use App\Models\AcrylicFinishOption;
use App\Models\AcrylicLetteringOption;
use App\Models\AcrylicLightingOption;
use App\Models\AcrylicMaterial;
use App\Models\AcrylicPricingSetting;
use App\Models\CompanySetting;
use App\Services\Acrylic\AcrylicSignQuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcrylicQuotationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_three_layers_base_lettering_and_assembly(): void
    {
        [$material, $lettering, $lighting, $finish] = $this->seedCatalog(pricePerLetter: 0);

        $result = app(AcrylicSignQuotationService::class)->calculate(
            widthCm: 100,
            heightCm: 50,
            quantity: 2,
            materialId: $material->id,
            lightingOptionId: $lighting->id,
            letteringOptionId: $lettering->id,
            finishOptionIds: [$finish->id],
            spacerCount: 4,
            letteringCoveragePercent: 40,
            cutComplexity: 1,
            signType: AcrylicSignType::WithBase,
            letterCount: 5,
        );

        $this->assertSame(0.5, $result['area_m2']);
        $this->assertTrue($result['has_base']);
        $this->assertSame(55000.0, $result['costs']['base']);
        $this->assertSame(52000.0, $result['costs']['lettering']);
        $this->assertSame(128200.0, $result['costs']['assembly']);
        $this->assertSame(235200.0, $result['costs']['subtotal']);
        $this->assertSame(352800.0, $result['costs']['unit_price_ex_iva']);
    }

    public function test_letters_only_has_zero_base_cost_and_full_coverage(): void
    {
        [$material, $lettering, $lighting] = $this->seedCatalog(pricePerLetter: 0);

        $result = app(AcrylicSignQuotationService::class)->calculate(
            widthCm: 0,
            heightCm: 0,
            quantity: 1,
            materialId: $material->id,
            lightingOptionId: $lighting->id,
            letteringOptionId: $lettering->id,
            finishOptionIds: [],
            spacerCount: 0,
            letteringCoveragePercent: 40,
            cutComplexity: 1,
            signType: AcrylicSignType::LettersOnly,
            letterCount: 8,
            lettersWidthCm: 12.5, // por letra → total 100 cm
            lettersHeightCm: 50,
        );

        $this->assertFalse($result['has_base']);
        // Material: 0.5 m² × 1.10 merma × 100000 = 55000
        $this->assertSame(55000.0, $result['costs']['base']);
        $this->assertSame(100.0, $result['lettering_coverage_percent']);
        // 0.125 × 0.50 × 8 = 0.5 m² · corte 2×(0.125+0.5)×8 = 10 m
        $this->assertSame(0.5, $result['lettering']['letter_area_m2']);
        $this->assertSame(200000.0, $result['costs']['lettering']);
        $this->assertTrue($result['lettering']['separate_sizes']);
        $this->assertSame(12.5, $result['lettering']['per_letter_width_cm']);
        $this->assertSame(12.5, $result['lettering']['per_letter_check']['estimated_width_cm']);
        $this->assertSame(50.0, $result['lettering']['per_letter_check']['estimated_height_cm']);
        $this->assertSame(100.0, $result['lettering']['width_cm']);
    }

    public function test_letters_only_with_independent_logo_sizes(): void
    {
        [$material, $lettering, $lighting] = $this->seedCatalog(pricePerLetter: 0);

        AcrylicPricingSetting::query()->first()->update([
            'logo_price_per_m2' => 200000,
            'logo_fixed_cost' => 0,
        ]);
        AcrylicPricingSetting::flushCache();

        $result = app(AcrylicSignQuotationService::class)->calculate(
            widthCm: 0,
            heightCm: 0,
            quantity: 1,
            materialId: $material->id,
            lightingOptionId: $lighting->id,
            letteringOptionId: $lettering->id,
            cutComplexity: 1,
            signType: AcrylicSignType::LettersOnly,
            letterCount: 5,
            hasLogo: true,
            lettersWidthCm: 16, // por letra → total texto 80 cm
            lettersHeightCm: 30,
            logoWidthCm: 40,
            logoHeightCm: 50,
        );

        // Envolvente total = (16×5)+40 × max(30,50)
        $this->assertSame(120.0, $result['width_cm']);
        $this->assertSame(50.0, $result['height_cm']);
        $this->assertSame(80.0, $result['lettering']['width_cm']);
        $this->assertSame(30.0, $result['lettering']['height_cm']);
        $this->assertSame(16.0, $result['lettering']['per_letter_width_cm']);
        $this->assertSame(40.0, $result['logo']['width_cm']);
        $this->assertSame(50.0, $result['logo']['height_cm']);
        $this->assertFalse($result['logo']['linked_to_letter_height']);
        // Logo 0.4 × 0.5 = 0.2 m² × 200000
        $this->assertSame(40000.0, $result['costs']['logo']);
        // Material: (0.24 letras + 0.2 logo) × 1.10 × 100000 = 48400
        $this->assertSame(48400.0, $result['costs']['base']);
        $this->assertSame(16.0, $result['lettering']['per_letter_check']['estimated_width_cm']);
        $this->assertSame(30.0, $result['lettering']['per_letter_check']['estimated_height_cm']);
        $this->assertFalse($result['lettering']['per_letter_check']['logo_reduces_letters']);
    }

    public function test_ten_letters_cost_more_than_five_and_logo_is_separate(): void
    {
        [$material, $lettering, $lighting] = $this->seedCatalog(pricePerLetter: 10000);

        AcrylicPricingSetting::query()->first()->update([
            'logo_price_per_m2' => 200000,
            'logo_fixed_cost' => 10000,
        ]);
        AcrylicPricingSetting::flushCache();

        $five = app(AcrylicSignQuotationService::class)->calculate(
            widthCm: 100,
            heightCm: 50,
            quantity: 1,
            materialId: $material->id,
            lightingOptionId: $lighting->id,
            letteringOptionId: $lettering->id,
            letteringCoveragePercent: 40,
            cutComplexity: 1,
            signType: AcrylicSignType::WithBase,
            letterCount: 5,
            hasLogo: false,
        );

        $ten = app(AcrylicSignQuotationService::class)->calculate(
            widthCm: 100,
            heightCm: 50,
            quantity: 1,
            materialId: $material->id,
            lightingOptionId: $lighting->id,
            letteringOptionId: $lettering->id,
            letteringCoveragePercent: 40,
            cutComplexity: 1,
            signType: AcrylicSignType::WithBase,
            letterCount: 10,
            hasLogo: true,
            logoCoveragePercent: 10,
        );

        // Same surface (52000) + 5*10000 vs 10*10000
        $this->assertSame(102000.0, $five['costs']['letters']);
        $this->assertSame(152000.0, $ten['costs']['letters']);
        $this->assertSame(0.0, $five['costs']['logo']);
        // logo area 0.05 * 200000 + 10000 = 20000
        $this->assertSame(20000.0, $ten['costs']['logo']);
        $this->assertSame(172000.0, $ten['costs']['lettering']);

        // Sin logo: 100 cm / 5 letras = 20 cm
        $this->assertSame(20.0, $five['lettering']['per_letter_check']['estimated_width_cm']);
        $this->assertSame(50.0, $five['lettering']['per_letter_check']['estimated_height_cm']);
        $this->assertSame(20400.0, $five['lettering']['per_letter_check']['avg_cost_per_letter']);
        $this->assertFalse($five['lettering']['per_letter_check']['logo_reduces_letters']);
        $this->assertNull($five['logo']['size_check']);

        // Con logo 10% del ancho: alto = letras (50 cm), ancho logo = 10 cm; letras 90/10 = 9 cm
        $this->assertNotNull($ten['logo']['size_check']);
        $this->assertTrue($ten['logo']['size_check']['linked_to_letter_height']);
        $this->assertSame(10.0, $ten['logo']['size_check']['width_cm']);
        $this->assertSame(50.0, $ten['logo']['size_check']['height_cm']);
        $this->assertTrue($ten['lettering']['per_letter_check']['logo_reduces_letters']);
        $this->assertSame(9.0, $ten['lettering']['per_letter_check']['estimated_width_cm']);
        $this->assertSame(50.0, $ten['lettering']['per_letter_check']['estimated_height_cm']);
        $this->assertSame(15200.0, $ten['lettering']['per_letter_check']['avg_cost_per_letter']);
    }

    public function test_logo_only_sign_without_letters(): void
    {
        [$material, , $lighting] = $this->seedCatalog();

        $none = AcrylicLetteringOption::query()->create([
            'name' => 'Sin letras',
            'type' => AcrylicLetteringType::None,
            'pricing_mode' => AcrylicLetteringPricingMode::None,
            'price_per_m2' => 0,
            'cut_price_per_meter' => 0,
            'fixed_price' => 0,
            'price_per_letter' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        AcrylicPricingSetting::query()->first()->update([
            'logo_price_per_m2' => 200000,
            'logo_fixed_cost' => 10000,
        ]);
        AcrylicPricingSetting::flushCache();

        $result = app(AcrylicSignQuotationService::class)->calculate(
            widthCm: 100,
            heightCm: 50,
            quantity: 1,
            materialId: $material->id,
            lightingOptionId: $lighting->id,
            letteringOptionId: $none->id,
            letteringCoveragePercent: 0,
            cutComplexity: 1,
            signType: AcrylicSignType::WithBase,
            letterCount: 0,
            hasLogo: true,
            logoCoveragePercent: 100,
        );

        $this->assertSame(0.0, $result['costs']['letters']);
        $this->assertTrue($result['logo']['logo_only']);
        $this->assertSame(100.0, $result['logo']['width_cm']);
        $this->assertSame(50.0, $result['logo']['height_cm']);
        // área 0.5 * 200000 + 10000
        $this->assertSame(110000.0, $result['costs']['logo']);
        $this->assertNull($result['lettering']['per_letter_check']);
    }

    /**
     * @return array{0: AcrylicMaterial, 1: AcrylicLetteringOption, 2: AcrylicLightingOption, 3: AcrylicFinishOption}
     */
    private function seedCatalog(float $pricePerLetter = 0): array
    {
        CompanySetting::query()->create([
            'name' => 'Test',
            'charges_iva' => true,
            'iva_rate' => 19,
            'primary_color' => '#3B82F6',
            'sidebar_theme' => 'indigo',
            'sidebar_color' => '#313A82',
        ]);

        AcrylicPricingSetting::query()->create([
            'labor_fixed_cost' => 10000,
            'labor_per_m2' => 10000,
            'assembly_percent_of_lettering' => 10,
            'logo_price_per_m2' => 180000,
            'logo_fixed_cost' => 0,
            'margin_percent' => 50,
        ]);

        $material = AcrylicMaterial::query()->create([
            'name' => 'Acrílico 5mm',
            'thickness_mm' => 5,
            'price_per_m2' => 100000,
            'waste_percent' => 10,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $lettering = AcrylicLetteringOption::query()->create([
            'name' => 'Relieve 2D',
            'type' => AcrylicLetteringType::Relief2d,
            'pricing_mode' => AcrylicLetteringPricingMode::CoverageAreaPlusCut,
            'price_per_m2' => 200000,
            'cut_price_per_meter' => 10000,
            'fixed_price' => 0,
            'price_per_letter' => $pricePerLetter,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $lighting = AcrylicLightingOption::query()->create([
            'name' => 'LED perimetral',
            'pricing_mode' => AcrylicLightingPricingMode::PerMeter,
            'unit_price' => 20000,
            'power_supply_cost' => 40000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $finish = AcrylicFinishOption::query()->create([
            'name' => 'Distanciadores',
            'type' => AcrylicFinishType::Spacer,
            'pricing_mode' => AcrylicFinishPricingMode::PerUnit,
            'unit_price' => 2000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$material, $lettering, $lighting, $finish];
    }
}
