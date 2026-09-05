<?php

namespace App\Services\Serna\Ai;

use App\Enums\SernaItemType;
use App\Models\SernaProcessRate;
use App\Support\Money;

class SernaQuoteDraftNormalizer
{
    /**
     * @param  array<string, mixed>  $draft
     * @return array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string}
     */
    public function normalize(array $draft, string $source = 'ai'): array
    {
        $project = $this->upper((string) ($draft['project_name'] ?? $draft['proyecto'] ?? ''));
        $piecesInput = $draft['pieces'] ?? $draft['piezas'] ?? [];
        if (! is_array($piecesInput)) {
            $piecesInput = [];
        }

        $pieces = [];

        foreach ($piecesInput as $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $pieceName = $this->upper((string) ($piece['name'] ?? $piece['pieza'] ?? $project ?: 'PIEZA'));
            $itemsInput = $piece['items'] ?? [];
            if (! is_array($itemsInput)) {
                $itemsInput = [];
            }

            $items = [];
            foreach ($itemsInput as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $normalized = $this->normalizeItem($item);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }

            if ($items === []) {
                continue;
            }

            $pieces[] = [
                'name' => $pieceName !== '' ? $pieceName : 'PIEZA',
                'items' => $items,
            ];
        }

        if ($pieces === [] && $project !== '') {
            $pieces[] = [
                'name' => $project,
                'items' => [$this->defaultLaserItem()],
            ];
        }

        $unrecognized = [];
        foreach ($draft['unrecognized'] ?? $draft['no_reconocido'] ?? [] as $row) {
            $text = trim((string) $row);
            if ($text !== '') {
                $unrecognized[] = $text;
            }
        }

        $explanation = isset($draft['explanation']) ? trim((string) $draft['explanation']) : null;

        return [
            'project_name' => $project !== '' ? $project : null,
            'contact_name' => $this->upperNullable($draft['contact_name'] ?? $draft['contacto'] ?? null),
            'notes' => isset($draft['notes']) ? trim((string) $draft['notes']) : null,
            'payment_form' => $this->upperNullable($draft['payment_form'] ?? $draft['forma_pago'] ?? null),
            'pieces' => $pieces,
            'unrecognized' => array_values(array_unique($unrecognized)),
            'explanation' => $explanation !== '' ? $explanation : null,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $item): ?array
    {
        $type = $this->resolveType($item['item_type'] ?? $item['tipo'] ?? null);
        if ($type === null) {
            return null;
        }

        $material = $this->upper((string) ($item['material'] ?? ''));
        $acabados = $this->sanitizeAcabados(
            trim((string) ($item['acabados'] ?? $item['descripcion'] ?? '')),
            $type,
        );
        $quantity = max(1, (int) ($item['quantity'] ?? $item['cantidad'] ?? 1));
        $width = $this->floatOrNull($item['width_cm'] ?? $item['x'] ?? $item['medida_x'] ?? null);
        $height = $this->floatOrNull($item['height_cm'] ?? $item['y'] ?? $item['medida_y'] ?? null);
        $thickness = $this->floatOrNull($item['thickness_mm'] ?? $item['calibre'] ?? null);
        $unitPrice = Money::parseInput($item['unit_price'] ?? $item['valor_unitario'] ?? null);

        if ($material === '') {
            $material = match ($type) {
                SernaItemType::CorteLaser, SernaItemType::ManoObra, SernaItemType::Terminado => $thickness
                    ? 'ACRILICO '.$this->n($thickness).'MM'
                    : 'ACRILICO',
                SernaItemType::Vinilo => 'VINILO ADHESIVO',
                SernaItemType::Plotter => 'PLOTTER',
                SernaItemType::PrecioFijo => 'INSTALACION',
                default => $type->getLabel(),
            };
        }

        $processRateId = null;
        if ($type->pricingMode() === 'per_cm2') {
            $processRateId = $this->resolveProcessRateId($type, $item, $thickness);
        }

        $sheetPriceId = filled($item['sheet_price_id'] ?? null) ? (int) $item['sheet_price_id'] : null;
        $catalogProductId = filled($item['catalog_product_id'] ?? null) ? (int) $item['catalog_product_id'] : null;
        $lightingOptionId = filled($item['lighting_option_id'] ?? null) ? (int) $item['lighting_option_id'] : null;
        $sku = filled($item['sku'] ?? null) ? trim((string) $item['sku']) : null;
        $format = filled($item['format'] ?? null) ? trim((string) $item['format']) : null;
        $finish = filled($item['finish'] ?? null) ? trim((string) $item['finish']) : null;

        return [
            'item_type' => $type->value,
            'material' => $material,
            'acabados' => $acabados,
            'thickness_mm' => $thickness,
            'width_cm' => $width,
            'height_cm' => $height,
            'quantity' => $quantity,
            'process_rate_id' => $processRateId,
            'process_rate_code' => $item['process_rate_code'] ?? null,
            'sheet_price_id' => $sheetPriceId,
            'catalog_product_id' => $catalogProductId,
            'lighting_option_id' => $lightingOptionId,
            'lighting_name' => $item['lighting_name'] ?? null,
            'sku' => $sku,
            'format' => $format,
            'finish' => $finish,
            // unit_price solo para precio fijo explícito; el binder/tablas pisan el resto.
            'unit_price' => $type === SernaItemType::PrecioFijo && $unitPrice !== null
                ? Money::formatInputState((int) round($unitPrice))
                : null,
        ];
    }

    private function resolveType(mixed $value): ?SernaItemType
    {
        if ($value instanceof SernaItemType) {
            return $value;
        }

        $raw = mb_strtolower(trim((string) $value), 'UTF-8');
        if ($raw === '') {
            return null;
        }

        $enum = SernaItemType::tryFrom($raw);
        if ($enum) {
            return $enum;
        }

        return match (true) {
            str_contains($raw, 'fuente') || str_contains($raw, 'psu') || str_contains($raw, 'power') => SernaItemType::Fuente,
            str_contains($raw, 'ilumin') || str_contains($raw, 'luz') || str_contains($raw, 'led') || str_contains($raw, 'backlight') || str_contains($raw, 'neon') || str_contains($raw, 'neón') => SernaItemType::Iluminacion,
            str_contains($raw, 'laser') || str_contains($raw, 'láser') || str_contains($raw, 'corte') => SernaItemType::CorteLaser,
            str_contains($raw, 'mano') || str_contains($raw, 'obra') => SernaItemType::ManoObra,
            str_contains($raw, 'canton') || str_contains($raw, 'letra') || str_contains($raw, 'termin') || str_contains($raw, '3d') || str_contains($raw, 'caja') => SernaItemType::Terminado,
            str_contains($raw, 'vinilo') => SernaItemType::Vinilo,
            str_contains($raw, 'plotter') => SernaItemType::Plotter,
            str_contains($raw, 'espejo') => SernaItemType::Espejo,
            str_contains($raw, 'enchap') => SernaItemType::Enchapado,
            str_contains($raw, 'lamina') || str_contains($raw, 'lámina') => SernaItemType::LaminaEntera,
            str_contains($raw, 'instal') || str_contains($raw, 'fijo') || str_contains($raw, 'transporte') => SernaItemType::PrecioFijo,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveProcessRateId(SernaItemType $type, array $item, ?float $thickness): ?int
    {
        if (filled($item['process_rate_id'] ?? null)) {
            return (int) $item['process_rate_id'];
        }

        if (filled($item['process_rate_code'] ?? null)) {
            $id = SernaProcessRate::query()->active()->where('code', $item['process_rate_code'])->value('id');

            return $id ? (int) $id : null;
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

        $query = SernaProcessRate::query()->active()->category($category)->ordered();

        if (in_array($type, [SernaItemType::CorteLaser, SernaItemType::ManoObra], true) && $thickness !== null) {
            $rate = $query->get()->first(fn (SernaProcessRate $row): bool => $row->appliesToThickness($thickness));

            return $rate?->id;
        }

        if ($type === SernaItemType::Terminado) {
            $acabados = mb_strtolower((string) ($item['acabados'] ?? ''), 'UTF-8');
            $code = match (true) {
                str_contains($acabados, 'pestaña') || str_contains($acabados, 'pestana') => 'letra_pestana',
                str_contains($acabados, 'curva') && str_contains($acabados, 'tapa') => 'letra_curva_con_tapa',
                str_contains($acabados, 'recta') && str_contains($acabados, 'tapa') => 'letra_recta_con_tapa',
                str_contains($acabados, 'curva') => 'letra_curva_sin_tapa',
                str_contains($acabados, 'caja') || str_contains($acabados, 'canton') => 'caja_cantonera',
                default => 'letra_recta_sin_tapa',
            };

            return SernaProcessRate::query()->where('code', $code)->value('id')
                ?? $query->value('id');
        }

        if ($type === SernaItemType::Vinilo) {
            $material = mb_strtolower((string) ($item['material'] ?? ''), 'UTF-8');
            $code = str_contains($material, 'instal') ? 'vinilo_instalado' : 'vinilo_adhesivo';

            return SernaProcessRate::query()->where('code', $code)->value('id')
                ?? $query->value('id');
        }

        return $query->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLaserItem(): array
    {
        return [
            'item_type' => SernaItemType::CorteLaser->value,
            'material' => 'ACRILICO 3MM',
            'acabados' => '',
            'thickness_mm' => 3,
            'width_cm' => 100,
            'height_cm' => 50,
            'quantity' => 1,
            'process_rate_id' => SernaProcessRate::query()->where('code', 'laser_2_4')->value('id'),
            'sheet_price_id' => null,
            'catalog_product_id' => null,
            'unit_price' => null,
        ];
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

    /**
     * Evita que el pedido completo del asistente IA quede pegado en acabados.
     */
    private function sanitizeAcabados(string $acabados, SernaItemType $type): string
    {
        $acabados = trim($acabados);
        if ($acabados === '') {
            return '';
        }

        $upper = mb_strtoupper($acabados, 'UTF-8');
        $hits = 0;
        foreach (['CONTACTO', 'CONTADO', 'ANTICIPO', 'VINILO', 'TRANSPORTE', 'INSTALACION', 'LED', 'BACKLIGHT'] as $token) {
            if (str_contains($upper, $token)) {
                $hits++;
            }
        }

        $looksLikePromptDump = $hits >= 2 || mb_strlen($acabados) > 160;

        if (! $looksLikePromptDump) {
            return $this->upper(mb_substr($acabados, 0, 120, 'UTF-8'));
        }

        return match ($type) {
            SernaItemType::Terminado => 'TERMINADO SEGÚN DISEÑO APROBADO',
            SernaItemType::CorteLaser => 'CORTE LÁSER SEGÚN DISEÑO APROBADO',
            SernaItemType::Vinilo => 'VINILO SEGÚN DISEÑO APROBADO',
            SernaItemType::Iluminacion => 'ILUMINACION SEGÚN DISEÑO',
            SernaItemType::PrecioFijo => 'VALOR MANUAL',
            default => 'SEGÚN DISEÑO APROBADO',
        };
    }

    private function upper(string $value): string
    {
        return mb_strtoupper(trim($value), 'UTF-8');
    }

    private function upperNullable(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->upper((string) $value);
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
