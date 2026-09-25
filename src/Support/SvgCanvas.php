<?php

namespace Unloc\FontAwesome\Support;

// FA7 glyphs overflow their trimmed viewBox into the canvas padding; a centered square grid
// with visible overflow renders them at FA's own scale in a square box without clipping.
class SvgCanvas
{
    private const CANVAS_RATIO = 1.25;

    public function square(string $svg, string $version): string
    {
        if ((int) $version < 7) {
            return $svg;
        }

        return (string) preg_replace_callback(
            '/<svg\b([^>]*\s)viewBox="0 0 (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)"([^>]*)>/i',
            function (array $m): string {
                $width = (float) $m[2];
                $height = (float) $m[3];

                if ($width > $height * self::CANVAS_RATIO) {
                    return $m[0];
                }

                $viewBox = implode(' ', array_map($this->number(...), [-($height - $width) / 2, 0, $height, $height]));
                $overflow = preg_match('/\soverflow=/i', $m[1] . $m[4]) ? '' : ' overflow="visible"';

                return '<svg' . $m[1] . 'viewBox="' . $viewBox . '"' . $overflow . $m[4] . '>';
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
