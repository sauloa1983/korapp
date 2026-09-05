<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleReceiptController extends Controller
{
    /** Comprobante de venta imprimible (una página lista para PDF). */
    public function show(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        $sale->load(['customer', 'user', 'items.item']);

        return view('sales.receipt', ['sale' => $sale]);
    }
}
