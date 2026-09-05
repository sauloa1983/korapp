<?php

namespace App\Services\Serna\Ai;

/**
 * Parser local sin API: extrae proyecto, piezas y tipos enlazados a Lista Serna 2026.
 */
class SernaHeuristicQuoteParser
{
    public function __construct(
        private readonly SernaQuoteDraftNormalizer $normalizer,
        private readonly SernaCatalogMatcher $catalog,
    ) {}

    /**
     * @return array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string}
     */
    public function parse(string $prompt): array
    {
        $text = trim($prompt);
        $project = $this->extractProject($text);
        $contact = $this->extractContact($text);
        $payment = $this->extractPayment($text);

        $segments = $this->splitPieces($text, $project);
        $pieces = [];

        foreach ($segments as $segment) {
            $pieceName = $segment['name'];
            $body = $segment['body'];
            $items = $this->extractItems($body);

            if ($items === []) {
                $dims = $this->extractDimensions($body);
                $thickness = $this->extractThickness($body) ?? 3.0;
                $type = 'corte_laser';
                $items[] = [
                    'item_type' => $type,
                    'material' => 'ACRILICO '.$this->n($thickness).'MM',
                    'acabados' => $this->extractAcabados($body, $type),
                    'thickness_mm' => $thickness,
                    'width_cm' => $dims['x'] ?? 100,
                    'height_cm' => $dims['y'] ?? 50,
                    'quantity' => $this->extractQuantity($body),
                    'process_rate_code' => $this->catalog->resolveProcessRateCode($type, $body, $thickness),
                ];
            }

            $pieces[] = [
                'name' => $pieceName,
                'items' => $items,
            ];
        }

        return $this->normalizer->normalize([
            'project_name' => $project,
            'contact_name' => $contact,
            'payment_form' => $payment,
            'notes' => $this->extractNotes($text),
            'pieces' => $pieces,
            'explanation' => 'Estructura armada con parser local sobre Lista Serna 2026 (tarifas, láminas, productos, iluminación).',
        ], source: 'heuristic');
    }

    private function extractProject(string $text): string
    {
        if (preg_match('/proyecto\s*[:\-]?\s*(.+)$/imu', $text, $m)) {
            return mb_strtoupper(trim(explode("\n", $m[1])[0]), 'UTF-8');
        }

        if (preg_match('/\b(aviso|letrero|caja|fachada|proyecto)\b[^\n,]{0,80}/iu', $text, $m)) {
            return mb_strtoupper(trim($m[0]), 'UTF-8');
        }

        $first = trim(explode("\n", $text)[0] ?? '');

        return mb_strtoupper(mb_substr($first, 0, 80, 'UTF-8'), 'UTF-8');
    }

    private function extractContact(string $text): ?string
    {
        if (preg_match('/contacto\s*[:\-]?\s*([^\n,.]+)/iu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function extractPayment(string $text): ?string
    {
        if (preg_match('/contado/iu', $text)) {
            return 'CONTADO';
        }
        if (preg_match('/(\d{1,3})\s*%\s*anticipo/iu', $text, $m)) {
            return $m[1].'% ANTICIPO';
        }

        return null;
    }

    private function extractNotes(string $text): ?string
    {
        if (preg_match('/observacion(?:es)?\s*[:\-]?\s*(.+)$/isu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @return list<array{name: string, body: string}>
     */
    private function splitPieces(string $text, string $fallbackName): array
    {
        if (preg_match_all('/pieza\s*(\d+)?\s*[:\-]?\s*([^\n]+)/iu', $text, $matches, PREG_OFFSET_CAPTURE) && count($matches[0]) > 1) {
            $segments = [];
            $count = count($matches[0]);

            for ($i = 0; $i < $count; $i++) {
                $start = $matches[0][$i][1];
                $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($text);
                $name = mb_strtoupper(trim($matches[2][$i][0]), 'UTF-8');
                $body = substr($text, $start, $end - $start);
                $segments[] = ['name' => $name !== '' ? $name : 'PIEZA '.($i + 1), 'body' => $body];
            }

            return $segments;
        }

        return [[
            'name' => $fallbackName !== '' ? $fallbackName : 'PIEZA 1',
            'body' => $text,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractItems(string $body): array
    {
        $items = [];
        $lower = mb_strtolower($body, 'UTF-8');
        $dims = $this->extractDimensions($body);
        $thickness = $this->extractThickness($body) ?? 3.0;
        $qty = $this->extractQuantity($body);

        foreach ($this->catalog->matchCatalogProducts($body) as $productItem) {
            $productItem['quantity'] = $qty;
            $items[] = $productItem;
        }

        $catalogOnly = $this->catalog->mentionsCatalogOnlyWork($body);
        $fullSheet = $this->catalog->mentionsFullSheet($body);

        if ($fullSheet) {
            $sheet = $this->catalog->matchSheetHints($body, $thickness, $dims['x'], $dims['y']);
            $items[] = [
                'item_type' => 'lamina_entera',
                'material' => 'ACRILICO '.$this->n($sheet['thickness_mm']).'MM',
                'acabados' => $this->extractAcabados($body, 'lamina_entera'),
                'thickness_mm' => $sheet['thickness_mm'],
                'width_cm' => $dims['x'],
                'height_cm' => $dims['y'],
                'quantity' => $qty,
                'format' => $sheet['format'],
                'finish' => $sheet['finish'],
                'sheet_price_id' => $sheet['sheet_price_id'],
            ];
        }

        // Cada proceso Serna es independiente: no usar elseif ni omitirlos por «lámina entera».
        if (! $catalogOnly) {
            if (preg_match('/\benchapado\b/iu', $body)) {
                $local = $this->dimensionsNear($body, 'enchapado') ?? $dims;
                $items[] = $this->processItem('enchapado', $body, $thickness, $local, $qty, 'ENCHAPADO');
            }

            if (preg_match('/\bespejo\b/iu', $body)) {
                $local = $this->dimensionsNear($body, 'espejo') ?? $dims;
                $items[] = $this->processItem('espejo', $body, $thickness, $local, $qty, 'ESPEJO');
            }

            if (preg_match('/mano\s+de\s+obra/iu', $body)) {
                $local = $this->dimensionsNear($body, 'mano\s+de\s+obra') ?? $dims;
                $items[] = $this->processItem(
                    'mano_obra',
                    $body,
                    $thickness,
                    $local,
                    $qty,
                    'ACRILICO '.$this->n($thickness).'MM',
                );
            }

            $wantsLetters = (bool) preg_match('/\b(3d|canton|letras?|caja)\b/iu', $body);
            $wantsLaser = (bool) preg_match('/corte\s*l[aá]ser/iu', $body);
            $wantsAcrylic = (bool) preg_match('/acr[ií]lico|aviso|fachada|letrero/iu', $body);

            if ($wantsLetters) {
                $local = $this->dimensionsNear($body, 'letras?|3d|canton|caja') ?? $dims;
                $letterQty = $this->extractQuantityNear($body, 'letras?') ?? $qty;
                $items[] = $this->processItem(
                    'terminado',
                    $body,
                    $thickness,
                    $local,
                    $letterQty,
                    'ACRILICO '.$this->n($thickness).'MM',
                );
            }

            if ($wantsLaser || ($wantsAcrylic && ! $fullSheet && ! $wantsLetters)) {
                $local = $this->dimensionsNear($body, 'corte\s*l[aá]ser|aviso|acr[ií]lico') ?? $dims;
                $items[] = $this->processItem(
                    'corte_laser',
                    $body,
                    $thickness,
                    $local,
                    $qty,
                    'ACRILICO '.$this->n($thickness).'MM',
                );
            }
        }

        if (str_contains($lower, 'vinilo')) {
            $type = 'vinilo';
            $items[] = [
                'item_type' => $type,
                'material' => str_contains($lower, 'instal') ? 'VINILO INSTALADO' : 'VINILO ADHESIVO',
                'acabados' => 'VINILO SEGÚN DISEÑO APROBADO',
                'width_cm' => $dims['x'] ?? 100,
                'height_cm' => $dims['y'] ?? 50,
                'quantity' => $qty,
                'process_rate_code' => $this->catalog->resolveProcessRateCode($type, $body),
            ];
        }

        if (str_contains($lower, 'plotter')) {
            $type = 'plotter';
            $items[] = [
                'item_type' => $type,
                'material' => 'PLOTTER',
                'acabados' => 'CORTE PLOTTER SEGÚN ARTE',
                'width_cm' => $dims['x'] ?? 50,
                'height_cm' => $dims['y'] ?? 30,
                'quantity' => $qty,
                'process_rate_code' => $this->catalog->resolveProcessRateCode($type, $body),
            ];
        }

        $lighting = $this->catalog->matchLightingOption($body);
        if ($lighting) {
            $items[] = [
                'item_type' => 'iluminacion',
                'material' => mb_strtoupper($lighting->name, 'UTF-8'),
                'acabados' => 'ILUMINACION '.$lighting->name,
                'lighting_name' => $lighting->name,
                'lighting_option_id' => $lighting->id,
                'width_cm' => $dims['x'] ?? null,
                'height_cm' => $dims['y'] ?? null,
                'quantity' => $qty,
            ];
        }

        if (preg_match('/instalaci[oó]n|transporte/iu', $body)) {
            $price = null;
            if (preg_match('/(?:instalaci[oó]n|transporte)[^\d$]{0,40}\$?\s*([\d]{1,3}(?:\.\d{3})+|\d{4,})/iu', $body, $m)
                || preg_match('/\$\s*([\d]{1,3}(?:\.\d{3})+|\d{4,})[^\d]{0,20}(?:instalaci[oó]n|transporte)/iu', $body, $m)) {
                $candidate = (float) str_replace('.', '', $m[1]);
                if ($candidate >= 10000) {
                    $price = $candidate;
                }
            }

            $hasTransporte = (bool) preg_match('/transporte/iu', $body);
            $hasInstalacion = (bool) preg_match('/instalaci[oó]n/iu', $body);

            if ($hasTransporte && $hasInstalacion) {
                $items[] = [
                    'item_type' => 'precio_fijo',
                    'material' => 'TRANSPORTE E INSTALACION',
                    'acabados' => 'VALOR MANUAL — completar en cotizador',
                    'width_cm' => 1,
                    'height_cm' => 1,
                    'quantity' => 1,
                    'unit_price' => $price,
                ];
            } else {
                $items[] = [
                    'item_type' => 'precio_fijo',
                    'material' => $hasTransporte ? 'TRANSPORTE' : 'INSTALACION',
                    'acabados' => $hasTransporte
                        ? 'TRANSPORTE — valor manual'
                        : 'INSTALACION EN SITIO — valor manual',
                    'width_cm' => 1,
                    'height_cm' => 1,
                    'quantity' => 1,
                    'unit_price' => $price,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array{x: ?float, y: ?float}
     */
    private function extractDimensions(string $text): array
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:x|×|\*)\s*(\d+(?:[.,]\d+)?)\s*(?:cm)?/iu', $text, $m)) {
            return [
                'x' => (float) str_replace(',', '.', $m[1]),
                'y' => (float) str_replace(',', '.', $m[2]),
            ];
        }

        return ['x' => null, 'y' => null];
    }

    /**
     * @return array{x: ?float, y: ?float}|null
     */
    private function dimensionsNear(string $body, string $keywordPattern): ?array
    {
        $pattern = '/(?:'.$keywordPattern.').{0,48}?(\d+(?:[.,]\d+)?)\s*(?:x|×|\*)\s*(\d+(?:[.,]\d+)?)/iu';
        if (preg_match($pattern, $body, $m)) {
            return [
                'x' => (float) str_replace(',', '.', $m[1]),
                'y' => (float) str_replace(',', '.', $m[2]),
            ];
        }

        $patternBefore = '/(\d+(?:[.,]\d+)?)\s*(?:x|×|\*)\s*(\d+(?:[.,]\d+)?).{0,48}?(?:'.$keywordPattern.')/iu';
        if (preg_match($patternBefore, $body, $m)) {
            return [
                'x' => (float) str_replace(',', '.', $m[1]),
                'y' => (float) str_replace(',', '.', $m[2]),
            ];
        }

        return null;
    }

    private function extractQuantityNear(string $body, string $keywordPattern): ?int
    {
        if (preg_match('/(\d+)\s*(?:und|unidades|pcs)?\s*(?:'.$keywordPattern.')/iu', $body, $m)
            || preg_match('/(?:'.$keywordPattern.')\s*(?:de\s+)?(\d+)\s*(?:und|unidades|pcs)?/iu', $body, $m)) {
            return max(1, (int) $m[1]);
        }

        return null;
    }

    /**
     * @param  array{x: ?float, y: ?float}  $dims
     * @return array<string, mixed>
     */
    private function processItem(
        string $type,
        string $body,
        float $thickness,
        array $dims,
        int $qty,
        string $material,
    ): array {
        return [
            'item_type' => $type,
            'material' => $material,
            'acabados' => $this->extractAcabados($body, $type),
            'thickness_mm' => $thickness,
            'width_cm' => $dims['x'] ?? 100,
            'height_cm' => $dims['y'] ?? 50,
            'quantity' => $qty,
            'process_rate_code' => $this->catalog->resolveProcessRateCode($type, $body, $thickness),
        ];
    }

    private function extractThickness(string $text): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mm/iu', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function extractQuantity(string $text): int
    {
        if (preg_match('/(\d+)\s*(?:und|unidades|pcs|piezas?)\b/iu', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    /**
     * Acabados cortos del ítem (color/canto/acabado). Nunca el texto completo del asistente.
     */
    private function extractAcabados(string $body, string $itemType): string
    {
        $lower = mb_strtolower($body, 'UTF-8');
        $hints = [];

        $finishMap = [
            'cristal' => 'CRISTAL',
            'ópalo' => 'OPAL',
            'opalo' => 'OPAL',
            'opal' => 'OPAL',
            'mate' => 'MATE',
            'negro' => 'NEGRO',
            'blanco' => 'BLANCO',
            'transparente' => 'TRANSPARENTE',
            'humo' => 'HUMO',
            'rojo' => 'ROJO',
            'azul' => 'AZUL',
            'verde' => 'VERDE',
            'amarillo' => 'AMARILLO',
            'plata' => 'PLATA',
            'dorado' => 'DORADO',
            'bronce' => 'BRONCE',
            'dos colores' => 'DOS COLORES',
            'estampad' => 'ESTAMPADO',
            'cantoner' => 'CANTONERA',
            'pestaña' => 'PESTAÑA',
            'pestana' => 'PESTAÑA',
            'con tapa' => 'CON TAPA',
            'sin tapa' => 'SIN TAPA',
            'curva' => 'CURVA',
            'recta' => 'RECTA',
            '3d' => '3D',
            'fachada' => 'FACHADA',
        ];

        // "espejo" solo como hint si el ítem ya es espejo; si no, evita contaminar acabados de corte.
        if ($itemType === 'espejo') {
            $finishMap['espejo'] = 'ESPEJO';
        }

        foreach ($finishMap as $needle => $label) {
            if (str_contains($lower, $needle)) {
                $hints[$label] = $label;
            }
        }

        if ($hints !== []) {
            return implode(' · ', array_values($hints));
        }

        return match ($itemType) {
            'terminado' => 'TERMINADO SEGÚN DISEÑO APROBADO',
            'corte_laser' => 'CORTE LÁSER SEGÚN DISEÑO APROBADO',
            'lamina_entera' => 'LÁMINA ENTERA LISTA SERNA',
            'espejo' => 'ACABADO ESPEJO',
            'enchapado' => 'ENCHAPADO',
            'mano_obra' => 'MANO DE OBRA',
            default => 'SEGÚN DISEÑO APROBADO',
        };
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
