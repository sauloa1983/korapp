<?php

namespace App\Models\Concerns;

use App\Support\Tax;

trait HasDocumentTotals
{
    /**
     * Aplica subtotal + IVA + total al documento.
     * Si no se pasa tasa, usa la configuración actual de la empresa.
     * La retención (si el modelo tiene los campos) se calcula sobre el subtotal.
     */
    public function applyTaxFromSubtotal(float $subtotal, ?float $rate = null, ?float $withholdingRate = null): void
    {
        $breakdown = Tax::breakdown($subtotal, $rate);

        $payload = [
            'subtotal' => $breakdown['subtotal'],
            'iva_rate' => $breakdown['iva_rate'],
            'iva_amount' => $breakdown['iva_amount'],
            'total' => $breakdown['total'],
        ];

        if (array_key_exists('withholding_rate', $this->getAttributes())
            || in_array('withholding_rate', $this->getFillable(), true)) {
            $resolvedWithholding = $withholdingRate;
            if ($resolvedWithholding === null) {
                $resolvedWithholding = (float) ($this->withholding_rate ?? 0);
            }

            $resolvedWithholding = max(0, round($resolvedWithholding, 2));
            $withholdingAmount = $resolvedWithholding > 0
                ? round($breakdown['subtotal'] * ($resolvedWithholding / 100), 2)
                : 0.0;

            $payload['withholding_rate'] = $resolvedWithholding;
            $payload['withholding_amount'] = $withholdingAmount;
        }

        $this->forceFill($payload)->saveQuietly();
    }

    public function hasIva(): bool
    {
        return (float) ($this->iva_amount ?? 0) > 0
            || (float) ($this->iva_rate ?? 0) > 0;
    }
}
