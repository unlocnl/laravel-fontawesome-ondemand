<?php

namespace Unloc\FontAwesome\Support;

// FA7 viewBoxes are trimmed to the grid, which official glyphs overflow into the canvas
// padding; widening to the centered square canvas (as in svgs-full/) stops the clipping.
class SvgCanvas
{
    private const CANVAS_RATIO = 1.25;

    public function __construct(private int|string $version) {}

    public function square(string $svg): string
    {
        if ((int) $this->version < 7) {
            return $svg;
        }

        return (string) preg_replace_callback(
            '/(<svg\b[^>]*\sviewBox=")0 0 (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)(")/i',
            function (array $m): string {
                $width = (float) $m[2];
                $height = (float) $m[3];
                $canvas = $height * self::CANVAS_RATIO;

                if ($width > $canvas) {
                    return $m[0];
                }

                return $m[1] . implode(' ', array_map($this->number(...), [
                    -($canvas - $width) / 2,
                    -($canvas - $height) / 2,
                    $canvas,
                    $canvas,
                ])) . $m[4];
            },
            $svg,
            1,
        );
    }

    private function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
