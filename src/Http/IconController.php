<?php

namespace Unloc\FontAwesome\Http;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Unloc\FontAwesome\FontAwesome;

class IconController
{
    public function __construct(
        private FontAwesome $fontAwesome,
        private int|string $version,
        private int $maxAge,
    ) {}

    public function __invoke(string $version, string $family, string $style, string $name): Response
    {
        if ($version !== (string) $this->version) {
            throw new NotFoundHttpException;
        }

        $svg = $this->fontAwesome->raw($name, $family, $style);
        if ($svg === null) {
            throw new NotFoundHttpException;
        }

        return new Response($this->prepare($svg), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => "public, max-age={$this->maxAge}, immutable",
        ]);
    }

    /**
     * The file is fetched as a standalone document, so it needs the SVG namespace
     * and the id that <use> points its fragment at.
     */
    private function prepare(string $svg): string
    {
        if (! preg_match('/<svg([^>]*)>/is', $svg, $m, PREG_OFFSET_CAPTURE)) {
            return $svg;
        }

        $attrs = (string) preg_replace('/\sid="[^"]*"/i', '', $m[1][0]);
        if (! preg_match('/\sxmlns\s*=/i', $attrs)) {
            $attrs = ' xmlns="http://www.w3.org/2000/svg"' . $attrs;
        }

        return substr($svg, 0, $m[0][1])
            . '<svg id="' . FontAwesome::FRAGMENT . '"' . $attrs . '>'
            . substr($svg, $m[0][1] + strlen($m[0][0]));
    }
}
