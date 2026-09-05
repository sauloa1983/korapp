<?php

namespace App\Services\EInvoice;

use App\Enums\EInvoiceStatus;

/** Resultado inmutable de una emisión de factura electrónica. */
class EInvoiceResult
{
    public function __construct(
        public readonly EInvoiceStatus $status,
        public readonly ?string $uuid = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function accepted(string $uuid, ?string $message = null, array $raw = []): self
    {
        return new self(EInvoiceStatus::Aceptada, $uuid, $message, $raw);
    }

    public static function rejected(?string $message = null, array $raw = []): self
    {
        return new self(EInvoiceStatus::Rechazada, null, $message, $raw);
    }
}
