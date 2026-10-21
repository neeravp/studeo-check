<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;

/**
 * Whether a class carries a project's system-action attribute, read from the syntax tree.
 */
final class SystemAttribute
{
    public static function on(Class_ $class, string $attribute): bool
    {
        $names = array_map(fn (AttributeGroup $group): array => array_map(fn (Attribute $attr): string => $attr->name->toString(), $group->attrs), $class->attrGroups);

        return in_array($attribute, array_merge([], ...$names), true);
    }
}
