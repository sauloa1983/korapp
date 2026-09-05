<?php

namespace App\Support;

use App\Models\CompanySetting;

class Tax
{
    public static function chargesIva(?CompanySetting $settings = null): bool
    {
        $settings ??= CompanySetting::current();

        return (bool) $settings->charges_iva;
    }

    public static function currentRate(?CompanySetting $settings = null): float
    {
        $settings ??= CompanySetting::current();

        if (! $settings->charges_iva) {
            return 0.0;
        }

        return Money::round($settings->iva_rate ?? 0);
    }

    /**
     * @return array{subtotal: float, iva_rate: float, iva_amount: float, total: float}
     */
    public static function breakdown(float $subtotal, ?float $rate = null): array
    {
        $subtotal = Money::round($subtotal);
        $rate = $rate === null ? static::currentRate() : Money::round($rate);
        $ivaAmount = $rate > 0 ? Money::round($subtotal * ($rate / 100)) : 0.0;

        return [
            'subtotal' => $subtotal,
            'iva_rate' => $rate,
            'iva_amount' => $ivaAmount,
            'total' => Money::round($subtotal + $ivaAmount),
        ];
    }
}
