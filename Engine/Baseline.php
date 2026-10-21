<?php

namespace Studeo\Check\Engine;

use RuntimeException;

final readonly class Baseline
{
    /**
     * @param  list<string>  $entries  sorted unique "rule file subject"
     */
    public function __construct(public array $entries = []) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Baseline file [{$path}] was not found.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Unable to read baseline file: {$path}");
        }

        return new self(self::normalizeEntries($lines));
    }

    /**
     * @param  list<Violation>  $violations
     */
    public static function fromViolations(array $violations): self
    {
        return new self(self::formatKeys($violations));
    }

    public function key(Violation $violation): string
    {
        return self::formatKey($violation);
    }

    /**
     * @param  list<Violation>  $violations
     * @return array{new: list<string>, stale: list<string>}
     */
    public function differences(array $violations): array
    {
        return $this->diffKeys(self::formatKeys($violations));
    }

    /**
     * @param  list<Violation>  $violations
     * @return list<string>
     */
    private static function formatKeys(array $violations): array
    {
        $keys = array_unique(array_map(self::formatKey(...), $violations));
        sort($keys);

        return $keys;
    }

    private static function formatKey(Violation $violation): string
    {
        return "{$violation->rule} {$violation->file} {$violation->subject}";
    }

    /**
     * @param  list<string>  $actualKeys
     * @return array{new: list<string>, stale: list<string>}
     */
    public function diffKeys(array $actualKeys): array
    {
        return [
            'new' => array_values(array_diff($actualKeys, $this->entries)),
            'stale' => array_values(array_diff($this->entries, $actualKeys)),
        ];
    }

    /**
     * @param  list<Violation>  $violations
     * @return array{active: list<Violation>, stale: list<string>}
     */
    public function apply(array $violations): array
    {
        $lookup = array_fill_keys($this->entries, true);
        $active = array_values(array_filter($violations, fn (Violation $violation): bool => ! isset($lookup[self::formatKey($violation)])));
        $actualKeys = self::formatKeys($violations);
        $stale = array_values(array_diff($this->entries, $actualKeys));

        return [
            'active' => $active,
            'stale' => $stale,
        ];
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->formatContent());
    }

    private function formatContent(): string
    {
        if ($this->entries === []) {
            return '';
        }

        return implode("\n", $this->entries)."\n";
    }

    /**
     * @param  array<int|string, string>  $lines
     * @return list<string>
     */
    private static function normalizeEntries(array $lines): array
    {
        $trimmed = array_values(array_filter(array_map('trim', $lines)));
        $unique = array_unique($trimmed);
        sort($unique);

        return $unique;
    }
}
