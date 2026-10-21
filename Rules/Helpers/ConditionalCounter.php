<?php

namespace Studeo\Check\Rules\Helpers;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Counts `if` and `elseif` in a body. Closures count; a class declared inside the body is its
 * own subject, so it is not entered.
 */
final class ConditionalCounter extends NodeVisitorAbstract
{
    private int $count = 0;

    /**
     * @param  array<int, Node>  $statements
     */
    public static function in(array $statements): int
    {
        $counter = new self;
        (new NodeTraverser($counter))->traverse($statements);

        return $counter->count;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof ClassLike) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        if ($this->isConditional($node)) {
            $this->count++;
        }

        return null;
    }

    private function isConditional(Node $node): bool
    {
        return $node instanceof If_ || $node instanceof ElseIf_;
    }
}
