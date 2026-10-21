<?php

namespace Studeo\Check\Exceptions;

use RuntimeException;

/**
 * @phpstan-consistent-constructor
 */
class InvalidRuleConfigurationException extends RuntimeException
{
    public static function option(string $rule, string $option, string $problem): static
    {
        return new static("Rule [{$rule}] option [{$option}] {$problem}");
    }

    public static function duplicate(string $class): static
    {
        return new static("Rule class {$class} is listed twice in the rule set. List each rule class once and configure it through its options().");
    }

    public static function notARule(mixed $given): static
    {
        return new static('A rule set holds rules built with a rule class\'s options(); got '.get_debug_type($given).'.');
    }
}
