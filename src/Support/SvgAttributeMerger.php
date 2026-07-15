<?php

namespace Unloc\FontAwesome\Support;

use Illuminate\View\ComponentAttributeBag;

class SvgAttributeMerger
{
    public function merge(string $svg, string $defaultClasses, ComponentAttributeBag|array|null $attributes = null): string
    {
        if (! preg_match('/<svg([^>]*)>/is', $svg, $m, PREG_OFFSET_CAPTURE)) {
            return $svg;
        }

        $bag = $attributes instanceof ComponentAttributeBag
            ? $attributes->getAttributes()
            : (array) ($attributes ?? []);

        $existing = $this->parseAttributes($m[1][0]);

        $classes = array_values(array_unique(array_filter(array_merge(
            $this->split($defaultClasses),
            $this->split($existing['class'] ?? ''),
            $this->split((string) ($bag['class'] ?? '')),
        ))));

        unset($existing['class'], $bag['class']);
        $merged = array_merge($existing, $bag);
        if ($classes !== []) {
            $merged['class'] = implode(' ', $classes);
        }

        $newTag = '<svg' . ($merged === [] ? '' : ' ' . $this->buildAttributes($merged)) . '>';

        $start = $m[0][1];
        $length = strlen($m[0][0]);

        return substr($svg, 0, $start) . $newTag . substr($svg, $start + $length);
    }

    /** @return array<string,string> */
    private function parseAttributes(string $raw): array
    {
        preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:.-]*)\s*=\s*"([^"]*)"/', $raw, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            $attrs[$match[1]] = $match[2];
        }

        return $attrs;
    }

    /** @return list<string> */
    private function split(string $value): array
    {
        $value = trim($value);

        return $value === '' ? [] : preg_split('/\s+/', $value);
    }

    /** @param array<string,string> $attrs */
    private function buildAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            $parts[] = $key . '="' . e($value, false) . '"';
        }

        return implode(' ', $parts);
    }
}
