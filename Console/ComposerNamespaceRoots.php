<?php

namespace Studeo\Check\Console;

use RuntimeException;

final readonly class ComposerNamespaceRoots
{
    /**
     * @return list<string>
     */
    public function read(string $basePath): array
    {
        $path = $basePath.DIRECTORY_SEPARATOR.'composer.json';
        if (! file_exists($path)) {
            return [];
        }

        $content = (string) file_get_contents($path);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('File [composer.json] is not valid JSON: '.json_last_error_msg().'.');
        }

        return $this->extractRoots($data, $content, ['autoload', 'autoload-dev']);
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    private function extractRoots(mixed $data, string $content, array $sections): array
    {
        if (! str_starts_with(ltrim($content), '{')) {
            throw new RuntimeException('File [composer.json] must hold a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return $this->collectSections($data, $sections);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $sections
     * @return list<string>
     */
    private function collectSections(array $data, array $sections): array
    {
        $collected = array_merge([], ...array_map(fn (string $section): array => $this->sectionRoots($data, $section), $sections));

        return array_values(array_unique($collected));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function sectionRoots(array $data, string $section): array
    {
        $sectionData = $data[$section] ?? null;
        if (! is_array($sectionData)) {
            return [];
        }

        $psr4 = $sectionData['psr-4'] ?? null;
        if (! is_array($psr4)) {
            return [];
        }

        return $this->filterRoots(array_keys($psr4));
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<string>
     */
    private function filterRoots(array $keys): array
    {
        $valid = array_filter($keys, fn (mixed $key): bool => $this->isValidRoot($key));

        /** @var list<string> */
        return array_values($valid);
    }

    private function isValidRoot(mixed $key): bool
    {
        return is_string($key) && $key !== '' && str_ends_with($key, '\\') && ! str_ends_with($key, '\\\\');
    }
}
