<?php

namespace Studeo\Check\Rules\Helpers;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;

/**
 * The typed members of a class that rules look at: its public instance properties, declared or
 * promoted, and the parameters of its constructor.
 */
final class ClassMembers
{
    /**
     * @return list<TypedMember>
     */
    public static function publicState(Class_ $class): array
    {
        $promoted = array_filter(self::rawParameters($class), fn (Param $param): bool => $param->isPublic());
        $declared = array_filter($class->getProperties(), self::isInstanceState(...));

        return [...self::fromProperties($class, $declared), ...array_map(fn (Param $param): TypedMember => self::fromParameter($class, $param), array_values($promoted))];
    }

    /**
     * @return list<TypedMember>
     */
    public static function constructorParameters(Class_ $class): array
    {
        return array_map(fn (Param $param): TypedMember => self::fromParameter($class, $param), self::rawParameters($class));
    }

    public static function label(Class_ $class): string
    {
        return $class->name?->toString() ?? 'class@anonymous';
    }

    private static function isInstanceState(Property $property): bool
    {
        return $property->isPublic() && ! $property->isStatic();
    }

    /**
     * @return list<Param>
     */
    private static function rawParameters(Class_ $class): array
    {
        /** @var list<Param> $params */
        $params = $class->getMethod('__construct')->params ?? [];

        return $params;
    }

    /**
     * @param  array<int, Property>  $properties
     * @return list<TypedMember>
     */
    private static function fromProperties(Class_ $class, array $properties): array
    {
        $members = array_map(fn (Property $property): array => array_map(fn (PropertyItem $item): TypedMember => self::member($class, $item->name->toString(), $item->getStartLine(), $property->type), $property->props), $properties);
        /** @var list<TypedMember> $flattened */
        $flattened = array_merge([], ...$members);

        return $flattened;
    }

    private static function fromParameter(Class_ $class, Param $param): TypedMember
    {
        /** @var Variable $var */
        $var = $param->var;
        /** @var string $name */
        $name = $var->name;

        return self::member($class, $name, $param->getStartLine(), $param->type);
    }

    private static function member(Class_ $class, string $variable, int $line, mixed $type): TypedMember
    {
        return new TypedMember(self::label($class).'::$'.$variable, $variable, $line, TypeNames::of($type));
    }
}
