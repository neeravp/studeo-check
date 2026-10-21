<?php

namespace Studeo\Check\Engine;

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;

/**
 * The value checks a rule's options() runs, each throwing an exception that names the rule and
 * the option.
 */
final class RuleOptions
{
    public static function positive(string $rule, string $option, int $value): int
    {
        if ($value < 1) {
            throw InvalidRuleConfigurationException::option($rule, $option, 'must be a positive integer.');
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string> the entries with surrounding whitespace removed
     */
    public static function names(string $rule, string $option, array $values): array
    {
        if ($values === []) {
            throw InvalidRuleConfigurationException::option($rule, $option, 'must not be empty.');
        }

        if (! self::areAllNames($values)) {
            throw InvalidRuleConfigurationException::option($rule, $option, 'must hold non-empty strings only.');
        }

        return array_map(trim(...), array_values($values));
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string> single directory names, never paths
     */
    public static function directories(string $rule, string $option, array $values): array
    {
        $names = self::names($rule, $option, $values);

        if (array_filter($names, self::hasSeparator(...)) !== []) {
            throw InvalidRuleConfigurationException::option($rule, $option, 'must hold directory names, not paths.');
        }

        return $names;
    }

    /**
     * @param  array<int|string, mixed>  $scope
     * @return list<string>
     */
    public static function scope(string $rule, array $scope): array
    {
        $trimmed = array_map(fn (mixed $directory): mixed => is_string($directory) ? trim($directory, " \t\n\r/") : $directory, $scope);

        return self::names($rule, 'scope', $trimmed);
    }

    public static function className(string $rule, string $option, string $value): string
    {
        $trimmed = trim($value);

        if (! self::isValidClassName($trimmed)) {
            throw InvalidRuleConfigurationException::option($rule, $option, 'must be a fully qualified class name without a leading backslash.');
        }

        return $trimmed;
    }

    public static function text(string $rule, string $option, string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidRuleConfigurationException::option($rule, $option, 'must not be empty.');
        }

        return $trimmed;
    }

    private static function isValidClassName(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $value);
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    private static function areAllNames(array $values): bool
    {
        return count(array_filter($values, self::isName(...))) === count($values);
    }

    private static function hasSeparator(string $name): bool
    {
        return str_contains($name, '/') || str_contains($name, '\\');
    }

    private static function isName(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
