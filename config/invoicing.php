<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Numeración fiscal de comprobantes de venta
    |--------------------------------------------------------------------------
    |
    | El consecutivo formal se asigna al confirmar la venta. Estos valores
    | permiten configurar el prefijo, el relleno de ceros y el número inicial
    | del rango autorizado (por ejemplo, una resolución DIAN).
    |
    */

    'prefix' => env('INVOICE_PREFIX', 'FV'),

    'padding' => (int) env('INVOICE_PADDING', 6),

    // Primer consecutivo autorizado del rango (from). El primer comprobante
    // tomará este número aunque la tabla esté vacía.
    'start' => (int) env('INVOICE_START', 1),

    // Datos de la resolución/autorización, mostrados en el comprobante.
    'resolution' => [
        'number' => env('INVOICE_RESOLUTION_NUMBER'),
        'from' => env('INVOICE_RANGE_FROM'),
        'to' => env('INVOICE_RANGE_TO'),
        'valid_until' => env('INVOICE_VALID_UNTIL'),
    ],
];
