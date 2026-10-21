<?php

namespace Studeo\Check\Engine;

final readonly class Scope
{
    public const ALL = ['.'];

    public const APP_CODE = [
        'Actions',
        'Commands',
        'Concerns',
        'Controllers',
        'Data',
        'Jobs',
        'Listeners',
        'Livewire',
        'Middleware',
        'Policies',
        'Services',
    ];

    /**
     * @param  list<string>  $patterns
     */
    public function __construct(
        public array $patterns = self::ALL,
    ) {}

    public static function all(): self
    {
        return new self(self::ALL);
    }

    public static function appCode(): self
    {
        return new self(self::APP_CODE);
    }

    public function matches(string $relativePath): bool
    {
        return array_filter($this->patterns, fn (string $pattern): bool => self::patternMatches($pattern, $relativePath)) !== [];
    }

    private static function patternMatches(string $pattern, string $relativePath): bool
    {
        if (self::hasGlob($pattern)) {
            return fnmatch($pattern, $relativePath);
        }

        return self::isInside($pattern, $relativePath);
    }

    private static function hasGlob(string $pattern): bool
    {
        return strpbrk($pattern, '*?[') !== false;
    }

    private static function isInside(string $directory, string $relativePath): bool
    {
        return $directory === '.' || str_starts_with($relativePath, $directory.'/');
    }
}
