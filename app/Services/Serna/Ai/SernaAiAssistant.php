<?php

namespace App\Services\Serna\Ai;

use App\Enums\AcrylicLightingPricingMode;
use App\Enums\SernaItemType;
use App\Models\AcrylicLightingOption;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;
use App\Services\Serna\SernaQuotationEngine;
use App\Support\Money;
use Throwable;

class SernaAiAssistant
{
    public function __construct(
        private readonly SernaAiClient $client,
        private readonly SernaHeuristicQuoteParser $heuristic,
        private readonly SernaQuoteDraftNormalizer $normalizer,
        private readonly SernaPriceTableBinder $tableBinder,
        private readonly SernaQuotationEngine $engine,
    ) {}

    public function aiEnabled(): bool
    {
        return $this->client->enabled();
    }

    /**
     * @param  list<array<string, mixed>>|null  $existingPieces
     * @return array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string, explanation?: string, pricing?: array<string, mixed>}
     */
    public function draftFromText(string $prompt, ?array $existingPieces = null, bool $append = false): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new \InvalidArgumentException('Escribe una descripción del proyecto para generar la cotización.');
        }

        $draft = null;

        if ($this->client->enabled()) {
            try {
                $catalog = $this->catalogHint();
                $modeHint = $append
                    ? "Modo APPEND: el usuario quiere AGREGAR piezas/ítems a una cotización existente. Devuelve solo las piezas/ítems NUEVOS."
                    : 'Modo REPLACE: genera la cotización completa.';

                $raw = $this->client->chatJson([
                    [
                        'role' => 'system',
                        'content' => <<<PROMPT
Eres un asistente que ARMA la estructura de una cotización usando EXCLUSIVAMENTE Lista Serna 2026.
NO inventes precios. Korapp calcula con: tarifas cm², láminas, productos de catálogo e iluminación/LED.
{$modeHint}
Devuelve SOLO JSON:
{
  "project_name": "string",
  "contact_name": "string|null",
  "payment_form": "CONTADO|50% ANTICIPO|null",
  "notes": "string|null",
  "pieces": [
    {
      "name": "NOMBRE PIEZA",
      "items": [
        {
          "item_type": "corte_laser|mano_obra|terminado|vinilo|plotter|espejo|enchapado|lamina_entera|producto_catalogo|precio_fijo|iluminacion|fuente",
          "material": "ACRILICO 3MM",
          "acabados": "solo acabado técnico corto del ÍTEM (color, canto, cristal/opal, etc). NUNCA pegues aquí el pedido completo ni contacto/pago/otros materiales",
          "thickness_mm": 3,
          "width_cm": 120,
          "height_cm": 80,
          "quantity": 1,
          "process_rate_code": "codigo_exacto_del_catalogo_o_null",
          "sheet_price_id": null,
          "lighting_option_id": null,
          "sku": "SKU_CATALOGO_O_NULL",
          "format": "120x180|null",
          "finish": "cristal_opal|color|dos_colores_estampada|null",
          "unit_price": null
        }
      ]
    }
  ],
  "explanation": "supuestos de estructura, sin inventar tarifas",
  "unrecognized": ["lista de pedidos/materiales del texto que NO pudiste mapear al catálogo"]
}
Reglas obligatorias:
- Elige process_rate_code / sheet_price_id / sku / lighting_option_id SOLO del catálogo Lista Serna 2026 enviado abajo.
- Lámina entera → item_type=lamina_entera + sheet_price_id (o format+finish+thickness del listado).
- Producto de catálogo (cubrealfombra, cuna, cono, plantilla…) → item_type=producto_catalogo + sku.
- Tarifas cm² (corte, terminado, vinilo, plotter, espejo, enchapado, mano de obra) → process_rate_code exacto.
- Si hay luces/LED/backlight/neón: item_type=iluminacion con lighting_option_id del listado. NO inventes precio de luz ni fuente.
- La fuente (PSU) se puede omitir: el sistema la agrega sola desde power_supply_cost.
- Si piden varias piezas, devuelve varias entradas en pieces.
- Si el pedido lista varios materiales separados por coma o "+" (acrílico, vinilo, LED, transporte…): crea UN ítem por material. Cada ítem tiene su propio "acabados" corto; NO copies el texto completo del pedido en acabados del primer ítem.
- "acabados" máximo ~80 caracteres: solo acabado/color/canto de ESE ítem. Prohibido incluir contacto, forma de pago, otros materiales o el prompt entero.
- Nunca inventes price_per_cm2 ni precios de lámina/catálogo/iluminación.
- Transporte e instalación NUNCA se calculan (ni por área, ni por fórmula, ni por tarifa).
  Si el pedido menciona transporte y/o instalación: SIEMPRE agrega item_type=precio_fijo
  con material TRANSPORTE / INSTALACION / TRANSPORTE E INSTALACION.
  unit_price SOLO si el vendedor escribió un valor explícito en el texto; si no, null
  para que lo escriba a mano en el cotizador.
- Si algo del pedido no encaja en Lista Serna 2026, NO lo inventes: ponlo en "unrecognized".
- Medidas en cm.
PROMPT
                    ],
                    [
                        'role' => 'user',
                        'content' => "Lista Serna 2026 (códigos/IDs oficiales; úsalos tal cual):\n{$catalog}\n\nPedido del vendedor:\n{$prompt}",
                    ],
                ]);

                $draft = $this->normalizer->normalize($raw, source: 'openai');
                $draft['explanation'] = isset($raw['explanation']) ? trim((string) $raw['explanation']) : null;
                $draft['unrecognized'] = $this->normalizeUnrecognized($raw['unrecognized'] ?? []);
            } catch (Throwable) {
                $draft = null;
            }
        }

        $draft ??= $this->heuristic->parse($prompt);
        $draft['unrecognized'] = $this->normalizeUnrecognized($draft['unrecognized'] ?? []);
        $draft = $this->tableBinder->bind($draft);

        if ($append && is_array($existingPieces) && $existingPieces !== []) {
            $draft = $this->mergeDraftIntoExisting($draft, $existingPieces);
        }

        return $this->withReadiness($draft, $prompt);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function withReadiness(array $draft, string $prompt): array
    {
        $readiness = app(SernaQuoteReadinessChecker::class)->check(
            $draft['pieces'] ?? [],
            $prompt,
            $draft['unresolved'] ?? [],
        );

        $draft['readiness'] = $readiness;
        $draft['unresolved'] = $readiness['errors'];

        if (! $readiness['ok']) {
            $alert = "ALERTAS (bloquean crear cotización):\n• ".implode("\n• ", $readiness['errors']);
            $draft['explanation'] = trim((string) ($draft['explanation'] ?? '')."\n\n".$alert);
        }

        return $draft;
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function normalizeUnrecognized(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            $text = trim((string) $row);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string, explanation?: ?string, pricing?: array<string, mixed>}  $draft
     * @param  list<array<string, mixed>>  $existingPieces
     * @return array{project_name: ?string, contact_name: ?string, notes: ?string, payment_form: ?string, pieces: list<array<string, mixed>>, source: string, explanation?: ?string, pricing?: array<string, mixed>}
     */
    public function mergeDraftIntoExisting(array $draft, array $existingPieces): array
    {
        $merged = [];

        foreach ($existingPieces as $piece) {
            if (! is_array($piece) || blank($piece['name'] ?? null)) {
                continue;
            }
            $key = mb_strtoupper(trim((string) $piece['name']), 'UTF-8');
            $merged[$key] = [
                'name' => $key,
                'items' => array_values(array_filter($piece['items'] ?? [], fn ($i): bool => is_array($i))),
            ];
        }

        foreach ($draft['pieces'] ?? [] as $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $key = mb_strtoupper(trim((string) ($piece['name'] ?? 'PIEZA NUEVA')), 'UTF-8');
            $items = array_values(array_filter($piece['items'] ?? [], fn ($i): bool => is_array($i)));

            if (! isset($merged[$key])) {
                $merged[$key] = ['name' => $key, 'items' => $items];

                continue;
            }

            $merged[$key]['items'] = array_values([
                ...$merged[$key]['items'],
                ...$items,
            ]);
        }

        $draft['pieces'] = array_values($merged);
        $draft['explanation'] = trim(
            (string) ($draft['explanation'] ?? '').
            "\n\nSe agregaron piezas/ítems a la cotización actual (modo agregar)."
        );

        return $this->tableBinder->bind($draft);
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return list<array<string, mixed>>
     */
    public function improveAcabados(array $pieces, ?string $projectName = null): array
    {
        if ($pieces === []) {
            throw new \InvalidArgumentException('No hay piezas para mejorar.');
        }

        if (! $this->client->enabled()) {
            return $this->improveAcabadosLocally($pieces, $projectName);
        }

        try {
            $payload = [
                'project_name' => $projectName,
                'pieces' => collect($pieces)->map(fn (array $piece): array => [
                    'name' => $piece['name'] ?? '',
                    'items' => collect($piece['items'] ?? [])->map(fn (array $item): array => [
                        'item_type' => $this->typeValue($item['item_type'] ?? null),
                        'material' => $item['material'] ?? '',
                        'acabados' => $item['acabados'] ?? '',
                        'width_cm' => $item['width_cm'] ?? null,
                        'height_cm' => $item['height_cm'] ?? null,
                        'thickness_mm' => $item['thickness_mm'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                    ])->values()->all(),
                ])->values()->all(),
            ];

            $raw = $this->client->chatJson([
                [
                    'role' => 'system',
                    'content' => 'Mejora textos de la columna ACABADOS de cotizaciones de acrílico estilo Acrílicos Serna. Devuelve JSON {"pieces":[{"name":"...","items":[{"acabados":"..."}]}]} manteniendo el mismo orden de piezas e ítems. Usa mayúsculas, tono comercial/técnico, sin inventar medidas nuevas.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ],
            ], temperature: 0.4);

            $improvedPieces = $raw['pieces'] ?? null;
            if (! is_array($improvedPieces)) {
                return $this->improveAcabadosLocally($pieces, $projectName);
            }

            foreach ($pieces as $pIndex => $piece) {
                foreach (($piece['items'] ?? []) as $iIndex => $item) {
                    $new = data_get($improvedPieces, "{$pIndex}.items.{$iIndex}.acabados");
                    if (is_string($new) && trim($new) !== '') {
                        $pieces[$pIndex]['items'][$iIndex]['acabados'] = mb_strtoupper(trim($new), 'UTF-8');
                    }
                }
            }

            return $pieces;
        } catch (Throwable) {
            return $this->improveAcabadosLocally($pieces, $projectName);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     */
    public function explainPrice(array $pieces, float $withholdingRate = 0, ?string $projectName = null): string
    {
        if ($pieces === []) {
            throw new \InvalidArgumentException('No hay piezas para explicar.');
        }

        $proposal = $this->engine->calculateProposalFromPieces($pieces, $withholdingRate);
        $facts = $this->buildPriceFacts($proposal, $projectName);

        if ($this->client->enabled()) {
            try {
                $raw = $this->client->chatJson([
                    [
                        'role' => 'system',
                        'content' => 'Eres un asesor comercial de cotizaciones en acrílico. Explica el precio de forma clara y breve para un vendedor. Devuelve JSON {"explanation":"texto en español, 3 a 8 oraciones, con bullets si ayuda"}. No inventes valores distintos a los hechos.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ],
                ], temperature: 0.3);

                $text = trim((string) ($raw['explanation'] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            } catch (Throwable) {
                // fallback local
            }
        }

        return $this->explainPriceLocally($facts);
    }

    /**
     * @param  list<array<string, mixed>>  $pieces
     * @return list<array<string, mixed>>
     */
    private function improveAcabadosLocally(array $pieces, ?string $projectName): array
    {
        foreach ($pieces as $pIndex => $piece) {
            $pieceName = (string) ($piece['name'] ?? 'PIEZA');
            foreach (($piece['items'] ?? []) as $iIndex => $item) {
                $type = SernaItemType::tryFrom((string) ($this->typeValue($item['item_type'] ?? null) ?? ''));
                $material = mb_strtoupper(trim((string) ($item['material'] ?? '')), 'UTF-8');
                $current = trim((string) ($item['acabados'] ?? ''));
                $x = $item['width_cm'] ?? null;
                $y = $item['height_cm'] ?? null;

                // Si parece el pedido completo pegado en acabados, regenerar (no conservar el dump).
                if ($current !== '' && mb_strlen($current) > 40 && ! $this->acabadosLooksLikeFullPrompt($current)) {
                    $pieces[$pIndex]['items'][$iIndex]['acabados'] = mb_strtoupper($current, 'UTF-8');

                    continue;
                }

                $parts = array_filter([
                    $projectName ? "PROYECTO {$projectName}" : null,
                    "PIEZA {$pieceName}",
                    $material !== '' ? "MATERIAL {$material}" : null,
                    $type?->getLabel() ? 'PROCESO '.$type->getLabel() : null,
                    $x && $y ? "MEDIDA {$this->n((float) $x)} X {$this->n((float) $y)} CM" : null,
                    $current !== '' ? $current : 'SEGÚN DISEÑO APROBADO POR EL CLIENTE',
                ]);

                $pieces[$pIndex]['items'][$iIndex]['acabados'] = implode(' · ', $parts);
            }
        }

        return $pieces;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    private function buildPriceFacts(array $proposal, ?string $projectName): array
    {
        return [
            'project_name' => $projectName,
            'subtotal' => $proposal['subtotal'],
            'iva_rate' => $proposal['iva_rate'],
            'iva_amount' => $proposal['iva_amount'],
            'withholding_rate' => $proposal['withholding_rate'],
            'withholding_amount' => $proposal['withholding_amount'],
            'total' => $proposal['total'],
            'total_payable' => $proposal['total_payable'],
            'pieces' => collect($proposal['pieces'] ?? [])->map(fn (array $piece): array => [
                'name' => $piece['name'],
                'subtotal' => $piece['subtotal'],
                'items' => collect($piece['items'] ?? [])->map(fn (array $line): array => [
                    'pieza' => $line['pieza'] ?? $piece['name'],
                    'material' => $line['material'] ?? '',
                    'item_type' => $line['item_type_label'] ?? $line['item_type'] ?? '',
                    'area_cm2' => $line['area_cm2'] ?? null,
                    'price_per_cm2' => $line['price_per_cm2'] ?? null,
                    'min_charge_applied' => $line['min_charge_applied'] ?? false,
                    'quantity' => $line['quantity'] ?? 1,
                    'unit_price' => $line['unit_price'] ?? 0,
                    'line_total' => $line['line_total'] ?? 0,
                    'pricing_mode' => $line['pricing_mode'] ?? null,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function explainPriceLocally(array $facts): string
    {
        $lines = [];
        if (filled($facts['project_name'] ?? null)) {
            $lines[] = 'Proyecto: '.$facts['project_name'].'.';
        }

        $lines[] = 'Subtotal '.Money::format($facts['subtotal'])
            .' + IVA '.number_format((float) $facts['iva_rate'], 2, ',', '.').'% ('.Money::format($facts['iva_amount']).')'
            .' − retefuente '.Money::format($facts['withholding_amount'])
            .' = total a pagar '.Money::format($facts['total_payable']).'.';

        foreach ($facts['pieces'] as $piece) {
            $lines[] = '• Pieza '.$piece['name'].': '.Money::format($piece['subtotal']).'.';
            foreach ($piece['items'] as $item) {
                $detail = $item['material'] ?: $item['item_type'];
                if (! empty($item['area_cm2']) && ! empty($item['price_per_cm2'])) {
                    $detail .= ' · '.$this->n((float) $item['area_cm2']).' cm² × $'
                        .number_format((float) $item['price_per_cm2'], 1, ',', '.')
                        .'/cm²';
                    if (! empty($item['min_charge_applied'])) {
                        $detail .= ' (aplica mínimo)';
                    }
                }
                $detail .= ' → '.Money::format($item['line_total']);
                $lines[] = '  - '.$detail;
            }
        }

        return implode("\n", $lines);
    }

    private function catalogHint(): string
    {
        $rates = SernaProcessRate::query()
            ->active()
            ->ordered()
            ->get(['id', 'code', 'name', 'category', 'price_per_cm2', 'min_charge', 'thickness_mm_min', 'thickness_mm_max'])
            ->map(fn (SernaProcessRate $rate): string => sprintf(
                'RATE id=%d code=%s category=%s name=%s price_per_cm2=%s min=%s mm=%s-%s',
                $rate->id,
                $rate->code,
                $rate->category,
                $rate->name,
                $rate->price_per_cm2,
                $rate->min_charge ?? '-',
                $rate->thickness_mm_min ?? '-',
                $rate->thickness_mm_max ?? '-',
            ))
            ->implode("\n");

        $sheets = SernaSheetPrice::query()
            ->active()
            ->ordered()
            ->get(['id', 'format', 'thickness_mm', 'finish', 'price', 'price_source'])
            ->map(fn (SernaSheetPrice $sheet): string => sprintf(
                'SHEET id=%d format=%s thickness_mm=%s finish=%s price=%s source=%s',
                $sheet->id,
                $sheet->format,
                $sheet->thickness_mm,
                $sheet->finish,
                $sheet->price,
                $sheet->price_source,
            ))
            ->implode("\n");

        $products = SernaCatalogProduct::query()
            ->active()
            ->ordered()
            ->get(['id', 'sku', 'name', 'category', 'unit_price'])
            ->map(fn (SernaCatalogProduct $product): string => sprintf(
                'PRODUCT id=%d sku=%s category=%s name=%s unit_price=%s',
                $product->id,
                $product->sku,
                $product->category,
                $product->name,
                $product->unit_price,
            ))
            ->implode("\n");

        $lights = AcrylicLightingOption::query()
            ->active()
            ->where('pricing_mode', '!=', AcrylicLightingPricingMode::None->value)
            ->ordered()
            ->get(['id', 'name', 'pricing_mode', 'unit_price', 'power_supply_cost'])
            ->map(fn (AcrylicLightingOption $light): string => sprintf(
                'LIGHT id=%d name=%s mode=%s unit_price=%s power_supply_cost=%s',
                $light->id,
                $light->name,
                $light->pricing_mode instanceof AcrylicLightingPricingMode
                    ? $light->pricing_mode->value
                    : $light->pricing_mode,
                $light->unit_price,
                $light->power_supply_cost,
            ))
            ->implode("\n");

        return "### LISTA SERNA 2026 — TARIFAS CM2\n{$rates}\n\n### LISTA SERNA 2026 — LAMINAS\n{$sheets}\n\n### LISTA SERNA 2026 — PRODUCTOS\n{$products}\n\n### LISTA SERNA 2026 — ILUMINACION / LED\n{$lights}";
    }

    private function typeValue(mixed $type): ?string
    {
        if ($type instanceof SernaItemType) {
            return $type->value;
        }

        if (is_string($type) && $type !== '') {
            return SernaItemType::tryFrom($type)?->value ?? $type;
        }

        return null;
    }

    /**
     * Detecta cuando "acabados" es el pedido completo del asistente IA.
     */
    private function acabadosLooksLikeFullPrompt(string $acabados): bool
    {
        $upper = mb_strtoupper($acabados, 'UTF-8');
        $hits = 0;

        foreach (['CONTACTO', 'CONTADO', 'ANTICIPO', 'VINILO', 'TRANSPORTE', 'INSTALACION', 'LED', 'BACKLIGHT'] as $token) {
            if (str_contains($upper, $token)) {
                $hits++;
            }
        }

        return $hits >= 2 || mb_strlen($acabados) > 160;
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
