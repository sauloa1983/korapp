<?php

namespace App\Services\EInvoice;

use App\Models\Sale;

/**
 * Contrato para proveedores de facturación electrónica. Implementa este
 * contrato con el proveedor tecnológico real (DIAN) cuando se disponga de
 * credenciales; el resto del sistema no cambia.
 */
interface EInvoiceProvider
{
    public function submit(Sale $sale): EInvoiceResult;
}
