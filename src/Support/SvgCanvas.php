<?php

namespace Unloc\FontAwesome\Support;

// FA7 glyphs overflow their trimmed viewBox into the canvas padding; centering the viewBox on
// the 512-unit grid with visible overflow renders any glyph at FA's own scale in a square box.
class SvgCanvas
{
    private const GRID = 512;
    private const CANVAS = 640;

    public function square(string $svg, string $version): string
    {
        if ((int) $version < 7) {
            return $svg;
        }

        $number = '(-?\d*\.?\d+)';

        return (string) preg_replace_callback(
            "/<svg\\b([^>]*\\s)viewBox=\"\\s*{$number}[\\s,]+{$number}[\\s,]+{$number}[\\s,]+{$number}\\s*\"([^>]*)>/i",
            function (array $m): string {
                [$x, $y, $width, $height] = array_map('floatval', [$m[2], $m[3], $m[4], $m[5]]);

                if ($width > self::CANVAS || $height > self::CANVAS) {
                    return $m[0];
                }

                $viewBox = implode(' ', array_map($this->number(...), [
                    $x - (self::GRID - $width) / 2,
                    $y - (self::GRID - $height) / 2,
                    self::GRID,
                    self::GRID,
                ]));
                $overflow = preg_match('/\soverflow=/i', $m[1] . $m[6]) ? '' : ' overflow="visible"';

                return '<svg' . $m[1] . 'viewBox="' . $viewBox . '"' . $overflow . $m[6] . '>';
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
