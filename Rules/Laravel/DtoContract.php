<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Studeo\Check\Engine\ClassIndex;

/**
 * The DTO split: a class in Data/ is named *Input and extends inputBase
 * with a protected constructor, or is named *Data and extends outputBase, and is never Wireable.
 */
final class DtoContract
{
    public const WIREABLE = 'Livewire\\Wireable';

    /**
     * @return list<string> what is wrong with the class, in the order a reader fixes it
     */
    public static function problems(Class_ $class, ClassIndex $index, string $inputBase, string $outputBase): array
    {
        $name = (string) $class->namespacedName;
        $suffix = self::suffixOf((string) $class->name);

        return array_values(array_filter([
            self::kindProblem((string) $class->name, $suffix, $name, $index, $inputBase, $outputBase),
            self::constructorProblem($suffix, $name, $index),
            self::implementsWireable($class) ? 'DTOs are never Wireable. Remove Livewire\\Wireable; hold plain snake_case state in the component and build the Input in toInput().' : null,
        ]));
    }

    private static function suffixOf(string $short): ?string
    {
        return match (true) {
            str_ends_with($short, 'Input') => 'Input',
            str_ends_with($short, 'Data') => 'Data',
            default => null,
        };
    }

    private static function kindProblem(string $short, ?string $suffix, string $name, ClassIndex $index, string $inputBase, string $outputBase): ?string
    {
        return match ($suffix) {
            'Input' => self::parentProblem('Input', $inputBase, $outputBase, $name, $index),
            'Data' => self::parentProblem('Data', $outputBase, $inputBase, $name, $index),
            default => 'the name must end in Input or Data: *Input for a class extending '.self::basename($inputBase).', *Data for one extending '.self::basename($outputBase).". Rename it, for example {$short}Data.",
        };
    }

    private static function parentProblem(string $suffix, string $expected, string $other, string $name, ClassIndex $index): ?string
    {
        return match (true) {
            $index->descendsFrom($name, $expected) => null,
            $index->descendsFrom($name, $other) => "is named *{$suffix} but extends ".self::basename($other).'. Rename it to end in '.($suffix === 'Input' ? 'Data' : 'Input').' or extend '.self::basename($expected).'.',
            default => "*{$suffix} classes must extend {$expected}. Add `extends ".self::basename($expected).'`.',
        };
    }

    private static function constructorProblem(?string $suffix, string $name, ClassIndex $index): ?string
    {
        return self::lacksProtectedConstructor($suffix, $index->constructorOf($name)) ? 'an Input constructor must be protected so the only way in is fromValidated(). Declare `protected function __construct(...)`.' : null;
    }

    private static function lacksProtectedConstructor(?string $suffix, ?ClassMethod $constructor): bool
    {
        return $suffix === 'Input' && ! ($constructor?->isProtected() ?? false);
    }

    private static function implementsWireable(Class_ $class): bool
    {
        return array_filter($class->implements, fn (Name $name): bool => (string) $name === self::WIREABLE) !== [];
    }

    private static function basename(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }
}
