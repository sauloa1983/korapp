<?php

namespace App\Services\EInvoice;

use App\Models\Sale;
use Illuminate\Support\Str;

/**
 * Driver de simulación: acepta la factura y genera un CUFE ficticio.
 * Útil para desarrollo, pruebas y demos sin conexión con la DIAN.
 */
class FakeEInvoiceProvider implements EInvoiceProvider
{
    public function submit(Sale $sale): EInvoiceResult
    {
        // Una venta sin líneas o total en cero se rechaza (validación básica).
        if ((float) $sale->total <= 0) {
            return EInvoiceResult::rejected('La factura no tiene valor a facturar.', [
                'sale' => $sale->code,
            ]);
        }

        $uuid = 'FAKE-'.strtoupper(Str::uuid()->toString());

        return EInvoiceResult::accepted($uuid, 'Documento aceptado (simulado).', [
            'sale' => $sale->code,
            'invoice_number' => $sale->invoice_number,
            'total' => (float) $sale->total,
            'provider' => 'fake',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
