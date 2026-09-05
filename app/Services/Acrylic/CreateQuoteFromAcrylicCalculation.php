<?php

namespace App\Services\Acrylic;

use App\Enums\AcrylicLetteringType;
use App\Enums\ItemType;
use App\Enums\QuoteStatus;
use App\Models\AcrylicLetteringOption;
use App\Models\Item;
use App\Models\Quote;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuoteFromAcrylicCalculation
{
    public function __construct(
        private readonly AcrylicSignQuotationService $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Quote
    {
        $customerId = filled($input['customer_id'] ?? null) ? (int) $input['customer_id'] : null;
        $leadId = filled($input['lead_id'] ?? null) ? (int) $input['lead_id'] : null;

        if ($customerId === null && $leadId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Selecciona un cliente o un prospecto para crear la cotización.',
            ]);
        }

        $finishIds = array_values(array_map(
            'intval',
            array_filter($input['finish_option_ids'] ?? [], fn ($id) => filled($id)),
        ));

        $margin = array_key_exists('margin_percent', $input) && $input['margin_percent'] !== null && $input['margin_percent'] !== ''
            ? (float) $input['margin_percent']
            : null;

        $contentMode = (string) ($input['content_mode'] ?? '');
        $hasLogo = in_array($contentMode, ['logo', 'both'], true) || (bool) ($input['has_logo'] ?? false);
        $hasLetters = in_array($contentMode, ['letters', 'both'], true)
            || ($contentMode === '' && ! $hasLogo);

        $letteringOptionId = filled($input['lettering_option_id'] ?? null)
            ? (int) $input['lettering_option_id']
            : (int) (AcrylicLetteringOption::query()
                ->active()
                ->where('type', AcrylicLetteringType::None)
                ->value('id') ?? 0);

        $result = $this->calculator->calculate(
            widthCm: (float) ($input['width_cm'] ?? 0),
            heightCm: (float) ($input['height_cm'] ?? 0),
            quantity: (int) $input['quantity'],
            materialId: filled($input['material_id'] ?? null) ? (int) $input['material_id'] : null,
            lightingOptionId: (int) $input['lighting_option_id'],
            letteringOptionId: $letteringOptionId,
            finishOptionIds: $finishIds,
            spacerCount: (int) ($input['spacer_count'] ?? 0),
            letteringCoveragePercent: (float) ($input['lettering_coverage_percent'] ?? 0),
            cutComplexity: (float) ($input['cut_complexity'] ?? 1),
            marginPercentOverride: $margin,
            signType: (string) ($input['sign_type'] ?? 'with_base'),
            letterCount: $hasLetters ? (int) ($input['letter_count'] ?? 0) : 0,
            hasLogo: $hasLogo,
            logoCoveragePercent: (float) ($input['logo_coverage_percent'] ?? ($hasLogo && ! $hasLetters ? 100 : 0)),
            lettersWidthCm: filled($input['letters_width_cm'] ?? null) ? (float) $input['letters_width_cm'] : null,
            lettersHeightCm: filled($input['letters_height_cm'] ?? null) ? (float) $input['letters_height_cm'] : null,
            logoWidthCm: filled($input['logo_width_cm'] ?? null) ? (float) $input['logo_width_cm'] : null,
            logoHeightCm: filled($input['logo_height_cm'] ?? null) ? (float) $input['logo_height_cm'] : null,
        );

        return DB::transaction(function () use ($input, $customerId, $leadId, $result): Quote {
            $quote = Quote::query()->create([
                'status' => QuoteStatus::Draft,
                'customer_id' => $customerId,
                'lead_id' => $leadId,
                'user_id' => auth()->id(),
                'valid_until' => $input['valid_until'] ?? now()->addDays(15)->toDateString(),
                'notes' => $this->composeNotes($input['notes'] ?? null, $result),
            ]);

            $quote->items()->create([
                'item_id' => $this->catalogItemId(),
                'description' => $result['description'],
                'quantity' => $result['quantity'],
                'unit_price' => $result['costs']['unit_price_ex_iva'],
                'meta' => [
                    'source' => 'acrylic_calculator',
                    'calculation' => $result,
                ],
            ]);

            $quote->recalculateTotal();

            return $quote->fresh(['items', 'customer', 'lead']);
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function composeNotes(?string $notes, array $result): string
    {
        $lines = [
            'Cotizador de avisos en acrílico (3 capas)',
            sprintf(
                'Área: %s m² · Perímetro: %s m · Desperdicio: %s%%',
                number_format($result['area_m2'], 4, ',', '.'),
                number_format($result['perimeter_m'], 4, ',', '.'),
                number_format($result['material']['waste_percent'], 2, ',', '.'),
            ),
            sprintf(
                'Capas: base %s · letras/logo %s · luces/accesorios/ensamble %s · margen %s%%',
                Money::format($result['costs']['base']),
                Money::format($result['costs']['lettering']),
                Money::format($result['costs']['assembly']),
                number_format($result['costs']['margin_percent'], 2, ',', '.'),
            ),
        ];

        if (filled($notes)) {
            $lines[] = '';
            $lines[] = trim((string) $notes);
        }

        return implode("\n", $lines);
    }

    private function catalogItemId(): ?int
    {
        $item = Item::query()
            ->where(function ($query): void {
                $query->where('sku', 'AVISO-ACRILICO')
                    ->orWhere('name', 'Aviso acrílico a medida');
            })
            ->first();

        if ($item) {
            return $item->id;
        }

        return Item::query()->create([
            'sku' => 'AVISO-ACRILICO',
            'name' => 'Aviso acrílico a medida',
            'type' => ItemType::ProductoTerminado,
            'unit_of_measure' => 'und',
            'stock' => 0,
            'min_stock' => 0,
            'cost' => 0,
            'price' => 0,
            'is_active' => true,
        ])->id;
    }
}
