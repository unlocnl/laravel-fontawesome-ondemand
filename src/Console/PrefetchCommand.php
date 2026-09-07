<?php

namespace Unloc\FontAwesome\Console;

use Illuminate\Console\Command;
use Unloc\FontAwesome\FontAwesome;

class PrefetchCommand extends Command
{
    protected $signature = 'fontawesome:prefetch';

    protected $description = 'Fetch and cache Font Awesome icons ahead of time.';

    private int $skipped = 0;

    public function handle(FontAwesome $fontawesome): int
    {
        $results = $fontawesome->warm($this->entries());

        $warmed = count(array_filter($results));
        $failed = count($results) - $warmed;

        $this->info("Font Awesome prefetch: {$warmed} warmed, {$failed} failed, {$this->skipped} dynamic skipped.");

        return self::SUCCESS;
    }

    /** @return list<array{name:string,family?:string,style?:string}> */
    private function entries(): array
    {
        $entries = [];

        foreach ((array) config('fontawesome.prefetch', []) as $entry) {
            $entries[] = is_array($entry) ? $entry : ['name' => $entry];
        }

        foreach ($this->scan() as $entry) {
            $entries[] = $entry;
        }

        return $this->dedupe($entries);
    }

    /** @return list<array{name:string,family?:string,style?:string}> */
    private function scan(): array
    {
        $paths = array_merge([resource_path('views')], (array) config('fontawesome.scan_paths', []));
        $found = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach ($this->bladeFiles($path) as $file) {
                $content = (string) file_get_contents($file);
                preg_match_all('/<x-fa\s+([^>]+?)\/?>/is', $content, $matches);

                foreach ($matches[1] as $attrString) {
                    $attrs = $this->parseAttrs($attrString);
                    if ($attrs === null || ! isset($attrs['name'])) {
                        $this->skipped++;

                        continue;
                    }

                    $found[] = array_filter([
                        'name' => $attrs['name'],
                        'family' => $attrs['family'] ?? null,
                        'style' => $attrs['variant'] ?? null,
                    ], fn ($v) => $v !== null);
                }
            }
        }

        return $found;
    }

    /** @return array<string,string>|null null when any attribute is a dynamic binding */
    private function parseAttrs(string $raw): ?array
    {
        if (preg_match('/(^|\s):[a-zA-Z]/', $raw)) {
            return null; // :name / :family binding
        }

        preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:.-]*)\s*=\s*"([^"]*)"/', $raw, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            if (str_contains($match[2], '{{') || str_contains($match[2], '$')) {
                return null; // interpolated value
            }
            $attrs[$match[1]] = $match[2];
        }

        return $attrs;
    }

    /** @return iterable<string> */
    private function bladeFiles(string $path): iterable
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file->getPathname();
            }
        }
    }

    /**
     * @param list<array<string,string>> $entries
     * @return list<array<string,string>>
     */
    private function dedupe(array $entries): array
    {
        $seen = [];
        foreach ($entries as $entry) {
            $key = ($entry['name'] ?? '') . '|' . ($entry['family'] ?? '') . '|' . ($entry['style'] ?? '');
            $seen[$key] = $entry;
        }

        return array_values($seen);
    }
}
