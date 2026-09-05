<?php

use App\Services\EInvoice\FakeEInvoiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Facturación electrónica
    |--------------------------------------------------------------------------
    |
    | Cuando 'enabled' es true, al confirmar una venta se despacha un job que
    | envía el documento al proveedor configurado. El driver por defecto es
    | 'fake' (simulación); registra aquí el driver real cuando esté disponible.
    |
    */

    'enabled' => (bool) env('EINVOICE_ENABLED', false),

    'default' => env('EINVOICE_DRIVER', 'fake'),

    'drivers' => [
        'fake' => FakeEInvoiceProvider::class,
        // 'dian' => \App\Services\EInvoice\DianEInvoiceProvider::class,
    ],
];
