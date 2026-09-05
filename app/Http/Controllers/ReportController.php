<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Exporta las ventas confirmadas del rango a CSV. */
    public function sales(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $sales = Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, $to])
            ->with(['customer', 'user'])
            ->orderBy('sold_at')
            ->get();

        return $this->stream("ventas_{$from->toDateString()}_{$to->toDateString()}.csv", function ($out) use ($sales): void {
            fputcsv($out, ['Código', 'Fecha', 'Cliente', 'Vendedor', 'Subtotal', 'IVA %', 'IVA', 'Total']);

            foreach ($sales as $sale) {
                fputcsv($out, [
                    $sale->code,
                    optional($sale->sold_at)->toDateString(),
                    $sale->customer?->name ?? 'Público',
                    $sale->user?->name ?? '',
                    \App\Support\Money::plain($sale->subtotal ?? $sale->total),
                    \App\Support\Money::plain($sale->iva_rate ?? 0),
                    \App\Support\Money::plain($sale->iva_amount ?? 0),
                    \App\Support\Money::plain($sale->total),
                ]);
            }
        });
    }

    /** Exporta la rotación de inventario (entradas/salidas) del rango a CSV. */
    public function rotation(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $movements = StockMovement::query()
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('item_id');

        $items = Item::query()->whereIn('id', $movements->keys())->get()->keyBy('id');

        return $this->stream("rotacion_{$from->toDateString()}_{$to->toDateString()}.csv", function ($out) use ($movements, $items): void {
            fputcsv($out, ['SKU', 'Artículo', 'Entradas', 'Salidas', 'Existencia actual']);

            foreach ($movements as $itemId => $group) {
                $item = $items->get($itemId);

                if (! $item) {
                    continue;
                }

                fputcsv($out, [
                    $item->sku,
                    $item->name,
                    number_format((float) $group->where('type', StockMovementType::Entrada)->sum('quantity'), 2, '.', ''),
                    number_format((float) $group->where('type', StockMovementType::Salida)->sum('quantity'), 2, '.', ''),
                    number_format((float) $item->stock, 2, '.', ''),
                ]);
            }
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        return [$from, $to];
    }

    private function stream(string $filename, callable $callback): StreamedResponse
    {
        return response()->streamDownload(function () use ($callback): void {
            $out = fopen('php://output', 'w');
            // BOM para que Excel reconozca UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            $callback($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
