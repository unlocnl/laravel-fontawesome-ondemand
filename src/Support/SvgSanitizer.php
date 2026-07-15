<?php

namespace Unloc\FontAwesome\Support;

class SvgSanitizer
{
    /** @param list<string> $removeAttributes */
    public function __construct(
        private bool $stripComments = true,
        private array $removeAttributes = [],
    ) {}

    public function sanitize(string $svg): string
    {
        if ($this->stripComments) {
            $svg = (string) preg_replace('/<!--.*?-->/s', '', $svg);
        }

        foreach ($this->removeAttributes as $attr) {
            $svg = (string) preg_replace($this->attributePattern($attr), '', $svg);
        }

        return trim($svg);
    }

    private function attributePattern(string $attr): string
    {
        $escaped = preg_quote($attr, '/');
        $escaped = str_replace('\*', '[a-zA-Z0-9_-]*', $escaped);

        return '/\s' . $escaped . '="[^"]*"/i';
    }
}
