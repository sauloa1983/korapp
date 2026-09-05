<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ProductionOrderCodes
{
    /** QR SVG encoding the production order business code. */
    public static function qrSvg(string $code, int $scale = 5): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'imageBase64' => false,
            'scale' => $scale,
        ]);

        return (new QRCode($options))->render($code);
    }

    /** Code 128-B barcode SVG for scanners that expect linear barcodes. */
    public static function barcodeSvg(string $code, int $barHeight = 64, float $moduleWidth = 1.6): string
    {
        $pattern = static::code128Pattern($code);
        $width = strlen($pattern) * $moduleWidth;
        $bars = '';

        for ($i = 0, $len = strlen($pattern); $i < $len; $i++) {
            if ($pattern[$i] === '1') {
                $x = $i * $moduleWidth;
                $bars .= sprintf(
                    '<rect x="%.2f" y="0" width="%.2f" height="%d" fill="#111"/>',
                    $x,
                    $moduleWidth,
                    $barHeight,
                );
            }
        }

        $labelY = $barHeight + 16;
        $svgHeight = $barHeight + 22;
        $escaped = htmlspecialchars($code, ENT_QUOTES | ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$svgHeight}" viewBox="0 0 {$width} {$svgHeight}" role="img" aria-label="{$escaped}">
  {$bars}
  <text x="50%" y="{$labelY}" text-anchor="middle" font-family="ui-monospace, Consolas, monospace" font-size="12" fill="#111">{$escaped}</text>
</svg>
SVG;
    }

    protected static function code128Pattern(string $text): string
    {
        $codes = static::code128Table();
        $bytes = array_values(unpack('C*', $text) ?: []);

        if ($bytes === []) {
            $bytes = [45]; // '-'
        }

        $pattern = $codes[104]; // Start Code B
        $checksum = 104;

        foreach ($bytes as $index => $byte) {
            $value = $byte - 32;
            if ($value < 0 || $value > 95) {
                $value = 15; // '/'
            }
            $pattern .= $codes[$value];
            $checksum += $value * ($index + 1);
        }

        $pattern .= $codes[$checksum % 103];
        $pattern .= $codes[106]; // Stop
        $pattern .= '11'; // termination bar

        return $pattern;
    }

    /**
     * Code 128 patterns indexed by symbol value (0-106).
     *
     * @return array<int, string>
     */
    protected static function code128Table(): array
    {
        return [
            0 => '11011001100', 1 => '11001101100', 2 => '11001100110', 3 => '10010011000',
            4 => '10010001100', 5 => '10001001100', 6 => '10011001000', 7 => '10011000100',
            8 => '10001100100', 9 => '11001001000', 10 => '11001000100', 11 => '11000100100',
            12 => '10110011100', 13 => '10011011100', 14 => '10011001110', 15 => '10111001100',
            16 => '10011101100', 17 => '10011100110', 18 => '11001110010', 19 => '11001011100',
            20 => '11001001110', 21 => '11011100100', 22 => '11001110100', 23 => '11101101110',
            24 => '11101001100', 25 => '11100101100', 26 => '11100100110', 27 => '11101100100',
            28 => '11100110100', 29 => '11100110010', 30 => '11011011000', 31 => '11011000110',
            32 => '11000110110', 33 => '10100011000', 34 => '10001011000', 35 => '10001000110',
            36 => '10110001000', 37 => '10001101000', 38 => '10001100010', 39 => '11010001000',
            40 => '11000101000', 41 => '11000100010', 42 => '10110111000', 43 => '10110001110',
            44 => '10001101110', 45 => '10111011000', 46 => '10111000110', 47 => '10001110110',
            48 => '11101110110', 49 => '11010001110', 50 => '11000101110', 51 => '11011101000',
            52 => '11011100010', 53 => '11011101110', 54 => '11101011000', 55 => '11101000110',
            56 => '11100010110', 57 => '11101101000', 58 => '11101100010', 59 => '11100011010',
            60 => '11101111010', 61 => '11001000010', 62 => '11110001010', 63 => '10100110000',
            64 => '10100001100', 65 => '10010110000', 66 => '10010000110', 67 => '10000101100',
            68 => '10000100110', 69 => '10110010000', 70 => '10110000100', 71 => '10011010000',
            72 => '10011000010', 73 => '10000110100', 74 => '10000110010', 75 => '11000010010',
            76 => '11001010000', 77 => '11110111010', 78 => '11000010100', 79 => '10001111010',
            80 => '10100111100', 81 => '10010111100', 82 => '10010011110', 83 => '10111100100',
            84 => '10011110100', 85 => '10011110010', 86 => '11110100100', 87 => '11110010100',
            88 => '11110010010', 89 => '11011011110', 90 => '11011110110', 91 => '11110110110',
            92 => '10101111000', 93 => '10100011110', 94 => '10001011110', 95 => '10111101000',
            96 => '10111100010', 97 => '11110101000', 98 => '11110100010', 99 => '10111011110',
            100 => '10111101110', 101 => '11101011110', 102 => '11110101110', 103 => '11010000100',
            104 => '11010010000', 105 => '11010011100', 106 => '11000111010',
        ];
    }
}
