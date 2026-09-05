<?php

namespace App\Support;

class Money
{
    /**
     * Formato de moneda COP para UI: $1.234.567
     */
    public static function format(mixed $amount, int $decimals = 0): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;

        return '$'.number_format($value, $decimals, ',', '.');
    }

    /**
     * Formato numérico COP sin símbolo: 1.234.567
     */
    public static function formatInput(mixed $amount, int $decimals = 0): string
    {
        return number_format(
            is_numeric($amount) ? (float) $amount : 0.0,
            $decimals,
            ',',
            '.',
        );
    }

    /**
     * Estado de input: número de BD o texto con puntos de miles → "1.234.567"
     */
    public static function formatInputState(mixed $state, int $decimals = 0): ?string
    {
        $parsed = self::parseInput($state);

        return $parsed === null ? null : number_format($parsed, $decimals, ',', '.');
    }

    /**
     * Parsea "500.000", "500000" o "500000.00" → float. Null si vacío.
     */
    public static function parseInput(mixed $state): ?float
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (is_int($state) || is_float($state)) {
            return (float) $state;
        }

        $string = trim((string) $state);

        // Crudo de BD / Livewire: enteros o con 1–2 decimales ("500000.00").
        // No usar "500.000" aquí: en COP los puntos son miles.
        if (preg_match('/^\d+$/', $string) === 1) {
            return (float) $string;
        }

        if (preg_match('/^\d+\.\d{1,2}$/', $string) === 1) {
            return (float) $string;
        }

        $raw = preg_replace('/[^\d]/', '', $string);

        return filled($raw) ? (float) $raw : null;
    }

    /**
     * Formato numérico sin símbolo (CSV / exportaciones).
     */
    public static function plain(mixed $amount, int $decimals = 2): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;

        return number_format($value, $decimals, '.', '');
    }

    public static function round(mixed $amount, int $decimals = 2): float
    {
        return round(is_numeric($amount) ? (float) $amount : 0.0, $decimals);
    }
}
