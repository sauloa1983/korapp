<?php

namespace App\Services\Serna\Ai;

use App\Enums\SernaItemType;
use App\Services\Serna\SernaQuotationEngine;
use App\Support\Money;
use Throwable;

/**
 * Valida que la cotización esté lista: nada del texto sin reconocer
 * y todos los ítems calculables / con valor manual completo.
 */
class SernaQuoteReadinessChecker
{
    public function __construct(
        private readonly SernaQuotationEngine $engine,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @param  list<string>  $unresolved
     * @return array{ok: bool, errors: list<string>}
     */
    public function check(array $pieces, ?string $prompt = null, array $unresolved = []): array
    {
        $errors = [];

        foreach ($unresolved as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                $errors[] = $message;
            }
        }

        if ($prompt !== null && trim($prompt) !== '') {
            $errors = [
                ...$errors,
                ...$this->unrecognizedFromPrompt($prompt, $pieces),
            ];
        }

        if ($pieces === []) {
            $errors[] = 'No hay piezas en la cotización.';
        }

        foreach ($pieces as $pieceIndex => $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $pieceName = trim((string) ($piece['name'] ?? '')) ?: ('Pieza '.($pieceIndex + 1));
            $items = array_values(array_filter(
                $piece['items'] ?? [],
                fn ($row): bool => is_array($row) && filled($row['item_type'] ?? null),
            ));

            if ($items === []) {
                $errors[] = "La pieza «{$pieceName}» no tiene ítems.";

                continue;
            }

            foreach ($items as $itemIndex => $item) {
                $errors = [
                    ...$errors,
                    ...$this->itemErrors($item, $pieceName, $itemIndex + 1),
                ];
            }
        }

        $errors = array_values(array_unique($errors));

        return [
            'ok' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return list<string>
     */
    public function unrecognizedFromPrompt(string $prompt, array $pieces): array
    {
        $lower = mb_strtolower($prompt, 'UTF-8');
        $types = $this->itemTypesInPieces($pieces);
        $errors = [];

        $checks = [
            [
                'pattern' => '/\b(vinilo|adhesivo)\b/iu',
                'types' => [SernaItemType::Vinilo->value],
                'label' => 'vinilo/adhesivo',
            ],
            [
                'pattern' => '/\bplotter\b/iu',
                'types' => [SernaItemType::Plotter->value],
                'label' => 'plotter',
            ],
            [
                'pattern' => '/\b(led|backlight|ne[oó]n|iluminaci[oó]n|luces?)\b/iu',
                'types' => [SernaItemType::Iluminacion->value, SernaItemType::Fuente->value],
                'label' => 'iluminación/LED',
            ],
            [
                'pattern' => '/\bfuente(?:\s+de\s+alimentaci[oó]n)?\b/iu',
                'types' => [SernaItemType::Fuente->value, SernaItemType::Iluminacion->value],
                'label' => 'fuente de alimentación',
            ],
            [
                'pattern' => '/\b(transporte|instalaci[oó]n)\b/iu',
                'types' => [SernaItemType::PrecioFijo->value],
                'label' => 'transporte/instalación',
            ],
            [
                'pattern' => '/\bespejo\b/iu',
                'types' => [SernaItemType::Espejo->value],
                'label' => 'acabado espejo',
            ],
            [
                'pattern' => '/\benchapado\b/iu',
                'types' => [SernaItemType::Enchapado->value],
                'label' => 'enchapado',
            ],
            [
                // «lámina de color» ≠ lámina entera; exige «entera/completa».
                'pattern' => '/\bl[aá]mina\s+(entera|completa)\b|\bplancha\s+(entera|completa)\b|\bhoja\s+entera\b/iu',
                'types' => [SernaItemType::LaminaEntera->value],
                'label' => 'lámina entera',
            ],
            [
                'pattern' => '/\b(cantonera|letra|terminado|3d)\b/iu',
                'types' => [SernaItemType::Terminado->value],
                'label' => 'terminado/cantonera/letras',
            ],
            [
                // No usar «corte» solo: dispara en casi cualquier texto.
                // Terminado/enchapado/espejo son el flujo habitual de avisos en acrílico.
                'pattern' => '/\b(acr[ií]lico|corte\s*l[aá]ser)\b/iu',
                'types' => [
                    SernaItemType::CorteLaser->value,
                    SernaItemType::ManoObra->value,
                    SernaItemType::Terminado->value,
                    SernaItemType::Enchapado->value,
                    SernaItemType::Espejo->value,
                    SernaItemType::LaminaEntera->value,
                    SernaItemType::ProductoCatalogo->value,
                ],
                'label' => 'acrílico/corte',
                'also_material' => '/acr[ií]lico/iu',
            ],
        ];

        foreach ($checks as $check) {
            if (! preg_match($check['pattern'], $lower)) {
                continue;
            }

            $found = false;
            foreach ($check['types'] as $type) {
                if (isset($types[$type])) {
                    $found = true;
                    break;
                }
            }

            if (! $found && isset($check['also_material'])) {
                $found = $this->piecesMatchMaterial($pieces, $check['also_material']);
            }

            if (! $found) {
                $errors[] = "El texto menciona «{$check['label']}» pero no quedó ningún ítem de ese tipo en la cotización.";
            }
        }

        // Materiales / procesos fuera de catálogo Serna.
        $unknownPatterns = [
            '/\b(aluminio|acero|hierro|madera|mdf|pvc|policarbonato|vidrio|lona|banner|impresión\s+uv|cnc\b(?!\s*laser))/iu',
        ];

        foreach ($unknownPatterns as $pattern) {
            if (preg_match($pattern, $prompt, $m)) {
                $errors[] = 'Hay un material/proceso no reconocido en el texto: «'.trim($m[0]).'». No está en las tablas Serna del cotizador.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function itemErrors(array $item, string $pieceName, int $itemNumber): array
    {
        $errors = [];
        $type = SernaItemType::tryFrom((string) ($item['item_type'] ?? ''));
        $label = trim((string) ($item['material'] ?? '')) ?: ($type?->getLabel() ?? "Ítem {$itemNumber}");
        $prefix = "«{$pieceName}» / {$label}";

        if ($type === null) {
            return ["{$prefix}: tipo de ítem no válido."];
        }

        if ($type === SernaItemType::PrecioFijo) {
            $price = Money::parseInput($item['unit_price'] ?? null);
            if ($price === null || $price <= 0) {
                $errors[] = "{$prefix}: es valor manual (transporte/instalación) y falta el precio.";
            }
        }

        if (in_array($type, [SernaItemType::Iluminacion, SernaItemType::Fuente], true)
            && blank($item['lighting_option_id'] ?? null)) {
            $errors[] = "{$prefix}: falta seleccionar la opción de iluminación/fuente del catálogo.";
        }

        if ($type === SernaItemType::LaminaEntera && blank($item['sheet_price_id'] ?? null)) {
            $errors[] = "{$prefix}: falta seleccionar la lámina del catálogo.";
        }

        if ($type === SernaItemType::ProductoCatalogo && blank($item['catalog_product_id'] ?? null)) {
            $errors[] = "{$prefix}: falta seleccionar el producto de catálogo.";
        }

        if ($type->pricingMode() === 'per_cm2'
            && ! in_array($type, [SernaItemType::CorteLaser, SernaItemType::ManoObra], true)
            && blank($item['process_rate_id'] ?? null)) {
            $errors[] = "{$prefix}: falta seleccionar la tarifa cm².";
        }

        try {
            $payload = $item;
            $payload['pieza'] = $pieceName;
            $this->engine->calculateItem($payload);
        } catch (Throwable $exception) {
            $errors[] = "{$prefix}: ".$exception->getMessage();
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return array<string, true>
     */
    private function itemTypesInPieces(array $pieces): array
    {
        $types = [];

        foreach ($pieces as $piece) {
            if (! is_array($piece)) {
                continue;
            }

            foreach ($piece['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $type = (string) ($item['item_type'] ?? '');
                if ($type !== '') {
                    $types[$type] = true;
                }
            }
        }

        return $types;
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     */
    private function piecesMatchMaterial(array $pieces, string $pattern): bool
    {
        foreach ($pieces as $piece) {
            if (! is_array($piece)) {
                continue;
            }

            foreach ($piece['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $material = (string) ($item['material'] ?? '');
                if ($material !== '' && preg_match($pattern, $material)) {
                    return true;
                }
            }
        }

        return false;
    }
}
