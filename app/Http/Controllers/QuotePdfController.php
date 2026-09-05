<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\View\View;

class QuotePdfController extends Controller
{
    /** Cotización imprimible (lista para guardar como PDF). */
    public function show(Quote $quote): View
    {
        $quote->load(['customer', 'lead', 'user', 'pieces.items.item', 'items.item']);

        return view('quotes.pdf', ['quote' => $quote]);
    }
}
