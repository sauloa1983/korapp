<?php

if (! function_exists('money')) {
    /**
     * Formatea un monto en pesos colombianos.
     */
    function money(mixed $amount, int $decimals = 0): string
    {
        return \App\Support\Money::format($amount, $decimals);
    }
}
