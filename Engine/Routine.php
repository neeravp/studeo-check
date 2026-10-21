<?php

namespace Studeo\Check\Engine;

use PhpParser\Node\FunctionLike;

/**
 * A method or top-level function of a file, with the stable subject name findings carry
 * (`Class::method`, `class@anonymous::method`, `function()`).
 */
final readonly class Routine
{
    public function __construct(
        public string $subject,
        public string $name,
        public FunctionLike $node,
    ) {}

    public function line(): int
    {
        return $this->node->getStartLine();
    }
}
