<?php

namespace Studeo\Check\Rules\Helpers;

/**
 * A property or constructor parameter of a class, with the classes its type mentions.
 */
final readonly class TypedMember
{
    /**
     * @param  list<string>  $types  full class names in the declared type
     */
    public function __construct(
        public string $subject,
        public string $variable,
        public int $line,
        public array $types,
    ) {}
}
