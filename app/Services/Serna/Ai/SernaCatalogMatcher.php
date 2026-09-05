<?php

namespace App\Services\Serna\Ai;

use App\Enums\AcrylicLightingPricingMode;
use App\Enums\SernaSheetFinish;
use App\Models\AcrylicLightingOption;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;

/**
 * Enlaza texto libre a elementos activos de Lista Serna 2026
 * (tarifas cm², láminas, productos e iluminación).
 */
class SernaCatalogMatcher
{
    /**
     * @return list<array<string, mixed>>
     */
    public function matchCatalogProducts(string $body): array
    {
        $lower = mb_strtolower($body, 'UTF-8');
        $items = [];
        $seen = [];

        $products = SernaCatalogProduct::query()
            ->active()
            ->ordered()
            ->get();

        foreach ($products as $product) {
            if (str_starts_with(mb_strtoupper($product->sku, 'UTF-8'), 'SERNA-')) {
                continue;
            }

            $score = $this->productScore($lower, $product);
            if ($score < 40) {
                continue;
            }

            if (isset($seen[$product->id])) {
                continue;
            }
            $seen[$product->id] = true;

            $items[] = [
                'item_type' => 'producto_catalogo',
                'material' => mb_strtoupper($product->category, 'UTF-8'),
                'acabados' => $product->name,
                'sku' => $product->sku,
                'catalog_product_id' => $product->id,
                'quantity' => 1,
                'width_cm' => isset($product->specs['width_cm']) ? (float) $product->specs['width_cm'] : 1,
                'height_cm' => isset($product->specs['height_cm']) ? (float) $product->specs['height_cm'] : 1,
                'thickness_mm' => isset($product->specs['thickness_mm']) ? (float) $product->specs['thickness_mm'] : null,
            ];
        }

        return $items;
    }

    public function matchLightingOption(string $body): ?AcrylicLightingOption
    {
        $lower = mb_strtolower($body, 'UTF-8');

        if (! preg_match('/\b(led|backlight|ne[oó]n|iluminaci[oó]n|luces?|fuente)\b/iu', $lower)) {
            return null;
        }

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

            if (str_contains($lower, $name)) {
                $score += 80;
            }
            if (str_contains($lower, 'backlight') && str_contains($name, 'backlight')) {
                $score += 60;
            }
            if ((str_contains($lower, 'perimetral') || str_contains($lower, 'modulo') || str_contains($lower, 'módulo'))
                && str_contains($name, 'perimetral')) {
                $score += 60;
            }
            if ((str_contains($lower, 'neon') || str_contains($lower, 'neón') || str_contains($lower, 'flex'))
                && (str_contains($name, 'neón') || str_contains($name, 'neon'))) {
                $score += 60;
            }
            if (str_contains($lower, 'led') && str_contains($name, 'led')) {
                $score += 30;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $option;
            }
        }

        if ($bestScore >= 20) {
            return $best;
        }

        return $options->firstWhere('name', 'LED perimetral / módulos') ?? $options->first();
    }

    /**
     * @return array{format: ?string, finish: string, thickness_mm: float, sheet_price_id: ?int}
     */
    public function matchSheetHints(string $body, ?float $thickness = null, ?float $width = null, ?float $height = null): array
    {
        $lower = mb_strtolower($body, 'UTF-8');
        $thickness ??= $this->extractThickness($body) ?? 3.0;

        $format = null;
        if (preg_match('/\b(120\s*[x×]\s*180|130\s*[x×]\s*190|120\s*[x×]\s*240|150\s*[x×]\s*250|180\s*[x×]\s*260|180\s*[x×]\s*300)\b/iu', $body, $m)) {
            $format = preg_replace('/\s+/u', '', str_replace(['×', 'X'], 'x', mb_strtolower($m[1], 'UTF-8')));
        } elseif ($width && $height) {
            $candidate = $this->n($width).'x'.$this->n($height);
            if (SernaSheetPrice::query()->active()->where('format', $candidate)->exists()) {
                $format = $candidate;
            }
        }

        $finish = match (true) {
            str_contains($lower, 'estamp') || str_contains($lower, '2 color') || str_contains($lower, 'dos color') => SernaSheetFinish::DosColoresEstampada->value,
            str_contains($lower, 'color') && ! str_contains($lower, 'cristal') && ! str_contains($lower, 'opal') => SernaSheetFinish::Color->value,
            default => SernaSheetFinish::CristalOpal->value,
        };

        $query = SernaSheetPrice::query()->active()->where('thickness_mm', $thickness)->where('finish', $finish);
        if ($format) {
            $query->where('format', $format);
        }

        $sheet = $query->ordered()->first()
            ?? SernaSheetPrice::query()
                ->active()
                ->where('thickness_mm', $thickness)
                ->where('finish', $finish)
                ->where('format', '120x180')
                ->first()
            ?? SernaSheetPrice::query()->active()->where('thickness_mm', $thickness)->ordered()->first();

        return [
            'format' => $sheet?->format ?? $format ?? '120x180',
            'finish' => $finish,
            'thickness_mm' => $thickness,
            'sheet_price_id' => $sheet?->id,
        ];
    }

    public function resolveProcessRateCode(string $itemType, string $body, ?float $thickness = null): ?string
    {
        $lower = mb_strtolower($body, 'UTF-8');

        $preferred = match ($itemType) {
            'corte_laser' => null,
            'mano_obra' => null,
            'terminado' => match (true) {
                str_contains($lower, 'pestaña') || str_contains($lower, 'pestana') => 'letra_pestana',
                str_contains($lower, 'curva') && str_contains($lower, 'tapa') => 'letra_curva_con_tapa',
                str_contains($lower, 'recta') && str_contains($lower, 'tapa') => 'letra_recta_con_tapa',
                str_contains($lower, 'curva') => 'letra_curva_sin_tapa',
                str_contains($lower, 'caja') || str_contains($lower, 'canton') => 'caja_cantonera',
                default => 'letra_recta_sin_tapa',
            },
            'vinilo' => str_contains($lower, 'instal') ? 'vinilo_instalado' : 'vinilo_adhesivo',
            'plotter' => str_contains($lower, 'instal') ? 'plotter_instalado' : 'plotter',
            'espejo' => (str_contains($lower, 'rosa') || str_contains($lower, 'rojo') || str_contains($lower, 'bronce'))
                ? 'espejo_rosa_rojo_bronce'
                : 'espejo_plata_dorado',
            'enchapado' => 'enchapado',
            default => null,
        };

        if ($preferred !== null) {
            $exists = SernaProcessRate::query()->active()->where('code', $preferred)->exists();

            return $exists ? $preferred : null;
        }

        if (! in_array($itemType, ['corte_laser', 'mano_obra'], true)) {
            return null;
        }

        $thickness ??= $this->extractThickness($body) ?? 3.0;
        $category = $itemType === 'mano_obra' ? 'mano_obra' : 'corte_laser';

        $rate = SernaProcessRate::query()
            ->active()
            ->category($category)
            ->ordered()
            ->get()
            ->first(fn (SernaProcessRate $row): bool => $row->appliesToThickness($thickness));

        return $rate?->code;
    }

    public function mentionsFullSheet(string $body): bool
    {
        return (bool) preg_match('/l[aá]mina\s+entera|plancha\s+entera|l[aá]mina\s+completa|hoja\s+entera/iu', $body);
    }

    public function mentionsCatalogOnlyWork(string $body): bool
    {
        $lower = mb_strtolower($body, 'UTF-8');

        $catalogCue = preg_match('/cubrealfombra|cuna|cono|plantilla|sku\b|cubre-/iu', $lower);
        $customCue = preg_match('/\b(aviso|fachada|letrero|corte\s*l[aá]ser|letras?\b|3d|cantoner|l[aá]mina\s+entera)\b/iu', $lower);

        return $catalogCue && ! $customCue;
    }

    private function productScore(string $lower, SernaCatalogProduct $product): int
    {
        $sku = mb_strtolower($product->sku, 'UTF-8');
        $name = mb_strtolower($product->name, 'UTF-8');
        $category = mb_strtolower($product->category, 'UTF-8');
        $score = 0;

        if (str_contains($lower, $sku)) {
            $score += 100;
        }
        if (str_contains($lower, $name)) {
            $score += 80;
        }
        if ($category === 'cubrealfombra' && str_contains($lower, 'cubrealfombra')) {
            $score += 10;
            if (str_contains($lower, 'presiden') && str_contains($name, 'presidente')) {
                $score += 50;
            } elseif (str_contains($lower, 'sub') && str_contains($name, 'sub')) {
                $score += 50;
            } elseif (str_contains($lower, 'gerente') && str_contains($name, 'gerente') && ! str_contains($name, 'sub')) {
                $score += 50;
            } elseif ((str_contains($lower, 'secret') || str_contains($lower, 'secretaria')) && str_contains($name, 'secret')) {
                $score += 50;
            }
        }
        if ($category === 'cuna' && preg_match('/\bcuna\b/u', $lower)) {
            $score += 50;
        }
        if ($category === 'cono' && preg_match('/\bcono\b/u', $lower)) {
            $score += 50;
        }
        if (str_contains($lower, 'plantilla') && str_contains($name, 'plantilla')) {
            $score += 60;
        }

        return $score;
    }

    private function extractThickness(string $text): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mm/iu', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
