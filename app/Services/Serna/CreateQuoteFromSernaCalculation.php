<?php

namespace App\Services\Serna;

use App\Enums\ItemType;
use App\Enums\QuoteStatus;
use App\Enums\SernaItemType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\SernaCatalogProduct;
use App\Services\Serna\Ai\SernaQuoteReadinessChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuoteFromSernaCalculation
{
    public function __construct(
        private readonly SernaQuotationEngine $engine,
        private readonly SernaQuoteReadinessChecker $readinessChecker,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, bool $allowIncomplete = false): Quote
    {
        $customerId = filled($input['customer_id'] ?? null) ? (int) $input['customer_id'] : null;
        $leadId = filled($input['lead_id'] ?? null) ? (int) $input['lead_id'] : null;

        if ($customerId === null && $leadId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Selecciona un cliente o un prospecto para crear la cotización.',
            ]);
        }

        $pieces = $this->normalizePieces($input);

        if ($pieces === []) {
            throw ValidationException::withMessages([
                'pieces' => 'Agrega al menos una pieza con ítems a la propuesta.',
            ]);
        }

        $withholdingRate = $this->resolveWithholdingRate($customerId, $input);
        $prompt = trim((string) ($input['ai_prompt'] ?? ''));
        $readiness = $this->readinessChecker->check(
            $pieces,
            $prompt !== '' ? $prompt : null,
        );

        if (! $allowIncomplete && ! $readiness['ok']) {
            throw ValidationException::withMessages([
                'pieces' => implode(' ', $readiness['errors']),
            ]);
        }

        $proposal = $this->engine->calculateProposalFromPieces(
            $pieces,
            $withholdingRate,
            allowIncomplete: $allowIncomplete,
        );

        return DB::transaction(function () use ($input, $customerId, $leadId, $proposal, $withholdingRate, $allowIncomplete, $readiness): Quote {
            $validityDays = max(1, (int) ($input['validity_days'] ?? 10));
            $validUntil = $input['valid_until'] ?? now()->addDays($validityDays)->toDateString();
            $deliveryDate = $input['delivery_date'] ?? null;
            $deliveryNote = filled($deliveryDate)
                ? ($input['delivery_note'] ?? null)
                : ($input['delivery_note'] ?? 'A CONVENIR');

            $contactName = $input['contact_name'] ?? null;
            if (blank($contactName)) {
                if ($customerId) {
                    $customer = Customer::query()->whereKey($customerId)->first([
                        'contact_name',
                        'name',
                        'document_type',
                    ]);
                    $contactName = $customer?->personContactName();
                } elseif ($leadId) {
                    $contactName = Lead::query()->whereKey($leadId)->value('name');
                }
            }

            $notes = $this->composeNotes(
                isset($input['notes']) ? (string) $input['notes'] : null,
                $allowIncomplete ? $readiness['errors'] : [],
                (bool) ($proposal['has_incomplete'] ?? false),
            );

            $quote = Quote::query()->create([
                'status' => QuoteStatus::Draft,
                'project_name' => filled($input['project_name'] ?? null)
                    ? mb_strtoupper(trim((string) $input['project_name']), 'UTF-8')
                    : ($allowIncomplete ? 'BORRADOR SIN NOMBRE' : null),
                'customer_id' => $customerId,
                'lead_id' => $leadId,
                'contact_name' => filled($contactName)
                    ? mb_strtoupper(trim((string) $contactName), 'UTF-8')
                    : null,
                'user_id' => auth()->id(),
                'valid_until' => $validUntil,
                'validity_days' => $validityDays,
                'delivery_date' => $deliveryDate,
                'delivery_note' => filled($deliveryNote)
                    ? mb_strtoupper(trim((string) $deliveryNote), 'UTF-8')
                    : null,
                'payment_form' => filled($input['payment_form'] ?? null)
                    ? mb_strtoupper(trim((string) $input['payment_form']), 'UTF-8')
                    : 'CONTADO',
                'advance_percent' => $input['advance_percent'] ?? 50,
                'notes' => $notes,
                'terms' => $input['terms'] ?? $this->defaultTerms(
                    (float) ($input['advance_percent'] ?? 50),
                    $validityDays,
                ),
                'withholding_rate' => $withholdingRate,
            ]);

            foreach ($proposal['pieces'] as $pieceIndex => $pieceData) {
                $piece = $quote->pieces()->create([
                    'name' => $pieceData['name'],
                    'sort_order' => $pieceIndex,
                ]);

                foreach ($pieceData['items'] as $itemIndex => $line) {
                    $piece->items()->create([
                        'quote_id' => $quote->id,
                        'item_id' => $this->resolveCatalogItemId($line),
                        'description' => $this->compactDescription($line),
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'sort_order' => $itemIndex,
                        'meta' => [
                            'source' => 'serna_cotizador',
                            'piece_name' => $pieceData['name'],
                            'incomplete' => (bool) ($line['incomplete'] ?? false),
                            'pending_reason' => $line['pending_reason'] ?? null,
                            'draft_incomplete' => $allowIncomplete,
                            'calculation' => $line,
                        ],
                    ]);
                }
            }

            $quote->recalculateTotal();

            return $quote->fresh(['pieces.items', 'items', 'customer', 'lead']);
        });
    }

    /**
     * @param  list<string>  $blockers
     */
    private function composeNotes(?string $notes, array $blockers, bool $hasIncompleteLines): ?string
    {
        $notes = trim((string) $notes);
        $parts = [];

        if ($blockers !== [] || $hasIncompleteLines) {
            $lines = $blockers !== []
                ? $blockers
                : ['Hay ítems pendientes de precio o catálogo (marcados como PENDIENTE).'];
            $parts[] = "[PENDIENTES BORRADOR]\n• ".implode("\n• ", $lines);
        }

        if ($notes !== '') {
            $parts[] = $notes;
        }

        $composed = trim(implode("\n\n", $parts));

        return $composed !== '' ? $composed : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{name: string, items: list<array<string, mixed>>}>
     */
    private function normalizePieces(array $input): array
    {
        if (! empty($input['pieces']) && is_array($input['pieces'])) {
            $pieces = [];

            foreach ($input['pieces'] as $piece) {
                if (! is_array($piece)) {
                    continue;
                }

                $items = array_values(array_filter(
                    $piece['items'] ?? [],
                    fn ($row): bool => is_array($row) && filled($row['item_type'] ?? null),
                ));

                if ($items === []) {
                    continue;
                }

                $name = trim((string) ($piece['name'] ?? ''));
                if ($name === '' && filled($input['project_name'] ?? null)) {
                    $name = (string) $input['project_name'];
                }

                $pieces[] = [
                    'name' => $name,
                    'items' => $items,
                ];
            }

            return $pieces;
        }

        // Compatibilidad: payload plano de ítems (sin piezas).
        $items = array_values(array_filter(
            $input['items'] ?? [],
            fn ($row): bool => is_array($row) && filled($row['item_type'] ?? null),
        ));

        if ($items === []) {
            return [];
        }

        return [[
            'name' => (string) ($input['project_name'] ?? 'PIEZA 1'),
            'items' => $items,
        ]];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveWithholdingRate(?int $customerId, array $input): float
    {
        if (array_key_exists('withholding_rate', $input) && $input['withholding_rate'] !== null && $input['withholding_rate'] !== '') {
            return max(0, (float) $input['withholding_rate']);
        }

        if (! $customerId) {
            return 0.0;
        }

        $customer = Customer::query()->find($customerId);

        if (! $customer?->is_retenedor) {
            return 0.0;
        }

        return max(0, (float) $customer->retenedor_percent);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveCatalogItemId(array $line): ?int
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
            // Se factura como producto, pero Quote::isManufacturingQuoteLine lo excluye de OP.
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

    private function defaultTerms(float $advancePercent, int $validityDays = 10): string
    {
        $advance = rtrim(rtrim(number_format($advancePercent, 2, ',', '.'), '0'), ',');
        $days = max(1, $validityDays);
        $daysLabel = $days === 1 ? '1 día calendario' : "{$days} días calendario";

        return implode("\n", [
            "FORMA DE PAGO: {$advance}% DE ANTICIPO Y SALDO CONTRA ENTREGA.",
            "Vigencia de la cotización: {$daysLabel}.",
        ]);
    }

    /**
     * Descripción corta para la línea; el detalle completo va en meta.calculation.
     *
     * @param  array<string, mixed>  $line
     */
    private function compactDescription(array $line): string
    {
        $pending = ! empty($line['incomplete']) ? '[PENDIENTE] ' : '';
        $pieza = trim((string) ($line['pieza'] ?? $line['piece_name'] ?? ''));
        $material = trim((string) ($line['material'] ?? ''));
        $acabados = trim((string) ($line['acabados'] ?? ''));
        $typeLabel = trim((string) ($line['item_type_label'] ?? ''));

        // Evita anidar descripciones previas dentro de acabados.
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
}
