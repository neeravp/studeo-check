<?php

namespace Studeo\Check\Rules\Helpers;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * The class names a declared type mentions, resolved to full names: a nullable, union or
 * intersection type counts every class in it, and built-in types count none.
 */
final class TypeNames
{
    /**
     * @return list<string>
     */
    public static function of(?Node $type): array
    {
        return match (true) {
            $type instanceof Name => [$type->toString()],
            $type instanceof NullableType => self::of($type->type),
            $type instanceof UnionType, $type instanceof IntersectionType => array_merge([], ...array_map(self::of(...), $type->types)),
            default => [],
        };
    }
}
