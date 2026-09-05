<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class Serna2026PriceListSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/serna_2026_price_list.json');
        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $year = (int) ($data['year'] ?? 2026);

        foreach ($data['process_rates'] as $row) {
            SernaProcessRate::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'thickness_mm_min' => $row['thickness_mm_min'],
                    'thickness_mm_max' => $row['thickness_mm_max'],
                    'price_per_cm2' => $row['price_per_cm2'],
                    'min_charge' => $row['min_charge'],
                    'year' => $year,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'] ?? 0,
                ],
            );
        }

        $baseFormat = collect($data['sheet_formats'])->firstWhere('format', '120x180');
        $baseArea = (float) $baseFormat['width_cm'] * (float) $baseFormat['height_cm'];

        foreach ($data['sheet_formats'] as $format) {
            $area = (float) $format['width_cm'] * (float) $format['height_cm'];
            $factor = $area / $baseArea;
            $isOfficial = $format['format'] === '120x180';

            foreach ($data['sheet_base_120x180'] as $sheet) {
                $price = $isOfficial
                    ? (float) $sheet['price']
                    : (float) round($sheet['price'] * $factor, -3);

                SernaSheetPrice::query()->updateOrCreate(
                    [
                        'format' => $format['format'],
                        'thickness_mm' => $sheet['thickness_mm'],
                        'finish' => $sheet['finish'],
                        'year' => $year,
                    ],
                    [
                        'width_cm' => $format['width_cm'],
                        'height_cm' => $format['height_cm'],
                        'price' => $price,
                        'price_source' => $isOfficial ? 'official_120x180' : 'scaled_from_120x180',
                        'is_active' => true,
                        'sort_order' => $format['sort_order'] ?? 0,
                    ],
                );
            }
        }

        $catalog = array_merge($data['catalog_products'] ?? [], $data['fixed_extras'] ?? []);

        foreach ($catalog as $product) {
            SernaCatalogProduct::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'category' => $product['category'],
                    'specs' => $product['specs'] ?? [],
                    'unit_price' => $product['unit_price'],
                    'pricing_mode' => 'fixed',
                    'year' => $year,
                    'is_active' => true,
                    'sort_order' => $product['sort_order'] ?? 0,
                ],
            );

            Item::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'type' => ItemType::ProductoTerminado,
                    'unit_of_measure' => 'und',
                    'stock' => 0,
                    'min_stock' => 0,
                    'cost' => 0,
                    'price' => $product['unit_price'],
                    'is_active' => true,
                ],
            );
        }

        Item::query()->updateOrCreate(
            ['sku' => 'SERNA-SERVICIO'],
            [
                'name' => 'Servicio / manufactura Serna',
                'type' => ItemType::ProductoTerminado,
                'unit_of_measure' => 'und',
                'stock' => 0,
                'min_stock' => 0,
                'cost' => 0,
                'price' => 0,
                'is_active' => true,
            ],
        );

        Item::query()->updateOrCreate(
            ['sku' => 'SERNA-LAMINA'],
            [
                'name' => 'Lámina acrílica Serna',
                'type' => ItemType::MateriaPrima,
                'unit_of_measure' => 'und',
                'stock' => 0,
                'min_stock' => 0,
                'cost' => 0,
                'price' => 0,
                'is_active' => true,
            ],
        );

        Item::query()->updateOrCreate(
            ['sku' => 'SERNA-MANUAL'],
            [
                'name' => 'Transporte / instalación / valor manual',
                'type' => ItemType::ProductoTerminado,
                'unit_of_measure' => 'und',
                'stock' => 0,
                'min_stock' => 0,
                'cost' => 0,
                'price' => 0,
                'is_active' => true,
            ],
        );
    }
}
